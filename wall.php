<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/app/core/functions.php';

// Get wall ID from URL
$wall_id = $_GET['id'] ?? null;
if (!$wall_id) {
    header("Location: index.php");
    exit;
}

// Fetch wall data
$wall = get_wall($wall_id);
if (!$wall) {
    header("Location: index.php");
    exit;
}

// Fetch parents for breadcrumbs
$side = get_side($wall['side_id']);
$building = $side ? get_building($side['building_id']) : null;

// Fetch links for this wall
$links = get_links_for_wall($wall_id);
$site_title = get_db()['settings']['site_title'] ?? 'Link-Wall-It';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($wall['name']) ?> - <?= htmlspecialchars($site_title) ?></title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f8f9fa; margin: 0; }
        .container { max-width: 800px; margin: 40px auto; padding: 0 20px; }
        header { text-align: center; margin-bottom: 50px; border-bottom: 1px solid #e9ecef; padding-bottom: 20px; }
        h1 { font-size: 2.5em; color: #2c3e50; margin-bottom: 0.2em; }
        .breadcrumb a { color: #3498db; text-decoration: none; }
        .breadcrumb { margin-bottom: 20px; font-size: 1.1em; }
        .link-list { list-style: none; padding: 0; }
        .link-item {
            background: #fff;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            display: block;
            text-decoration: none;
            color: inherit;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .link-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 10px rgba(0,0,0,0.08);
        }
        .link-item h2 { margin-top: 0; font-size: 1.3em; color: #3498db; }
        .link-item p { margin-bottom: 0; color: #555; }
        .link-content { display: flex; align-items: center; }
        .link-image { flex-shrink: 0; width: 80px; height: 80px; margin-right: 20px; }
        .link-image img { width: 100%; height: 100%; object-fit: cover; border-radius: 8px; }
        .link-text { flex-grow: 1; }
        .no-content { text-align: center; color: #7f8c8d; padding: 20px; background-color: #fff; border-radius: 8px; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <p class="breadcrumb">
                <a href="index.php">Home</a> &raquo;
                <?php if ($building): ?><a href="building.php?id=<?= htmlspecialchars($building['id']) ?>"><?= htmlspecialchars($building['name']) ?></a> &raquo; <?php endif; ?>
                <?php if ($side): ?><a href="side.php?id=<?= htmlspecialchars($side['id']) ?>"><?= htmlspecialchars($side['name']) ?></a> &raquo; <?php endif; ?>
                <?= htmlspecialchars($wall['name']) ?>
            </p>
            <h1><?= htmlspecialchars($wall['name']) ?></h1>
        </header>

        <main>
            <div class="link-list">
                <?php if (empty($links)): ?>
                    <div class="no-content">
                        <p>This wall has no links yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($links as $link): ?>
                        <a href="<?= htmlspecialchars($link['url']) ?>" target="_blank" class="link-item">
                            <div class="link-content">
                                <?php if (!empty($link['image'])): ?>
                                    <div class="link-image">
                                        <img src="<?= htmlspecialchars($link['image']) ?>" alt="Link thumbnail">
                                    </div>
                                <?php endif; ?>
                                <div class="link-text">
                                    <h2><?= htmlspecialchars($link['title']) ?></h2>
                                    <?php if (!empty($link['description'])): ?>
                                        <p><?= htmlspecialchars($link['description']) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
