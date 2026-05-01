<?php
session_start();

require_once '../config/Database.php';
require_once '../models/PartOrder.php';

if (
    !isset($_SESSION['logged_in']) ||
    ($_SESSION['role'] !== 'Cashier' && $_SESSION['role'] !== 'Owner')
) {
    header("Location: ../views/login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $db = (new Database())->getConnection();
    $partOrder = new PartOrder($db);

    $order_id = intval($_POST['order_id']);
    $new_status = trim($_POST['status']);
    $user_id = intval($_SESSION['user_id']);

    if ($partOrder->updateStatus($order_id, $new_status, $user_id)) {
        $_SESSION['success_message'] = "Order #{$order_id} updated to '{$new_status}' successfully.";
    } else {
        $_SESSION['error_message'] = "Failed to update order status. Please check stock availability or invoice status.";
    }

    header("Location: ../views/online_orders.php");
    exit;
}

header("Location: ../views/online_orders.php");
exit;
?>