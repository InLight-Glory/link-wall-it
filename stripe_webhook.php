<?php
/**
 * Stripe webhook endpoint (Phase 4).
 *
 * Configure in Stripe Dashboard:
 *   URL:    <your-site>/stripe_webhook.php
 *   Events: checkout.session.completed, charge.refunded, charge.refund.updated
 *
 * Set the signing secret in Admin → Settings → Stripe webhook secret. Without it,
 * this endpoint refuses every request.
 *
 * Idempotent: complete_purchase() / refund_purchase() short-circuit on duplicate events.
 */

error_reporting(E_ALL);
ini_set('display_errors', 0); // never leak errors back to Stripe
ini_set('log_errors', 1);

require_once __DIR__ . '/app/core/functions.php';

// Always 200 unless we explicitly reject — Stripe retries on non-2xx.
header('Content-Type: application/json');

$payload = file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if (!stripe_verify_webhook_signature($payload, $sig_header)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid signature']);
    exit;
}

$event = json_decode($payload, true);
if (!is_array($event)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid payload']);
    exit;
}

$type = $event['type'] ?? '';

try {
    switch ($type) {
        case 'checkout.session.completed': {
            $session = $event['data']['object'] ?? [];
            $session_id = $session['id'] ?? '';
            $payment_status = $session['payment_status'] ?? '';
            $email = $session['customer_details']['email']
                  ?? $session['customer_email']
                  ?? '';

            if ($payment_status !== 'paid' || !$session_id || !$email) { break; }
            complete_purchase('stripe', $session_id, $email);
            break;
        }

        case 'charge.refunded':
        case 'charge.refund.updated': {
            // Stripe refunds: the charge object includes a payment_intent and metadata
            // back to the original session. The simplest path: walk our own purchases
            // looking for a stripe purchase whose provider_id == payment_intent or
            // session id stored in the refund metadata.
            $charge = $event['data']['object'] ?? [];
            $checkout_session = $charge['metadata']['checkout_session_id'] ?? '';
            // Best-effort: try the session id we stored.
            if ($checkout_session) {
                refund_purchase('stripe', $checkout_session);
            }
            // We don't have a perfect mapping here in self-hosted-no-DB-FK land.
            // The important thing is: if we recorded the session_id, we can revoke.
            break;
        }

        default:
            // Unhandled event types — we just acknowledge.
            break;
    }
} catch (Throwable $t) {
    error_log('[Webhook:stripe] ' . $t->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'internal']);
    exit;
}

http_response_code(200);
echo json_encode(['received' => true]);
