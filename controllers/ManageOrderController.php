<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/activity_log.php';

require_once '../config/Database.php';
require_once '../models/PartOrder.php';
require_once '../includes/online_payment.php';

requirePermission('online_orders.manage', '../views/login.php');

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'])) {
    $db = (new Database())->getConnection();
    $partOrder = new PartOrder($db);
    $user_id = intval($_SESSION['user_id']);

    if ($_POST['action'] === 'update_status') {
        $order_id = intval($_POST['order_id'] ?? 0);
        $new_status = trim($_POST['status'] ?? '');
        $cancellation_reason = trim($_POST['cancellation_reason'] ?? '');

        // Close any open PayMongo checkout first; if it was just paid, the order's
        // verified payment then blocks the cancellation below.
        if ($new_status === 'Cancelled') {
            cancelPendingOnlinePayments($db, $order_id);
        }

        if ($partOrder->updateStatus($order_id, $new_status, $user_id, $cancellation_reason)) {
            if ($new_status === 'Cancelled') {
                logActivity($db, 'Cancelled web order', 'Web order', $order_id, 'PO-' . $order_id, 'Reason: ' . $cancellation_reason);
            }

            $_SESSION['success_message'] = "Order #{$order_id} updated to '{$new_status}' successfully.";
        } else {
            $_SESSION['error_message'] = $partOrder->lastError !== ''
                ? $partOrder->lastError
                : "Failed to update order status. Please check stock, invoice, payment, or GCash verification status.";
        }

        header("Location: ../views/online_orders.php");
        exit;
    }

    if ($_POST['action'] === 'update_gcash_verification') {
        $order_id = intval($_POST['order_id'] ?? 0);
        $verification_status = trim($_POST['gcash_verification_status'] ?? '');

        if ($partOrder->updateGcashVerification($order_id, $verification_status, $user_id)) {
            $_SESSION['success_message'] = "GCash verification for Order #{$order_id} updated to '{$verification_status}'.";
        } else {
            $_SESSION['error_message'] = $partOrder->lastError !== ''
                ? $partOrder->lastError
                : "Failed to update GCash verification. Make sure this is an active GCash payment order with a valid reference and amount.";
        }

        header("Location: ../views/online_orders.php");
        exit;
    }
}

header("Location: ../views/online_orders.php");
exit;
?>