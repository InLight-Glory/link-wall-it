<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../app/core/functions.php';

// --- Authentication and Initialization ---
$wall_id = $_GET['wall_id'] ?? null;
if (!$wall_id) {
    header('Location: index.php');
    exit;
}

$wall = get_wall($wall_id);
if (!$wall) {
    header('Location: index.php');
    exit;
}

// Get parents for breadcrumbs
$side = get_side($wall['side_id']);
$building = $side ? get_building($side['building_id']) : null;

$error_message = '';
$success_message = '';

// --- Form Handling ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle 'Create Link'
    if (isset($_POST['create_link'])) {
        $title = $_POST['link_title'];
        $url = $_POST['link_url'];
        $description = $_POST['link_description'] ?? '';

        if (!empty($title) && !empty($url)) {
            if (create_link($wall_id, $title, $url, $description)) {
                $success_message = 'Link created successfully!';
            } else {
                $error_message = 'Failed to create link. Please ensure the URL is valid.';
            }
        } else {
            $error_message = 'Title and URL are required.';
        }
    }
    // Handle 'Update Link'
    elseif (isset($_POST['update_link'])) {
        $link_id = $_POST['link_id'];
        $title = $_POST['link_title'];
        $url = $_POST['link_url'];
        $description = $_POST['link_description'] ?? '';

        if (update_link($link_id, $title, $url, $description)) {
            header('Location: manage_wall.php?wall_id=' . $wall_id . '&update=success');
            exit;
        } else {
            $error_message = 'Failed to update link. Please ensure the URL is valid.';
        }
    }
}

// Handle 'Delete Link'
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    if (delete_link($_GET['id'])) {
        header('Location: manage_wall.php?wall_id=' . $wall_id . '&delete=success');
        exit;
    } else {
        $error_message = 'Failed to delete link.';
    }
}

// --- Data Retrieval ---
$links = get_links_for_wall($wall_id);

if (isset($_GET['update']) && $_GET['update'] == 'success') {
    $success_message = 'Link updated successfully!';
}
if (isset($_GET['delete']) && $_GET['delete'] == 'success') {
    $success_message = 'Link deleted successfully!';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Links for <?= htmlspecialchars($wall['name']) ?></title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f4; }
        .container { max-width: 800px; margin: 20px auto; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1, h2 { color: #2c3e50; }
        .breadcrumb { margin-bottom: 20px; }
        .breadcrumb a { color: #3498db; text-decoration: none; }
        hr { border: 0; height: 1px; background: #ddd; margin: 20px 0; }
        form { margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        input[type="text"], input[type="url"], textarea { width: 95%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; margin-bottom: 10px; }
        button { padding: 10px 15px; border: none; background-color: #3498db; color: white; border-radius: 4px; cursor: pointer; }
        button[type="submit"] { background-color: #2ecc71; }
        .item-list { list-style: none; padding: 0; }
        .item { display: flex; justify-content: space-between; align-items: center; padding: 10px; border-bottom: 1px solid #eee; }
        .item:last-child { border-bottom: none; }
        .item-actions a { text-decoration: none; color: #3498db; margin-left: 15px; }
        .item-actions a.delete { color: #e74c3c; }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .success { background-color: #e8f5e9; color: #2e7d32; }
        .error { background-color: #ffebee; color: #c62828; }
    </style>
</head>
<body>
    <div class="container">
        <p class="breadcrumb">
            <a href="index.php">Admin Home</a> &raquo;
            <?php if ($building): ?>
                <a href="manage_building.php?building_id=<?= htmlspecialchars($building['id']) ?>"><?= htmlspecialchars($building['name']) ?></a> &raquo;
            <?php endif; ?>
            <?php if ($side): ?>
                <a href="manage_side.php?side_id=<?= htmlspecialchars($side['id']) ?>"><?= htmlspecialchars($side['name']) ?></a> &raquo;
            <?php endif; ?>
            Manage Wall
        </p>
        <h1>Manage Links for "<?= htmlspecialchars($wall['name']) ?>"</h1>

        <?php if ($success_message): ?><div class="message success"><?= htmlspecialchars($success_message) ?></div><?php endif; ?>
        <?php if ($error_message): ?><div class="message error"><?= htmlspecialchars($error_message) ?></div><?php endif; ?>

        <form action="manage_wall.php?wall_id=<?= htmlspecialchars($wall_id) ?>" method="post">
            <h2>Create New Link</h2>
            <input type="text" name="link_title" placeholder="Link Title" required>
            <input type="url" name="link_url" placeholder="https://example.com" required>
            <textarea name="link_description" placeholder="Optional Description"></textarea>
            <button type="submit" name="create_link">Create Link</button>
        </form>

        <hr>

        <h2>Existing Links</h2>
        <div class="item-list">
            <?php if (empty($links)): ?>
                <p>No links found. Create one above!</p>
            <?php else: ?>
                <?php foreach ($links as $link): ?>
                    <div class="item">
                        <?php
                        $is_editing = (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id']) && $_GET['id'] === $link['id']);
                        ?>

                        <?php if ($is_editing): ?>
                            <form action="manage_wall.php?wall_id=<?= htmlspecialchars($wall_id) ?>" method="post" style="width: 100%;">
                                <input type="hidden" name="link_id" value="<?= htmlspecialchars($link['id']) ?>">
                                <input type="text" name="link_title" value="<?= htmlspecialchars($link['title']) ?>" required>
                                <input type="url" name="link_url" value="<?= htmlspecialchars($link['url']) ?>" required>
                                <textarea name="link_description"><?= htmlspecialchars($link['description']) ?></textarea>
                                <button type="submit" name="update_link">Update</button>
                                <a href="manage_wall.php?wall_id=<?= htmlspecialchars($wall_id) ?>">Cancel</a>
                            </form>
                        <?php else: ?>
                            <span>
                                <strong><a href="<?= htmlspecialchars($link['url']) ?>" target="_blank"><?= htmlspecialchars($link['title']) ?></a></strong>
                                <small>(<?= htmlspecialchars($link['url']) ?>)</small>
                                <p><?= htmlspecialchars($link['description']) ?></p>
                            </span>
                            <span class="item-actions">
                                <a href="manage_wall.php?wall_id=<?= htmlspecialchars($wall_id) ?>&action=edit&id=<?= htmlspecialchars($link['id']) ?>">Edit</a>
                                <a href="manage_wall.php?wall_id=<?= htmlspecialchars($wall_id) ?>&action=delete&id=<?= htmlspecialchars($link['id']) ?>" class="delete" onclick="return confirm('Are you sure?');">Delete</a>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
