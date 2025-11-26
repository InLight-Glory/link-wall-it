<?php
require_once __DIR__ . '/../app/core/functions.php';
require_once __DIR__ . '/../app/core/csrf.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $username = $_POST['username'] ?? '';
    $recovery_phrase = trim($_POST['recovery_phrase'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($username) || empty($recovery_phrase) || empty($new_password)) {
        $error = 'All fields are required.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        // Verify phrase
        $db = get_db();
        $found = false;
        foreach ($db['users'] as $user) {
            if ($user['username'] === $username) {
                $hash = $user['recovery_hash'] ?? '';
                $salt = $user['recovery_salt'] ?? '';

                if (empty($hash)) {
                    $error = 'Recovery is not set up for this user.';
                } elseif (verify_password($recovery_phrase, $hash, $salt)) {
                    // Reset password
                    if (update_user_password($username, $new_password)) {
                        $success = true;
                    } else {
                        $error = 'Failed to update password.';
                    }
                } else {
                    $error = 'Invalid recovery phrase.';
                }
                $found = true;
                break;
            }
        }
        if (!$found) {
            $error = 'User not found.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Recovery - Link-Wall-It</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background: #f4f4f4; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .login-box { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        h2 { text-align: center; margin-top: 0; color: #2c3e50; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, textarea { width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; font-size: 16px; }
        button { width: 100%; padding: 12px; background: #e67e22; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; transition: background 0.3s; }
        button:hover { background: #d35400; }
        .error { color: #e74c3c; font-size: 0.9em; margin-bottom: 15px; text-align: center; background: #fce4ec; padding: 10px; border-radius: 4px; }
        .success { color: #27ae60; font-size: 0.9em; margin-bottom: 15px; text-align: center; background: #e8f5e9; padding: 10px; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>Recover Password</h2>
        <?php if ($success): ?>
            <div class="success">Password reset successfully!</div>
            <p style="text-align: center;"><a href="login.php">Go to Login</a></p>
        <?php else: ?>
            <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

                <label for="username">Username</label>
                <input type="text" name="username" id="username" required>

                <label for="recovery_phrase">Recovery Phrase</label>
                <textarea name="recovery_phrase" id="recovery_phrase" rows="3" placeholder="enter your 12 word phrase here..." required></textarea>

                <label for="new_password">New Password</label>
                <input type="password" name="new_password" id="new_password" required>

                <label for="confirm_password">Confirm New Password</label>
                <input type="password" name="confirm_password" id="confirm_password" required>

                <button type="submit">Reset Password</button>
            </form>
            <p style="text-align: center; margin-top: 20px; font-size: 0.9em;"><a href="login.php" style="color: #7f8c8d; text-decoration: none;">Cancel</a></p>
        <?php endif; ?>
    </div>
</body>
</html>
