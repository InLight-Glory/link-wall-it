<?php
/**
 * Slug resolver — the link shortener.
 *
 * Looks up a wall by its short slug and 302-redirects to the full wall URL.
 * Hit either as `s.php?s=<slug>` or, with the optional .htaccess rewrite, as `/s/<slug>`.
 *
 * If no slug matches, the visitor is sent to the homepage. We deliberately do NOT
 * 404 — that would tell scrapers "this slug used to exist." Same noise as random misses.
 */

require_once __DIR__ . '/app/core/functions.php';

$slug = $_GET['s'] ?? '';
$wall = $slug !== '' ? get_wall_by_slug($slug) : null;

// Use absolute URLs — when the .htaccess rewrite is on, the visible URL is /lwi/s/<slug>
// and a relative redirect to wall.php would resolve to /lwi/s/wall.php?id=... (broken).
$base = app_base_url();

if ($wall) {
    header('Location: ' . $base . '/wall.php?id=' . urlencode($wall['id']), true, 302);
    exit;
}

// Unknown slug — redirect to home.
header('Location: ' . $base . '/index.php', true, 302);
exit;
