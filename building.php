<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/app/core/functions.php';

$building_id = $_GET['id'] ?? null;
if (!$building_id) {
    header("Location: index.php");
    exit;
}

$building = get_building($building_id);
if (!$building) {
    header("Location: index.php");
    exit;
}

$sides = get_publicly_listable_sides_for_building($building_id);

// If a visitor lands on a building that has no publicly-listable content, treat it
// as "not found" rather than rendering an empty page that telegraphs its existence.
if (empty($sides)) {
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
    <title><?= htmlspecialchars($building['name']) ?> &middot; <?= htmlspecialchars($site_title) ?></title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
    <div class="container">
        <nav class="breadcrumb">
            <a href="index.php">Home</a>
            <span class="breadcrumb__sep">/</span>
            <?= htmlspecialchars($building['name']) ?>
        </nav>

        <header class="page-header">
            <h1><?= htmlspecialchars($building['name']) ?></h1>
            <p class="page-header__sub"><?= count($sides) ?> <?= count($sides) === 1 ? 'side' : 'sides' ?></p>
        </header>

        <main>
            <?php if (empty($sides)): ?>
                <div class="card">
                    <p class="list__empty">This building has no sides yet.</p>
                </div>
            <?php else: ?>
                <div class="tile-grid">
                    <?php foreach ($sides as $side): ?>
                        <a class="tile" href="side.php?id=<?= htmlspecialchars($side['id']) ?>">
                            <h2 class="tile__title"><?= htmlspecialchars($side['name']) ?></h2>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
