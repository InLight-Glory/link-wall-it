<?php
// admin/index.php

// Set the error reporting level for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include the core functions file
require_once __DIR__ . '/../app/core/functions.php';

$error_message = '';
$success_message = '';

// --- Form Handling ---

// Check if the request is a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Handle 'Create Building' action
    if (isset($_POST['create_building']) && !empty($_POST['building_name'])) {
        if (create_building($_POST['building_name'])) {
            $success_message = 'Building created successfully!';
        } else {
            $error_message = 'Failed to create building.';
        }
    }

    // Handle 'Update Building' action
    elseif (isset($_POST['update_building']) && !empty($_POST['building_name']) && !empty($_POST['building_id'])) {
        if (update_building($_POST['building_id'], $_POST['building_name'])) {
            // Redirect to clean the URL and prevent re-submission
            header('Location: index.php?update=success');
            exit;
        } else {
            $error_message = 'Failed to update building.';
        }
    }

}

// Handle 'Delete Building' action from GET request
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    if (delete_building($_GET['id'])) {
        header('Location: index.php?delete=success');
        exit;
    } else {
        $error_message = 'Failed to delete building.';
    }
}

// --- Data Retrieval for Display ---

// Get all buildings to display on the page
$buildings = get_all_buildings();

// Check for messages from redirects
if(isset($_GET['update']) && $_GET['update'] == 'success') {
    $success_message = 'Building updated successfully!';
}
if(isset($_GET['delete']) && $_GET['delete'] == 'success') {
    $success_message = 'Building deleted successfully!';
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Manage Buildings</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f4; }
        .container { max-width: 800px; margin: 20px auto; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1, h2 { color: #2c3e50; }
        hr { border: 0; height: 1px; background: #ddd; margin: 20px 0; }
        form { margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        input[type="text"] { width: calc(100% - 110px); padding: 10px; border: 1px solid #ccc; border-radius: 4px; }
        button { padding: 10px 15px; border: none; background-color: #3498db; color: white; border-radius: 4px; cursor: pointer; }
        button[type="submit"] { background-color: #2ecc71; }
        .building-list { list-style: none; padding: 0; }
        .building-item { display: flex; justify-content: space-between; align-items: center; padding: 10px; border-bottom: 1px solid #eee; }
        .building-item:last-child { border-bottom: none; }
        .building-actions a { text-decoration: none; color: #3498db; margin-left: 15px; }
        .building-actions a.delete { color: #e74c3c; }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .success { background-color: #e8f5e9; color: #2e7d32; }
        .error { background-color: #ffebee; color: #c62828; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Manage Buildings</h1>
        <p><a href="feedback.php" style="color: #3498db; text-decoration: none;">View User Feedback</a></p>

        <?php if ($success_message): ?>
            <div class="message success"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="message error"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <!-- Form to create a new building -->
        <form action="index.php" method="post">
            <h2>Create New Building</h2>
            <input type="text" name="building_name" placeholder="Enter building name" required>
            <button type="submit" name="create_building">Create Building</button>
        </form>

        <hr>

        <!-- List of existing buildings -->
        <h2>Existing Buildings</h2>
        <div class="building-list">
            <?php if (empty($buildings)): ?>
                <p>No buildings found. Create one above!</p>
            <?php else: ?>
                <?php foreach ($buildings as $building): ?>
                    <div class="building-item">
                        <?php
                        // Check if we are in edit mode for this building
                        $is_editing = (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id']) && $_GET['id'] === $building['id']);
                        ?>

                        <?php if ($is_editing): ?>
                            <!-- Edit form -->
                            <form action="index.php" method="post" style="width: 100%;">
                                <input type="hidden" name="building_id" value="<?= htmlspecialchars($building['id']) ?>">
                                <input type="text" name="building_name" value="<?= htmlspecialchars($building['name']) ?>" required>
                                <button type="submit" name="update_building">Update</button>
                                <a href="index.php">Cancel</a>
                            </form>
                        <?php else: ?>
                            <!-- Display building name and actions -->
                            <span>
                                <strong><?= htmlspecialchars($building['name']) ?></strong>
                                <small>(ID: <?= htmlspecialchars($building['id']) ?>)</small>
                            </span>
                            <span class="building-actions">
                                <a href="manage_building.php?building_id=<?= htmlspecialchars($building['id']) ?>">Manage Sides</a>
                                <a href="index.php?action=edit&id=<?= htmlspecialchars($building['id']) ?>">Edit Name</a>
                                <a href="index.php?action=delete&id=<?= htmlspecialchars($building['id']) ?>" class="delete" onclick="return confirm('Are you sure you want to delete this building and all its contents?');">Delete</a>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
