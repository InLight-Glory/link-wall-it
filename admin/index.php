<?php
// admin/index.php

// Set the error reporting level for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include the core functions file
require_once __DIR__ . '/../app/core/functions.php';

require_login();

$error_message = '';
$success_message = '';

// --- Form Handling ---

// Check if the request is a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

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

    // Handle 'Delete Building' action
    elseif (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['id'])) {
        if (delete_building($_POST['id'])) {
            header('Location: index.php?delete=success');
            exit;
        } else {
            $error_message = 'Failed to delete building.';
        }
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
    <title>Buildings &middot; Admin</title>
    <link rel="stylesheet" href="../assets/css/app.css">
</head>
<body>
    <div class="container">
        <header class="app-header">
            <h1>Buildings</h1>
            <nav class="app-header__nav">
                <a href="editor.php"><strong>Editor</strong></a>
                <a href="../index.php">View site</a>
                <a href="settings.php">Settings</a>
                <a href="logout.php" class="danger">Sign out</a>
            </nav>
        </header>

        <?php if ($success_message): ?>
            <div class="message message--success"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="message message--error"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <section class="section">
            <div class="section__heading"><h2>Create building</h2></div>
            <form action="index.php" method="post" class="field--inline">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="text" name="building_name" placeholder="Building name" required>
                <button type="submit" name="create_building" class="btn">Create</button>
            </form>
        </section>

        <section class="section">
            <div class="section__heading"><h2>All buildings</h2></div>

            <?php if (empty($buildings)): ?>
                <div class="list__empty">No buildings yet. Create one above.</div>
            <?php else: ?>
                <div>
                    <?php foreach ($buildings as $building): ?>
                        <?php $is_editing = (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id']) && $_GET['id'] === $building['id']); ?>
                        <div class="node">
                            <?php if ($is_editing): ?>
                                <form action="index.php" method="post" class="field--inline" style="flex: 1;">
                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                    <input type="hidden" name="building_id" value="<?= htmlspecialchars($building['id']) ?>">
                                    <input type="text" name="building_name" value="<?= htmlspecialchars($building['name']) ?>" required autofocus>
                                    <button type="submit" name="update_building" class="btn btn--sm">Save</button>
                                    <a href="index.php" class="btn btn--ghost btn--sm">Cancel</a>
                                </form>
                            <?php else: ?>
                                <span>
                                    <span class="node__name"><?= htmlspecialchars($building['name']) ?></span>
                                    <span class="node__id"><?= htmlspecialchars($building['id']) ?></span>
                                </span>
                                <span class="node__actions">
                                    <a class="btn btn--secondary btn--sm" href="manage_building.php?building_id=<?= htmlspecialchars($building['id']) ?>">Sides</a>
                                    <a class="btn btn--ghost btn--sm" href="index.php?action=edit&id=<?= htmlspecialchars($building['id']) ?>">Rename</a>
                                    <form action="index.php" method="post" style="display:inline;" onsubmit="return confirm('Delete this building and all its contents?');">
                                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= htmlspecialchars($building['id']) ?>">
                                        <button type="submit" class="btn btn--ghost btn--sm" style="color: var(--color-danger);">Delete</button>
                                    </form>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</body>
</html>
