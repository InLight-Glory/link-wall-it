<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../app/core/functions.php';

$error_message = '';
$success_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_settings = [
        'site_title' => $_POST['site_title'] ?? '',
        'site_description' => $_POST['site_description'] ?? '',
        'stripe_publishable_key' => $_POST['stripe_publishable_key'] ?? '',
        'stripe_secret_key' => $_POST['stripe_secret_key'] ?? ''
    ];

    if (save_settings($new_settings)) {
        $success_message = 'Settings saved successfully!';
    } else {
        $error_message = 'Failed to save settings.';
    }
}

// Get current settings to display in the form
$settings = get_settings();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Site Settings</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f4; }
        .container { max-width: 800px; margin: 20px auto; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1, h2 { color: #2c3e50; }
        .breadcrumb { margin-bottom: 20px; }
        .breadcrumb a { color: #3498db; text-decoration: none; }
        form { margin-top: 20px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], input[type="password"] { width: 95%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; margin-bottom: 15px; }
        button { padding: 10px 15px; border: none; background-color: #2ecc71; color: white; border-radius: 4px; cursor: pointer; }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .success { background-color: #e8f5e9; color: #2e7d32; }
        .error { background-color: #ffebee; color: #c62828; }
    </style>
</head>
<body>
    <div class="container">
        <p class="breadcrumb"><a href="index.php">Admin Home</a> &raquo; Site Settings</p>
        <h1>Site Settings</h1>

        <?php if ($success_message): ?><div class="message success"><?= htmlspecialchars($success_message) ?></div><?php endif; ?>
        <?php if ($error_message): ?><div class="message error"><?= htmlspecialchars($error_message) ?></div><?php endif; ?>

        <form action="settings.php" method="post">
            <h2>General Settings</h2>
            <label for="site_title">Site Title</label>
            <input type="text" id="site_title" name="site_title" value="<?= htmlspecialchars($settings['site_title'] ?? '') ?>">

            <label for="site_description">Site Description</label>
            <input type="text" id="site_description" name="site_description" value="<?= htmlspecialchars($settings['site_description'] ?? '') ?>">

            <h2>Stripe Settings</h2>
            <p>Enter your API keys from the Stripe Dashboard.</p>
            <label for="stripe_publishable_key">Stripe Publishable Key</label>
            <input type="text" id="stripe_publishable_key" name="stripe_publishable_key" value="<?= htmlspecialchars($settings['stripe_publishable_key'] ?? '') ?>">

            <label for="stripe_secret_key">Stripe Secret Key</label>
            <input type="password" id="stripe_secret_key" name="stripe_secret_key" value="<?= htmlspecialchars($settings['stripe_secret_key'] ?? '') ?>">

            <button type="submit">Save Settings</button>
        </form>
    </div>
</body>
</html>
