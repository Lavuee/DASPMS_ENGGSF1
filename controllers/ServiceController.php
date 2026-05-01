<?php
session_start();

require_once '../config/Database.php';
require_once '../models/Service.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] === 'Customer' || $_SESSION['role'] === 'Head Mechanic') {
    header("Location: ../views/login.php");
    exit;
}

function uploadServiceImage($fileInputName, $existingImage = '') {
    if (
        !isset($_FILES[$fileInputName]) ||
        $_FILES[$fileInputName]['error'] === UPLOAD_ERR_NO_FILE
    ) {
        return $existingImage;
    }

    if ($_FILES[$fileInputName]['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Image upload failed.");
    }

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
    $originalName = $_FILES[$fileInputName]['name'];
    $tmpName = $_FILES[$fileInputName]['tmp_name'];
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if (!in_array($extension, $allowedExtensions)) {
        throw new Exception("Invalid image type. Only JPG, PNG, and WEBP are allowed.");
    }

    $newFileName = 'service-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
    $uploadDir = '../assets/images/services/';
    $destination = $uploadDir . $newFileName;

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    if (!move_uploaded_file($tmpName, $destination)) {
        throw new Exception("Unable to save uploaded image.");
    }

    return $newFileName;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'])) {
    $database = new Database();
    $db = $database->getConnection();
    $service = new Service($db);

    try {
        if ($_POST['action'] === 'add') {
            $service->service_name = trim($_POST['service_name']);
            $service->category = trim($_POST['category']);
            $service->base_price = $_POST['base_price'];
            $service->warranty_days = $_POST['warranty_days'] ?? 0;
            $service->description = trim($_POST['description']);
            $service->full_description = trim($_POST['full_description']);
            $service->features = trim($_POST['features']);
            $service->requires_down_payment = isset($_POST['requires_down_payment']) ? 1 : 0;
            $service->image = uploadServiceImage('image');

            if ($service->create()) {
                $_SESSION['success_message'] = "Service added successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to add service.";
            }
        }

        if ($_POST['action'] === 'edit') {
            $service->service_id = $_POST['service_id'];
            $service->service_name = trim($_POST['service_name']);
            $service->category = trim($_POST['category']);
            $service->base_price = $_POST['base_price'];
            $service->warranty_days = $_POST['warranty_days'] ?? 0;
            $service->description = trim($_POST['description']);
            $service->full_description = trim($_POST['full_description']);
            $service->features = trim($_POST['features']);
            $service->requires_down_payment = isset($_POST['requires_down_payment']) ? 1 : 0;
            $service->image = uploadServiceImage('image', $_POST['existing_image'] ?? '');

            if ($service->update()) {
                $_SESSION['success_message'] = "Service updated successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to update service.";
            }
        }

        if ($_POST['action'] === 'delete') {
            $service->service_id = $_POST['service_id'];

            if ($service->deactivate()) {
                $_SESSION['success_message'] = "Service deactivated successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to deactivate service.";
            }
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }

    header("Location: ../views/services.php");
    exit;
}

header("Location: ../views/services.php");
exit;
?>