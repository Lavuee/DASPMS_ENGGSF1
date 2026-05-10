<?php
session_start();

require_once '../config/Database.php';
require_once '../models/Vehicle.php';

if (!isset($_SESSION['logged_in'])) {
    header("Location: ../views/login.php");
    exit;
}

$db = (new Database())->getConnection();
$vehicle = new Vehicle($db);

$userRole = $_SESSION['role'] ?? '';
$isOwner = $userRole === 'Owner';
$isStaff = in_array($userRole, ['Owner', 'Cashier'], true);
$isCustomer = $userRole === 'Customer';

function cleanInput($value) {
    return trim($value ?? '');
}

function redirectToVehicles($statusFilter = null) {
    $url = "../views/vehicles.php";

    if ($statusFilter !== null && $statusFilter !== '') {
        $url .= "?status=" . urlencode($statusFilter);
    }

    header("Location: " . $url);
    exit;
}

function redirectToCustomerDashboard() {
    header("Location: ../views/dashboard_customer.php#myVehiclesSection");
    exit;
}

function getLoggedInCustomerId(PDO $db) {
    $stmt = $db->prepare("SELECT customer_id FROM customer WHERE user_id = ? LIMIT 1");
    $stmt->execute([intval($_SESSION['user_id'] ?? 0)]);
    $customerId = $stmt->fetchColumn();

    if (!$customerId) {
        throw new Exception("Customer profile not found for this account.");
    }

    return intval($customerId);
}

function validateVehicleFields($customer_id, $plate_number, $make, $model, $year, $color) {
    if (intval($customer_id) <= 0) {
        throw new Exception("Please select a valid customer.");
    }

    if ($plate_number === '' || $make === '' || $model === '' || $year === '' || $color === '') {
        throw new Exception("Plate number, make, model, year, and color are required.");
    }

    if (!preg_match('/^[A-Z0-9 -]{3,20}$/i', $plate_number)) {
        throw new Exception("Plate number must be 3 to 20 characters and may contain letters, numbers, spaces, or hyphens only.");
    }

    if (!preg_match('/^\d{4}$/', $year)) {
        throw new Exception("Year must be a 4-digit year.");
    }

    $currentYear = intval(date('Y')) + 1;
    if (intval($year) < 1900 || intval($year) > $currentYear) {
        throw new Exception("Please enter a realistic vehicle year.");
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'])) {
    $action = $_POST['action'];
    $currentFilter = cleanInput($_POST['current_filter'] ?? 'Active');

    try {
        if ($action === 'add_customer_vehicle') {
            if (!$isCustomer) {
                throw new Exception("Only customer accounts can use this action.");
            }

            $customer_id = getLoggedInCustomerId($db);
            $plate_number = cleanInput($_POST['plate_number'] ?? '');
            $make = cleanInput($_POST['make'] ?? '');
            $model = cleanInput($_POST['model'] ?? '');
            $year = cleanInput($_POST['year'] ?? '');
            $color = cleanInput($_POST['color'] ?? '');
            $notes = cleanInput($_POST['notes'] ?? '');

            validateVehicleFields($customer_id, $plate_number, $make, $model, $year, $color);

            $vehicle->customer_id = $customer_id;
            $vehicle->plate_number = $plate_number;
            $vehicle->make = $make;
            $vehicle->model = $model;
            $vehicle->year = $year;
            $vehicle->color = $color;
            $vehicle->notes = $notes;

            if ($vehicle->create()) {
                $_SESSION['success_message'] = "Vehicle added to your profile successfully.";
            } else {
                $_SESSION['error_message'] = "A vehicle with this plate number already exists.";
            }

            redirectToCustomerDashboard();
        }

        if ($action === 'update_customer_vehicle') {
            if (!$isCustomer) {
                throw new Exception("Only customer accounts can use this action.");
            }

            $customer_id = getLoggedInCustomerId($db);
            $vehicle_id = intval($_POST['vehicle_id'] ?? 0);
            $plate_number = cleanInput($_POST['plate_number'] ?? '');
            $make = cleanInput($_POST['make'] ?? '');
            $model = cleanInput($_POST['model'] ?? '');
            $year = cleanInput($_POST['year'] ?? '');
            $color = cleanInput($_POST['color'] ?? '');
            $notes = cleanInput($_POST['notes'] ?? '');

            if ($vehicle_id <= 0) {
                throw new Exception("Invalid vehicle record.");
            }

            validateVehicleFields($customer_id, $plate_number, $make, $model, $year, $color);

            $vehicle->vehicle_id = $vehicle_id;
            $vehicle->plate_number = $plate_number;
            $vehicle->make = $make;
            $vehicle->model = $model;
            $vehicle->year = $year;
            $vehicle->color = $color;
            $vehicle->notes = $notes;

            if ($vehicle->updateCustomerOwnedVehicle($customer_id)) {
                $_SESSION['success_message'] = "Vehicle details updated successfully.";
            } else {
                $_SESSION['error_message'] = "Unable to update this vehicle. The plate number may already exist or the record does not belong to your account.";
            }

            redirectToCustomerDashboard();
        }

        if ($action === 'add' || $action === 'add_vehicle') {
            if (!$isStaff) {
                throw new Exception("Only the Owner or Cashier can register walk-in/customer vehicles from this page.");
            }

            $customer_id = intval($_POST['customer_id'] ?? 0);
            $plate_number = cleanInput($_POST['plate_number'] ?? '');
            $make = cleanInput($_POST['make'] ?? '');
            $model = cleanInput($_POST['model'] ?? '');
            $year = cleanInput($_POST['year'] ?? '');
            $color = cleanInput($_POST['color'] ?? '');
            $notes = cleanInput($_POST['notes'] ?? '');

            validateVehicleFields($customer_id, $plate_number, $make, $model, $year, $color);

            $vehicle->customer_id = $customer_id;
            $vehicle->plate_number = $plate_number;
            $vehicle->make = $make;
            $vehicle->model = $model;
            $vehicle->year = $year;
            $vehicle->color = $color;
            $vehicle->notes = $notes;

            if ($vehicle->create()) {
                $_SESSION['success_message'] = "Vehicle registered successfully.";
            } else {
                $_SESSION['error_message'] = "A vehicle with this plate number already exists.";
            }

            redirectToVehicles('Active');
        }

        if ($action === 'update_vehicle') {
            if (!$isStaff) {
                throw new Exception("Only the Owner or Cashier can update vehicle records from this page.");
            }

            $vehicle_id = intval($_POST['vehicle_id'] ?? 0);
            $customer_id = intval($_POST['customer_id'] ?? 0);
            $plate_number = cleanInput($_POST['plate_number'] ?? '');
            $make = cleanInput($_POST['make'] ?? '');
            $model = cleanInput($_POST['model'] ?? '');
            $year = cleanInput($_POST['year'] ?? '');
            $color = cleanInput($_POST['color'] ?? '');
            $notes = cleanInput($_POST['notes'] ?? '');

            if ($vehicle_id <= 0) {
                throw new Exception("Invalid vehicle record.");
            }

            validateVehicleFields($customer_id, $plate_number, $make, $model, $year, $color);

            $vehicle->vehicle_id = $vehicle_id;
            $vehicle->customer_id = $customer_id;
            $vehicle->plate_number = $plate_number;
            $vehicle->make = $make;
            $vehicle->model = $model;
            $vehicle->year = $year;
            $vehicle->color = $color;
            $vehicle->notes = $notes;

            if ($vehicle->update()) {
                $_SESSION['success_message'] = "Vehicle record updated successfully.";
            } else {
                $_SESSION['error_message'] = "A vehicle with this plate number already exists.";
            }

            redirectToVehicles($currentFilter);
        }

        if ($action === 'deactivate_vehicle') {
            if (!$isOwner) {
                throw new Exception("Only the Owner can deactivate vehicle records.");
            }

            $vehicle->vehicle_id = intval($_POST['vehicle_id'] ?? 0);

            if ($vehicle->vehicle_id <= 0) {
                throw new Exception("Invalid vehicle record.");
            }

            if ($vehicle->deactivate()) {
                $_SESSION['success_message'] = "Vehicle record deactivated successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to deactivate vehicle record.";
            }

            redirectToVehicles($currentFilter);
        }

        if ($action === 'reactivate_vehicle') {
            if (!$isOwner) {
                throw new Exception("Only the Owner can reactivate vehicle records.");
            }

            $vehicle->vehicle_id = intval($_POST['vehicle_id'] ?? 0);

            if ($vehicle->vehicle_id <= 0) {
                throw new Exception("Invalid vehicle record.");
            }

            if ($vehicle->reactivate()) {
                $_SESSION['success_message'] = "Vehicle record reactivated successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to reactivate vehicle record.";
            }

            redirectToVehicles($currentFilter);
        }

        if ($action === 'archive_vehicle') {
            if (!$isOwner) {
                throw new Exception("Only the Owner can archive vehicle records.");
            }

            $vehicle->vehicle_id = intval($_POST['vehicle_id'] ?? 0);

            if ($vehicle->vehicle_id <= 0) {
                throw new Exception("Invalid vehicle record.");
            }

            if ($vehicle->archive()) {
                $_SESSION['success_message'] = "Vehicle record archived successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to archive vehicle record.";
            }

            redirectToVehicles($currentFilter);
        }

    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Database Error: Plate number may already exist. Please check the selected customer and plate number.";
        $isCustomer ? redirectToCustomerDashboard() : redirectToVehicles($currentFilter);
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
        $isCustomer ? redirectToCustomerDashboard() : redirectToVehicles($currentFilter);
    }
}

$isCustomer ? redirectToCustomerDashboard() : redirectToVehicles('Active');
?>