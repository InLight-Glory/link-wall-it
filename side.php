<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/app/core/functions.php';

$side_id = $_GET['id'] ?? null;
if (!$side_id) {
    header("Location: index.php");
    exit;
}

$side = get_side($side_id);
if (!$side) {
    header("Location: index.php");
    exit;
}

$building = get_building($side['building_id']);
$walls = get_publicly_listable_walls_for_side($side_id);

// If a visitor lands on a side with no publicly-listable walls, treat it as not found
// rather than render an empty page that confirms the side exists.
if (empty($walls)) {
    header("Location: index.php");
    exit;
}
$site_title = get_db()['settings']['site_title'] ?? 'Link-Wall-It';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($side['name']) ?> &middot; <?= htmlspecialchars($site_title) ?></title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
    <div class="container">
        <nav class="breadcrumb">
            <a href="index.php">Home</a>
            <?php if ($building): ?>
                <span class="breadcrumb__sep">/</span>
                <a href="building.php?id=<?= htmlspecialchars($building['id']) ?>"><?= htmlspecialchars($building['name']) ?></a>
            <?php endif; ?>
            <span class="breadcrumb__sep">/</span>
            <?= htmlspecialchars($side['name']) ?>
        </nav>

        <header class="page-header row row--between">
            <div>
                <h1><?= htmlspecialchars($side['name']) ?></h1>
                <p class="page-header__sub"><?= count($walls) ?> <?= count($walls) === 1 ? 'wall' : 'walls' ?></p>
            </div>
            <div class="row">
                <button type="button" class="btn btn--secondary btn--sm" onclick="copyToClipboard(window.location.href, this)">Copy link</button>
                <button type="button" class="btn btn--secondary btn--sm" onclick="shareToTwitter(window.location.href, '<?= htmlspecialchars($side['name'], ENT_QUOTES) ?>')">Twitter</button>
                <button type="button" class="btn btn--secondary btn--sm" onclick="shareToFacebook(window.location.href)">Facebook</button>
            </div>
        </header>

        <main>
            <?php if (empty($walls)): ?>
                <div class="card">
                    <p class="list__empty">This side has no walls yet.</p>
                </div>
            <?php else: ?>
                <div class="tile-grid">
                    <?php foreach ($walls as $wall): ?>
                        <a class="tile" href="wall.php?id=<?= htmlspecialchars($wall['id']) ?>">
                            <h2 class="tile__title"><?= htmlspecialchars($wall['name']) ?></h2>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
    <script src="assets/js/sharing.js"></script>
</body>
</html>
