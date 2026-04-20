<?php
session_start();
require_once '../config/Database.php';
require_once '../models/Billing.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] == 'Head Mechanic') {
    header("Location: ../views/login.php");
    exit;
}

$database = new Database();
$db = $database->getConnection();
$billing = new Billing($db);

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'pay') {
    
    $job_order_id = $_POST['job_order_id'];
    $customer_id = $_POST['customer_id'];
    $amount = floatval($_POST['amount']);
    $method = $_POST['payment_method'];
    $ref = $_POST['reference_number'] ?? '';
    $user_id = $_SESSION['user_id'];

    if ($billing->receivePayment($job_order_id, $customer_id, $user_id, $amount, $method, $ref)) {
        $_SESSION['success_message'] = "Payment of ₱" . number_format($amount, 2) . " processed successfully.";
    } else {
        $_SESSION['error_message'] = "Payment processing failed. Please try again.";
    }
    
    header("Location: ../views/billing.php");
    exit;
}
?>