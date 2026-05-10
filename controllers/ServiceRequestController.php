<?php
session_start();

require_once '../config/Database.php';
require_once '../models/ServiceRequest.php';

if (!isset($_SESSION['logged_in'])) {
    header("Location: ../views/login.php");
    exit;
}

$db = (new Database())->getConnection();
$serviceRequest = new ServiceRequest($db);

$userRole = $_SESSION['role'] ?? '';
$userId = intval($_SESSION['user_id'] ?? 0);

$isCustomer = $userRole === 'Customer';
$canReviewRequests = in_array($userRole, ['Owner', 'Cashier'], true);

function redirectCustomerServiceRequests() {
    header("Location: ../views/customer_service_requests.php");
    exit;
}

function redirectServiceRequests($status = 'Pending') {
    header("Location: ../views/service_requests.php?status=" . urlencode($status));
    exit;
}

function cleanInput($value) {
    return trim($value ?? '');
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'])) {
    $action = $_POST['action'];

    try {
        if ($action === 'submit_customer_request') {
            if (!$isCustomer) {
                throw new Exception("Only customer accounts can submit service requests.");
            }

            $customerId = $serviceRequest->getCustomerIdByUserId($userId);

            if (!$customerId) {
                throw new Exception("Customer profile not found.");
            }

            $vehicleId = intval($_POST['vehicle_id'] ?? 0);
            $serviceId = intval($_POST['service_id'] ?? 0);
            $concernDescription = cleanInput($_POST['concern_description'] ?? '');
            $preferredDate = cleanInput($_POST['preferred_appointment_date'] ?? '');
            $preferredTime = cleanInput($_POST['preferred_appointment_time'] ?? '');

            if ($vehicleId <= 0) {
                throw new Exception("Please select a vehicle.");
            }

            if ($serviceId <= 0) {
                throw new Exception("Please select a service.");
            }

            if ($concernDescription === '') {
                throw new Exception("Please describe your service concern.");
            }

            if ($preferredDate === '') {
                throw new Exception("Please select your preferred appointment date.");
            }

            $today = date('Y-m-d');

            if ($preferredDate < $today) {
                throw new Exception("Preferred appointment date cannot be in the past.");
            }

            $requestNumber = $serviceRequest->createCustomerRequest([
                'customer_id' => $customerId,
                'vehicle_id' => $vehicleId,
                'service_id' => $serviceId,
                'concern_description' => $concernDescription,
                'preferred_appointment_date' => $preferredDate,
                'preferred_appointment_time' => $preferredTime !== '' ? $preferredTime : null
            ]);

            if ($requestNumber) {
                $_SESSION['success_message'] = "Service request {$requestNumber} submitted successfully. Please wait for shop approval.";
            } else {
                $_SESSION['error_message'] = "Failed to submit service request.";
            }

            redirectCustomerServiceRequests();
        }

        if ($action === 'cancel_customer_request') {
            if (!$isCustomer) {
                throw new Exception("Only customer accounts can cancel their service requests.");
            }

            $customerId = $serviceRequest->getCustomerIdByUserId($userId);

            if (!$customerId) {
                throw new Exception("Customer profile not found.");
            }

            $requestId = intval($_POST['service_request_id'] ?? 0);

            if ($requestId <= 0) {
                throw new Exception("Invalid service request.");
            }

            if ($serviceRequest->cancelByCustomer($requestId, $customerId)) {
                $_SESSION['success_message'] = "Service request cancelled successfully.";
            } else {
                $_SESSION['error_message'] = "Only pending service requests can be cancelled.";
            }

            redirectCustomerServiceRequests();
        }

        if ($action === 'approve_convert') {
            if (!$canReviewRequests) {
                throw new Exception("Only Owner or Cashier can approve service requests.");
            }

            $requestId = intval($_POST['service_request_id'] ?? 0);
            $estimatedCost = floatval($_POST['estimated_cost'] ?? 0);
            $assignedMechanicId = intval($_POST['assigned_mechanic_id'] ?? 0);

            /*
                Optional only.
                This is NOT required based on Ma'am's notes.
                The real required appointment field is the customer's preferred appointment date.
            */
            $expectedCompletionDate = cleanInput($_POST['expected_completion_date'] ?? '');

            if ($requestId <= 0) {
                throw new Exception("Invalid service request.");
            }

            if ($estimatedCost <= 0) {
                throw new Exception("Estimated cost must be greater than zero.");
            }

            if ($assignedMechanicId <= 0) {
                throw new Exception("Please assign a mechanic.");
            }

            if ($expectedCompletionDate !== '' && $expectedCompletionDate < date('Y-m-d')) {
                throw new Exception("Target completion date cannot be in the past.");
            }

            $result = $serviceRequest->approveAndConvert(
                $requestId,
                $estimatedCost,
                $assignedMechanicId,
                $userId,
                $expectedCompletionDate !== '' ? $expectedCompletionDate : null
            );

            $_SESSION['success_message'] = "Service request approved and converted to Job Order " . $result['job_order_number'] . ".";
            redirectServiceRequests('Converted');
        }

        if ($action === 'reject_request') {
            if (!$canReviewRequests) {
                throw new Exception("Only Owner or Cashier can reject service requests.");
            }

            $requestId = intval($_POST['service_request_id'] ?? 0);
            $reason = cleanInput($_POST['rejection_reason'] ?? '');

            if ($requestId <= 0) {
                throw new Exception("Invalid service request.");
            }

            if ($reason === '') {
                throw new Exception("Please provide a rejection reason.");
            }

            if ($serviceRequest->reject($requestId, $reason, $userId)) {
                $_SESSION['success_message'] = "Service request rejected successfully.";
            } else {
                $_SESSION['error_message'] = "Only pending service requests can be rejected.";
            }

            redirectServiceRequests('Rejected');
        }

    } catch (Exception $e) {
        $_SESSION['error_message'] = "System Error: " . $e->getMessage();

        if ($isCustomer) {
            redirectCustomerServiceRequests();
        }

        redirectServiceRequests('Pending');
    }
}

if ($isCustomer) {
    redirectCustomerServiceRequests();
}

redirectServiceRequests('Pending');
?>