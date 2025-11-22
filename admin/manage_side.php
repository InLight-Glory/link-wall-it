<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../app/core/functions.php';

require_login();

// --- Authentication and Initialization ---
$side_id = $_GET['side_id'] ?? null;
if (!$side_id) {
    header('Location: index.php');
    exit;
}

$side = get_side($side_id);
if (!$side) {
    header('Location: index.php');
    exit;
}

// Get parent building for breadcrumbs
$building = get_building($side['building_id']);

$error_message = '';
$success_message = '';

// --- Form Handling ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle 'Create Wall'
    if (isset($_POST['create_wall']) && !empty($_POST['wall_name'])) {
        if (create_wall($side_id, $_POST['wall_name'])) {
            $success_message = 'Wall created successfully!';
        } else {
            $error_message = 'Failed to create wall.';
        }
    }
    // Handle 'Update Wall'
    elseif (isset($_POST['update_wall']) && !empty($_POST['wall_name']) && !empty($_POST['wall_id'])) {
        if (update_wall($_POST['wall_id'], $_POST['wall_name'])) {
            header('Location: manage_side.php?side_id=' . $side_id . '&update=success');
            exit;
        } else {
            $error_message = 'Failed to update wall.';
        }
    }
}

// Handle 'Delete Wall'
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    if (delete_wall($_GET['id'])) {
        header('Location: manage_side.php?side_id=' . $side_id . '&delete=success');
        exit;
    } else {
        $error_message = 'Failed to delete wall.';
    }
}

// --- Data Retrieval ---
$walls = get_walls_for_side($side_id);

if (isset($_GET['update']) && $_GET['update'] == 'success') {
    $success_message = 'Wall updated successfully!';
}
if (isset($_GET['delete']) && $_GET['delete'] == 'success') {
    $success_message = 'Wall deleted successfully!';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Walls for <?= htmlspecialchars($side['name']) ?></title>
    <!-- Re-using the same stylesheet as it's generic enough -->
    <link rel="stylesheet" href="manage_building.css">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f4; }
        .container { max-width: 800px; margin: 20px auto; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1, h2 { color: #2c3e50; }
        .breadcrumb { margin-bottom: 20px; }
        .breadcrumb a { color: #3498db; text-decoration: none; }
        hr { border: 0; height: 1px; background: #ddd; margin: 20px 0; }
        form { margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        input[type="text"] { width: calc(100% - 110px); padding: 10px; border: 1px solid #ccc; border-radius: 4px; }
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
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <p class="breadcrumb">
                <a href="index.php">Admin Home</a> &raquo;
                <a href="manage_building.php?building_id=<?= htmlspecialchars($building['id']) ?>"><?= htmlspecialchars($building['name']) ?></a> &raquo;
                Manage Side
            </p>
            <a href="logout.php" style="color: #e74c3c; text-decoration: none; font-weight: bold;">Logout</a>
        </div>
        <h1>Manage Walls for "<?= htmlspecialchars($side['name']) ?>"</h1>

        <?php if ($success_message): ?>
            <div class="message success"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="message error"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <!-- Form to create a new wall -->
        <form action="manage_side.php?side_id=<?= htmlspecialchars($side_id) ?>" method="post">
            <h2>Create New Wall</h2>
            <input type="text" name="wall_name" placeholder="Enter wall name" required>
            <button type="submit" name="create_wall">Create Wall</button>
        </form>

        <hr>

        <!-- List of existing walls -->
        <h2>Existing Walls</h2>
        <div class="item-list">
            <?php if (empty($walls)): ?>
                <p>No walls found. Create one above!</p>
            <?php else: ?>
                <?php foreach ($walls as $wall): ?>
                    <div class="item">
                        <?php
                        $is_editing = (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id']) && $_GET['id'] === $wall['id']);
                        ?>

                        <?php if ($is_editing): ?>
                            <form action="manage_side.php?side_id=<?= htmlspecialchars($side_id) ?>" method="post" style="width: 100%;">
                                <input type="hidden" name="wall_id" value="<?= htmlspecialchars($wall['id']) ?>">
                                <input type="text" name="wall_name" value="<?= htmlspecialchars($wall['name']) ?>" required>
                                <button type="submit" name="update_wall">Update</button>
                                <a href="manage_side.php?side_id=<?= htmlspecialchars($side_id) ?>">Cancel</a>
                            </form>
                        <?php else: ?>
                            <span>
                                <strong><?= htmlspecialchars($wall['name']) ?></strong>
                                <small>(ID: <?= htmlspecialchars($wall['id']) ?>)</small>
                            </span>
                            <span class="item-actions">
                                <a href="manage_wall.php?wall_id=<?= htmlspecialchars($wall['id']) ?>">Manage Links</a>
                                <a href="manage_side.php?side_id=<?= htmlspecialchars($side_id) ?>&action=edit&id=<?= htmlspecialchars($wall['id']) ?>">Edit Name</a>
                                <a href="manage_side.php?side_id=<?= htmlspecialchars($side_id) ?>&action=delete&id=<?= htmlspecialchars($wall['id']) ?>" class="delete" onclick="return confirm('Are you sure you want to delete this wall and all its contents?');">Delete</a>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
