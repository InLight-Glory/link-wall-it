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
    if (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES[$file_input_name];
        $max_size = 500 * 1024;
        if ($file['size'] > $max_size) {
            return ['error' => 'File is too large. Maximum size is 500KB.'];
        }
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime_type, $allowed_types)) {
            return ['error' => 'Invalid file type. Only JPG, PNG, GIF, and WEBP are allowed.'];
        }

        $upload_dir = __DIR__ . '/../assets/images/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $new_filename = uniqid('', true) . '_' . basename($file['name']);
        $destination = $upload_dir . $new_filename;

        if (move_uploaded_file($file['tmp_name'], $destination)) {
            return ['path' => 'assets/images/' . $new_filename];
        } else {
            return ['error' => 'Failed to move uploaded file.'];
        }
    }
    return ['path' => null];
}

// --- Form Handling ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Handle 'Update Access Control'
    if (isset($_POST['update_access'])) {
        $access_type = $_POST['access_type'] ?? 'public';
        $access_value = null;

        if ($access_type === 'password') {
            $access_value = $_POST['access_password'] ?? '';
            if (empty($access_value)) $access_type = 'public';
        } elseif ($access_type === 'codelist') {
            $codelist_str = $_POST['access_codelist'] ?? '';
            $access_value = preg_split('/[,\n\r]+/', $codelist_str);
            $access_value = array_filter(array_map('trim', $access_value));
            if (empty($access_value)) $access_type = 'public';
        }

        if (update_wall_access($wall_id, $access_type, $access_value)) {
            $success_message = 'List access control updated successfully!';
            $wall = get_wall($wall_id); // Refresh data
        } else {
            $error_message = 'Failed to update access control.';
        }
    }

    // Common logic for checking password protection
    $is_protected = $wall['access_control']['type'] === 'password';
    $password_for_enc = $_POST['wall_password_for_encryption'] ?? '';
    $can_proceed = true;
    if ($is_protected) {
        if (!verify_password($password_for_enc, $wall['access_control']['password']['hash'], $wall['access_control']['password']['salt'])) {
            $error_message = 'Incorrect list password. Cannot save changes.';
            $can_proceed = false;
        }
    }

    // Handle 'Create Link'
    if (isset($_POST['create_link']) && $can_proceed) {
        $title = $_POST['link_title'] ?? '';
        $url = $_POST['link_url'] ?? '';
        $description = $_POST['link_description'] ?? '';

        if (empty($title) || empty($url)) {
            $error_message = 'Title and URL are required.';
        } elseif (!filter_var($url, FILTER_VALIDATE_URL)) {
            $error_message = 'The provided URL is not valid.';
        } else {
            $image_result = handle_image_upload('link_image');
            if (isset($image_result['error'])) {
                $error_message = $image_result['error'];
            } else {
                $link_data = [
                    'type' => 'link',
                    'title' => $is_protected ? encrypt_data($title, $password_for_enc) : htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
                    'url' => $url,
                    'description' => $is_protected ? encrypt_data($description, $password_for_enc) : htmlspecialchars($description, ENT_QUOTES, 'UTF-8'),
                    'image' => $image_result['path'],
                ];
                if (create_link($wall_id, $link_data)) {
                    $success_message = 'Link created successfully!';
                } else {
                    $error_message = 'Failed to create link.';
                }
            }
        }
    }

    // Handle 'Create Instruction'
    elseif (isset($_POST['create_instruction']) && $can_proceed) {
        $content = $_POST['instruction_content'] ?? '';
        if (empty($content)) {
            $error_message = 'Instruction content is required.';
        } else {
            $link_data = [
                'type' => 'instruction',
                'content' => $is_protected ? encrypt_data($content, $password_for_enc) : htmlspecialchars($content, ENT_QUOTES, 'UTF-8'),
            ];
            if (create_link($wall_id, $link_data)) {
                $success_message = 'Instruction added successfully!';
            } else {
                $error_message = 'Failed to add instruction.';
            }
        }
    }

    // Handle 'Update Item' (Link or Instruction)
    elseif (isset($_POST['update_item']) && $can_proceed) {
        $link_id = $_POST['item_id'];
        $item_type = $_POST['item_type'];

        $current_link = get_link($link_id);

        if ($item_type === 'link') {
            $title = $_POST['link_title'] ?? '';
            $url = $_POST['link_url'] ?? '';
            $description = $_POST['link_description'] ?? '';

            if (empty($title) || empty($url)) {
                $error_message = 'Title and URL are required.';
            } elseif (!filter_var($url, FILTER_VALIDATE_URL)) {
                $error_message = 'Invalid URL.';
            } else {
                $image_path = $current_link['image'];
                if (isset($_POST['delete_image']) && $_POST['delete_image'] == '1') {
                     if (!empty($image_path) && file_exists(__DIR__ . '/../' . $image_path)) unlink(__DIR__ . '/../' . $image_path);
                     $image_path = '';
                }
                $image_result = handle_image_upload('link_image');
                if (isset($image_result['error'])) {
                    $error_message = $image_result['error'];
                } else {
                    if ($image_result['path'] !== null) {
                         if (!empty($image_path) && file_exists(__DIR__ . '/../' . $image_path)) unlink(__DIR__ . '/../' . $image_path);
                         $image_path = $image_result['path'];
                    }
                    $link_data = [
                        'type' => 'link',
                        'title' => $is_protected ? encrypt_data($title, $password_for_enc) : htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
                        'url' => $url,
                        'description' => $is_protected ? encrypt_data($description, $password_for_enc) : htmlspecialchars($description, ENT_QUOTES, 'UTF-8'),
                        'image' => $image_path,
                    ];
                    if (update_link($link_id, $link_data)) {
                        header('Location: manage_list.php?wall_id=' . $wall_id . '&update=success');
                        exit;
                    } else {
                         $error_message = 'Failed to update link.';
                    }
                }
            }
        } elseif ($item_type === 'instruction') {
             $content = $_POST['instruction_content'] ?? '';
             if (empty($content)) {
                 $error_message = 'Content required.';
             } else {
                 $link_data = [
                     'type' => 'instruction',
                     'content' => $is_protected ? encrypt_data($content, $password_for_enc) : htmlspecialchars($content, ENT_QUOTES, 'UTF-8'),
                 ];
                 if (update_link($link_id, $link_data)) {
                     header('Location: manage_list.php?wall_id=' . $wall_id . '&update=success');
                     exit;
                 } else {
                     $error_message = 'Failed to update instruction.';
                 }
             }
        }
    }
}

// Handle 'Delete Item'
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    if (delete_link($_GET['id'])) {
        header('Location: manage_list.php?wall_id=' . $wall_id . '&delete=success');
        exit;
    } else {
        $error_message = 'Failed to delete item.';
    }
}

// --- Data Retrieval ---
$items = get_links_for_wall($wall_id);

if (isset($_GET['update']) && $_GET['update'] == 'success') $success_message = 'Item updated successfully!';
if (isset($_GET['delete']) && $_GET['delete'] == 'success') $success_message = 'Item deleted successfully!';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage List for <?= htmlspecialchars($wall['name']) ?></title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f4; }
        .container { max-width: 800px; margin: 20px auto; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1, h2 { color: #2c3e50; }
        .breadcrumb { margin-bottom: 20px; }
        .breadcrumb a { color: #3498db; text-decoration: none; }
        hr { border: 0; height: 1px; background: #ddd; margin: 20px 0; }
        form { margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        input[type="text"], input[type="url"], textarea { width: 95%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; margin-bottom: 10px; }
        button { padding: 10px 15px; border: none; background-color: #3498db; color: white; border-radius: 4px; cursor: pointer; margin-right: 5px;}
        button[type="submit"] { background-color: #2ecc71; }
        .item-list { list-style: none; padding: 0; }
        .item { display: flex; justify-content: space-between; align-items: center; padding: 10px; border-bottom: 1px solid #eee; background: #fff; }
        .item:nth-child(even) { background: #fafafa; }
        .item-actions a { text-decoration: none; color: #3498db; margin-left: 10px; }
        .item-actions a.delete { color: #e74c3c; }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .success { background-color: #e8f5e9; color: #2e7d32; }
        .error { background-color: #ffebee; color: #c62828; }
        .instruction-badge { background: #f1c40f; color: #fff; padding: 2px 6px; border-radius: 4px; font-size: 0.8em; margin-right: 5px;}
        .link-badge { background: #3498db; color: #fff; padding: 2px 6px; border-radius: 4px; font-size: 0.8em; margin-right: 5px;}
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
            Manage List
        </p>
        <h1>Manage List: "<?= htmlspecialchars($wall['name']) ?>"</h1>

        <?php if ($success_message): ?><div class="message success"><?= htmlspecialchars($success_message) ?></div><?php endif; ?>
        <?php if ($error_message): ?><div class="message error"><?= htmlspecialchars($error_message) ?></div><?php endif; ?>

        <hr>
        <h2>List Security</h2>
        <form action="manage_list.php?wall_id=<?= htmlspecialchars($wall_id) ?>" method="post">
            <label for="access_type">Access Type:</label>
            <select name="access_type" id="access_type" onchange="toggleAccessInputs()">
                <option value="public" <?= $wall['access_control']['type'] === 'public' ? 'selected' : '' ?>>Public</option>
                <option value="password" <?= $wall['access_control']['type'] === 'password' ? 'selected' : '' ?>>Password / Code</option>
                <option value="codelist" <?= $wall['access_control']['type'] === 'codelist' ? 'selected' : '' ?>>Codelist (Multiple Codes)</option>
            </select>

            <div id="password_input" style="display: none; margin-top: 10px;">
                <label for="access_password">Password or Code (leave empty to remove):</label>
                <input type="password" name="access_password" id="access_password">
            </div>

            <div id="codelist_input" style="display: none; margin-top: 10px;">
                <label for="access_codelist">Access Codes (one per line or comma-separated):</label>
                <textarea name="access_codelist" id="access_codelist" rows="3"></textarea>
            </div>

            <button type="submit" name="update_access" style="margin-top: 10px;">Update Access Control</button>
        </form>

        <script>
            function toggleAccessInputs() {
                var type = document.getElementById('access_type').value;
                document.getElementById('password_input').style.display = (type === 'password') ? 'block' : 'none';
                document.getElementById('codelist_input').style.display = (type === 'codelist') ? 'block' : 'none';
            }
            toggleAccessInputs();
        </script>

        <hr>

        <div style="display: flex; gap: 20px;">
            <div style="flex: 1;">
                <h2>Add Link</h2>
                <form action="manage_list.php?wall_id=<?= htmlspecialchars($wall_id) ?>" method="post" enctype="multipart/form-data">
                    <?php if ($wall['access_control']['type'] === 'password'): ?>
                        <p style="color: #c0392b; font-size: 0.9em;"><b>Password Protected:</b> Enter password to save.</p>
                        <input type="password" name="wall_password_for_encryption" placeholder="List Password" required>
                    <?php endif; ?>
                    <input type="text" name="link_title" placeholder="Link Title" required>
                    <input type="url" name="link_url" placeholder="https://example.com" required>
                    <textarea name="link_description" placeholder="Description" rows="2"></textarea>
                    <label>Image (Optional): <input type="file" name="link_image"></label>
                    <button type="submit" name="create_link">Add Link</button>
                </form>
            </div>
            <div style="flex: 1;">
                <h2>Add Instruction</h2>
                <form action="manage_list.php?wall_id=<?= htmlspecialchars($wall_id) ?>" method="post">
                    <?php if ($wall['access_control']['type'] === 'password'): ?>
                        <p style="color: #c0392b; font-size: 0.9em;"><b>Password Protected:</b> Enter password to save.</p>
                        <input type="password" name="wall_password_for_encryption" placeholder="List Password" required>
                    <?php endif; ?>
                    <textarea name="instruction_content" placeholder="Enter instruction text here..." rows="5" required></textarea>
                    <button type="submit" name="create_instruction" style="background-color: #f39c12;">Add Instruction</button>
                </form>
            </div>
        </div>

        <hr>

        <h2>List Content</h2>
        <div class="item-list">
            <?php if (empty($items)): ?>
                <p>No content yet.</p>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
                    <div class="item">
                        <?php
                        $is_editing = (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id']) && $_GET['id'] === $item['id']);
                        $type = $item['type'] ?? 'link';
                        ?>

                        <?php if ($is_editing): ?>
                            <form action="manage_list.php?wall_id=<?= htmlspecialchars($wall_id) ?>" method="post" enctype="multipart/form-data" style="width: 100%;">
                                <input type="hidden" name="item_id" value="<?= htmlspecialchars($item['id']) ?>">
                                <input type="hidden" name="item_type" value="<?= htmlspecialchars($type) ?>">

                                <?php if ($wall['access_control']['type'] === 'password'): ?>
                                    <input type="password" name="wall_password_for_encryption" placeholder="List Password" required style="margin-bottom: 5px;">
                                <?php endif; ?>

                                <?php if ($type === 'link'): ?>
                                    <input type="text" name="link_title" value="<?= htmlspecialchars($item['title']) ?>" required>
                                    <input type="url" name="link_url" value="<?= htmlspecialchars($item['url']) ?>" required>
                                    <textarea name="link_description"><?= htmlspecialchars($item['description']) ?></textarea>
                                    <label>New Image: <input type="file" name="link_image"></label>
                                    <?php if (!empty($item['image'])): ?>
                                        <label><input type="checkbox" name="delete_image" value="1"> Delete current image</label>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <textarea name="instruction_content" rows="4" required><?= htmlspecialchars($item['content'] ?? '') ?></textarea>
                                <?php endif; ?>

                                <button type="submit" name="update_item">Update</button>
                                <a href="manage_list.php?wall_id=<?= htmlspecialchars($wall_id) ?>">Cancel</a>
                            </form>
                        <?php else: ?>
                            <div style="display: flex; align-items: center; width: 100%;">
                                <div style="flex-grow: 1;">
                                    <?php if ($type === 'link'): ?>
                                        <span class="link-badge">LINK</span>
                                        <strong><a href="<?= htmlspecialchars($item['url']) ?>" target="_blank"><?= htmlspecialchars($item['title']) ?></a></strong>
                                        <br><small><?= htmlspecialchars($item['url']) ?></small>
                                    <?php else: ?>
                                        <span class="instruction-badge">INSTRUCTION</span>
                                        <span><?= nl2br(htmlspecialchars(substr($item['content'] ?? '', 0, 100))) ?><?= strlen($item['content'] ?? '') > 100 ? '...' : '' ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="item-actions">
                                    <a href="manage_list.php?wall_id=<?= htmlspecialchars($wall_id) ?>&action=move&id=<?= htmlspecialchars($item['id']) ?>&dir=up" title="Move Up">⬆️</a>
                                    <a href="manage_list.php?wall_id=<?= htmlspecialchars($wall_id) ?>&action=move&id=<?= htmlspecialchars($item['id']) ?>&dir=down" title="Move Down">⬇️</a>
                                    <a href="manage_list.php?wall_id=<?= htmlspecialchars($wall_id) ?>&action=edit&id=<?= htmlspecialchars($item['id']) ?>">Edit</a>
                                    <a href="manage_list.php?wall_id=<?= htmlspecialchars($wall_id) ?>&action=delete&id=<?= htmlspecialchars($item['id']) ?>" class="delete" onclick="return confirm('Delete this item?');">Delete</a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
