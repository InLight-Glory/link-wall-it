<?php
/**
 * PayPal Orders v2 integration (Phase 4).
 *
 * Uses PayPal REST API directly via cURL.
 * API docs: https://developer.paypal.com/docs/api/orders/v2/
 *
 * Flow:
 *   1. Visitor clicks "Pay with PayPal" on wall.php (locked screen).
 *   2. wall.php POSTs `start_payment=paypal` -> calls paypal_create_order().
 *   3. We POST to /v2/checkout/orders with intent=CAPTURE, purchase_units, return_url, cancel_url.
 *   4. PayPal returns order with `links[]` including `approve` (HATEOAS).
 *   5. Visitor redirected to that approve URL; pays.
 *   6. PayPal redirects to return_url with ?token=<order-id>&PayerID=...
 *   7. wall.php captures via paypal_capture_order(); on success, completes purchase.
 *   8. Webhook back-stop: paypal_webhook.php receives PAYMENT.CAPTURE.COMPLETED.
 */

require_once __DIR__ . '/payments.php';

function paypal_api_base(): string {
    $mode = payment_mode();
    return $mode === 'live'
        ? 'https://api-m.paypal.com'
        : 'https://api-m.sandbox.paypal.com';
}

/**
 * Acquires (and caches per-request) an OAuth2 access token for PayPal REST API.
 * Returns ['token' => string] or ['error' => string].
 */
function paypal_get_access_token() {
    static $cached = null;
    if ($cached !== null) { return ['token' => $cached]; }

    $settings = payment_settings();
    if (empty($settings['paypal_client_id']) || empty($settings['paypal_client_secret'])) {
        return ['error' => 'PayPal is not configured.'];
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, paypal_api_base() . '/v1/oauth2/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_USERPWD, $settings['paypal_client_id'] . ':' . $settings['paypal_client_secret']);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json',
    ]);

    $body = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        error_log('[Engine:paypal] token cURL error: ' . $err);
        return ['error' => 'Network error contacting PayPal.'];
    }
    $decoded = json_decode($body, true);
    if (!is_array($decoded) || $http >= 400) {
        $msg = $decoded['error_description'] ?? $decoded['message'] ?? ('HTTP ' . $http);
        return ['error' => 'PayPal auth failed: ' . $msg];
    }
    $cached = $decoded['access_token'] ?? '';
    if ($cached === '') { return ['error' => 'PayPal returned no access token.']; }
    return ['token' => $cached];
}

/**
 * Creates a PayPal order. Returns ['url' => approve_url, 'order_id' => id] or ['error' => ...].
 */
function paypal_create_order(array $wall): array {
    $settings = payment_settings();
    if (empty($settings['paypal_client_id'])) {
        return ['error' => 'PayPal is not configured.'];
    }

    $price = (float)($wall['access_control']['payment']['price'] ?? 0);
    if ($price <= 0) {
        return ['error' => 'Wall price is not set.'];
    }

    $token_resp = paypal_get_access_token();
    if (isset($token_resp['error'])) { return $token_resp; }

    $base = app_base_url();
    $return_url = $base . '/wall.php?id=' . urlencode($wall['id']) . '&payment_success=paypal';
    $cancel_url = $base . '/wall.php?id=' . urlencode($wall['id']) . '&payment_canceled=1';

    $payload = [
        'intent' => 'CAPTURE',
        'purchase_units' => [[
            'reference_id' => $wall['id'],
            'description'  => 'Access: ' . $wall['name'],
            'amount' => [
                'currency_code' => 'USD',
                'value' => number_format($price, 2, '.', ''),
            ],
        ]],
        'application_context' => [
            'return_url'         => $return_url,
            'cancel_url'         => $cancel_url,
            'shipping_preference' => 'NO_SHIPPING',
            'user_action'        => 'PAY_NOW',
            'brand_name'         => $wall['name'],
        ],
    ];

    $resp = paypal_api_request('POST', '/v2/checkout/orders', $payload, $token_resp['token']);
    if (isset($resp['error'])) { return ['error' => 'PayPal error: ' . $resp['error']]; }
    if (empty($resp['id'])) { return ['error' => 'PayPal returned an unexpected response.']; }

    $approve_url = null;
    foreach ($resp['links'] ?? [] as $l) {
        if (($l['rel'] ?? '') === 'approve') { $approve_url = $l['href']; break; }
    }
    if (!$approve_url) { return ['error' => 'PayPal did not return an approval URL.']; }

    record_pending_purchase($wall['id'], 'paypal', $price, 'USD', $resp['id']);
    return ['url' => $approve_url, 'order_id' => $resp['id']];
}

/**
 * Captures a previously-approved PayPal order. Returns ['email' => ..., 'order_id' => ..., 'wall_id' => ...]
 * on success or ['error' => ...] on failure.
 */
function paypal_capture_order(string $order_id): array {
    $token_resp = paypal_get_access_token();
    if (isset($token_resp['error'])) { return $token_resp; }

    $resp = paypal_api_request(
        'POST',
        '/v2/checkout/orders/' . urlencode($order_id) . '/capture',
        new stdClass(), // empty JSON object body required
        $token_resp['token']
    );
    if (isset($resp['error'])) { return ['error' => 'PayPal error: ' . $resp['error']]; }

    if (($resp['status'] ?? '') !== 'COMPLETED') {
        return ['error' => 'PayPal order not completed (status: ' . ($resp['status'] ?? 'unknown') . ').'];
    }

    $email = $resp['payer']['email_address'] ?? '';
    if (!$email) { return ['error' => 'No payer email returned by PayPal.']; }

    $wall_id = $resp['purchase_units'][0]['reference_id'] ?? '';
    return ['email' => $email, 'order_id' => $order_id, 'wall_id' => $wall_id];
}

/**
 * Internal helper for PayPal API JSON requests.
 */
function paypal_api_request(string $method, string $path, $body, string $access_token) {
    $ch = curl_init();
    $url = paypal_api_base() . $path;

    $headers = [
        'Authorization: Bearer ' . $access_token,
        'Content-Type: application/json',
        'Accept: application/json',
    ];

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    } elseif ($method === 'GET') {
        // default
    } else {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($body) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
    }

    $body_resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($body_resp === false) {
        error_log('[Engine:paypal] cURL error: ' . $err);
        return ['error' => 'Network error contacting PayPal.'];
    }
    $decoded = json_decode($body_resp, true);
    if (!is_array($decoded)) {
        return ['error' => 'Invalid response from PayPal.'];
    }
    if ($http >= 400) {
        $msg = $decoded['message'] ?? $decoded['error_description'] ?? ('HTTP ' . $http);
        return ['error' => $msg];
    }
    return $decoded;
}

/**
 * Verifies a PayPal webhook signature by calling PayPal's verify-webhook-signature endpoint.
 * Returns true if the signature is valid.
 *
 * Less elegant than Stripe's HMAC because PayPal requires a server-side API call to validate.
 * See: https://developer.paypal.com/api/rest/webhooks/rest/#link-verifywebhooksignature
 */
function paypal_verify_webhook_signature(array $headers, string $raw_body): bool {
    $settings = payment_settings();
    $webhook_id = $settings['paypal_webhook_id'] ?? '';
    if ($webhook_id === '') { return false; }

    $token_resp = paypal_get_access_token();
    if (isset($token_resp['error'])) { return false; }

    $needed = ['paypal-auth-algo', 'paypal-cert-url', 'paypal-transmission-id', 'paypal-transmission-sig', 'paypal-transmission-time'];
    $lower = array_change_key_case($headers, CASE_LOWER);
    foreach ($needed as $h) {
        if (empty($lower[$h])) { return false; }
    }

    $event = json_decode($raw_body, true);
    if (!is_array($event)) { return false; }

    $payload = [
        'auth_algo'         => $lower['paypal-auth-algo'],
        'cert_url'          => $lower['paypal-cert-url'],
        'transmission_id'   => $lower['paypal-transmission-id'],
        'transmission_sig'  => $lower['paypal-transmission-sig'],
        'transmission_time' => $lower['paypal-transmission-time'],
        'webhook_id'        => $webhook_id,
        'webhook_event'     => $event,
    ];

    $resp = paypal_api_request('POST', '/v1/notifications/verify-webhook-signature', $payload, $token_resp['token']);
    return ($resp['verification_status'] ?? '') === 'SUCCESS';
}
