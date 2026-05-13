<?php
/**
 * Email-allowlist primitives for Wall access control (Phase 3).
 *
 * Storage shape (on $wall['access_control']['email_allowlist']):
 *   [ ['email_hash' => '...', 'email_encrypted' => '...'], ... ]
 *
 *   - email_hash: HMAC-SHA256(lowercase(email), wall_secret), hex. Used for fast O(N)
 *     match without decrypt. Stable per wall (different wall produces different hash for
 *     the same email — by design, prevents cross-wall correlation).
 *   - email_encrypted: AES-256-GCM ciphertext (iv | tag | ct), base64. Decrypt only when
 *     the creator views the list to manage it.
 *
 * Cookie format for long-term gate-bypass:
 *   Cookie name:  lwi_access_<wall_id>
 *   Cookie value: <email_hash>:<hmac>
 *     where hmac = HMAC-SHA256(email_hash + ':' + wall_id, install_secret), hex.
 *   Verifying: HMAC must match, and email_hash must still be present in the wall's
 *   allowlist. Removing an email from the allowlist therefore revokes the cookie too.
 */

require_once __DIR__ . '/install_secret.php';

/* ------------------------------------------------------------------ */
/* Hashing & encryption                                                */
/* ------------------------------------------------------------------ */

/**
 * Stable per-wall hash of an email address. Used to look up entries without decrypting.
 */
function email_match_hash(string $email, string $wall_id): string {
    $normalized = strtolower(trim($email));
    return hash_hmac('sha256', $normalized, derive_wall_secret($wall_id));
}

/**
 * Symmetric encryption of an email using a per-wall key.
 * Returns base64(iv | tag | ct). Returns false on failure.
 */
function encrypt_email(string $email, string $wall_id) {
    $key = derive_wall_secret($wall_id);
    $iv_length = openssl_cipher_iv_length('aes-256-gcm');
    $iv = random_bytes($iv_length);
    $tag = '';
    $ct = openssl_encrypt(
        $email, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16
    );
    if ($ct === false) {
        error_log('[Engine:email_access] encrypt_email failed for wall ' . $wall_id);
        return false;
    }
    return base64_encode($iv . $tag . $ct);
}

/**
 * Decrypts a previously-encrypted email. Returns plaintext or false.
 */
function decrypt_email(string $encrypted, string $wall_id) {
    $key = derive_wall_secret($wall_id);
    $blob = base64_decode($encrypted, true);
    if ($blob === false) { return false; }

    $iv_length = openssl_cipher_iv_length('aes-256-gcm');
    $tag_length = 16;
    if (strlen($blob) < $iv_length + $tag_length) { return false; }

    $iv = substr($blob, 0, $iv_length);
    $tag = substr($blob, $iv_length, $tag_length);
    $ct = substr($blob, $iv_length + $tag_length);

    return openssl_decrypt($ct, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
}

/* ------------------------------------------------------------------ */
/* Wall allowlist CRUD                                                 */
/* ------------------------------------------------------------------ */

/**
 * Add an email to a wall's allowlist. Idempotent — duplicates collapse via email_hash.
 * Returns true on save, false on failure.
 */
function add_wall_email(string $wall_id, string $email): bool {
    $email = trim($email);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        error_log("[Engine:email_access] Invalid email rejected for wall $wall_id");
        return false;
    }

    $db = get_db();
    foreach ($db['walls'] as &$wall) {
        if ($wall['id'] !== $wall_id) { continue; }

        $hash = email_match_hash($email, $wall_id);

        if (!isset($wall['access_control']['email_allowlist'])) {
            $wall['access_control']['email_allowlist'] = [];
        }

        // Skip duplicates.
        foreach ($wall['access_control']['email_allowlist'] as $entry) {
            if (hash_equals($entry['email_hash'], $hash)) {
                return true;
            }
        }

        $encrypted = encrypt_email($email, $wall_id);
        if ($encrypted === false) { return false; }

        $wall['access_control']['email_allowlist'][] = [
            'email_hash'      => $hash,
            'email_encrypted' => $encrypted,
        ];
        return save_db($db);
    }
    return false;
}

/**
 * Remove an entry from a wall's allowlist by its email_hash.
 */
function remove_wall_email(string $wall_id, string $email_hash): bool {
    $db = get_db();
    foreach ($db['walls'] as &$wall) {
        if ($wall['id'] !== $wall_id) { continue; }
        if (empty($wall['access_control']['email_allowlist'])) { return true; }

        $wall['access_control']['email_allowlist'] = array_values(array_filter(
            $wall['access_control']['email_allowlist'],
            fn($e) => !hash_equals($e['email_hash'], $email_hash)
        ));
        return save_db($db);
    }
    return false;
}

/**
 * Returns an array of [['email' => plaintext, 'email_hash' => h], ...] for the creator UI.
 * Decrypts each entry; entries that fail to decrypt are logged and skipped.
 */
function list_wall_emails(string $wall_id): array {
    $wall = get_wall($wall_id);
    if (!$wall) { return []; }
    $entries = $wall['access_control']['email_allowlist'] ?? [];
    $out = [];
    foreach ($entries as $e) {
        $plain = decrypt_email($e['email_encrypted'] ?? '', $wall_id);
        if ($plain === false) {
            error_log("[Engine:email_access] decrypt_email failed for entry on wall $wall_id");
            continue;
        }
        $out[] = ['email' => $plain, 'email_hash' => $e['email_hash']];
    }
    return $out;
}

/**
 * Does $email match any allowlist entry on $wall?
 */
function wall_email_matches(array $wall, string $email): bool {
    if (empty($wall['access_control']['email_allowlist'])) { return false; }
    $hash = email_match_hash($email, $wall['id']);
    foreach ($wall['access_control']['email_allowlist'] as $entry) {
        if (hash_equals($entry['email_hash'], $hash)) {
            return true;
        }
    }
    return false;
}

/* ------------------------------------------------------------------ */
/* Long-term gate-bypass cookie                                        */
/* ------------------------------------------------------------------ */

/**
 * Builds the cookie value for long-term access on a wall, given an email_hash.
 */
function build_email_access_cookie(string $wall_id, string $email_hash): string {
    $hmac = hash_hmac('sha256', $email_hash . ':' . $wall_id, get_install_secret());
    return $email_hash . ':' . $hmac;
}

/**
 * Verifies a cookie value and confirms the email_hash is still present in the wall.
 * Returns true if the cookie grants access right now.
 */
function verify_email_access_cookie(array $wall, string $cookie_value): bool {
    $parts = explode(':', $cookie_value, 2);
    if (count($parts) !== 2) { return false; }
    [$email_hash, $hmac] = $parts;

    $expected = hash_hmac('sha256', $email_hash . ':' . $wall['id'], get_install_secret());
    if (!hash_equals($expected, $hmac)) { return false; }

    foreach ($wall['access_control']['email_allowlist'] ?? [] as $entry) {
        if (hash_equals($entry['email_hash'], $email_hash)) {
            return true;
        }
    }
    return false;
}

function email_access_cookie_name(string $wall_id): string {
    return 'lwi_access_' . preg_replace('/[^a-zA-Z0-9_]/', '', $wall_id);
}
