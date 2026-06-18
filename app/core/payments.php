<?php
/**
 * Payments core (Phase 4).
 *
 * - Tracks purchase records in $db['purchases'][].
 * - On a successful purchase, adds the buyer's email to the wall's email_allowlist
 *   (reusing Phase 3 infrastructure) so returning buyers get long-term access via the
 *   same 30-day cookie OR by re-verifying their email on a new browser.
 *
 * Purchase record shape:
 *   [
 *     'id'                => 'pur_<uniqid>',
 *     'wall_id'           => 'w_*',
 *     'provider'          => 'stripe' | 'paypal',
 *     'amount'            => float,
 *     'currency'          => 'USD',
 *     'buyer_email_hash'  => HMAC of normalized email (per-wall) — for fast match without storing plaintext here.
 *     'provider_id'       => provider session/order/intent id (for idempotency on webhook retries)
 *     'status'            => 'pending' | 'completed' | 'refunded' | 'failed'
 *     'created_at'        => 'YYYY-MM-DD HH:MM:SS'
 *     'completed_at'      => null | 'YYYY-MM-DD HH:MM:SS'
 *   ]
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/install_secret.php';
require_once __DIR__ . '/email_access.php';

/**
 * Returns true if a purchase with this provider_id already exists. Used by webhooks
 * to skip duplicate event delivery.
 */
function find_purchase_by_provider_id(string $provider, string $provider_id): ?array {
    $db = get_db();
    foreach ($db['purchases'] ?? [] as $p) {
        if (($p['provider'] ?? '') === $provider && ($p['provider_id'] ?? '') === $provider_id) {
            return $p;
        }
    }
    return null;
}

/**
 * Records a new pending purchase. Returns the purchase id, or null on failure.
 */
function record_pending_purchase(
    string $wall_id,
    string $provider,
    float  $amount,
    string $currency,
    string $provider_id
): ?string {
    $db = get_db();
    if (!isset($db['purchases']) || !is_array($db['purchases'])) {
        $db['purchases'] = [];
    }
    $id = 'pur_' . uniqid('', true);
    $db['purchases'][] = [
        'id'               => $id,
        'wall_id'          => $wall_id,
        'provider'         => $provider,
        'amount'           => $amount,
        'currency'         => $currency,
        'buyer_email_hash' => null,
        'provider_id'      => $provider_id,
        'status'           => 'pending',
        'created_at'       => date('Y-m-d H:i:s'),
        'completed_at'     => null,
    ];
    return save_db($db) ? $id : null;
}

/**
 * Marks a purchase complete and adds the buyer's email to the wall's email_allowlist.
 * Idempotent — calling twice on the same provider_id is a no-op for the second call.
 *
 * Returns true on success, false on failure.
 */
function complete_purchase(string $provider, string $provider_id, string $buyer_email): bool {
    $buyer_email = trim($buyer_email);
    if (!filter_var($buyer_email, FILTER_VALIDATE_EMAIL)) {
        error_log("[Engine:payments] complete_purchase: invalid buyer email for $provider:$provider_id");
        return false;
    }

    $db = get_db();
    $wall_id = null;
    $purchase_index = null;
    foreach ($db['purchases'] ?? [] as $i => $p) {
        if (($p['provider'] ?? '') === $provider && ($p['provider_id'] ?? '') === $provider_id) {
            $wall_id = $p['wall_id'];
            $purchase_index = $i;
            break;
        }
    }
    if ($wall_id === null) {
        error_log("[Engine:payments] complete_purchase: no pending purchase found for $provider:$provider_id");
        return false;
    }

    // Idempotency — skip if already completed.
    if (($db['purchases'][$purchase_index]['status'] ?? '') === 'completed') {
        return true;
    }

    $email_hash = email_match_hash($buyer_email, $wall_id);
    $db['purchases'][$purchase_index]['status'] = 'completed';
    $db['purchases'][$purchase_index]['completed_at'] = date('Y-m-d H:i:s');
    $db['purchases'][$purchase_index]['buyer_email_hash'] = $email_hash;

    if (!save_db($db)) {
        return false;
    }

    // Add to allowlist via the existing engine (it handles idempotency + encryption).
    if (!add_wall_email($wall_id, $buyer_email)) {
        error_log("[Engine:payments] complete_purchase: add_wall_email failed for wall $wall_id");
        // Don't return false — purchase is recorded; allowlist add can be retried manually.
    }
    return true;
}

/**
 * Marks a previously completed purchase as refunded and revokes the buyer's allowlist entry.
 */
function refund_purchase(string $provider, string $provider_id): bool {
    $db = get_db();
    $found = false;
    $wall_id = null;
    $email_hash = null;

    foreach ($db['purchases'] ?? [] as &$p) {
        if (($p['provider'] ?? '') === $provider && ($p['provider_id'] ?? '') === $provider_id) {
            $p['status'] = 'refunded';
            $wall_id = $p['wall_id'];
            $email_hash = $p['buyer_email_hash'];
            $found = true;
            break;
        }
    }
    if (!$found) { return false; }
    if (!save_db($db)) { return false; }

    if ($wall_id && $email_hash) {
        // Best-effort revoke. The allowlist entry might also have been added manually;
        // we still remove by hash since the buyer did receive access via this purchase.
        remove_wall_email($wall_id, $email_hash);
    }
    return true;
}

/**
 * Sets the long-term access cookie for a buyer who just completed payment.
 * Caller is responsible for the redirect; this only writes the cookie + session unlock.
 */
function grant_purchase_session_access(string $wall_id, string $buyer_email): void {
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    if (!isset($_SESSION['unlocked_walls']) || !is_array($_SESSION['unlocked_walls'])) {
        $_SESSION['unlocked_walls'] = [];
    }
    if (!in_array($wall_id, $_SESSION['unlocked_walls'], true)) {
        $_SESSION['unlocked_walls'][] = $wall_id;
    }

    $hash = email_match_hash($buyer_email, $wall_id);
    setcookie(
        email_access_cookie_name($wall_id),
        build_email_access_cookie($wall_id, $hash),
        [
            'expires'  => time() + 60 * 60 * 24 * 365, // 1 year for purchases
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => !empty($_SERVER['HTTPS']),
        ]
    );
}

/**
 * List all purchases for a wall (admin view).
 */
function list_purchases_for_wall(string $wall_id): array {
    $db = get_db();
    $out = [];
    foreach ($db['purchases'] ?? [] as $p) {
        if ($p['wall_id'] === $wall_id) { $out[] = $p; }
    }
    usort($out, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
    return $out;
}

/**
 * Globals accessor — payment mode ('live' or 'test') and provider keys.
 */
function payment_mode(): string {
    $db = get_db();
    $mode = $db['settings']['payment_mode'] ?? 'test';
    return in_array($mode, ['test', 'live'], true) ? $mode : 'test';
}

function payment_settings(): array {
    $db = get_db();
    $s = $db['settings'] ?? [];
    return [
        'mode'                  => payment_mode(),
        'stripe_secret_key'     => $s['stripe_secret_key'] ?? '',
        'stripe_publishable'    => $s['stripe_publishable_key'] ?? '',
        'stripe_webhook_secret' => $s['stripe_webhook_secret'] ?? '',
        'paypal_client_id'      => $s['paypal_client_id'] ?? '',
        'paypal_client_secret'  => $s['paypal_client_secret'] ?? '',
        'paypal_webhook_id'     => $s['paypal_webhook_id'] ?? '',
    ];
}

function stripe_is_configured(): bool {
    $s = payment_settings();
    return $s['stripe_secret_key'] !== '';
}

function paypal_is_configured(): bool {
    $s = payment_settings();
    return $s['paypal_client_id'] !== '' && $s['paypal_client_secret'] !== '';
}

/**
 * Returns the providers enabled on a given wall, intersected with what's configured globally.
 * Default (no `providers` key on the wall): all globally-configured providers are enabled.
 */
function wall_enabled_providers(array $wall): array {
    $configured = [];
    if (stripe_is_configured()) { $configured[] = 'stripe'; }
    if (paypal_is_configured()) { $configured[] = 'paypal'; }

    $declared = $wall['access_control']['payment']['providers'] ?? null;
    if (!is_array($declared)) { return $configured; }
    return array_values(array_intersect($declared, $configured));
}

/**
 * Builds an absolute base URL (scheme://host[/path]) for the install. Used to construct
 * Stripe success_url / PayPal return_url etc.
 */
function app_base_url(): string {
    $scheme = !empty($_SERVER['HTTPS']) ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // Resolve based on the document root layout — wall.php lives at the project root.
    // If the script that called us is in /admin, strip that. Otherwise we're already at root.
    $script = $_SERVER['SCRIPT_NAME'] ?? '/';
    $base = rtrim(dirname($script), '/\\');
    $base = preg_replace('#/admin$#', '', $base);
    if ($base === '/' || $base === '\\') { $base = ''; }
    return $scheme . '://' . $host . $base;
}
