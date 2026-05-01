<?php
session_start();

require_once '../config/Database.php';
require_once '../models/PartOrder.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Customer') {
    header("Location: ../views/login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $db = (new Database())->getConnection();
    $partOrder = new PartOrder($db);

    try {
        $stmtCustomer = $db->prepare("
            SELECT customer_id
            FROM customer
            WHERE user_id = ?
            LIMIT 1
        ");
        $stmtCustomer->execute([$_SESSION['user_id']]);
        $customer_id = $stmtCustomer->fetchColumn();

        if (!$customer_id) {
            throw new Exception("Customer profile not found.");
        }

        $part_id = intval($_POST['part_id'] ?? 0);
        $quantity = intval($_POST['quantity'] ?? 0);

        if ($part_id <= 0) {
            throw new Exception("Please select a valid part.");
        }

        if ($quantity <= 0) {
            throw new Exception("Quantity must be greater than zero.");
        }

        if ($partOrder->createOrder($customer_id, $part_id, $quantity)) {
            $_SESSION['success_message'] = "Your order has been placed. Please wait for shop confirmation.";
        } else {
            $_SESSION['error_message'] = "Failed to place order. The item may be unavailable or insufficient in stock.";
        }

    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }

    header("Location: ../views/dashboard_customer.php");
    exit;
}

header("Location: ../views/dashboard_customer.php");
exit;
?>