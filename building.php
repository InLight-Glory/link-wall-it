<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/app/core/functions.php';

// Get building ID from URL
$building_id = $_GET['id'] ?? null;
if (!$building_id) {
    header("Location: index.php");
    exit;
}

// Fetch building data
$building = get_building($building_id);
if (!$building) {
    // Optional: Redirect to a 404 page
    header("Location: index.php");
    exit;
}

// Fetch sides for this building
$sides = get_sides_for_building($building_id);
$site_title = get_db()['settings']['site_title'] ?? 'Link-Wall-It';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($building['name']) ?> - <?= htmlspecialchars($site_title) ?></title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f8f9fa; margin: 0; }
        .container { max-width: 960px; margin: 40px auto; padding: 0 20px; }
        header { text-align: center; margin-bottom: 50px; border-bottom: 1px solid #e9ecef; padding-bottom: 20px; }
        h1 { font-size: 2.5em; color: #2c3e50; margin-bottom: 0.2em; }
        .breadcrumb a { color: #3498db; text-decoration: none; }
        .breadcrumb { margin-bottom: 20px; font-size: 1.1em; }
        .side-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 25px; }
        .side-card {
            background: #fff;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        .side-card h2 { margin-top: 0; font-size: 1.4em; }
        .side-card a { text-decoration: none; color: #2c3e50; font-weight: bold; }
        .side-card a:hover { text-decoration: underline; }
        .no-content { text-align: center; color: #7f8c8d; padding: 20px; background-color: #fff; border-radius: 8px; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <p class="breadcrumb"><a href="index.php">Home</a> &raquo; <?= htmlspecialchars($building['name']) ?></p>
            <h1>🏢 <?= htmlspecialchars($building['name']) ?></h1>
        </header>

        <main>
            <h2>Sides</h2>
            <?php if (empty($sides)): ?>
                <div class="no-content">
                    <p>This building has no sides yet.</p>
                </div>
            <?php else: ?>
                <div class="side-grid">
                    <?php foreach ($sides as $side): ?>
                        <div class="side-card">
                            <h2><a href="side.php?id=<?= htmlspecialchars($side['id']) ?>">📐 <?= htmlspecialchars($side['name']) ?></a></h2>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
