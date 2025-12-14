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

// Handle Feedback
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback']) && !empty($_POST['feedback_content'])) {
    save_feedback('side', $side_id, $_POST['feedback_content']);
    $feedback_success = "Thank you for your feedback!";
}

// Fetch parent building for breadcrumb
$building = get_building($side['building_id']);

// Fetch lists (walls) for this side
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
        .wall-item { background: #fff; border: 1px solid #e9ecef; border-radius: 8px; padding: 20px; margin-bottom: 15px; display: block; text-decoration: none; color: inherit; }
        .wall-item h2 { margin-top: 0; }
        .no-content { text-align: center; color: #7f8c8d; padding: 20px; background-color: #fff; border-radius: 8px; }
        .share-buttons { margin-top: 15px; display: flex; gap: 10px; justify-content: center; }
        .share-btn { display: inline-block; padding: 5px 10px; border-radius: 4px; background-color: #ecf0f1; color: #34495e; text-decoration: none; font-size: 0.9em; border: 1px solid #bdc3c7; cursor: pointer; }
        .feedback-form { background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #eee; margin-top: 40px; }
        .feedback-form textarea { width: 100%; height: 100px; padding: 10px; margin-bottom: 10px; border: 1px solid #ccc; border-radius: 4px; }
        .success-message { color: #2ecc71; margin-bottom: 15px; text-align: center; }
        .feedback-form button { padding: 10px 15px; border: none; background-color: #3498db; color: white; border-radius: 4px; cursor: pointer; }
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
            <div class="share-buttons">
                <button class="share-btn" onclick="copyToClipboard(window.location.href, this)">Copy Side Link</button>
                <button class="share-btn" onclick="shareToTwitter(window.location.href, 'Check out this page: <?= htmlspecialchars($side['name']) ?>')">Share to Twitter</button>
                <button class="share-btn" onclick="shareToFacebook(window.location.href)">Share to Facebook</button>
            </div>
        </header>

        <main>
            <h2>Lists</h2>
            <?php if (empty($walls)): ?>
                <div class="no-content">
                    <p>This side has no lists yet.</p>
                </div>
            <?php else: ?>
                <div class="wall-list">
                    <?php foreach ($walls as $wall): ?>
                        <a href="list.php?id=<?= htmlspecialchars($wall['id']) ?>" class="wall-item">
                            <h2><?= htmlspecialchars($wall['name']) ?></h2>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="feedback-form">
                <h3>Feedback</h3>
                <?php if (isset($feedback_success)): ?>
                    <p class="success-message"><?= htmlspecialchars($feedback_success) ?></p>
                <?php else: ?>
                    <form action="side.php?id=<?= htmlspecialchars($side_id) ?>" method="post">
                        <textarea name="feedback_content" required placeholder="Send feedback..."></textarea>
                        <button type="submit" name="submit_feedback">Send Feedback</button>
                    </form>
                <?php endif; ?>
            </div>
        </main>
    </div>
    <script src="assets/js/sharing.js"></script>
</body>
</html>
