<?php
// admin/wall_settings.php — Per-wall settings page (Phase 3).
//
// Three tabs (server-rendered, not actual <tabs>): Access, Email allowlist, Invite links.
// Lives at /admin/wall_settings.php?wall_id=<w_id>.

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../app/core/functions.php';

require_login();

$wall_id = $_GET['wall_id'] ?? '';
$wall = $wall_id ? get_wall($wall_id) : null;
if (!$wall) {
    header('Location: editor.php');
    exit;
}

$side = get_side($wall['side_id']);
$building = $side ? get_building($side['building_id']) : null;

$success_message = '';
$error_message = '';
$just_generated_invite = null; // ['token' => ..., 'url' => ...]

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'update_access_type':
                $type = $_POST['access_type'] ?? 'public';
                $value = null;
                $handled = false;

                if ($type === 'password') {
                    $value = $_POST['access_password'] ?? '';
                    if ($value === '' && ($wall['access_control']['password']['hash'] ?? null)) {
                        // Empty submitted password but a hash exists — keep current password,
                        // just flip the type. Bypass update_wall_access (which would clear it).
                        $db = get_db();
                        foreach ($db['walls'] as &$w) {
                            if ($w['id'] === $wall_id) { $w['access_control']['type'] = 'password'; }
                        }
                        unset($w);
                        if (!save_db($db)) { throw new Exception('Save failed.'); }
                        $success_message = 'Access type set to password.';
                        $handled = true;
                    }
                } elseif ($type === 'codelist') {
                    $codelist_str = $_POST['access_codelist'] ?? '';
                    $value = array_filter(array_map('trim', preg_split('/[,\n\r]+/', $codelist_str)));
                } elseif ($type === 'payment') {
                    // Phase 4: payment value carries price + provider toggles.
                    $providers = $_POST['providers'] ?? [];
                    if (!is_array($providers)) { $providers = []; }
                    $value = [
                        'price'     => floatval($_POST['access_price'] ?? 0),
                        'providers' => array_values($providers),
                    ];
                }

                if (!$handled) {
                    if (!update_wall_access($wall_id, $type, $value)) {
                        throw new Exception('Failed to update access type.');
                    }
                    $success_message = 'Access type updated.';
                }
                break;

            case 'add_email':
                $email = trim($_POST['email'] ?? '');
                if ($email === '') { throw new Exception('Email is required.'); }
                if (!add_wall_email($wall_id, $email)) {
                    throw new Exception('Could not add email (invalid format or save error).');
                }
                $success_message = 'Email added to allowlist.';
                break;

            case 'remove_email':
                $hash = $_POST['email_hash'] ?? '';
                if ($hash === '') { throw new Exception('Missing entry.'); }
                if (!remove_wall_email($wall_id, $hash)) {
                    throw new Exception('Failed to remove email.');
                }
                $success_message = 'Email removed.';
                break;

            case 'create_invite':
                $note = $_POST['invite_note'] ?? '';
                $result = create_invite($wall_id, $note);
                if (!$result) {
                    throw new Exception('Failed to generate invite.');
                }
                // Build the public URL for the creator to copy and share.
                $scheme = !empty($_SERVER['HTTPS']) ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                // wall.php lives at the root, so go up from /admin.
                $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
                $base = preg_replace('#/admin$#', '', $base);
                $invite_url = $scheme . '://' . $host . $base . '/wall.php?id=' . urlencode($wall_id) . '&invite=' . urlencode($result['token']);

                // Stash in session so we can show it once after PRG redirect.
                if (session_status() === PHP_SESSION_NONE) { session_start(); }
                $_SESSION['flash_invite_url'] = $invite_url;
                $success_message = 'Invite generated.';
                break;

            case 'revoke_invite':
                $invite_id = $_POST['invite_id'] ?? '';
                if (!revoke_invite($invite_id)) {
                    throw new Exception('Failed to revoke invite.');
                }
                $success_message = 'Invite revoked.';
                break;

            case 'update_slug':
                $new = $_POST['slug'] ?? '';
                $r = update_wall_slug($wall_id, $new);
                if (isset($r['error'])) {
                    throw new Exception($r['error']);
                }
                $success_message = 'Short link updated.';
                break;

            default:
                throw new Exception('Unknown action.');
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }

    if ($error_message === '') {
        $tab = $_POST['tab'] ?? 'access';
        $params = ['wall_id' => $wall_id, 'tab' => $tab, 'ok' => $success_message];
        header('Location: wall_settings.php?' . http_build_query($params));
        exit;
    }
}

if (isset($_GET['ok']) && $_GET['ok'] !== '') {
    $success_message = $_GET['ok'];
}

// Pull the just-generated invite URL out of session (one-shot).
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!empty($_SESSION['flash_invite_url'])) {
    $just_generated_invite = $_SESSION['flash_invite_url'];
    unset($_SESSION['flash_invite_url']);
}

// Lazy backfill: ensure every wall has a slug the first time its settings page loads.
if (empty($wall['slug'])) {
    ensure_wall_slug($wall_id);
}

// Refetch wall so we render current state.
$wall = get_wall($wall_id);
$active_tab = $_GET['tab'] ?? 'access';
if (!in_array($active_tab, ['access', 'allowlist', 'invites'], true)) {
    $active_tab = 'access';
}
$access_type = $wall['access_control']['type'] ?? 'public';
$emails = list_wall_emails($wall_id);
$invites = list_invites_for_wall($wall_id);
$csrf = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($wall['name']) ?> &middot; Settings</title>
    <link rel="stylesheet" href="../assets/css/app.css">
</head>
<body>
    <div class="container">
        <header class="app-header">
            <h1><?= htmlspecialchars($wall['name']) ?></h1>
            <nav class="app-header__nav">
                <a href="editor.php">Editor</a>
                <a href="index.php">Buildings</a>
                <a href="settings.php">Settings</a>
                <a href="logout.php" class="danger">Sign out</a>
            </nav>
        </header>

        <nav class="breadcrumb">
            <a href="editor.php">Editor</a>
            <?php if ($building): ?>
                <span class="breadcrumb__sep">/</span>
                <?= htmlspecialchars($building['name']) ?>
            <?php endif; ?>
            <?php if ($side): ?>
                <span class="breadcrumb__sep">/</span>
                <?= htmlspecialchars($side['name']) ?>
            <?php endif; ?>
            <span class="breadcrumb__sep">/</span>
            <?= htmlspecialchars($wall['name']) ?>
            <span class="breadcrumb__sep">/</span>
            Settings
        </nav>

        <?php if ($success_message): ?>
            <div class="message message--success"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="message message--error"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <?php if ($just_generated_invite): ?>
            <div class="callout">
                <strong>Invite link generated.</strong>
                <p style="margin-top: var(--space-1); margin-bottom: var(--space-2);">
                    Copy this now &mdash; for security, it's shown only once.
                </p>
                <textarea readonly rows="2" id="just_generated_invite_url"><?= htmlspecialchars($just_generated_invite) ?></textarea>
                <div style="margin-top: var(--space-2);">
                    <button type="button" class="btn btn--secondary btn--sm" onclick="(function(){const el=document.getElementById('just_generated_invite_url');navigator.clipboard.writeText(el.value);})();">Copy</button>
                </div>
            </div>
        <?php endif; ?>

        <section class="section">
            <div class="section__heading">
                <h2>Short link</h2>
                <small>3-char auto-generated; customize to any 2&ndash;32 chars (a-z, 0-9, _, -).</small>
            </div>
            <?php $short_url = wall_short_url($wall); ?>
            <div class="field--inline" style="margin-bottom: var(--space-3);">
                <input type="text" readonly value="<?= htmlspecialchars($short_url) ?>" id="wall_short_url_display" style="font-family: var(--font-mono); font-size: var(--text-xs);">
                <button type="button" class="btn btn--secondary" onclick="(function(){const el=document.getElementById('wall_short_url_display');navigator.clipboard.writeText(el.value);})();">Copy</button>
                <a href="<?= htmlspecialchars($short_url) ?>" target="_blank" rel="noopener noreferrer" class="btn btn--ghost">Open</a>
            </div>
            <form method="post" class="field--inline">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="action" value="update_slug">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($active_tab) ?>">
                <input type="text" name="slug" value="<?= htmlspecialchars($wall['slug'] ?? '') ?>" placeholder="abc">
                <button type="submit" class="btn">Save slug</button>
            </form>
            <small>Leave empty in the form to auto-regenerate a fresh 3-char slug.</small>
        </section>

        <section class="section" style="padding: 0;">
            <div class="tabs" style="margin: 0; padding: 0 var(--space-5);">
                <a class="tab <?= $active_tab === 'access' ? 'is-active' : '' ?>"
                   href="?wall_id=<?= htmlspecialchars($wall_id) ?>&tab=access">Access type</a>
                <a class="tab <?= $active_tab === 'allowlist' ? 'is-active' : '' ?>"
                   href="?wall_id=<?= htmlspecialchars($wall_id) ?>&tab=allowlist">Email allowlist <small>(<?= count($emails) ?>)</small></a>
                <a class="tab <?= $active_tab === 'invites' ? 'is-active' : '' ?>"
                   href="?wall_id=<?= htmlspecialchars($wall_id) ?>&tab=invites">Invite links <small>(<?= count(array_filter($invites, fn($i) => empty($i['used_at']))) ?> active)</small></a>
            </div>

            <div style="padding: var(--space-5);">

                <!-- ACCESS TYPE TAB -->
                <?php if ($active_tab === 'access'): ?>
                    <p style="color: var(--color-text-muted); font-size: var(--text-sm); margin-top: 0;">
                        Choose how visitors gain access to this wall. Anything other than <strong>Public</strong> is a private wall.
                    </p>

                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <input type="hidden" name="action" value="update_access_type">
                        <input type="hidden" name="tab" value="access">

                        <div class="field">
                            <label for="access_type">Access type</label>
                            <select name="access_type" id="access_type" onchange="toggleAccessFields()">
                                <option value="public" <?= $access_type === 'public' ? 'selected' : '' ?>>Public &mdash; anyone with the link can view</option>
                                <option value="password" <?= $access_type === 'password' ? 'selected' : '' ?>>Password</option>
                                <option value="codelist" <?= $access_type === 'codelist' ? 'selected' : '' ?>>Codelist (multiple codes)</option>
                                <option value="email_allowlist" <?= $access_type === 'email_allowlist' ? 'selected' : '' ?>>Email allowlist (long-term, manage on next tab)</option>
                                <option value="payment" <?= $access_type === 'payment' ? 'selected' : '' ?>>Payment (Stripe)</option>
                            </select>
                        </div>

                        <div class="field" id="f_password" style="display:none;">
                            <label for="access_password">Password</label>
                            <input type="password" name="access_password" id="access_password" placeholder="<?= ($wall['access_control']['password']['hash'] ?? null) ? 'Leave blank to keep current' : 'Set a password' ?>">
                        </div>

                        <div class="field" id="f_codelist" style="display:none;">
                            <label for="access_codelist">Codes (comma- or newline-separated)</label>
                            <textarea name="access_codelist" id="access_codelist" rows="4" placeholder="code1, code2, code3"></textarea>
                            <small>Leaving this empty when codelist is selected reverts the wall to public.</small>
                        </div>

                        <div id="f_payment" style="display:none;">
                            <?php
                                $current_providers = $wall['access_control']['payment']['providers'] ?? null;
                                $provider_default = is_array($current_providers) ? $current_providers : [];
                                $stripe_on = stripe_is_configured();
                                $paypal_on = paypal_is_configured();
                            ?>
                            <div class="field">
                                <label for="access_price">Price (USD)</label>
                                <input type="number" name="access_price" id="access_price" step="0.01" min="0" placeholder="5.00" value="<?= htmlspecialchars((string)($wall['access_control']['payment']['price'] ?? '')) ?>">
                            </div>
                            <div class="field">
                                <label>Accepted providers</label>
                                <div style="display: flex; flex-direction: column; gap: var(--space-1);">
                                    <label style="font-weight: 400; <?= $stripe_on ? '' : 'color: var(--color-text-subtle);' ?>">
                                        <input type="checkbox" name="providers[]" value="stripe" style="width: auto; margin-right: var(--space-1);"
                                               <?= in_array('stripe', $provider_default, true) || (empty($provider_default) && $stripe_on) ? 'checked' : '' ?>
                                               <?= $stripe_on ? '' : 'disabled' ?>>
                                        Stripe <?= $stripe_on ? '' : '<small>(not configured in <a href="settings.php">Settings</a>)</small>' ?>
                                    </label>
                                    <label style="font-weight: 400; <?= $paypal_on ? '' : 'color: var(--color-text-subtle);' ?>">
                                        <input type="checkbox" name="providers[]" value="paypal" style="width: auto; margin-right: var(--space-1);"
                                               <?= in_array('paypal', $provider_default, true) || (empty($provider_default) && $paypal_on) ? 'checked' : '' ?>
                                               <?= $paypal_on ? '' : 'disabled' ?>>
                                        PayPal <?= $paypal_on ? '' : '<small>(not configured in <a href="settings.php">Settings</a>)</small>' ?>
                                    </label>
                                </div>
                                <small>Visitors choose any enabled provider on the locked screen.</small>
                            </div>
                            <?php if (!$stripe_on && !$paypal_on): ?>
                                <div class="message message--warn">
                                    No payment providers are configured. Add keys in <a href="settings.php">Settings &rarr; Payments</a> first.
                                </div>
                            <?php endif; ?>
                        </div>

                        <div id="f_email_allowlist" style="display:none;">
                            <p style="color: var(--color-text-muted); font-size: var(--text-sm);">
                                Visitors will enter their email at the wall to verify. The list is managed
                                on the <a href="?wall_id=<?= htmlspecialchars($wall_id) ?>&tab=allowlist">Email allowlist tab</a>.
                            </p>
                        </div>

                        <button type="submit" class="btn">Save access type</button>
                    </form>

                    <script>
                        function toggleAccessFields() {
                            const t = document.getElementById('access_type').value;
                            const sections = {
                                password:        document.getElementById('f_password'),
                                codelist:        document.getElementById('f_codelist'),
                                payment:         document.getElementById('f_payment'),
                                email_allowlist: document.getElementById('f_email_allowlist'),
                            };
                            for (const [key, el] of Object.entries(sections)) {
                                if (!el) continue;
                                const active = (t === key);
                                el.style.display = active ? 'block' : 'none';
                                // Disable hidden inputs so they don't block validation/submission.
                                el.querySelectorAll('input, textarea, select').forEach(field => {
                                    field.disabled = !active;
                                });
                            }
                        }
                        toggleAccessFields();
                    </script>
                <?php endif; ?>

                <!-- EMAIL ALLOWLIST TAB -->
                <?php if ($active_tab === 'allowlist'): ?>
                    <p style="color: var(--color-text-muted); font-size: var(--text-sm); margin-top: 0;">
                        Visitors whose email is on this list can verify themselves on the wall page
                        and get long-term access (30-day cookie). Removing an email here revokes
                        that visitor's access automatically. Emails are stored encrypted at rest.
                        <?php if ($access_type !== 'email_allowlist'): ?>
                            <br><strong>Note:</strong> the wall is not currently set to use the email allowlist.
                            Switch on the <a href="?wall_id=<?= htmlspecialchars($wall_id) ?>&tab=access">Access type tab</a>.
                        <?php endif; ?>
                    </p>

                    <form method="post" class="field--inline" style="margin-bottom: var(--space-5);">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <input type="hidden" name="action" value="add_email">
                        <input type="hidden" name="tab" value="allowlist">
                        <input type="email" name="email" placeholder="visitor@example.com" required>
                        <button type="submit" class="btn">Add</button>
                    </form>

                    <?php if (empty($emails)): ?>
                        <div class="list__empty">No emails yet.</div>
                    <?php else: ?>
                        <div>
                            <?php foreach ($emails as $entry): ?>
                                <div class="node">
                                    <span>
                                        <span class="node__name"><?= htmlspecialchars($entry['email']) ?></span>
                                    </span>
                                    <span class="node__actions">
                                        <form method="post" style="display:inline;" onsubmit="return confirm('Remove <?= htmlspecialchars($entry['email'], ENT_QUOTES) ?> from the allowlist?');">
                                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                            <input type="hidden" name="action" value="remove_email">
                                            <input type="hidden" name="tab" value="allowlist">
                                            <input type="hidden" name="email_hash" value="<?= htmlspecialchars($entry['email_hash']) ?>">
                                            <button type="submit" class="btn btn--ghost btn--sm" style="color: var(--color-danger);">Remove</button>
                                        </form>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- INVITE LINKS TAB -->
                <?php if ($active_tab === 'invites'): ?>
                    <p style="color: var(--color-text-muted); font-size: var(--text-sm); margin-top: 0;">
                        Invite links work regardless of the wall's access type. Each link is single-use:
                        the recipient clicks once and is granted access for that browser session. After that
                        the link is consumed and can't be reused. Send the link via your own channel
                        (email, chat, etc.) &mdash; the app does not send mail.
                    </p>

                    <form method="post" style="margin-bottom: var(--space-5);">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <input type="hidden" name="action" value="create_invite">
                        <input type="hidden" name="tab" value="invites">
                        <div class="field--inline">
                            <input type="text" name="invite_note" placeholder="Optional note (e.g., recipient's name)">
                            <button type="submit" class="btn">Generate invite</button>
                        </div>
                    </form>

                    <?php if (empty($invites)): ?>
                        <div class="list__empty">No invites yet.</div>
                    <?php else: ?>
                        <div>
                            <?php foreach ($invites as $inv): $used = !empty($inv['used_at']); ?>
                                <div class="node">
                                    <span>
                                        <span class="node__name">
                                            <?= htmlspecialchars($inv['note'] !== '' ? $inv['note'] : 'Invite') ?>
                                        </span>
                                        <span class="<?= $used ? 'tree__badge tree__badge--paid' : 'tree__badge' ?>" style="margin-left: var(--space-2);">
                                            <?= $used ? 'used' : 'active' ?>
                                        </span>
                                        <span class="node__id">
                                            created <?= htmlspecialchars($inv['created_at']) ?>
                                            <?= $used ? ' &middot; used ' . htmlspecialchars($inv['used_at']) : '' ?>
                                        </span>
                                    </span>
                                    <span class="node__actions">
                                        <form method="post" style="display:inline;" onsubmit="return confirm('<?= $used ? 'Delete this used invite from the log?' : 'Revoke this active invite?' ?>');">
                                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                            <input type="hidden" name="action" value="revoke_invite">
                                            <input type="hidden" name="tab" value="invites">
                                            <input type="hidden" name="invite_id" value="<?= htmlspecialchars($inv['id']) ?>">
                                            <button type="submit" class="btn btn--ghost btn--sm" style="color: var(--color-danger);"><?= $used ? 'Delete' : 'Revoke' ?></button>
                                        </form>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

            </div>
        </section>

        <p style="margin-top: var(--space-6); font-size: var(--text-xs); color: var(--color-text-subtle);">
            Wall ID: <code><?= htmlspecialchars($wall_id) ?></code> &middot;
            Public link: <a href="../wall.php?id=<?= htmlspecialchars($wall_id) ?>" target="_blank" rel="noopener noreferrer">view as visitor</a>
        </p>
    </div>
</body>
</html>
