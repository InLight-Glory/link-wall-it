<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

require_once __DIR__ . '/app/core/functions.php';

// Get wall ID from URL (keep param 'id')
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

// --- Check Access Control ---
$access_type = $wall['access_control']['type'];
$auth_error = '';
$is_unlocked = false;

if (isset($_SESSION['unlocked_walls']) && in_array($wall_id, $_SESSION['unlocked_walls'])) {
    $is_unlocked = true;
}
if (isset($_SESSION['wall_passwords'][$wall_id])) {
    $is_unlocked = true;
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Unlock
    if ($access_type === 'password' && isset($_POST['wall_password'])) {
        $submitted_password = $_POST['wall_password'];
        if (verify_password($submitted_password, $wall['access_control']['password']['hash'], $wall['access_control']['password']['salt'])) {
            $_SESSION['wall_passwords'][$wall_id] = $submitted_password;
            $is_unlocked = true;
        } else {
            $auth_error = 'Incorrect password.';
        }
    } elseif ($access_type === 'codelist' && isset($_POST['access_code'])) {
        $submitted_code = $_POST['access_code'];
        if (verify_codelist_code($submitted_code, $wall['access_control']['codelist'])) {
            $_SESSION['unlocked_walls'][] = $wall_id;
            $is_unlocked = true;
        } else {
            $auth_error = 'Incorrect access code.';
        }
    }

    // Feedback
    if (isset($_POST['submit_feedback']) && !empty($_POST['feedback_content'])) {
        save_feedback('list', $wall_id, $_POST['feedback_content']);
        $feedback_success = "Thank you for your feedback!";
    }

    // Link Feedback
    if (isset($_POST['submit_link_feedback']) && !empty($_POST['feedback_content']) && !empty($_POST['feedback_link_id'])) {
        save_feedback('link', $_POST['feedback_link_id'], $_POST['feedback_content']);
        $feedback_success = "Thank you for your feedback on the link!";
    }
}

$can_view_content = ($access_type === 'public') || $is_unlocked;

// --- Data Retrieval for Display ---
$items = [];
if ($can_view_content) {
    $raw_items = get_links_for_wall($wall_id); // Sorted
    if ($access_type === 'password' && $is_unlocked) {
        $password = $_SESSION['wall_passwords'][$wall_id];
        foreach ($raw_items as $item) {
            $type = $item['type'] ?? 'link';
            if ($type === 'link') {
                $item['title'] = decrypt_data($item['title'], $password) ?: '[Decryption Failed]';
                $item['description'] = decrypt_data($item['description'], $password) ?: '';
            } else {
                $item['content'] = decrypt_data($item['content'] ?? '', $password) ?: '[Decryption Failed]';
            }
            $items[] = $item;
        }
    } else {
        $items = $raw_items;
    }
}

// --- Parent data for breadcrumbs ---
$side = get_side($wall['side_id']);
$building = $side ? get_building($side['building_id']) : null;
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
        .link-item { background: #fff; border: 1px solid #e9ecef; border-radius: 8px; padding: 20px; margin-bottom: 15px; display: block; text-decoration: none; color: inherit; transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .link-item:hover { transform: translateY(-3px); box-shadow: 0 5px 10px rgba(0,0,0,0.08); }
        .link-item h2 { margin-top: 0; font-size: 1.3em; color: #3498db; }
        .link-item p { margin-bottom: 0; color: #555; }
        .link-content { display: flex; align-items: center; }
        .link-image { flex-shrink: 0; width: 80px; height: 80px; margin-right: 20px; }
        .link-image img { width: 100%; height: 100%; object-fit: cover; border-radius: 8px; }
        .link-text { flex-grow: 1; }
        .instruction-item { background: #fff8e1; border: 1px solid #ffe0b2; border-radius: 8px; padding: 20px; margin-bottom: 15px; color: #5d4037; white-space: pre-wrap; }
        .no-content, .access-form { text-align: center; color: #7f8c8d; padding: 40px 20px; background-color: #fff; border-radius: 8px; }
        .access-form input { padding: 10px; width: 250px; border: 1px solid #ccc; border-radius: 4px; }
        .access-form button { padding: 10px 15px; border: none; background-color: #3498db; color: white; border-radius: 4px; cursor: pointer; }
        .error-message { color: #e74c3c; margin-bottom: 15px; }
        .success-message { color: #2ecc71; margin-bottom: 15px; text-align: center; }
        .share-buttons { margin-top: 15px; display: flex; gap: 10px; justify-content: center; }
        .share-btn { display: inline-block; padding: 5px 10px; border-radius: 4px; background-color: #ecf0f1; color: #34495e; text-decoration: none; font-size: 0.9em; border: 1px solid #bdc3c7; cursor: pointer; }
        .link-item-footer { margin-top: 15px; text-align: right; }
        .feedback-form { background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #eee; margin-top: 40px; }
        .feedback-form textarea { width: 100%; height: 100px; padding: 10px; margin-bottom: 10px; border: 1px solid #ccc; border-radius: 4px; }
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
            <div class="share-buttons">
                <button class="share-btn" onclick="copyToClipboard(window.location.href, this)">Copy List Link</button>
                <button class="share-btn" onclick="shareToTwitter(window.location.href, 'Check out this list: <?= htmlspecialchars($wall['name']) ?>')">Share to Twitter</button>
                <button class="share-btn" onclick="shareToFacebook(window.location.href)">Share to Facebook</button>
            </div>
        </header>

        <main>
            <?php if ($can_view_content): ?>
                <div class="link-list">
                    <?php if (empty($items)): ?>
                        <div class="no-content"><p>This list is empty.</p></div>
                    <?php else: ?>
                        <?php foreach ($items as $item): ?>
                            <?php if (($item['type'] ?? 'link') === 'instruction'): ?>
                                <div class="instruction-item"><?= nl2br(htmlspecialchars($item['content'] ?? '')) ?></div>
                            <?php else: ?>
                                <a href="<?= htmlspecialchars($item['url']) ?>" target="_blank" class="link-item">
                                    <div class="link-content">
                                        <?php if (!empty($item['image'])): ?>
                                            <div class="link-image"><img src="<?= htmlspecialchars($item['image']) ?>" alt="Link thumbnail"></div>
                                        <?php endif; ?>
                                        <div class="link-text">
                                            <h2><?= htmlspecialchars($item['title']) ?></h2>
                                            <?php if (!empty($item['description'])): ?><p><?= htmlspecialchars($item['description']) ?></p><?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="link-item-footer share-buttons">
                                        <button class="share-btn" onclick="event.preventDefault(); copyToClipboard('<?= htmlspecialchars($item['url']) ?>', this)">Copy Link</button>
                                        <button class="share-btn" onclick="event.preventDefault(); shareToTwitter('<?= htmlspecialchars($item['url']) ?>', 'Check out this link: <?= htmlspecialchars($item['title']) ?>')">Share to Twitter</button>
                                        <button class="share-btn" onclick="event.preventDefault(); shareToFacebook('<?= htmlspecialchars($item['url']) ?>')">Share to Facebook</button>
                                        <button class="share-btn" onclick="event.preventDefault(); sendLinkFeedback('<?= htmlspecialchars($item['id']) ?>', '<?= htmlspecialchars(addslashes($item['title'])) ?>')">Feedback</button>
                                    </div>
                                </a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="feedback-form">
                    <h3>Feedback</h3>
                    <?php if (isset($feedback_success)): ?>
                        <p class="success-message"><?= htmlspecialchars($feedback_success) ?></p>
                    <?php else: ?>
                        <form action="list.php?id=<?= htmlspecialchars($wall_id) ?>" method="post">
                            <textarea name="feedback_content" required placeholder="Send feedback to the list owner..."></textarea>
                            <button type="submit" name="submit_feedback">Send Feedback</button>
                        </form>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <div class="access-form">
                    <h2>This content is protected</h2>
                    <?php if ($access_type === 'password'): ?>
                        <p>Please enter the password to view this list.</p>
                        <form action="list.php?id=<?= htmlspecialchars($wall_id) ?>" method="post">
                            <?php if ($auth_error): ?><p class="error-message"><?= htmlspecialchars($auth_error) ?></p><?php endif; ?>
                            <input type="password" name="wall_password" required>
                            <button type="submit">Unlock</button>
                        </form>
                    <?php elseif ($access_type === 'codelist'): ?>
                        <p>Please enter an access code to view this list.</p>
                        <form action="list.php?id=<?= htmlspecialchars($wall_id) ?>" method="post">
                            <?php if ($auth_error): ?><p class="error-message"><?= htmlspecialchars($auth_error) ?></p><?php endif; ?>
                            <input type="text" name="access_code" required>
                            <button type="submit">Unlock</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
    <script src="assets/js/sharing.js"></script>
    <script>
        function sendLinkFeedback(linkId, linkTitle) {
            const feedback = prompt("Enter feedback for link '" + linkTitle + "':");
            if (feedback) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = window.location.href;

                const inputId = document.createElement('input');
                inputId.type = 'hidden';
                inputId.name = 'feedback_link_id';
                inputId.value = linkId;

                const inputContent = document.createElement('input');
                inputContent.type = 'hidden';
                inputContent.name = 'feedback_content';
                inputContent.value = feedback;

                const inputSubmit = document.createElement('input');
                inputSubmit.type = 'hidden';
                inputSubmit.name = 'submit_link_feedback';
                inputSubmit.value = '1';

                form.appendChild(inputId);
                form.appendChild(inputContent);
                form.appendChild(inputSubmit);
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
