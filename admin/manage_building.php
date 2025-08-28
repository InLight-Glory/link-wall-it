<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../app/core/functions.php';

// --- Authentication and Initialization ---
$building_id = $_GET['building_id'] ?? null;
if (!$building_id) {
    header('Location: index.php');
    exit;
}

$building = get_building($building_id);
if (!$building) {
    header('Location: index.php');
    exit;
}

$error_message = '';
$success_message = '';

// --- Form Handling ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle 'Create Side'
    if (isset($_POST['create_side']) && !empty($_POST['side_name'])) {
        $result = create_side($building_id, $_POST['side_name']);
        if ($result) {
            $success_message = 'Side created successfully!';
        } else {
            $error_message = 'Failed to create side. A building can only have a maximum of 4 sides.';
        }
    }
    // Handle 'Update Side'
    elseif (isset($_POST['update_side']) && !empty($_POST['side_name']) && !empty($_POST['side_id'])) {
        if (update_side($_POST['side_id'], $_POST['side_name'])) {
            header('Location: manage_building.php?building_id=' . $building_id . '&update=success');
            exit;
        } else {
            $error_message = 'Failed to update side.';
        }
    }
}

// Handle 'Delete Side'
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    if (delete_side($_GET['id'])) {
        header('Location: manage_building.php?building_id=' . $building_id . '&delete=success');
        exit;
    } else {
        $error_message = 'Failed to delete side.';
    }
}

// --- Data Retrieval ---
$sides = get_sides_for_building($building_id);

if (isset($_GET['update']) && $_GET['update'] == 'success') {
    $success_message = 'Side updated successfully!';
}
if (isset($_GET['delete']) && $_GET['delete'] == 'success') {
    $success_message = 'Side deleted successfully!';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Sides for <?= htmlspecialchars($building['name']) ?></title>
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
        .disabled { background-color: #bdc3c7; cursor: not-allowed; }
    </style>
</head>
<body>
    <div class="container">
        <p class="breadcrumb"><a href="index.php">Admin Home</a> &raquo; Manage Building</p>
        <h1>Manage Sides for "<?= htmlspecialchars($building['name']) ?>"</h1>

        <?php if ($success_message): ?>
            <div class="message success"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="message error"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <!-- Form to create a new side -->
        <?php if (count($sides) < 4): ?>
            <form action="manage_building.php?building_id=<?= htmlspecialchars($building_id) ?>" method="post">
                <h2>Create New Side</h2>
                <input type="text" name="side_name" placeholder="Enter side name" required>
                <button type="submit" name="create_side">Create Side</button>
            </form>
        <?php else: ?>
            <h2>Create New Side</h2>
            <p>This building already has the maximum of 4 sides.</p>
        <?php endif; ?>

        <hr>

        <!-- List of existing sides -->
        <h2>Existing Sides</h2>
        <div class="item-list">
            <?php if (empty($sides)): ?>
                <p>No sides found. Create one above!</p>
            <?php else: ?>
                <?php foreach ($sides as $side): ?>
                    <div class="item">
                        <?php
                        $is_editing = (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id']) && $_GET['id'] === $side['id']);
                        ?>

                        <?php if ($is_editing): ?>
                            <form action="manage_building.php?building_id=<?= htmlspecialchars($building_id) ?>" method="post" style="width: 100%;">
                                <input type="hidden" name="side_id" value="<?= htmlspecialchars($side['id']) ?>">
                                <input type="text" name="side_name" value="<?= htmlspecialchars($side['name']) ?>" required>
                                <button type="submit" name="update_side">Update</button>
                                <a href="manage_building.php?building_id=<?= htmlspecialchars($building_id) ?>">Cancel</a>
                            </form>
                        <?php else: ?>
                            <span>
                                <strong><?= htmlspecialchars($side['name']) ?></strong>
                                <small>(ID: <?= htmlspecialchars($side['id']) ?>)</small>
                            </span>
                            <span class="item-actions">
                                <a href="manage_side.php?side_id=<?= htmlspecialchars($side['id']) ?>">Manage Walls</a>
                                <a href="manage_building.php?building_id=<?= htmlspecialchars($building_id) ?>&action=edit&id=<?= htmlspecialchars($side['id']) ?>">Edit Name</a>
                                <a href="manage_building.php?building_id=<?= htmlspecialchars($building_id) ?>&action=delete&id=<?= htmlspecialchars($side['id']) ?>" class="delete" onclick="return confirm('Are you sure you want to delete this side and all its contents?');">Delete</a>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
