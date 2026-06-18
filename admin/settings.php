<?php
require_once __DIR__ . '/../app/core/functions.php';

require_login();

$db = get_db();
$settings = $db['settings'] ?? [];

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    if (isset($_POST['update_settings'])) {
        // Allow updating ANY of the known settings keys via POST. Missing keys retain old value.
        $known_keys = [
            'site_title', 'site_description', 'theme',
            'stripe_publishable_key', 'stripe_secret_key', 'stripe_webhook_secret',
            'paypal_client_id', 'paypal_client_secret', 'paypal_webhook_id',
            'payment_mode',
        ];
        $new_settings = [];
        foreach ($known_keys as $k) {
            if (array_key_exists($k, $_POST)) {
                $new_settings[$k] = trim((string)$_POST[$k]);
            }
        }
        if (isset($new_settings['payment_mode']) && !in_array($new_settings['payment_mode'], ['test', 'live'], true)) {
            $new_settings['payment_mode'] = 'test';
        }
        if (isset($new_settings['theme']) && !in_array($new_settings['theme'], ['default', 'dark', 'evening'], true)) {
            $new_settings['theme'] = 'default';
        }

        // Boolean toggles (form sends '1' if checked, missing if unchecked)
        $new_settings['short_url_rewrite'] = !empty($_POST['short_url_rewrite']);

        $db['settings'] = array_merge($settings, $new_settings);

        if (save_db($db)) {
            $success_message = 'Settings updated successfully!';
            $settings = $db['settings']; // Refresh from memory
        } else {
            $error_message = 'Failed to update settings.';
        }
    } elseif (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Get current user
        $username = $_SESSION['user_id']; // Assuming simple username storage

        // Validate current password
        if (login_user($username, $current_password)) {
            if (empty($new_password)) {
                $error_message = 'New password cannot be empty.';
            } elseif ($new_password !== $confirm_password) {
                $error_message = 'New passwords do not match.';
            } else {
                if (update_user_password($username, $new_password)) {
                    $success_message = 'Password changed successfully!';
                } else {
                    $error_message = 'Failed to update password.';
                }
            }
        } else {
            $error_message = 'Incorrect current password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en"<?= theme_html_attr() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings &middot; Admin</title>
    <link rel="stylesheet" href="../assets/css/app.css">
</head>
<body>
    <div class="container">
        <header class="app-header">
            <h1>Settings</h1>
            <nav class="app-header__nav">
                <a href="editor.php">Editor</a>
                <a href="index.php">Buildings</a>
                <a href="logout.php" class="danger">Sign out</a>
            </nav>
        </header>

        <?php if ($success_message): ?><div class="message message--success"><?= htmlspecialchars($success_message) ?></div><?php endif; ?>
        <?php if ($error_message): ?><div class="message message--error"><?= htmlspecialchars($error_message) ?></div><?php endif; ?>

        <section class="section">
            <div class="section__heading"><h2>Site</h2></div>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

                <div class="field">
                    <label for="site_title">Site title</label>
                    <input type="text" name="site_title" id="site_title" value="<?= htmlspecialchars($settings['site_title'] ?? '') ?>" required>
                </div>

                <div class="field">
                    <label for="site_description">Site description</label>
                    <textarea name="site_description" id="site_description" rows="2"><?= htmlspecialchars($settings['site_description'] ?? '') ?></textarea>
                </div>

                <?php $current_theme_value = $settings['theme'] ?? 'default'; ?>
                <div class="field">
                    <label for="theme">Theme</label>
                    <select name="theme" id="theme">
                        <option value="default" <?= $current_theme_value === 'default' ? 'selected' : '' ?>>Default &mdash; light, neutral</option>
                        <option value="dark"    <?= $current_theme_value === 'dark'    ? 'selected' : '' ?>>Dark &mdash; deep navy</option>
                        <option value="evening" <?= $current_theme_value === 'evening' ? 'selected' : '' ?>>Evening &mdash; warm amber, low blue light</option>
                    </select>
                    <small>Applies site-wide (admin and public pages).</small>
                </div>

                <div class="field">
                    <label style="font-weight: 400;">
                        <input type="checkbox" name="short_url_rewrite" value="1" style="width: auto; margin-right: var(--space-1);"
                               <?= !empty($settings['short_url_rewrite']) ? 'checked' : '' ?>>
                        Use pretty short URLs (<code>/s/abc</code> instead of <code>/s.php?s=abc</code>)
                    </label>
                    <small>Requires <code>mod_rewrite</code> + the bundled root <code>.htaccess</code>. Test on your host first &mdash; if <code>/s/abc</code> 404s, leave this off.</small>
                </div>

                <button type="submit" name="update_settings" class="btn">Save site settings</button>
            </form>
        </section>

        <section class="section">
            <div class="section__heading">
                <h2>Payments</h2>
                <small>Mode: <strong><?= htmlspecialchars($settings['payment_mode'] ?? 'test') ?></strong></small>
            </div>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

                <div class="field">
                    <label for="payment_mode">Mode</label>
                    <select name="payment_mode" id="payment_mode">
                        <option value="test" <?= ($settings['payment_mode'] ?? 'test') === 'test' ? 'selected' : '' ?>>Test / sandbox</option>
                        <option value="live" <?= ($settings['payment_mode'] ?? 'test') === 'live' ? 'selected' : '' ?>>Live</option>
                    </select>
                    <small>Affects PayPal endpoint (sandbox vs. production). Stripe uses whichever key you paste below — the prefix indicates test vs. live.</small>
                </div>

                <h3 style="margin-top: var(--space-5); margin-bottom: var(--space-3);">Stripe</h3>
                <p style="color: var(--color-text-muted); font-size: var(--text-sm); margin-top: 0;">
                    Get your keys from the <a href="https://dashboard.stripe.com/apikeys" target="_blank" rel="noopener noreferrer">Stripe API keys page</a>.
                    For webhook events, point Stripe at <code>/stripe_webhook.php</code> and paste the signing secret here.
                </p>

                <div class="field">
                    <label for="stripe_publishable_key">Publishable key</label>
                    <input type="text" name="stripe_publishable_key" id="stripe_publishable_key" value="<?= htmlspecialchars($settings['stripe_publishable_key'] ?? '') ?>" placeholder="pk_test_...">
                </div>

                <div class="field">
                    <label for="stripe_secret_key">Secret key</label>
                    <input type="text" name="stripe_secret_key" id="stripe_secret_key" value="<?= htmlspecialchars($settings['stripe_secret_key'] ?? '') ?>" placeholder="sk_test_...">
                </div>

                <div class="field">
                    <label for="stripe_webhook_secret">Webhook signing secret</label>
                    <input type="text" name="stripe_webhook_secret" id="stripe_webhook_secret" value="<?= htmlspecialchars($settings['stripe_webhook_secret'] ?? '') ?>" placeholder="whsec_...">
                    <small>Without this, the webhook endpoint refuses every request.</small>
                </div>

                <h3 style="margin-top: var(--space-5); margin-bottom: var(--space-3);">PayPal</h3>
                <p style="color: var(--color-text-muted); font-size: var(--text-sm); margin-top: 0;">
                    Create an app in the <a href="https://developer.paypal.com/dashboard/applications/sandbox" target="_blank" rel="noopener noreferrer">PayPal Developer Dashboard</a>. For webhooks, point PayPal at <code>/paypal_webhook.php</code> and paste the webhook ID here.
                </p>

                <div class="field">
                    <label for="paypal_client_id">Client ID</label>
                    <input type="text" name="paypal_client_id" id="paypal_client_id" value="<?= htmlspecialchars($settings['paypal_client_id'] ?? '') ?>" placeholder="A1B2...">
                </div>

                <div class="field">
                    <label for="paypal_client_secret">Client secret</label>
                    <input type="text" name="paypal_client_secret" id="paypal_client_secret" value="<?= htmlspecialchars($settings['paypal_client_secret'] ?? '') ?>" placeholder="EX2...">
                </div>

                <div class="field">
                    <label for="paypal_webhook_id">Webhook ID</label>
                    <input type="text" name="paypal_webhook_id" id="paypal_webhook_id" value="<?= htmlspecialchars($settings['paypal_webhook_id'] ?? '') ?>" placeholder="WH-...">
                </div>

                <button type="submit" name="update_settings" class="btn">Save payment settings</button>
            </form>
        </section>

        <section class="section">
            <div class="section__heading"><h2>Change password</h2></div>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

                <div class="field">
                    <label for="current_password">Current password</label>
                    <input type="password" name="current_password" id="current_password" required>
                </div>

                <div class="field">
                    <label for="new_password">New password</label>
                    <input type="password" name="new_password" id="new_password" required>
                </div>

                <div class="field">
                    <label for="confirm_password">Confirm new password</label>
                    <input type="password" name="confirm_password" id="confirm_password" required>
                </div>

                <button type="submit" name="change_password" class="btn btn--secondary">Update password</button>
            </form>
        </section>
    </div>
    <?= theme_picker_html() ?>
</body>
</html>
