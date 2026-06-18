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
require_once __DIR__ . '/app/core/mnemonic.php';

$error = '';
$success = '';
$recovery_phrase = '';

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

            // Generate Recovery Phrase
            $recovery_phrase = generate_recovery_phrase();
            $recovery_data = hash_password($recovery_phrase);

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
                        "salt" => $password_data['salt'],
                        "recovery_hash" => $recovery_data['hash'],
                        "recovery_salt" => $recovery_data['salt']
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
    <title>Install &middot; Link-Wall-It</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
    <div class="auth-shell">
        <div class="auth-card" style="max-width: 460px;">
            <?php if ($success): ?>
                <h1>Installation complete</h1>
                <p class="auth-card__sub">Link-Wall-It has been configured.</p>

                <div class="callout">
                    <strong>Save this recovery phrase.</strong>
                    <p style="margin-top: var(--space-1); margin-bottom: 0;">This is the only way to recover your account if you lose your password.</p>
                    <textarea readonly rows="3"><?= htmlspecialchars($recovery_phrase) ?></textarea>
                </div>

                <a href="admin/login.php" class="btn btn--block" style="margin-top: var(--space-5);">Go to admin panel</a>
            <?php else: ?>
                <h1>Install Link-Wall-It</h1>
                <p class="auth-card__sub">Configure your site to get started.</p>

                <?php if ($error): ?>
                    <div class="message message--error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="post">
                    <div class="field">
                        <label for="site_title">Site title</label>
                        <input type="text" name="site_title" id="site_title" placeholder="My Link Wall" value="<?= isset($_POST['site_title']) ? htmlspecialchars($_POST['site_title']) : '' ?>" required>
                    </div>

                    <div class="field">
                        <label for="username">Admin username</label>
                        <input type="text" name="username" id="username" placeholder="admin" value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>" required>
                    </div>

                    <div class="field">
                        <label for="password">Admin password</label>
                        <input type="password" name="password" id="password" required>
                    </div>

                    <div class="field">
                        <label for="confirm_password">Confirm password</label>
                        <input type="password" name="confirm_password" id="confirm_password" required>
                    </div>

                    <button type="submit" class="btn btn--block">Install</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
