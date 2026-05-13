<?php
/**
 * PayPal webhook endpoint (Phase 4).
 *
 * Configure in PayPal Developer Dashboard:
 *   URL:    <your-site>/paypal_webhook.php
 *   Events: CHECKOUT.ORDER.APPROVED, PAYMENT.CAPTURE.COMPLETED, PAYMENT.CAPTURE.REFUNDED
 *
 * Set the webhook id in Admin → Settings → PayPal webhook ID. Without it, this endpoint
 * refuses every request.
 *
 * Idempotent: complete_purchase() short-circuits on duplicate events.
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/app/core/functions.php';

header('Content-Type: application/json');

$raw = file_get_contents('php://input');
$headers = function_exists('getallheaders') ? getallheaders() : [];

if (!paypal_verify_webhook_signature($headers, $raw)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid signature']);
    exit;
}

$event = json_decode($raw, true);
if (!is_array($event)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid payload']);
    exit;
}

$type = $event['event_type'] ?? '';

try {
    switch ($type) {
        case 'CHECKOUT.ORDER.APPROVED': {
            // Order approved but not yet captured. Our return-URL flow normally captures
            // synchronously — but if the visitor closed the tab, capture here as fallback.
            $order_id = $event['resource']['id'] ?? '';
            if (!$order_id) { break; }
            $captured = paypal_capture_order($order_id);
            if (!isset($captured['error']) && !empty($captured['email'])) {
                complete_purchase('paypal', $order_id, $captured['email']);
            }
            break;
        }

        case 'PAYMENT.CAPTURE.COMPLETED': {
            // Capture succeeded — confirm purchase. The supplementary_data carries the order id.
            $resource = $event['resource'] ?? [];
            $order_id = $resource['supplementary_data']['related_ids']['order_id']
                     ?? ($resource['custom_id'] ?? '');
            $email = $event['resource']['payer']['email_address']
                  ?? '';
            if (!$order_id || !$email) {
                // Fall back to PayPal API — fetch order details to find email.
                // Skipped here; rely on synchronous capture for email harvesting.
                break;
            }
            complete_purchase('paypal', $order_id, $email);
            break;
        }

        case 'PAYMENT.CAPTURE.REFUNDED': {
            $resource = $event['resource'] ?? [];
            $order_id = $resource['supplementary_data']['related_ids']['order_id'] ?? '';
            if ($order_id) {
                refund_purchase('paypal', $order_id);
            }
            break;
        }

        default:
            // Unhandled — acknowledge.
            break;
    }
} catch (Throwable $t) {
    error_log('[Webhook:paypal] ' . $t->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'internal']);
    exit;
}

http_response_code(200);
echo json_encode(['received' => true]);
