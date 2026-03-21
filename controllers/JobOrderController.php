<?php
session_start();
require_once '../config/Database.php';
require_once '../models/JobOrder.php';

if (!isset($_SESSION['logged_in'])) {
    header("Location: ../views/login.php");
    exit;
}

$database = new Database();
$db = $database->getConnection();
$jobOrder = new JobOrder($db);

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'create') {
    
    // Generate a unique Job Order Number
    $joNumber = 'JO-' . date('Ymd') . '-' . rand(1000, 9999);
    
    // Prepare arrays for services and parts
    $services = [];
    $parts = [];
    $estimated_cost = 0;
    $requires_down_payment = isset($_POST['requires_down_payment']) ? 1 : 0;

    // Process Services
    if (isset($_POST['service_ids'])) {
        for ($i = 0; $i < count($_POST['service_ids']); $i++) {
            if (!empty($_POST['service_ids'][$i])) {
                $price = floatval($_POST['service_prices'][$i]);
                $services[] = [
                    'id' => $_POST['service_ids'][$i],
                    'price' => $price
                ];
                $estimated_cost += $price;
            }
        }
    }

    // Process Parts
    if (isset($_POST['part_ids'])) {
        for ($i = 0; $i < count($_POST['part_ids']); $i++) {
            if (!empty($_POST['part_ids'][$i])) {
                $qty = intval($_POST['part_qtys'][$i]);
                $price = floatval($_POST['part_prices'][$i]);
                $parts[] = [
                    'id' => $_POST['part_ids'][$i],
                    'qty' => $qty,
                    'price' => $price
                ];
                $estimated_cost += ($qty * $price);
            }
        }
    }

    $data = [
        'vehicle_id' => $_POST['vehicle_id'],
        'customer_id' => $_POST['customer_id'],
        'created_by' => $_SESSION['user_id'],
        'job_order_number' => $joNumber,
        'description' => $_POST['description'],
        'estimated_cost' => $estimated_cost,
        'requires_down_payment' => $requires_down_payment
    ];

    if ($jobOrder->createWithDetails($data, $services, $parts)) {
        $_SESSION['success_message'] = "Job Order {$joNumber} created successfully.";
    } else {
        $_SESSION['error_message'] = "Failed to create Job Order. Please try again.";
    }
    
    header("Location: ../views/job_orders.php");
    exit;
}
?>