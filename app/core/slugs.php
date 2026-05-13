<?php
/**
 * Wall slugs (link shortener).
 *
 * Each wall has a short, URL-safe slug stored as $wall['slug']. The slug is
 * auto-generated on wall creation; creators can customize it from wall_settings.
 *
 * Charset choice: lowercase alphanumeric MINUS visually-ambiguous chars (l/1/i, o/0).
 *   abcdefghjkmnpqrstuvwxyz23456789  -> 31 chars
 *   31^3 = 29,791 possible 3-char slugs (plenty for self-hosted personal scale).
 *
 * Custom slugs accept a slightly looser charset: [a-z0-9_-], length 2-32.
 *
 * Lookup: get_wall_by_slug($slug) — case-insensitive on the auto-gen charset, but
 * we normalize to lowercase on save so equality comparison is straightforward.
 */

require_once __DIR__ . '/db.php';

const SLUG_AUTOGEN_CHARSET = 'abcdefghjkmnpqrstuvwxyz23456789';
const SLUG_AUTOGEN_LEN = 3;
const SLUG_CUSTOM_RE = '/^[a-z0-9_-]{2,32}$/';

/**
 * Generates a unique random slug. Returns null if no free slug after $attempts tries
 * at the current length (caller could escalate length, but for personal scale we won't).
 */
function generate_unique_slug(int $attempts = 200): ?string {
    $existing = collect_existing_slugs();
    $charset_len = strlen(SLUG_AUTOGEN_CHARSET);

    for ($i = 0; $i < $attempts; $i++) {
        $slug = '';
        for ($j = 0; $j < SLUG_AUTOGEN_LEN; $j++) {
            $slug .= SLUG_AUTOGEN_CHARSET[random_int(0, $charset_len - 1)];
        }
        if (!isset($existing[$slug])) { return $slug; }
    }

    error_log('[Engine:slugs] generate_unique_slug exhausted attempts at length ' . SLUG_AUTOGEN_LEN);
    return null;
}

/**
 * Returns an associative array [slug => true] of every slug currently in use across walls.
 */
function collect_existing_slugs(): array {
    $db = get_db();
    $out = [];
    foreach ($db['walls'] ?? [] as $w) {
        if (!empty($w['slug'])) { $out[strtolower($w['slug'])] = true; }
    }
    return $out;
}

/**
 * Looks up a wall by slug (case-insensitive). Returns the wall record or null.
 */
function get_wall_by_slug(string $slug): ?array {
    $slug = strtolower(trim($slug));
    if ($slug === '') { return null; }
    $db = get_db();
    foreach ($db['walls'] ?? [] as $w) {
        if (isset($w['slug']) && strtolower($w['slug']) === $slug) {
            return $w;
        }
    }
    return null;
}

/**
 * Sets / changes a wall's slug. Returns ['ok' => true] on success or
 * ['error' => message] on charset/collision failure.
 *
 * Pass an empty string to clear and auto-regenerate.
 */
function update_wall_slug(string $wall_id, string $new_slug): array {
    $new_slug = strtolower(trim($new_slug));

    $db = get_db();
    $wall_index = null;
    foreach ($db['walls'] ?? [] as $i => $w) {
        if ($w['id'] === $wall_id) { $wall_index = $i; break; }
    }
    if ($wall_index === null) { return ['error' => 'Wall not found.']; }

    if ($new_slug === '') {
        // Clear and regenerate.
        $generated = generate_unique_slug();
        if ($generated === null) { return ['error' => 'Could not generate a unique slug.']; }
        $new_slug = $generated;
    } else {
        if (!preg_match(SLUG_CUSTOM_RE, $new_slug)) {
            return ['error' => 'Slug must be 2-32 chars, lowercase letters, digits, "_" or "-".'];
        }
        // Collision check (allow keeping the same value on the same wall).
        foreach ($db['walls'] as $i => $w) {
            if ($i === $wall_index) { continue; }
            if (isset($w['slug']) && strtolower($w['slug']) === $new_slug) {
                return ['error' => 'That slug is already taken.'];
            }
        }
    }

    $db['walls'][$wall_index]['slug'] = $new_slug;
    if (!save_db($db)) { return ['error' => 'Save failed.']; }
    return ['ok' => true, 'slug' => $new_slug];
}

/**
 * Lazy backfill: ensure a wall has a slug. If it doesn't, assign one and persist.
 * Idempotent.
 */
function ensure_wall_slug(string $wall_id): ?string {
    $db = get_db();
    foreach ($db['walls'] as $i => $w) {
        if ($w['id'] !== $wall_id) { continue; }
        if (!empty($w['slug'])) { return $w['slug']; }
        $slug = generate_unique_slug();
        if ($slug === null) { return null; }
        $db['walls'][$i]['slug'] = $slug;
        return save_db($db) ? $slug : null;
    }
    return null;
}

/**
 * Builds the full short URL for a wall. Falls back to the long URL if no slug.
 * Whether the install has the .htaccess rewrite is detected by the WALLIT_HAS_REWRITE
 * setting (off by default — admin opts in after testing the rewrite works on their host).
 */
function wall_short_url(array $wall): string {
    $base = app_base_url(); // from payments.php
    if (empty($wall['slug'])) {
        return $base . '/wall.php?id=' . urlencode($wall['id']);
    }

    $db = get_db();
    $rewrite = !empty($db['settings']['short_url_rewrite']);
    if ($rewrite) {
        return $base . '/s/' . $wall['slug'];
    }
    return $base . '/s.php?s=' . urlencode($wall['slug']);
}
