<?php
/**
 * Per-install symmetric secret.
 *
 * Generated lazily on first use into data/install_secret (which is .htaccess-blocked
 * from web access). Distinct from APP_SECRET_KEY (legacy hardcoded — see LWI-001 / LWI-006).
 *
 * Used by the email-allowlist + invite-link features (Phase 3 access expansion) for HMAC
 * key derivation and symmetric email encryption. Existing password-derived link encryption
 * still uses APP_SECRET_KEY for backward compatibility.
 */

define('INSTALL_SECRET_FILE', __DIR__ . '/../../data/install_secret');

/**
 * Returns the per-install secret as a 64-char hex string.
 * Generates one on first call.
 */
function get_install_secret(): string {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    if (file_exists(INSTALL_SECRET_FILE)) {
        $raw = trim(file_get_contents(INSTALL_SECRET_FILE));
        if (preg_match('/^[a-f0-9]{64}$/i', $raw)) {
            $cached = $raw;
            return $cached;
        }
        // File exists but is malformed — log and fall through to regenerate.
        error_log('[Engine:install_secret] Malformed install_secret file; regenerating.');
    }

    $cached = bin2hex(random_bytes(32));
    if (file_put_contents(INSTALL_SECRET_FILE, $cached, LOCK_EX) === false) {
        error_log('[Engine:install_secret] Failed to write install_secret file. Falling back to in-memory only (NOT persisted).');
    } else {
        @chmod(INSTALL_SECRET_FILE, 0600);
    }
    return $cached;
}

/**
 * Derive a wall-scoped 32-byte secret for HMAC + symmetric encryption.
 * Different per wall, so leaking one wall's data doesn't compromise others.
 */
function derive_wall_secret(string $wall_id): string {
    return hash_hmac('sha256', 'wall:' . $wall_id, get_install_secret(), true);
}
