<?php
require_once __DIR__ . '/../app/core/functions.php';

$error = '';

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (login_user($username, $password)) {
        header('Location: index.php');
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en"<?= theme_html_attr() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in &middot; Link-Wall-It</title>
    <link rel="stylesheet" href="../assets/css/app.css">
</head>
<body>
    <div class="auth-shell">
        <div class="auth-card">
            <h1>Sign in</h1>
            <p class="auth-card__sub">Admin access</p>

            <?php if ($error): ?>
                <div class="message message--error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <div class="field">
                    <label for="username">Username</label>
                    <input type="text" name="username" id="username" required autofocus>
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <input type="password" name="password" id="password" required>
                </div>
                <button type="submit" class="btn btn--block">Sign in</button>
            </form>

            <div class="auth-card__footer">
                <a href="recover.php">Forgot password?</a>
                <span style="margin: 0 var(--space-2); color: var(--color-border-strong);">&middot;</span>
                <a href="../index.php">Back to site</a>
            </div>
        </div>
    </div>
    <?= theme_picker_html() ?>
</body>
</html>
