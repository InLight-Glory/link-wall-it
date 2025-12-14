<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../app/core/functions.php';

// --- Data Retrieval ---
$feedbacks = get_all_feedback();

// Sort by timestamp desc
usort($feedbacks, function($a, $b) {
    return $b['timestamp'] - $a['timestamp'];
});

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Feedback</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f4; }
        .container { max-width: 800px; margin: 20px auto; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #2c3e50; }
        .breadcrumb { margin-bottom: 20px; }
        .breadcrumb a { color: #3498db; text-decoration: none; }
        .feedback-list { list-style: none; padding: 0; }
        .feedback-item { padding: 15px; border-bottom: 1px solid #eee; }
        .feedback-meta { font-size: 0.9em; color: #7f8c8d; margin-bottom: 5px; }
        .feedback-content { white-space: pre-wrap; }
    </style>
</head>
<body>
    <div class="container">
        <p class="breadcrumb"><a href="index.php">Admin Home</a> &raquo; Feedback</p>
        <h1>User Feedback</h1>

        <ul class="feedback-list">
            <?php if (empty($feedbacks)): ?>
                <li>No feedback received yet.</li>
            <?php else: ?>
                <?php foreach ($feedbacks as $fb): ?>
                    <li class="feedback-item">
                        <div class="feedback-meta">
                            <strong><?= strtoupper(htmlspecialchars($fb['type'])) ?></strong>
                            (ID: <?= htmlspecialchars($fb['reference_id']) ?>)
                            - <?= date('Y-m-d H:i:s', $fb['timestamp']) ?>
                        </div>
                        <div class="feedback-content"><?= htmlspecialchars($fb['content']) ?></div>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </div>
</body>
</html>
