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

    // Handle 'Update Access Control'
    if (isset($_POST['update_access'])) {
        $access_type = $_POST['access_type'] ?? 'public';
        $access_value = null;

        if ($access_type === 'password') {
            $access_value = $_POST['access_password'] ?? '';
            if (empty($access_value)) {
                // If password is empty, treat as making it public
                $access_type = 'public';
            }
        } elseif ($access_type === 'codelist') {
            $codelist_str = $_POST['access_codelist'] ?? '';
            $access_value = preg_split('/[,\n\r]+/', $codelist_str);
            $access_value = array_filter(array_map('trim', $access_value));
            if (empty($access_value)) {
                $access_type = 'public';
            }
        } elseif ($access_type === 'stripe') {
            $price = $_POST['access_price'] ?? '0';
            // Convert price in dollars/euros to cents
            $price_in_cents = (int)((float)$price * 100);
            $currency = $_POST['access_currency'] ?? 'usd';
            if ($price_in_cents > 0) {
                 $access_value = [
                    'price_in_cents' => $price_in_cents,
                    'currency' => $currency
                ];
            } else {
                $access_type = 'public';
            }
        }

        if (update_wall_access($wall_id, $access_type, $access_value)) {
            $success_message = 'Wall access control updated successfully!';
            $wall = get_wall($wall_id); // Refresh wall data
        } else {
            $error_message = 'Failed to update wall access control.';
        }
    }

    // Handle 'Create Link'
    elseif (isset($_POST['create_link'])) {
        $title = $_POST['link_title'] ?? '';
        $url = $_POST['link_url'] ?? '';
        $description = $_POST['link_description'] ?? '';

        if (empty($title) || empty($url)) {
            $error_message = 'Title and URL are required.';
        } elseif (!filter_var($url, FILTER_VALIDATE_URL)) {
            $error_message = 'The provided URL is not valid.';
        } else {
            $is_protected = $wall['access_control']['type'] === 'password';
            $password = $_POST['wall_password_for_encryption'] ?? '';
            $can_proceed = false;

            if ($is_protected) {
                if (verify_password($password, $wall['access_control']['password']['hash'], $wall['access_control']['password']['salt'])) {
                    $can_proceed = true;
                } else {
                    $error_message = 'Incorrect wall password. Cannot save link.';
                }
            } else {
                $can_proceed = true; // Not protected, so we can proceed
            }

            if ($can_proceed) {
                $image_result = handle_image_upload('link_image');
                if (isset($image_result['error'])) {
                    $error_message = $image_result['error'];
                } else {
                    $link_data = [
                        'title' => $is_protected ? encrypt_data($title, $password) : htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
                        'url' => $url, // We decided not to encrypt the URL
                        'description' => $is_protected ? encrypt_data($description, $password) : htmlspecialchars($description, ENT_QUOTES, 'UTF-8'),
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
    }
    // Handle 'Update Link'
    elseif (isset($_POST['update_link'])) {
        $link_id = $_POST['link_id'];
        $title = $_POST['link_title'] ?? '';
        $url = $_POST['link_url'] ?? '';
        $description = $_POST['link_description'] ?? '';

        if (empty($title) || empty($url)) {
            $error_message = 'Title and URL are required.';
        } elseif (!filter_var($url, FILTER_VALIDATE_URL)) {
            $error_message = 'The provided URL is not valid.';
        } else {
            $is_protected = $wall['access_control']['type'] === 'password';
            $password = $_POST['wall_password_for_encryption'] ?? '';
            $can_proceed = false;

            if ($is_protected) {
                if (verify_password($password, $wall['access_control']['password']['hash'], $wall['access_control']['password']['salt'])) {
                    $can_proceed = true;
                } else {
                    $error_message = 'Incorrect wall password. Cannot save link.';
                }
            } else {
                $can_proceed = true;
            }

            if ($can_proceed) {
                $current_link = get_link($link_id);
                $image_path = $current_link['image'];

                if (isset($_POST['delete_image']) && $_POST['delete_image'] == '1') {
                    if (!empty($image_path) && file_exists(__DIR__ . '/../' . $image_path)) {
                        unlink(__DIR__ . '/../' . $image_path);
                    }
                    $image_path = '';
                }

                $image_result = handle_image_upload('link_image');
                if (isset($image_result['error'])) {
                    $error_message = $image_result['error'];
                } else {
                    if ($image_result['path'] !== null) {
                        if (!empty($image_path) && file_exists(__DIR__ . '/../' . $image_path)) {
                            unlink(__DIR__ . '/../' . $image_path);
                        }
                        $image_path = $image_result['path'];
                    }

                    $link_data = [
                        'title' => $is_protected ? encrypt_data($title, $password) : htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
                        'url' => $url,
                        'description' => $is_protected ? encrypt_data($description, $password) : htmlspecialchars($description, ENT_QUOTES, 'UTF-8'),
                        'image' => $image_path,
                    ];

                    if (update_link($link_id, $link_data)) {
                        header('Location: manage_wall.php?wall_id=' . $wall_id . '&update=success');
                        exit;
                    } else {
                        $error_message = 'Failed to update link.';
                    }
                }
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

        <hr>
        <h2>Wall Security</h2>
        <form action="manage_wall.php?wall_id=<?= htmlspecialchars($wall_id) ?>" method="post">
            <label for="access_type">Access Type:</label>
            <select name="access_type" id="access_type" onchange="toggleAccessInputs()">
                <option value="public" <?= $wall['access_control']['type'] === 'public' ? 'selected' : '' ?>>Public</option>
                <option value="password" <?= $wall['access_control']['type'] === 'password' ? 'selected' : '' ?>>Password / Code</option>
                <option value="codelist" <?= $wall['access_control']['type'] === 'codelist' ? 'selected' : '' ?>>Codelist (Multiple Codes)</option>
                <option value="stripe" <?= $wall['access_control']['type'] === 'stripe' ? 'selected' : '' ?>>Stripe (Pay to Access)</option>
            </select>

            <div id="password_input" style="display: none; margin-top: 10px;">
                <label for="access_password">Password or Code (leave empty to remove):</label>
                <input type="password" name="access_password" id="access_password" placeholder="Enter password">
            </div>

            <div id="codelist_input" style="display: none; margin-top: 10px;">
                <label for="access_codelist">Access Codes (one per line or comma-separated):</label>
                <textarea name="access_codelist" id="access_codelist" rows="5" placeholder="code1, code2, code3"></textarea>
            </div>

            <div id="stripe_input" style="display: none; margin-top: 10px;">
                <label for="access_price">Price (e.g., 5.00):</label>
                <input type="number" step="0.01" name="access_price" id="access_price" placeholder="5.00" value="<?= htmlspecialchars(($wall['access_control']['stripe']['price_in_cents'] ?? 0) / 100) ?>">
                <label for="access_currency">Currency:</label>
                <input type="text" name="access_currency" id="access_currency" placeholder="usd" value="<?= htmlspecialchars($wall['access_control']['stripe']['currency'] ?? 'usd') ?>">
            </div>

            <button type="submit" name="update_access" style="margin-top: 10px;">Update Access Control</button>
        </form>

        <script>
            function toggleAccessInputs() {
                var type = document.getElementById('access_type').value;
                document.getElementById('password_input').style.display = (type === 'password') ? 'block' : 'none';
                document.getElementById('codelist_input').style.display = (type === 'codelist') ? 'block' : 'none';
                document.getElementById('stripe_input').style.display = (type === 'stripe') ? 'block' : 'none';
            }
            // Run on page load to set initial state
            toggleAccessInputs();
        </script>
        <hr>

        <form action="manage_wall.php?wall_id=<?= htmlspecialchars($wall_id) ?>" method="post" enctype="multipart/form-data">
            <h2>Create New Link</h2>
            <?php if ($wall['access_control']['type'] === 'password'): ?>
                <p style="color: #c0392b; font-weight: bold;">This wall is password protected. You must enter the wall's password to encrypt and save new links.</p>
                <input type="password" name="wall_password_for_encryption" placeholder="Enter Wall Password" required>
            <?php endif; ?>
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

                                <?php if ($wall['access_control']['type'] === 'password'): ?>
                                    <p style="color: #c0392b; font-weight: bold;">This wall is password protected. You must enter the wall's password to save changes.</p>
                                    <input type="password" name="wall_password_for_encryption" placeholder="Enter Wall Password" required>
                                <?php endif; ?>

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
