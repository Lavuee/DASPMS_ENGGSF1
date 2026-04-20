<?php
session_start();
require_once '../config/Database.php';
require_once '../models/Part.php';

if (!isset($_SESSION['logged_in'])) {
    header("Location: ../views/login.php");
    exit;
}

$database = new Database();
$db = $database->getConnection();
$part = new Part($db);

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add') {
    
    $part->category = $_POST['category'];
    $part->part_name = $_POST['part_name'];
    $part->description = $_POST['description'];
    $part->unit_price = $_POST['unit_price'];
    $part->quantity_on_hand = $_POST['quantity_on_hand'];
    $part->low_stock_threshold = $_POST['low_stock_threshold'];
    $part->supplier_reference = $_POST['supplier_reference'];

    if ($part->create()) {
        $_SESSION['success_message'] = "Auto part registered successfully.";
    } else {
        $_SESSION['error_message'] = "Failed to register auto part. Please check your inputs.";
    }
    
    header("Location: ../views/inventory.php");
    exit;
}
?>