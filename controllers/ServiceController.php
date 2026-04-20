<?php
session_start();
require_once '../config/Database.php';
require_once '../models/Service.php';

if (!isset($_SESSION['logged_in'])) {
    header("Location: ../views/login.php");
    exit;
}

$database = new Database();
$db = $database->getConnection();
$service = new Service($db);

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add') {
    
    $service->service_name = $_POST['service_name'];
    $service->category = $_POST['category'];
    $service->base_price = $_POST['base_price'];
    $service->warranty_days = $_POST['warranty_days'];
    $service->description = $_POST['description'];
    
    $service->requires_down_payment = isset($_POST['requires_down_payment']) ? 1 : 0;

    if ($service->create()) {
        $_SESSION['success_message'] = "Repair service added to the catalog.";
    } else {
        $_SESSION['error_message'] = "Failed to add the service. Please check your inputs.";
    }
    
    header("Location: ../views/services.php");
    exit;
}
?>