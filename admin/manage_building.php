<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../app/core/functions.php';

require_login();

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
    require_csrf();

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

    // Handle 'Delete Side'
    elseif (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['id'])) {
        if (delete_side($_POST['id'])) {
            header('Location: manage_building.php?building_id=' . $building_id . '&delete=success');
            exit;
        } else {
            $error_message = 'Failed to delete side.';
        }
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
    <title><?= htmlspecialchars($building['name']) ?> &middot; Sides</title>
    <link rel="stylesheet" href="../assets/css/app.css">
</head>
<body>
    <div class="container">
        <header class="app-header">
            <h1><?= htmlspecialchars($building['name']) ?></h1>
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
            <?= htmlspecialchars($building['name']) ?>
        </nav>

        <?php if ($success_message): ?>
            <div class="message message--success"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="message message--error"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <section class="section">
            <div class="section__heading">
                <h2>Create side</h2>
                <small><?= count($sides) ?> / 4 sides used</small>
            </div>
            <?php if (count($sides) < 4): ?>
                <form action="manage_building.php?building_id=<?= htmlspecialchars($building_id) ?>" method="post" class="field--inline">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="text" name="side_name" placeholder="Side name" required>
                    <button type="submit" name="create_side" class="btn">Create</button>
                </form>
            <?php else: ?>
                <p style="color: var(--color-text-muted); font-size: var(--text-sm); margin: 0;">This building has the maximum of 4 sides.</p>
            <?php endif; ?>
        </section>

        <section class="section">
            <div class="section__heading"><h2>Sides</h2></div>

            <?php if (empty($sides)): ?>
                <div class="list__empty">No sides yet. Create one above.</div>
            <?php else: ?>
                <div>
                    <?php foreach ($sides as $side): ?>
                        <?php $is_editing = (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id']) && $_GET['id'] === $side['id']); ?>
                        <div class="node">
                            <?php if ($is_editing): ?>
                                <form action="manage_building.php?building_id=<?= htmlspecialchars($building_id) ?>" method="post" class="field--inline" style="flex: 1;">
                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                    <input type="hidden" name="side_id" value="<?= htmlspecialchars($side['id']) ?>">
                                    <input type="text" name="side_name" value="<?= htmlspecialchars($side['name']) ?>" required autofocus>
                                    <button type="submit" name="update_side" class="btn btn--sm">Save</button>
                                    <a href="manage_building.php?building_id=<?= htmlspecialchars($building_id) ?>" class="btn btn--ghost btn--sm">Cancel</a>
                                </form>
                            <?php else: ?>
                                <span>
                                    <span class="node__name"><?= htmlspecialchars($side['name']) ?></span>
                                    <span class="node__id"><?= htmlspecialchars($side['id']) ?></span>
                                </span>
                                <span class="node__actions">
                                    <a class="btn btn--secondary btn--sm" href="manage_side.php?side_id=<?= htmlspecialchars($side['id']) ?>">Walls</a>
                                    <a class="btn btn--ghost btn--sm" href="manage_building.php?building_id=<?= htmlspecialchars($building_id) ?>&action=edit&id=<?= htmlspecialchars($side['id']) ?>">Rename</a>
                                    <form action="manage_building.php?building_id=<?= htmlspecialchars($building_id) ?>" method="post" style="display:inline;" onsubmit="return confirm('Delete this side and all its contents?');">
                                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= htmlspecialchars($side['id']) ?>">
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
