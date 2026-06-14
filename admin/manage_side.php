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
    require_csrf();

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

    // Handle 'Delete Wall'
    elseif (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['id'])) {
        if (delete_wall($_POST['id'])) {
            header('Location: manage_side.php?side_id=' . $side_id . '&delete=success');
            exit;
        } else {
            $error_message = 'Failed to delete wall.';
        }
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
<html lang="en"<?= theme_html_attr() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($side['name']) ?> &middot; Walls</title>
    <link rel="stylesheet" href="../assets/css/app.css">
</head>
<body>
    <div class="container">
        <header class="app-header">
            <h1><?= htmlspecialchars($side['name']) ?></h1>
            <nav class="app-header__nav">
                <a href="editor.php">Editor</a>
                <a href="index.php">Buildings</a>
                <a href="settings.php">Settings</a>
                <a href="logout.php" class="danger">Sign out</a>
            </nav>
        </header>

        <nav class="breadcrumb">
            <a href="index.php">Buildings</a>
            <span class="breadcrumb__sep">/</span>
            <a href="manage_building.php?building_id=<?= htmlspecialchars($building['id']) ?>"><?= htmlspecialchars($building['name']) ?></a>
            <span class="breadcrumb__sep">/</span>
            <?= htmlspecialchars($side['name']) ?>
        </nav>

        <?php if ($success_message): ?>
            <div class="message message--success"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="message message--error"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <section class="section">
            <div class="section__heading"><h2>Create wall</h2></div>
            <form action="manage_side.php?side_id=<?= htmlspecialchars($side_id) ?>" method="post" class="field--inline">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="text" name="wall_name" placeholder="Wall name" required>
                <button type="submit" name="create_wall" class="btn">Create</button>
            </form>
        </section>

        <section class="section">
            <div class="section__heading"><h2>Walls</h2></div>

            <?php if (empty($walls)): ?>
                <div class="list__empty">No walls yet. Create one above.</div>
            <?php else: ?>
                <div>
                    <?php foreach ($walls as $wall): ?>
                        <?php $is_editing = (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id']) && $_GET['id'] === $wall['id']); ?>
                        <div class="node">
                            <?php if ($is_editing): ?>
                                <form action="manage_side.php?side_id=<?= htmlspecialchars($side_id) ?>" method="post" class="field--inline" style="flex: 1;">
                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                    <input type="hidden" name="wall_id" value="<?= htmlspecialchars($wall['id']) ?>">
                                    <input type="text" name="wall_name" value="<?= htmlspecialchars($wall['name']) ?>" required autofocus>
                                    <button type="submit" name="update_wall" class="btn btn--sm">Save</button>
                                    <a href="manage_side.php?side_id=<?= htmlspecialchars($side_id) ?>" class="btn btn--ghost btn--sm">Cancel</a>
                                </form>
                            <?php else: ?>
                                <span>
                                    <span class="node__name"><?= htmlspecialchars($wall['name']) ?></span>
                                    <span class="node__id"><?= htmlspecialchars($wall['id']) ?></span>
                                </span>
                                <span class="node__actions">
                                    <a class="btn btn--secondary btn--sm" href="manage_wall.php?wall_id=<?= htmlspecialchars($wall['id']) ?>">Links</a>
                                    <a class="btn btn--ghost btn--sm" href="manage_side.php?side_id=<?= htmlspecialchars($side_id) ?>&action=edit&id=<?= htmlspecialchars($wall['id']) ?>">Rename</a>
                                    <form action="manage_side.php?side_id=<?= htmlspecialchars($side_id) ?>" method="post" style="display:inline;" onsubmit="return confirm('Delete this wall and all its contents?');">
                                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= htmlspecialchars($wall['id']) ?>">
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
    <?= theme_picker_html() ?>
</body>
</html>
