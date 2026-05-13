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
    <title>Recover account &middot; Link-Wall-It</title>
    <link rel="stylesheet" href="../assets/css/app.css">
</head>
<body>
    <div class="auth-shell">
        <div class="auth-card">
            <h1>Recover account</h1>
            <p class="auth-card__sub">Use your 12-word recovery phrase to reset your password.</p>

            <?php if ($success): ?>
                <div class="message message--success">Password reset successfully.</div>
                <a href="login.php" class="btn btn--block">Go to sign in</a>
            <?php else: ?>
                <?php if ($error): ?>
                    <div class="message message--error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

                    <div class="field">
                        <label for="username">Username</label>
                        <input type="text" name="username" id="username" required>
                    </div>

                    <div class="field">
                        <label for="recovery_phrase">Recovery phrase</label>
                        <textarea name="recovery_phrase" id="recovery_phrase" rows="3" placeholder="Enter your 12-word phrase" required></textarea>
                    </div>

                    <div class="field">
                        <label for="new_password">New password</label>
                        <input type="password" name="new_password" id="new_password" required>
                    </div>

                    <div class="field">
                        <label for="confirm_password">Confirm new password</label>
                        <input type="password" name="confirm_password" id="confirm_password" required>
                    </div>

                    <button type="submit" class="btn btn--block">Reset password</button>
                </form>
                <div class="auth-card__footer">
                    <a href="login.php">Cancel</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
