<?php
/**
 * One-time invite tokens (Phase 3).
 *
 * Storage: top-level $db['invites'][] = [
 *     'id'         => 'inv_<uniqid>',
 *     'wall_id'    => 'w_*',
 *     'token_hash' => 'sha256-hex',   // store only the hash; plaintext is shown ONCE
 *     'created_at' => 'YYYY-MM-DD HH:MM:SS',
 *     'used_at'    => null | 'YYYY-MM-DD HH:MM:SS',
 *     'note'       => string (optional creator note)
 * ]
 *
 * Tokens bypass the wall's normal access type — they grant a one-time session unlock
 * regardless of whether the wall is public, password, codelist, payment, or email allowlist.
 *
 * Why: the user wants two channels — one-time invite link (creator generates, sends out-of-band,
 * recipient clicks once) AND email allowlist (recipient self-verifies by email). Invites are
 * orthogonal to access type so they work universally.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/install_secret.php';

/**
 * Generates a new invite. Returns ['id' => ..., 'token' => <PLAINTEXT>, 'url_path' => '?invite=...'].
 * The plaintext token is returned ONCE so the caller can display it; only the hash is persisted.
 *
 * Returns false on save failure.
 */
function create_invite(string $wall_id, string $note = '') {
    $db = get_db();

    // Verify wall exists.
    $wall_exists = false;
    foreach ($db['walls'] ?? [] as $w) {
        if ($w['id'] === $wall_id) { $wall_exists = true; break; }
    }
    if (!$wall_exists) {
        error_log("[Engine:invites] create_invite: wall $wall_id not found");
        return false;
    }

    // 24 random bytes as the user-visible token (~32 chars urlsafe).
    $token = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
    $token_hash = hash('sha256', $token);

    $invite = [
        'id'         => 'inv_' . uniqid('', true),
        'wall_id'    => $wall_id,
        'token_hash' => $token_hash,
        'created_at' => date('Y-m-d H:i:s'),
        'used_at'    => null,
        'note'       => trim($note),
    ];

    if (!isset($db['invites']) || !is_array($db['invites'])) {
        $db['invites'] = [];
    }
    $db['invites'][] = $invite;

    if (!save_db($db)) {
        return false;
    }

    return [
        'id'    => $invite['id'],
        'token' => $token, // plaintext, shown ONCE
    ];
}

/**
 * Consumes an invite token. If valid + unused, marks it used and returns the wall_id.
 * Returns null on invalid / used / wrong-wall token.
 *
 * If $expected_wall_id is provided, the token must match that wall.
 */
function consume_invite(string $token, ?string $expected_wall_id = null): ?string {
    $token_hash = hash('sha256', $token);
    $db = get_db();
    if (empty($db['invites'])) { return null; }

    foreach ($db['invites'] as &$inv) {
        if (!hash_equals($inv['token_hash'], $token_hash)) { continue; }
        if ($expected_wall_id !== null && $inv['wall_id'] !== $expected_wall_id) {
            return null;
        }
        if (!empty($inv['used_at'])) {
            return null; // already consumed
        }
        $inv['used_at'] = date('Y-m-d H:i:s');
        $wall_id = $inv['wall_id'];
        return save_db($db) ? $wall_id : null;
    }
    return null;
}

/**
 * Returns invites for a wall (without exposing token plaintext — only id, used status, dates).
 */
function list_invites_for_wall(string $wall_id): array {
    $db = get_db();
    $out = [];
    foreach ($db['invites'] ?? [] as $inv) {
        if ($inv['wall_id'] !== $wall_id) { continue; }
        $out[] = [
            'id'         => $inv['id'],
            'created_at' => $inv['created_at'],
            'used_at'    => $inv['used_at'] ?? null,
            'note'       => $inv['note'] ?? '',
        ];
    }
    // Newest first.
    usort($out, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
    return $out;
}

/**
 * Revokes (deletes) an invite by its id. Used invites can also be deleted to keep the list clean.
 */
function revoke_invite(string $invite_id): bool {
    $db = get_db();
    if (empty($db['invites'])) { return false; }

    $before = count($db['invites']);
    $db['invites'] = array_values(array_filter(
        $db['invites'],
        fn($inv) => $inv['id'] !== $invite_id
    ));
    if (count($db['invites']) === $before) { return false; }
    return save_db($db);
}
