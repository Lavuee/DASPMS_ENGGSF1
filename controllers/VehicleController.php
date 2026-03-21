<?php
session_start();
require_once '../config/Database.php';
require_once '../models/Vehicle.php';

// Security check
if (!isset($_SESSION['logged_in'])) {
    header("Location: ../views/login.php");
    exit;
}

$database = new Database();
$db = $database->getConnection();
$vehicle = new Vehicle($db);

// Handle the "Register Vehicle" form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add') {
    
    $vehicle->customer_id = $_POST['customer_id'];
    $vehicle->plate_number = $_POST['plate_number'];
    $vehicle->make = $_POST['make'];
    $vehicle->model = $_POST['model'];
    $vehicle->year = $_POST['year'];
    $vehicle->color = $_POST['color'];
    $vehicle->notes = $_POST['notes'];

    if ($vehicle->create()) {
        $_SESSION['success_message'] = "Vehicle registered successfully.";
    } else {
        $_SESSION['error_message'] = "Failed to register vehicle. Please ensure the plate number is unique.";
    }
    
    // Redirect back to the vehicles page
    header("Location: ../views/vehicles.php");
    exit;
}
?>