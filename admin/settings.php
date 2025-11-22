<?php
require_once __DIR__ . '/../app/core/functions.php';

require_login();

$db = get_db();
$settings = $db['settings'] ?? [];

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_settings = [
        'site_title' => $_POST['site_title'] ?? 'Link-Wall-It',
        'site_description' => $_POST['site_description'] ?? '',
        'stripe_publishable_key' => $_POST['stripe_publishable_key'] ?? '',
        'stripe_secret_key' => $_POST['stripe_secret_key'] ?? '',
    ];

    // Preserve other settings if any (merge)
    // We merge new over old to update
    $db['settings'] = array_merge($settings, $new_settings);

    if (save_db($db)) {
        $success_message = 'Settings updated successfully!';
        $settings = $db['settings']; // Refresh from memory
    } else {
        $error_message = 'Failed to update settings.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Settings - Link-Wall-It</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f4; }
        .container { max-width: 800px; margin: 20px auto; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1, h2 { color: #2c3e50; }
        .breadcrumb { margin-bottom: 20px; }
        .breadcrumb a { color: #3498db; text-decoration: none; }
        form { margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; margin-bottom: 15px; box-sizing: border-box; }
        button { padding: 10px 15px; border: none; background-color: #2ecc71; color: white; border-radius: 4px; cursor: pointer; font-size: 16px; transition: background 0.3s; }
        button:hover { background-color: #27ae60; }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .success { background-color: #e8f5e9; color: #2e7d32; }
        .error { background-color: #ffebee; color: #c62828; }
    </style>
</head>
<body>
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <p class="breadcrumb"><a href="index.php">Admin Home</a> &raquo; Settings</p>
            <a href="logout.php" style="color: #e74c3c; text-decoration: none; font-weight: bold;">Logout</a>
        </div>
        <h1>Global Settings</h1>

        <?php if ($success_message): ?><div class="message success"><?= htmlspecialchars($success_message) ?></div><?php endif; ?>
        <?php if ($error_message): ?><div class="message error"><?= htmlspecialchars($error_message) ?></div><?php endif; ?>

        <form method="post">
            <h2>General Information</h2>

            <label for="site_title">Site Title</label>
            <input type="text" name="site_title" id="site_title" value="<?= htmlspecialchars($settings['site_title'] ?? '') ?>" required>

            <label for="site_description">Site Description</label>
            <textarea name="site_description" id="site_description" rows="3"><?= htmlspecialchars($settings['site_description'] ?? '') ?></textarea>

            <hr style="margin: 20px 0; border: 0; border-top: 1px solid #eee;">

            <h2>Stripe Integration</h2>
            <p style="color: #7f8c8d; font-size: 0.9em;">Enter your Stripe API keys to enable monetization features. You can find these in your <a href="https://dashboard.stripe.com/apikeys" target="_blank" style="color: #3498db;">Stripe Dashboard</a>.</p>

            <label for="stripe_publishable_key">Publishable Key</label>
            <input type="text" name="stripe_publishable_key" id="stripe_publishable_key" value="<?= htmlspecialchars($settings['stripe_publishable_key'] ?? '') ?>" placeholder="pk_test_...">

            <label for="stripe_secret_key">Secret Key</label>
            <input type="text" name="stripe_secret_key" id="stripe_secret_key" value="<?= htmlspecialchars($settings['stripe_secret_key'] ?? '') ?>" placeholder="sk_test_...">

            <button type="submit">Save Settings</button>
        </form>
    </div>
</body>
</html>
