<?php
session_start();
require_once '../config/Database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Customer') {
    header("Location: ../views/login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $db = (new Database())->getConnection();
    
    $customer_id = $_POST['customer_id'];
    $part_id = $_POST['part_id'];
    $quantity = intval($_POST['quantity']);

    try {
        $db->beginTransaction();

        $stmtPrice = $db->prepare("SELECT unit_price FROM part WHERE part_id = ?");
        $stmtPrice->execute([$part_id]);
        $unit_price = $stmtPrice->fetchColumn();

        $total_amount = $unit_price * $quantity;

        $stmtOrder = $db->prepare("INSERT INTO part_order (customer_id, total_amount, status) VALUES (?, ?, 'Pending')");
        $stmtOrder->execute([$customer_id, $total_amount]);
        $order_id = $db->lastInsertId();

        $stmtItem = $db->prepare("INSERT INTO part_order_item (order_id, part_id, quantity, unit_price) VALUES (?, ?, ?, ?)");
        $stmtItem->execute([$order_id, $part_id, $quantity, $unit_price]);

        $db->commit();
        $_SESSION['success_message'] = "Your order has been placed! We will notify you when it is ready for pickup.";
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error_message'] = "Failed to place order. Please try again later.";
    }
    
    header("Location: ../views/dashboard_customer.php");
    exit;
}
?>