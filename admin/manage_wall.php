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

// --- Helper function for image uploads ---
function handle_image_upload($file_input_name) {
    // Check if a file was uploaded
    if (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES[$file_input_name];

        // 1. Check file size (500 KB limit)
        $max_size = 500 * 1024;
        if ($file['size'] > $max_size) {
            return ['error' => 'File is too large. Maximum size is 500KB.'];
        }

        // 2. Check MIME type
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime_type, $allowed_types)) {
            return ['error' => 'Invalid file type. Only JPG, PNG, GIF, and WEBP are allowed.'];
        }

        // 3. Move file
        $upload_dir = __DIR__ . '/../assets/images/';
        // Ensure directory exists
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $new_filename = uniqid('', true) . '_' . basename($file['name']);
        $destination = $upload_dir . $new_filename;

        if (move_uploaded_file($file['tmp_name'], $destination)) {
            // Return the relative path for storage
            return ['path' => 'assets/images/' . $new_filename];
        } else {
            return ['error' => 'Failed to move uploaded file.'];
        }
    }
    // No file uploaded or an error occurred that wasn't UPLOAD_ERR_OK
    return ['path' => null];
}


// --- Form Handling ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Handle 'Create Link'
    if (isset($_POST['create_link'])) {
        $title = $_POST['link_title'];
        $url = $_POST['link_url'];
        $description = $_POST['link_description'] ?? '';

        if (!empty($title) && !empty($url)) {
            $image_result = handle_image_upload('link_image');
            if (isset($image_result['error'])) {
                $error_message = $image_result['error'];
            } else {
                $image_path = $image_result['path'];
                if (create_link($wall_id, $title, $url, $description, $image_path)) {
                    $success_message = 'Link created successfully!';
                } else {
                    $error_message = 'Failed to create link. Please ensure the URL is valid.';
                }
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

        $current_link = get_link($link_id);
        $image_path = $current_link['image'];

        // Handle image deletion
        if (isset($_POST['delete_image']) && $_POST['delete_image'] == '1') {
            if (!empty($image_path) && file_exists(__DIR__ . '/../' . $image_path)) {
                unlink(__DIR__ . '/../' . $image_path);
            }
            $image_path = ''; // Clear the image path
        }

        // Handle new image upload
        $image_result = handle_image_upload('link_image');
        if (isset($image_result['error'])) {
            $error_message = $image_result['error'];
        } else {
            // If a new image was uploaded, use its path
            if ($image_result['path'] !== null) {
                 // Delete old image if it exists
                if (!empty($image_path) && file_exists(__DIR__ . '/../' . $image_path)) {
                    unlink(__DIR__ . '/../' . $image_path);
                }
                $image_path = $image_result['path'];
            }

            if (update_link($link_id, $title, $url, $description, $image_path)) {
                header('Location: manage_wall.php?wall_id=' . $wall_id . '&update=success');
                exit;
            } else {
                $error_message = 'Failed to update link. Please ensure the URL is valid.';
            }
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

        <form action="manage_wall.php?wall_id=<?= htmlspecialchars($wall_id) ?>" method="post" enctype="multipart/form-data">
            <h2>Create New Link</h2>
            <input type="text" name="link_title" placeholder="Link Title" required>
            <input type="url" name="link_url" placeholder="https://example.com" required>
            <textarea name="link_description" placeholder="Optional Description"></textarea>
            <label for="link_image">Image (Optional, max 500KB):</label>
            <input type="file" name="link_image" id="link_image">
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
                            <form action="manage_wall.php?wall_id=<?= htmlspecialchars($wall_id) ?>" method="post" enctype="multipart/form-data" style="width: 100%;">
                                <input type="hidden" name="link_id" value="<?= htmlspecialchars($link['id']) ?>">
                                <input type="text" name="link_title" value="<?= htmlspecialchars($link['title']) ?>" required>
                                <input type="url" name="link_url" value="<?= htmlspecialchars($link['url']) ?>" required>
                                <textarea name="link_description"><?= htmlspecialchars($link['description']) ?></textarea>

                                <label for="link_image_<?= htmlspecialchars($link['id']) ?>">New Image (Optional, max 500KB):</label>
                                <input type="file" name="link_image" id="link_image_<?= htmlspecialchars($link['id']) ?>">

                                <?php if (!empty($link['image'])): ?>
                                    <div class="current-image">
                                        <p>Current Image:</p>
                                        <img src="../<?= htmlspecialchars($link['image']) ?>" alt="Current Image" style="max-width: 100px; max-height: 100px;">
                                        <label>
                                            <input type="checkbox" name="delete_image" value="1"> Delete current image
                                        </label>
                                    </div>
                                <?php endif; ?>

                                <button type="submit" name="update_link">Update</button>
                                <a href="manage_wall.php?wall_id=<?= htmlspecialchars($wall_id) ?>">Cancel</a>
                            </form>
                        <?php else: ?>
                            <span style="display: flex; align-items: center;">
                                <?php if (!empty($link['image'])): ?>
                                    <img src="../<?= htmlspecialchars($link['image']) ?>" alt="Link thumbnail" style="width: 50px; height: 50px; object-fit: cover; margin-right: 15px; border-radius: 4px;">
                                <?php endif; ?>
                                <div>
                                    <strong><a href="<?= htmlspecialchars($link['url']) ?>" target="_blank"><?= htmlspecialchars($link['title']) ?></a></strong>
                                    <small>(<?= htmlspecialchars($link['url']) ?>)</small>
                                    <p style="margin: 0;"><?= htmlspecialchars($link['description']) ?></p>
                                </div>
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
