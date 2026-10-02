<?php
require_once __DIR__ . '/../includes/auth.php';
require_once '../includes/online_payment.php';

requirePermission('shop.pay_online', '../views/login.php');

$db = (new Database())->getConnection();
$customerId = intval($_SESSION['customer_id'] ?? 0);

function redirectToOrders($messageKey = null, $message = null) {
    if ($messageKey !== null) {
        $_SESSION[$messageKey] = $message;
    }

    header("Location: ../views/dashboard_customer.php#myOrdersSection");
    exit;
}

try {
    // Start (or resume) paying an order on PayMongo's GCash checkout page.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'pay') {
        $checkoutUrl = startOnlinePayment($db, intval($_POST['order_id'] ?? 0), $customerId);

        if (!preg_match('#^https://#', $checkoutUrl) && isProduction()) {
            throw new Exception("Invalid checkout address from the payment gateway.");
        }

        header("Location: " . $checkoutUrl);
        exit;
    }

    /*
        PayMongo redirects the customer here after paying or cancelling.
        The query string is never trusted: the status is read from PayMongo's API.
    */
    if (($_GET['action'] ?? '') === 'return') {
        $transactions = new PaymentTransaction($db);
        $txn = $transactions->find(intval($_GET['txn'] ?? 0));

        if (!$txn || intval($txn['customer_id']) !== $customerId) {
            redirectToOrders('error_message', "Payment record not found.");
        }

        $status = syncPaymentTransaction($db, $txn);

        if ($status === 'Paid') {
            redirectToOrders('success_message', "GCash payment received for Order #{$txn['part_order_id']}. Thank you!");
        }

        if (!empty($_GET['cancelled'])) {
            redirectToOrders('error_message', "GCash payment was cancelled. You can pay again from your orders list.");
        }

        redirectToOrders('success_message', "We are waiting for PayMongo to confirm your GCash payment for Order #{$txn['part_order_id']}. This page will update once it is confirmed.");
    }
} catch (Exception $e) {
    redirectToOrders('error_message', $e->getMessage());
}

redirectToOrders();
