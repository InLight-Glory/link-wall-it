<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/app/core/functions.php';

// Get side ID from URL
$side_id = $_GET['id'] ?? null;
if (!$side_id) {
    header("Location: index.php");
    exit;
}

// Fetch side data
$side = get_side($side_id);
if (!$side) {
    header("Location: index.php");
    exit;
}

// Fetch parent building for breadcrumb
$building = get_building($side['building_id']);

// Fetch walls for this side
$walls = get_walls_for_side($side_id);
$site_title = get_db()['settings']['site_title'] ?? 'Link-Wall-It';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($side['name']) ?> - <?= htmlspecialchars($site_title) ?></title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f8f9fa; margin: 0; }
        .container { max-width: 960px; margin: 40px auto; padding: 0 20px; }
        header { text-align: center; margin-bottom: 50px; border-bottom: 1px solid #e9ecef; padding-bottom: 20px; }
        h1 { font-size: 2.5em; color: #2c3e50; margin-bottom: 0.2em; }
        .breadcrumb a { color: #3498db; text-decoration: none; }
        .breadcrumb { margin-bottom: 20px; font-size: 1.1em; }
        .wall-list { list-style: none; padding: 0; }
        .wall-item { background: #fff; border: 1px solid #e9ecef; border-radius: 8px; padding: 20px; margin-bottom: 15px; }
        .wall-item h2 { margin-top: 0; }
        .no-content { text-align: center; color: #7f8c8d; padding: 20px; background-color: #fff; border-radius: 8px; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <p class="breadcrumb">
                <a href="index.php">Home</a> &raquo;
                <?php if ($building): ?>
                    <a href="building.php?id=<?= htmlspecialchars($building['id']) ?>"><?= htmlspecialchars($building['name']) ?></a> &raquo;
                <?php endif; ?>
                <?= htmlspecialchars($side['name']) ?>
            </p>
            <h1><?= htmlspecialchars($side['name']) ?></h1>
        </header>

        <main>
            <h2>Walls</h2>
            <?php if (empty($walls)): ?>
                <div class="no-content">
                    <p>This side has no walls yet.</p>
                </div>
            <?php else: ?>
                <div class="wall-list">
                    <?php foreach ($walls as $wall): ?>
                        <a href="wall.php?id=<?= htmlspecialchars($wall['id']) ?>" class="wall-item">
                            <h2><?= htmlspecialchars($wall['name']) ?></h2>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
