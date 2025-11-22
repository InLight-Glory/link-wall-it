<?php
// public/index.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/app/core/functions.php';

$db = get_db();
$settings = $db['settings'] ?? [];
$site_title = $settings['site_title'] ?? 'Link-Wall-It';
$site_description = $settings['site_description'] ?? 'Your personal link wall.';

$buildings = get_all_buildings();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($site_title) ?></title>
    <meta name="description" content="<?= htmlspecialchars($site_description) ?>">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f8f9fa; margin: 0; }
        .container { max-width: 960px; margin: 40px auto; padding: 0 20px; }
        header { text-align: center; margin-bottom: 50px; }
        h1 { font-size: 2.5em; color: #2c3e50; margin-bottom: 0.2em; }
        header p { font-size: 1.1em; color: #7f8c8d; }
        .building-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 25px; }
        .building-card {
            background: #fff;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .building-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.1);
        }
        .building-card h2 { margin-top: 0; font-size: 1.4em; }
        .building-card a { text-decoration: none; color: #3498db; font-weight: bold; }
        .building-card a:hover { text-decoration: underline; }
        .no-buildings { text-align: center; color: #7f8c8d; padding: 20px; background-color: #fff; border-radius: 8px; }
        footer { text-align: center; margin-top: 60px; padding: 20px; font-size: 0.9em; color: #95a5a6; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1><?= htmlspecialchars($site_title) ?></h1>
            <p><?= htmlspecialchars($site_description) ?></p>
        </header>

        <main>
            <h2>Our Buildings</h2>
            <?php if (empty($buildings)): ?>
                <div class="no-buildings">
                    <p>No buildings have been set up yet. Please check back later.</p>
                </div>
            <?php else: ?>
                <div class="building-grid">
                    <?php foreach ($buildings as $building): ?>
                        <div class="building-card">
                            <h2><a href="building.php?id=<?= htmlspecialchars($building['id']) ?>">🏢 <?= htmlspecialchars($building['name']) ?></a></h2>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>

        <footer>
            <p>Powered by Link-Wall-It.</p>
        </footer>
    </div>
</body>
</html>
