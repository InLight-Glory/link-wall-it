<?php
/**
 * Stripe Checkout integration (Phase 4).
 *
 * Uses Stripe REST API directly via cURL — no PHP SDK dependency.
 * API docs: https://stripe.com/docs/api/checkout/sessions
 *
 * Flow:
 *   1. Visitor clicks "Pay with Stripe" on wall.php (locked screen).
 *   2. wall.php POSTs `start_payment=stripe` -> calls stripe_create_checkout_session().
 *   3. We POST to /v1/checkout/sessions with line item, success_url, cancel_url.
 *   4. Visitor is redirected to Stripe-hosted page; pays.
 *   5. Stripe redirects back to success_url with ?session_id=cs_test_...
 *   6. wall.php verifies via stripe_verify_session() — if status=paid, complete purchase.
 *   7. Asynchronously, Stripe also POSTs `checkout.session.completed` to stripe_webhook.php
 *      as a back-stop (e.g., if the visitor closes the tab before redirect).
 */

require_once __DIR__ . '/payments.php';

const STRIPE_API_BASE = 'https://api.stripe.com/v1';

/**
 * Creates a Stripe Checkout session for a wall purchase.
 *
 * Returns ['url' => string, 'session_id' => string] on success.
 * Returns ['error' => string] on failure.
 */
function stripe_create_checkout_session(array $wall): array {
    $settings = payment_settings();
    if (empty($settings['stripe_secret_key'])) {
        return ['error' => 'Stripe is not configured.'];
    }

    $price = (float)($wall['access_control']['payment']['price'] ?? 0);
    if ($price <= 0) {
        return ['error' => 'Wall price is not set.'];
    }

    $base = app_base_url();
    $success_url = $base . '/wall.php?id=' . urlencode($wall['id'])
                 . '&payment_success=stripe&session_id={CHECKOUT_SESSION_ID}';
    $cancel_url  = $base . '/wall.php?id=' . urlencode($wall['id']) . '&payment_canceled=1';

    $payload = [
        'mode' => 'payment',
        'success_url' => $success_url,
        'cancel_url'  => $cancel_url,
        'customer_creation' => 'if_required',
        'line_items[0][quantity]' => 1,
        'line_items[0][price_data][currency]' => 'usd',
        'line_items[0][price_data][unit_amount]' => (int)round($price * 100),
        'line_items[0][price_data][product_data][name]' => 'Access: ' . $wall['name'],
        'metadata[wall_id]' => $wall['id'],
    ];

    $resp = stripe_api_request('POST', '/checkout/sessions', $payload, $settings['stripe_secret_key']);
    if (isset($resp['error'])) {
        return ['error' => 'Stripe error: ' . $resp['error']];
    }
    if (empty($resp['id']) || empty($resp['url'])) {
        return ['error' => 'Stripe returned an unexpected response.'];
    }

    record_pending_purchase($wall['id'], 'stripe', $price, 'USD', $resp['id']);

    return ['url' => $resp['url'], 'session_id' => $resp['id']];
}

/**
 * Verifies a Stripe Checkout session and, if paid, returns the buyer email.
 *
 * Returns ['email' => string, 'session_id' => string, 'wall_id' => string] on paid.
 * Returns ['error' => string] otherwise.
 */
function stripe_verify_session(string $session_id): array {
    $settings = payment_settings();
    if (empty($settings['stripe_secret_key'])) {
        return ['error' => 'Stripe is not configured.'];
    }

    $resp = stripe_api_request('GET', '/checkout/sessions/' . urlencode($session_id), [], $settings['stripe_secret_key']);
    if (isset($resp['error'])) {
        return ['error' => 'Stripe error: ' . $resp['error']];
    }
    if (($resp['payment_status'] ?? '') !== 'paid') {
        return ['error' => 'Payment not completed.'];
    }
    $email = $resp['customer_details']['email'] ?? ($resp['customer_email'] ?? '');
    if (!$email) {
        return ['error' => 'No buyer email returned.'];
    }
    $wall_id = $resp['metadata']['wall_id'] ?? '';
    return ['email' => $email, 'session_id' => $session_id, 'wall_id' => $wall_id];
}

/**
 * Verifies a Stripe webhook signature using the stripe-signature header and the configured
 * webhook secret. Returns true if signature is valid AND timestamp is within tolerance.
 *
 * Implements the v1 scheme: t=<timestamp>,v1=<hmac>
 * See: https://stripe.com/docs/webhooks/signatures
 */
function stripe_verify_webhook_signature(string $payload, string $sig_header, int $tolerance_seconds = 300): bool {
    $settings = payment_settings();
    $secret = $settings['stripe_webhook_secret'] ?? '';
    if ($secret === '' || $sig_header === '') { return false; }

    $parts = [];
    foreach (explode(',', $sig_header) as $kv) {
        $kv = trim($kv);
        if (strpos($kv, '=') === false) { continue; }
        [$k, $v] = explode('=', $kv, 2);
        $parts[$k][] = $v;
    }
    if (empty($parts['t']) || empty($parts['v1'])) { return false; }

    $timestamp = (int)$parts['t'][0];
    if (abs(time() - $timestamp) > $tolerance_seconds) { return false; }

    $signed_payload = $timestamp . '.' . $payload;
    $expected = hash_hmac('sha256', $signed_payload, $secret);

    foreach ($parts['v1'] as $candidate) {
        if (hash_equals($expected, $candidate)) { return true; }
    }
    return false;
}

/**
 * Internal HTTP helper for the Stripe REST API. Form-urlencoded body with bracket notation
 * for nested fields. Returns decoded JSON or ['error' => message] on transport/HTTP error.
 */
function stripe_api_request(string $method, string $path, array $params, string $secret_key) {
    $url = STRIPE_API_BASE . $path;
    $ch = curl_init();
    $headers = [
        'Authorization: Bearer ' . $secret_key,
        'Content-Type: application/x-www-form-urlencoded',
        'Stripe-Version: 2024-06-20',
    ];

    if ($method === 'GET') {
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
    } else {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    }

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $body = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        error_log('[Engine:stripe] cURL error: ' . $err);
        return ['error' => 'Network error contacting Stripe.'];
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        return ['error' => 'Invalid response from Stripe.'];
    }
    if ($http >= 400) {
        $msg = $decoded['error']['message'] ?? ('HTTP ' . $http);
        return ['error' => $msg];
    }
    return $decoded;
}
