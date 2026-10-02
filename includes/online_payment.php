<?php
/*
    Online GCash payments for part orders through PayMongo.

    Flow:
      1. Checkout creates the order with gcash_verification_status = 'Awaiting Payment'.
      2. startOnlinePayment() creates a payment_transaction + PayMongo checkout session
         and the customer is redirected to PayMongo to pay with GCash.
      3. PayMongo notifies webhooks/paymongo.php and redirects the customer back to
         PaymentController (action=return). Both only call syncPaymentTransaction(),
         which reads the session from PayMongo's API and, if it is paid with the exact
         expected amount, marks the order's GCash payment as Verified.
*/

require_once __DIR__ . '/paymongo.php';
require_once __DIR__ . '/../models/PaymentTransaction.php';

const ONLINE_PAYMENT_METHODS = ['GCash Down Payment', 'GCash Full Payment'];

function isOnlinePaymentMethod($paymentMethod) {
    return in_array($paymentMethod, ONLINE_PAYMENT_METHODS, true);
}

/*
    Returns the PayMongo checkout URL for the customer's order, creating a new
    checkout session when there is no usable one. Throws with a user-facing message.
*/
function startOnlinePayment(PDO $db, $orderId, $customerId) {
    $stmt = $db->prepare("
        SELECT order_id, customer_id, status, total_amount, payment_method,
               gcash_payment_amount, gcash_verification_status
        FROM part_order
        WHERE order_id = ? AND customer_id = ?
        LIMIT 1
    ");
    $stmt->execute([intval($orderId), intval($customerId)]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception("Order not found.");
    }

    if (!isOnlinePaymentMethod($order['payment_method']) || $order['gcash_verification_status'] !== 'Awaiting Payment') {
        throw new Exception("This order does not need an online payment.");
    }

    if ($order['status'] !== 'Pending') {
        throw new Exception("Only pending orders can be paid online.");
    }

    $transactions = new PaymentTransaction($db);

    // Reuse an open checkout session if PayMongo still accepts payment for it.
    $pending = $transactions->findPendingForOrder($orderId);

    if ($pending) {
        $result = syncPaymentTransaction($db, $pending);

        if ($result === 'Paid') {
            throw new Exception("This order has already been paid.");
        }

        if ($result === 'Pending') {
            return $pending['checkout_url'];
        }
    }

    $isFull = $order['payment_method'] === 'GCash Full Payment';
    $amount = round($isFull ? floatval($order['total_amount']) : floatval($order['gcash_payment_amount']), 2);

    if ($amount < paymongoMinimumAmount()) {
        throw new Exception("Online payments must be at least PHP " . number_format(paymongoMinimumAmount(), 2) . ".");
    }

    $purpose = $isFull ? 'Full Payment' : 'Down Payment';
    $transactionId = $transactions->create($orderId, $customerId, $purpose, $amount);
    $returnUrl = appBaseUrl() . '/controllers/PaymentController.php?action=return&txn=' . $transactionId;

    try {
        $session = paymongoCreateCheckoutSession(
            $amount,
            "Parts Order #{$orderId} - {$purpose}",
            "Norily's Repair Shop parts reservation #{$orderId}",
            "PO-{$orderId}-T{$transactionId}",
            ['part_order_id' => $orderId, 'transaction_id' => $transactionId],
            $returnUrl,
            $returnUrl . '&cancelled=1'
        );
    } catch (Exception $e) {
        $transactions->setStatus($transactionId, 'Failed');
        throw $e;
    }

    $transactions->attachCheckoutSession($transactionId, $session);

    return $session['checkout_url'];
}

/*
    Brings a transaction up to date with PayMongo's API and returns its status
    ('Paid', 'Pending', 'Expired', ...). The gateway is the only source of truth:
    amounts, currency and mode must match what this app requested.
*/
function syncPaymentTransaction(PDO $db, array $txn) {
    if ($txn['status'] !== 'Pending' || empty($txn['checkout_session_id'])) {
        return $txn['status'];
    }

    $session = paymongoRetrieveCheckoutSession($txn['checkout_session_id']);
    $attributes = $session['attributes'] ?? [];
    $transactions = new PaymentTransaction($db);

    if (($session['id'] ?? '') !== $txn['checkout_session_id']) {
        error_log("DASPMS PAYMENT: session id mismatch for transaction {$txn['transaction_id']}");
        return 'Pending';
    }

    if (!empty($attributes['livemode']) !== paymongoLivemode()) {
        error_log("DASPMS PAYMENT: livemode mismatch for transaction {$txn['transaction_id']}");
        return 'Pending';
    }

    $payment = paymongoPaidPayment($session);

    if ($payment) {
        if ($payment['amount'] !== toCentavos($txn['amount']) || $payment['currency'] !== 'PHP') {
            error_log("DASPMS PAYMENT: amount mismatch for transaction {$txn['transaction_id']}: got {$payment['amount']} {$payment['currency']}");
            return 'Pending';
        }

        $transactions->markPaid($txn['transaction_id'], $payment['id']);
        return 'Paid';
    }

    if (($attributes['status'] ?? '') === 'expired') {
        $transactions->setStatus($txn['transaction_id'], 'Expired');
        return 'Expired';
    }

    return 'Pending';
}

// Stops open checkouts for an order (e.g. when the shop cancels it). Best effort.
function cancelPendingOnlinePayments(PDO $db, $orderId) {
    $transactions = new PaymentTransaction($db);

    while ($pending = $transactions->findPendingForOrder($orderId)) {
        try {
            if (syncPaymentTransaction($db, $pending) === 'Pending') {
                paymongoExpireCheckoutSession($pending['checkout_session_id']);
                $transactions->setStatus($pending['transaction_id'], 'Cancelled');
            }
        } catch (Exception $e) {
            error_log("DASPMS PAYMENT: could not expire session for order $orderId: " . $e->getMessage());
            $transactions->setStatus($pending['transaction_id'], 'Cancelled');
        }
    }
}
