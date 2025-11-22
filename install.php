<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('DATA_DIR', __DIR__ . '/data');
define('DB_FILE', DATA_DIR . '/database.json');
define('LOCK_FILE', DATA_DIR . '/installed.lock');

// 1. Check if already installed
if (file_exists(LOCK_FILE)) {
    die("Application is already installed. To reinstall, delete 'data/installed.lock'. <a href='index.php'>Go to Home</a>");
}

require_once __DIR__ . '/app/core/encryption.php';

$error = '';
$success = '';

// 2. Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $site_title = trim($_POST['site_title'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($site_title) || empty($username) || empty($password)) {
        $error = 'All fields are required.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        // Check write permissions
        if (!is_writable(DATA_DIR)) {
            $error = 'The "data/" directory is not writable. Please set permissions (e.g., chmod 755 data).';
        } else {
            // Hash password
            $password_data = hash_password($password);

            // Create Initial Database
            $initial_db = [
                "settings" => [
                    "site_title" => htmlspecialchars($site_title, ENT_QUOTES, 'UTF-8'),
                    "site_description" => "Your personal link wall.",
                    "stripe_publishable_key" => "",
                    "stripe_secret_key" => ""
                ],
                "users" => [
                    [
                        "username" => htmlspecialchars($username, ENT_QUOTES, 'UTF-8'),
                        "password_hash" => $password_data['hash'],
                        "salt" => $password_data['salt']
                    ]
                ],
                "buildings" => [],
                "sides" => [],
                "walls" => [],
                "links" => []
            ];

            $json_data = json_encode($initial_db, JSON_PRETTY_PRINT);

            if (file_put_contents(DB_FILE, $json_data, LOCK_EX) !== false) {
                // Create Lock File
                if (file_put_contents(LOCK_FILE, date('Y-m-d H:i:s')) !== false) {
                    $success = true;
                } else {
                    $error = 'Failed to create lock file.';
                }
            } else {
                $error = 'Failed to write database file.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install Link-Wall-It</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f4; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .container { width: 100%; max-width: 500px; padding: 40px; background: #fff; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        h1 { text-align: center; color: #2c3e50; margin-top: 0; }
        p.intro { text-align: center; color: #7f8c8d; margin-bottom: 30px; }
        form { display: flex; flex-direction: column; }
        label { margin-bottom: 5px; font-weight: bold; }
        input[type="text"], input[type="password"] { padding: 12px; margin-bottom: 20px; border: 1px solid #ddd; border-radius: 4px; font-size: 16px; }
        button { padding: 12px; background-color: #3498db; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; transition: background 0.3s; }
        button:hover { background-color: #2980b9; }
        .error { background-color: #ffebee; color: #c62828; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .success { text-align: center; }
        .success h2 { color: #27ae60; }
        .btn-admin { display: inline-block; margin-top: 20px; padding: 12px 25px; background-color: #2ecc71; color: white; text-decoration: none; border-radius: 4px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($success): ?>
            <div class="success">
                <h2>Installation Successful!</h2>
                <p>Link-Wall-It has been installed and configured.</p>
                <p>You can now log in to the administration panel.</p>
                <a href="admin/login.php" class="btn-admin">Go to Admin Panel</a>
            </div>
        <?php else: ?>
            <h1>Link-Wall-It Installation</h1>
            <p class="intro">Welcome! Please configure your site settings to get started.</p>

            <?php if ($error): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post">
                <label for="site_title">Site Title</label>
                <input type="text" name="site_title" id="site_title" placeholder="My Link Wall" value="<?= isset($_POST['site_title']) ? htmlspecialchars($_POST['site_title']) : '' ?>" required>

                <label for="username">Admin Username</label>
                <input type="text" name="username" id="username" placeholder="admin" value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>" required>

                <label for="password">Admin Password</label>
                <input type="password" name="password" id="password" required>

                <label for="confirm_password">Confirm Password</label>
                <input type="password" name="confirm_password" id="confirm_password" required>

                <button type="submit">Install</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
