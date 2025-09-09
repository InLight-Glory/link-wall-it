<?php

header('Content-Type: application/json');

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/app/core/functions.php';

// Get the Wall ID from the query string
$wall_id = $_GET['wall_id'] ?? null;
if (!$wall_id) {
    echo json_encode(['error' => 'Wall ID is missing.']);
    exit;
}

$wall = get_wall($wall_id);
$settings = get_settings();

if (!$wall || $wall['access_control']['type'] !== 'stripe' || empty($settings['stripe_secret_key'])) {
    echo json_encode(['error' => 'This wall is not configured for payments or Stripe keys are missing.']);
    exit;
}

\Stripe\Stripe::setApiKey($settings['stripe_secret_key']);

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$base_url = "{$protocol}://{$host}";

try {
    $checkout_session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'line_items' => [[
            'price_data' => [
                'currency' => $wall['access_control']['stripe']['currency'],
                'product_data' => [
                    'name' => 'Access to Wall: ' . htmlspecialchars($wall['name']),
                ],
                'unit_amount' => $wall['access_control']['stripe']['price_in_cents'],
            ],
            'quantity' => 1,
        ]],
        'mode' => 'payment',
        'success_url' => $base_url . '/wall.php?id=' . $wall_id . '&stripe_session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => $base_url . '/wall.php?id=' . $wall_id,
    ]);

    echo json_encode(['id' => $checkout_session->id]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
