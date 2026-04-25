<?php
session_start();
require_once '../config/Database.php';
require_once '../models/PartOrder.php';

if (!isset($_SESSION['logged_in']) || ($_SESSION['role'] !== 'Cashier' && $_SESSION['role'] !== 'Owner')) {
    header("Location: ../views/login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_status') {
    $db = (new Database())->getConnection();
    $partOrder = new PartOrder($db);
    
    $order_id = $_POST['order_id'];
    $new_status = $_POST['status'];
    $part_id = $_POST['part_id'];
    $quantity = $_POST['quantity'];

    if ($partOrder->updateStatus($order_id, $new_status, $part_id, $quantity)) {
        $_SESSION['success_message'] = "Order #$order_id updated to '$new_status' successfully.";
    } else {
        $_SESSION['error_message'] = "Failed to update order status.";
    }
    
    header("Location: ../views/online_orders.php");
    exit;
}
?>