<?php
/*
    PayMongo webhook endpoint.
    Register this URL in the PayMongo dashboard (Developers > Webhooks) with the events
    checkout_session.payment.paid and payment.paid, then put the webhook's secret
    key in PAYMONGO_WEBHOOK_SECRET.

    Security:
      - The Paymongo-Signature header is verified (HMAC-SHA256, timestamp tolerance).
      - Each event id is processed once (payment_webhook_event).
      - The event body is only used to find our transaction; the payment is then
        confirmed by reading the checkout session back from PayMongo's API.
    No session or CSRF token is involved here: the signature authenticates the sender.
*/

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../includes/online_payment.php';

header('Content-Type: application/json');

function respond($code, $message) {
    http_response_code($code);
    echo json_encode(['message' => $message]);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, 'Method not allowed');
}

$rawBody = file_get_contents('php://input');

if (strlen($rawBody) > 1048576) {
    respond(413, 'Payload too large');
}

if (!paymongoVerifyWebhookSignature($rawBody, $_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? '', env('PAYMONGO_WEBHOOK_SECRET', ''))) {
    respond(401, 'Invalid signature');
}

$event = json_decode($rawBody, true);
$eventId = $event['data']['id'] ?? '';
$eventType = $event['data']['attributes']['type'] ?? '';
$resource = $event['data']['attributes']['data'] ?? [];

if (!is_string($eventId) || !preg_match('/^[A-Za-z0-9_]{1,100}$/', $eventId) || $eventType === '') {
    respond(400, 'Malformed event');
}

if (!empty($event['data']['attributes']['livemode']) !== paymongoLivemode()) {
    respond(200, 'Ignored: different mode');
}

$db = (new Database())->getConnection();

// Record the event first; a duplicate delivery is acknowledged without reprocessing.
$stmt = $db->prepare("INSERT IGNORE INTO payment_webhook_event (event_id, event_type) VALUES (?, ?)");
$stmt->execute([$eventId, substr($eventType, 0, 100)]);

if ($stmt->rowCount() === 0) {
    respond(200, 'Already processed');
}

$transactions = new PaymentTransaction($db);
$txn = null;

if (($resource['type'] ?? '') === 'checkout_session') {
    $txn = $transactions->findBySessionId($resource['id'] ?? '');
} elseif (($resource['type'] ?? '') === 'payment') {
    $txn = $transactions->findByPaymentIntentId($resource['attributes']['payment_intent_id'] ?? '');
}

$result = 'No matching transaction';

try {
    if ($txn) {
        $result = 'Transaction ' . $txn['transaction_id'] . ': ' . syncPaymentTransaction($db, $txn);
    }
} catch (Exception $e) {
    // Let PayMongo retry later by allowing the event to be processed again.
    $db->prepare("DELETE FROM payment_webhook_event WHERE event_id = ?")->execute([$eventId]);
    error_log("DASPMS WEBHOOK ERROR ($eventId): " . $e->getMessage());
    respond(500, 'Temporary error');
}

$db->prepare("UPDATE payment_webhook_event SET result = ? WHERE event_id = ?")->execute([substr($result, 0, 255), $eventId]);

respond(200, 'OK');
