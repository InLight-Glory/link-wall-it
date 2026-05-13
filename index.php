<?php
// public/index.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/app/core/functions.php';

$db = get_db();
$settings = $db['settings'] ?? [];
$site_title = $settings['site_title'] ?? 'Link-Wall-It';
$site_description = $settings['site_description'] ?? 'Your personal link wall.';

$buildings = get_publicly_listable_buildings();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($site_title) ?></title>
    <meta name="description" content="<?= htmlspecialchars($site_description) ?>">
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
    <div class="container">
        <header class="page-header">
            <h1><?= htmlspecialchars($site_title) ?></h1>
            <p class="page-header__sub"><?= htmlspecialchars($site_description) ?></p>
        </header>

        <main>
            <?php if (empty($buildings)): ?>
                <div class="card">
                    <p class="list__empty">No buildings have been set up yet.</p>
                </div>
            <?php else: ?>
                <div class="tile-grid">
                    <?php foreach ($buildings as $building): ?>
                        <a class="tile" href="building.php?id=<?= htmlspecialchars($building['id']) ?>">
                            <h2 class="tile__title"><?= htmlspecialchars($building['name']) ?></h2>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>

        <footer class="app-footer">
            Powered by Link-Wall-It
        </footer>
    </div>
</body>
</html>
