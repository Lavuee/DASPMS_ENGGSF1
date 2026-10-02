<?php
require_once __DIR__ . '/../includes/auth.php';

require_once '../config/Database.php';
require_once '../includes/upload.php';
require_once '../models/Service.php';

requirePermission('services.manage', '../views/login.php');

// Copies the submitted form fields onto the Service model.
function fillServiceFromPost(Service $service) {
    $service->service_name = trim($_POST['service_name'] ?? '');
    $service->category = trim($_POST['category'] ?? '');
    $service->base_price = floatval($_POST['base_price'] ?? 0);
    $service->warranty_days = intval($_POST['warranty_days'] ?? 0);
    $service->description = trim($_POST['description'] ?? '');
    $service->full_description = trim($_POST['full_description'] ?? '');
    $service->features = trim($_POST['features'] ?? '');
    $service->requires_down_payment = isset($_POST['requires_down_payment']) ? 1 : 0;

    if ($service->service_name === '' || $service->category === '') {
        throw new Exception("Service name and category are required.");
    }

    if ($service->base_price < 0 || $service->warranty_days < 0) {
        throw new Exception("Price and warranty days cannot be negative.");
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'])) {
    $db = (new Database())->getConnection();
    $service = new Service($db);

    try {
        if ($_POST['action'] === 'add') {
            fillServiceFromPost($service);
            $service->image = uploadImage('image', '../assets/images/services/', 'service');

            if ($service->create()) {
                $_SESSION['success_message'] = "Service added successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to add service.";
            }
        }

        if ($_POST['action'] === 'edit') {
            $service->service_id = intval($_POST['service_id'] ?? 0);
            fillServiceFromPost($service);
            $service->image = uploadImage('image', '../assets/images/services/', 'service', basename($_POST['existing_image'] ?? ''));

            if ($service->update()) {
                $_SESSION['success_message'] = "Service updated successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to update service.";
            }
        }

        if ($_POST['action'] === 'delete') {
            $service->service_id = intval($_POST['service_id'] ?? 0);

            if ($service->deactivate()) {
                $_SESSION['success_message'] = "Service deactivated successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to deactivate service.";
            }
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
}

header("Location: ../views/services.php");
exit;
