<?php
session_start();
require_once '../config/Database.php';
require_once '../models/Vehicle.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $db = (new Database())->getConnection();
    $vehicle = new Vehicle($db);

    if ($_POST['action'] == 'add') {
        $vehicle->customer_id = $_POST['customer_id'];
        $vehicle->plate_number = $_POST['plate_number'];
        $vehicle->make = $_POST['make'];
        $vehicle->model = $_POST['model'];
        $vehicle->year = $_POST['year'];
        $vehicle->color = $_POST['color'];
        
        // Safely fallback if notes aren't provided to stop the undefined key warning
        $vehicle->notes = $_POST['notes'] ?? ''; 

        try {
            if ($vehicle->create()) {
                $_SESSION['success_message'] = "Vehicle registered successfully!";
            } else {
                $_SESSION['error_message'] = "Failed to register vehicle.";
            }
        } catch (PDOException $e) {
            // Gracefully catch the foreign key violation instead of fatal crashing
            if ($e->getCode() == 23000) {
                $_SESSION['error_message'] = "Database Error: Please ensure you selected a valid customer from the dropdown.";
            } else {
                $_SESSION['error_message'] = "System Error: Something went wrong saving to the database.";
            }
        }
        
        header("Location: ../views/vehicles.php");
        exit;
    }
}
?>