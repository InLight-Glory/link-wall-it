<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

require_once __DIR__ . '/app/core/functions.php';

// Get wall ID from URL
$wall_id = $_GET['id'] ?? null;
if (!$wall_id) {
    header("Location: index.php");
    exit;
}

// Fetch wall data
$wall = get_wall($wall_id);
if (!$wall) {
    header("Location: index.php");
    exit;
}

// --- Check Access Control ---
$access_type = $wall['access_control']['type'];
$auth_error = '';
$is_unlocked = false;

// A wall is considered unlocked if its ID is in the 'unlocked_walls' session array.
// This is used for non-encrypted, code-based access.
if (isset($_SESSION['unlocked_walls']) && in_array($wall_id, $_SESSION['unlocked_walls'])) {
    $is_unlocked = true;
}
// A wall is also unlocked if its password is in the 'wall_passwords' session array.
// This is used for encrypted, password-based access.
if (isset($_SESSION['wall_passwords'][$wall_id])) {
    $is_unlocked = true;
}

// Handle POST request for unlocking
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($access_type === 'password' && isset($_POST['wall_password'])) {
        $submitted_password = $_POST['wall_password'];
        if (verify_password($submitted_password, $wall['access_control']['password']['hash'], $wall['access_control']['password']['salt'])) {
            $_SESSION['wall_passwords'][$wall_id] = $submitted_password;
            header("Location: wall.php?id=" . $wall_id);
            exit;
        } else {
            $auth_error = 'Incorrect password.';
        }
    } elseif ($access_type === 'codelist' && isset($_POST['access_code'])) {
        $submitted_code = $_POST['access_code'];
        if (verify_codelist_code($submitted_code, $wall['access_control']['codelist'])) {
            $_SESSION['unlocked_walls'][] = $wall_id;
            header("Location: wall.php?id=" . $wall_id);
            exit;
        } else {
            $auth_error = 'Incorrect access code.';
        }
    }
}

$can_view_content = ($access_type === 'public') || $is_unlocked;

// --- Data Retrieval for Display ---
$links = [];
if ($can_view_content) {
    $raw_links = get_links_for_wall($wall_id);
    if ($access_type === 'password' && $is_unlocked) {
        $password = $_SESSION['wall_passwords'][$wall_id];
        foreach ($raw_links as $link) {
            $link['title'] = decrypt_data($link['title'], $password) ?: '[Decryption Failed]';
            $link['description'] = decrypt_data($link['description'], $password) ?: '';
            $links[] = $link;
        }
    } else {
        $links = $raw_links;
    }
}

// --- Parent data for breadcrumbs ---
$side = get_side($wall['side_id']);
$building = $side ? get_building($side['building_id']) : null;
$site_title = get_db()['settings']['site_title'] ?? 'Link-Wall-It';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($wall['name']) ?> - <?= htmlspecialchars($site_title) ?></title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f8f9fa; margin: 0; }
        .container { max-width: 800px; margin: 40px auto; padding: 0 20px; }
        header { text-align: center; margin-bottom: 50px; border-bottom: 1px solid #e9ecef; padding-bottom: 20px; }
        h1 { font-size: 2.5em; color: #2c3e50; margin-bottom: 0.2em; }
        .breadcrumb a { color: #3498db; text-decoration: none; }
        .breadcrumb { margin-bottom: 20px; font-size: 1.1em; }
        .link-list { list-style: none; padding: 0; }
        .link-item { background: #fff; border: 1px solid #e9ecef; border-radius: 8px; padding: 20px; margin-bottom: 15px; display: block; text-decoration: none; color: inherit; transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .link-item:hover { transform: translateY(-3px); box-shadow: 0 5px 10px rgba(0,0,0,0.08); }
        .link-item h2 { margin-top: 0; font-size: 1.3em; color: #3498db; }
        .link-item p { margin-bottom: 0; color: #555; }
        .link-content { display: flex; align-items: center; }
        .link-image { flex-shrink: 0; width: 80px; height: 80px; margin-right: 20px; }
        .link-image img { width: 100%; height: 100%; object-fit: cover; border-radius: 8px; }
        .link-text { flex-grow: 1; }
        .no-content, .access-form { text-align: center; color: #7f8c8d; padding: 40px 20px; background-color: #fff; border-radius: 8px; }
        .access-form input { padding: 10px; width: 250px; border: 1px solid #ccc; border-radius: 4px; }
        .access-form button { padding: 10px 15px; border: none; background-color: #3498db; color: white; border-radius: 4px; cursor: pointer; }
        .error-message { color: #e74c3c; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <p class="breadcrumb">
                <a href="index.php">Home</a> &raquo;
                <?php if ($building): ?><a href="building.php?id=<?= htmlspecialchars($building['id']) ?>"><?= htmlspecialchars($building['name']) ?></a> &raquo; <?php endif; ?>
                <?php if ($side): ?><a href="side.php?id=<?= htmlspecialchars($side['id']) ?>"><?= htmlspecialchars($side['name']) ?></a> &raquo; <?php endif; ?>
                <?= htmlspecialchars($wall['name']) ?>
            </p>
            <h1><?= htmlspecialchars($wall['name']) ?></h1>
        </header>

        <main>
            <?php if ($can_view_content): ?>
                <div class="link-list">
                    <?php if (empty($links)): ?>
                        <div class="no-content"><p>This wall has no links yet.</p></div>
                    <?php else: ?>
                        <?php foreach ($links as $link): ?>
                            <a href="<?= htmlspecialchars($link['url']) ?>" target="_blank" class="link-item">
                                <div class="link-content">
                                    <?php if (!empty($link['image'])): ?>
                                        <div class="link-image"><img src="<?= htmlspecialchars($link['image']) ?>" alt="Link thumbnail"></div>
                                    <?php endif; ?>
                                    <div class="link-text">
                                        <h2><?= htmlspecialchars($link['title']) ?></h2>
                                        <?php if (!empty($link['description'])): ?><p><?= htmlspecialchars($link['description']) ?></p><?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="access-form">
                    <h2>This content is protected</h2>
                    <?php if ($access_type === 'password'): ?>
                        <p>Please enter the password to view this wall.</p>
                        <form action="wall.php?id=<?= htmlspecialchars($wall_id) ?>" method="post">
                            <?php if ($auth_error): ?><p class="error-message"><?= htmlspecialchars($auth_error) ?></p><?php endif; ?>
                            <input type="password" name="wall_password" required>
                            <button type="submit">Unlock</button>
                        </form>
                    <?php elseif ($access_type === 'codelist'): ?>
                        <p>Please enter an access code to view this wall.</p>
                        <form action="wall.php?id=<?= htmlspecialchars($wall_id) ?>" method="post">
                            <?php if ($auth_error): ?><p class="error-message"><?= htmlspecialchars($auth_error) ?></p><?php endif; ?>
                            <input type="text" name="access_code" required>
                            <button type="submit">Unlock</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
