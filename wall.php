<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

require_once __DIR__ . '/app/core/functions.php';

$wall_id = $_GET['id'] ?? null;
if (!$wall_id) {
    header("Location: index.php");
    exit;
}

$wall = get_wall($wall_id);
if (!$wall) {
    header("Location: index.php");
    exit;
}

// --- Access Control ---
$access_type = $wall['access_control']['type'];
$auth_error = '';
$is_unlocked = false;

if (isset($_SESSION['unlocked_walls']) && in_array($wall_id, $_SESSION['unlocked_walls'])) {
    $is_unlocked = true;
}
if (isset($_SESSION['wall_passwords'][$wall_id])) {
    $is_unlocked = true;
}

// Phase 3+4: long-term cookie auto-unlock.
// Used by `email_allowlist` walls (Phase 3) AND `payment` walls (Phase 4 — buyer's email
// is added to the allowlist when they complete payment, and the same cookie applies).
// Restricted to these two access types so a stale cookie doesn't bypass password/codelist gates.
if (!$is_unlocked && in_array($access_type, ['email_allowlist', 'payment'], true)) {
    $cookie_name = email_access_cookie_name($wall_id);
    if (!empty($_COOKIE[$cookie_name]) && verify_email_access_cookie($wall, $_COOKIE[$cookie_name])) {
        $_SESSION['unlocked_walls'][] = $wall_id;
        $is_unlocked = true;
    }
}

// Phase 4: payment cancellation message.
if (!$is_unlocked && !empty($_GET['payment_canceled'])) {
    $auth_error = 'Payment was canceled.';
}

// Phase 4: payment return URLs. Stripe and PayPal redirect back here after the buyer pays.
// We synchronously verify with the provider, and on success: complete the purchase record,
// add the buyer to the allowlist, set the long-term cookie, unlock the session.
if (!$is_unlocked && !empty($_GET['payment_success'])) {
    $provider = $_GET['payment_success'];

    if ($provider === 'stripe' && !empty($_GET['session_id'])) {
        $verify = stripe_verify_session($_GET['session_id']);
        if (!isset($verify['error']) && ($verify['wall_id'] ?? $wall_id) === $wall_id) {
            if (complete_purchase('stripe', $verify['session_id'], $verify['email'])) {
                grant_purchase_session_access($wall_id, $verify['email']);
                header("Location: wall.php?id=" . $wall_id);
                exit;
            }
            $auth_error = 'Payment succeeded but we could not finalize access. Please contact the wall owner.';
        } else {
            $auth_error = 'Stripe could not confirm the payment.' . (isset($verify['error']) ? ' ' . $verify['error'] : '');
        }
    } elseif ($provider === 'paypal' && !empty($_GET['token'])) {
        $capture = paypal_capture_order($_GET['token']);
        if (!isset($capture['error']) && ($capture['wall_id'] ?? $wall_id) === $wall_id) {
            if (complete_purchase('paypal', $capture['order_id'], $capture['email'])) {
                grant_purchase_session_access($wall_id, $capture['email']);
                header("Location: wall.php?id=" . $wall_id);
                exit;
            }
            $auth_error = 'Payment succeeded but we could not finalize access.';
        } else {
            $auth_error = 'PayPal could not capture the payment.' . (isset($capture['error']) ? ' ' . $capture['error'] : '');
        }
    }
}

// Phase 3: one-time invite link. ?invite=<token> grants session unlock and consumes the token.
// Works regardless of access_type — invites are a parallel access channel.
if (!$is_unlocked && !empty($_GET['invite'])) {
    $consumed_for = consume_invite($_GET['invite'], $wall_id);
    if ($consumed_for === $wall_id) {
        $_SESSION['unlocked_walls'][] = $wall_id;
        $is_unlocked = true;
        // Strip the token from the URL so it's not bookmarked/shared.
        header("Location: wall.php?id=" . $wall_id);
        exit;
    } else {
        $auth_error = 'This invite link is invalid or has already been used.';
    }
}

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
    } elseif (in_array($access_type, ['email_allowlist', 'payment'], true) && isset($_POST['access_email'])) {
        // Phase 3 (email_allowlist) AND Phase 4 (payment) — both gate on the same allowlist.
        // Returning buyers in a new browser can re-verify by typing their email here.
        $submitted_email = trim($_POST['access_email']);
        if (filter_var($submitted_email, FILTER_VALIDATE_EMAIL) && wall_email_matches($wall, $submitted_email)) {
            $_SESSION['unlocked_walls'][] = $wall_id;
            $hash = email_match_hash($submitted_email, $wall_id);
            setcookie(
                email_access_cookie_name($wall_id),
                build_email_access_cookie($wall_id, $hash),
                [
                    'expires'  => time() + 60 * 60 * 24 * ($access_type === 'payment' ? 365 : 30),
                    'path'     => '/',
                    'httponly' => true,
                    'samesite' => 'Lax',
                    'secure'   => !empty($_SERVER['HTTPS']),
                ]
            );
            header("Location: wall.php?id=" . $wall_id);
            exit;
        } else {
            $auth_error = ($access_type === 'payment')
                ? "We can't find a purchase under that email address."
                : "That email isn't on the access list for this wall.";
        }
    } elseif ($access_type === 'payment' && !empty($_POST['start_payment'])) {
        // Phase 4: visitor clicked "Pay with Stripe" or "Pay with PayPal"
        $chosen = $_POST['start_payment'];
        $enabled = wall_enabled_providers($wall);
        if (!in_array($chosen, $enabled, true)) {
            $auth_error = 'That payment provider is not available for this wall.';
        } elseif ($chosen === 'stripe') {
            $session = stripe_create_checkout_session($wall);
            if (isset($session['error'])) { $auth_error = $session['error']; }
            else { header('Location: ' . $session['url']); exit; }
        } elseif ($chosen === 'paypal') {
            $order = paypal_create_order($wall);
            if (isset($order['error'])) { $auth_error = $order['error']; }
            else { header('Location: ' . $order['url']); exit; }
        }
    }
}

$can_view_content = ($access_type === 'public') || $is_unlocked;

// --- Data ---
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

$side = get_side($wall['side_id']);
$building = $side ? get_building($side['building_id']) : null;
$site_title = get_db()['settings']['site_title'] ?? 'Link-Wall-It';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($wall['name']) ?> &middot; <?= htmlspecialchars($site_title) ?></title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
    <div class="container">
        <nav class="breadcrumb">
            <a href="index.php">Home</a>
            <?php if ($building): ?>
                <span class="breadcrumb__sep">/</span>
                <a href="building.php?id=<?= htmlspecialchars($building['id']) ?>"><?= htmlspecialchars($building['name']) ?></a>
            <?php endif; ?>
            <?php if ($side): ?>
                <span class="breadcrumb__sep">/</span>
                <a href="side.php?id=<?= htmlspecialchars($side['id']) ?>"><?= htmlspecialchars($side['name']) ?></a>
            <?php endif; ?>
            <span class="breadcrumb__sep">/</span>
            <?= htmlspecialchars($wall['name']) ?>
        </nav>

        <header class="page-header row row--between">
            <div>
                <h1><?= htmlspecialchars($wall['name']) ?></h1>
                <?php if ($can_view_content): ?>
                    <p class="page-header__sub"><?= count($links) ?> <?= count($links) === 1 ? 'link' : 'links' ?></p>
                <?php endif; ?>
            </div>
            <?php $share_url = wall_short_url($wall); ?>
            <div class="row">
                <button type="button" class="btn btn--secondary btn--sm" onclick="copyToClipboard('<?= htmlspecialchars($share_url, ENT_QUOTES) ?>', this)">Copy link</button>
                <button type="button" class="btn btn--secondary btn--sm" onclick="shareToTwitter('<?= htmlspecialchars($share_url, ENT_QUOTES) ?>', '<?= htmlspecialchars($wall['name'], ENT_QUOTES) ?>')">Twitter</button>
                <button type="button" class="btn btn--secondary btn--sm" onclick="shareToFacebook('<?= htmlspecialchars($share_url, ENT_QUOTES) ?>')">Facebook</button>
            </div>
        </header>

        <main>
            <?php if ($can_view_content): ?>
                <?php if (empty($links)): ?>
                    <div class="card">
                        <p class="list__empty">This wall has no links yet.</p>
                    </div>
                <?php else: ?>
                    <div>
                        <?php foreach ($links as $link): ?>
                            <a class="link-row" href="<?= htmlspecialchars($link['url']) ?>" target="_blank" rel="noopener noreferrer">
                                <?php if (!empty($link['image'])): ?>
                                    <span class="link-row__thumb"><img src="<?= htmlspecialchars($link['image']) ?>" alt=""></span>
                                <?php endif; ?>
                                <span class="link-row__body">
                                    <span class="link-row__title"><?= htmlspecialchars($link['title']) ?></span>
                                    <span class="link-row__url"><?= htmlspecialchars($link['url']) ?></span>
                                    <?php if (!empty($link['description'])): ?>
                                        <span class="link-row__desc"><?= htmlspecialchars($link['description']) ?></span>
                                    <?php endif; ?>
                                </span>
                                <span class="link-row__actions">
                                    <button type="button" class="btn btn--secondary btn--sm" onclick="event.preventDefault(); copyToClipboard('<?= htmlspecialchars($link['url'], ENT_QUOTES) ?>', this)">Copy</button>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="card">
                    <h2 style="margin-bottom: var(--space-2);">
                        <?php if ($access_type === 'payment'):
                            $price = (float)($wall['access_control']['payment']['price'] ?? 0);
                        ?>
                            <?= $price > 0 ? '$' . number_format($price, 2) . ' to view' : 'Locked' ?>
                        <?php else: ?>
                            Locked
                        <?php endif; ?>
                    </h2>
                    <p style="color: var(--color-text-muted); font-size: var(--text-sm);">
                        <?php if ($access_type === 'password'): ?>
                            Enter the password to view this wall.
                        <?php elseif ($access_type === 'codelist'): ?>
                            Enter your access code to view this wall.
                        <?php elseif ($access_type === 'email_allowlist'): ?>
                            Enter your email to verify access.
                        <?php elseif ($access_type === 'payment'): ?>
                            Pay once for long-term access. Already purchased? Enter the email you used at checkout.
                        <?php else: ?>
                            This wall is not currently viewable.
                        <?php endif; ?>
                    </p>

                    <?php if ($auth_error): ?>
                        <div class="message message--error" style="margin-top: var(--space-3);"><?= htmlspecialchars($auth_error) ?></div>
                    <?php endif; ?>

                    <?php if ($access_type === 'password'): ?>
                        <form action="wall.php?id=<?= htmlspecialchars($wall_id) ?>" method="post" style="margin-top: var(--space-4);">
                            <div class="field">
                                <input type="password" name="wall_password" placeholder="Password" required autofocus>
                            </div>
                            <button type="submit" class="btn btn--block">Unlock</button>
                        </form>
                    <?php elseif ($access_type === 'codelist'): ?>
                        <form action="wall.php?id=<?= htmlspecialchars($wall_id) ?>" method="post" style="margin-top: var(--space-4);">
                            <div class="field">
                                <input type="text" name="access_code" placeholder="Access code" required autofocus>
                            </div>
                            <button type="submit" class="btn btn--block">Unlock</button>
                        </form>
                    <?php elseif ($access_type === 'email_allowlist'): ?>
                        <form action="wall.php?id=<?= htmlspecialchars($wall_id) ?>" method="post" style="margin-top: var(--space-4);">
                            <div class="field">
                                <input type="email" name="access_email" placeholder="you@example.com" required autofocus>
                            </div>
                            <button type="submit" class="btn btn--block">Verify</button>
                        </form>
                    <?php elseif ($access_type === 'payment'):
                        $price = (float)($wall['access_control']['payment']['price'] ?? 0);
                        $providers = wall_enabled_providers($wall);
                    ?>
                        <?php if ($price <= 0 || empty($providers)): ?>
                            <div class="message message--warn" style="margin-top: var(--space-3);">
                                Payment for this wall is not configured. Please contact the wall owner.
                            </div>
                        <?php else: ?>
                            <div style="display: flex; flex-direction: column; gap: var(--space-2); margin-top: var(--space-4);">
                                <?php if (in_array('stripe', $providers, true)): ?>
                                    <form action="wall.php?id=<?= htmlspecialchars($wall_id) ?>" method="post">
                                        <input type="hidden" name="start_payment" value="stripe">
                                        <button type="submit" class="btn btn--block">Pay $<?= number_format($price, 2) ?> with card (Stripe)</button>
                                    </form>
                                <?php endif; ?>
                                <?php if (in_array('paypal', $providers, true)): ?>
                                    <form action="wall.php?id=<?= htmlspecialchars($wall_id) ?>" method="post">
                                        <input type="hidden" name="start_payment" value="paypal">
                                        <button type="submit" class="btn btn--secondary btn--block">Pay $<?= number_format($price, 2) ?> with PayPal</button>
                                    </form>
                                <?php endif; ?>
                            </div>

                            <div style="margin-top: var(--space-5); padding-top: var(--space-4); border-top: 1px solid var(--color-border);">
                                <p style="font-size: var(--text-xs); color: var(--color-text-muted); margin-bottom: var(--space-2);">Already purchased? Enter the email you used:</p>
                                <form action="wall.php?id=<?= htmlspecialchars($wall_id) ?>" method="post">
                                    <div class="field--inline">
                                        <input type="email" name="access_email" placeholder="you@example.com" required>
                                        <button type="submit" class="btn btn--secondary">Verify</button>
                                    </div>
                                </form>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
    <script src="assets/js/sharing.js"></script>
</body>
</html>
