<?php
session_start();
require_once '../config/Database.php';
require_once '../models/Customer.php';

if (!isset($_SESSION['logged_in'])) {
    header("Location: ../views/login.php");
    exit;
}

$database = new Database();
$db = $database->getConnection();
$customer = new Customer($db);

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add') {
    
    $customer->first_name = $_POST['first_name'];
    $customer->middle_name = $_POST['middle_name'];
    $customer->last_name = $_POST['last_name'];
    $customer->contact_number = $_POST['contact_number'];
    $customer->address = $_POST['address'];

    if ($customer->create()) {
        $_SESSION['success_message'] = "Customer added successfully.";
    } else {
        $_SESSION['error_message'] = "Failed to add customer. Please try again.";
    }
    
    header("Location: ../views/customers.php");
    exit;
}
?>