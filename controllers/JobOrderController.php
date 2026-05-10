<?php
session_start();

require_once '../config/Database.php';
require_once '../models/JobOrder.php';

if (!isset($_SESSION['logged_in'])) {
    header("Location: ../views/login.php");
    exit;
}

$db = (new Database())->getConnection();
$jobOrder = new JobOrder($db);

$userRole = $_SESSION['role'] ?? '';
$userId = intval($_SESSION['user_id'] ?? 0);

$canCreateAndEditJobOrders = in_array($userRole, ['Owner', 'Cashier'], true);
$canUpdateRepairProgress = in_array($userRole, ['Owner', 'Cashier', 'Head Mechanic'], true);
$canCompleteAndCancelJobOrders = in_array($userRole, ['Owner', 'Cashier'], true);

function redirectJobOrders() {
    header("Location: ../views/job_orders.php");
    exit;
}

function validateStatusTransition($currentStatus, $newStatus, $userRole) {
    $allowedTransitions = [
        'Pending' => ['In Progress', 'Cancelled'],
        'In Progress' => ['Ready for Pickup', 'Completed', 'Cancelled'],
        'Ready for Pickup' => ['Completed', 'Cancelled'],
        'Completed' => [],
        'Cancelled' => []
    ];

    if (!isset($allowedTransitions[$currentStatus])) {
        throw new Exception("Invalid current job order status.");
    }

    if (!in_array($newStatus, $allowedTransitions[$currentStatus], true)) {
        throw new Exception("Invalid status transition from {$currentStatus} to {$newStatus}.");
    }

    if ($userRole === 'Head Mechanic') {
        $mechanicAllowedTransitions = [
            'Pending' => ['In Progress'],
            'In Progress' => ['Ready for Pickup']
        ];

        if (
            !isset($mechanicAllowedTransitions[$currentStatus]) ||
            !in_array($newStatus, $mechanicAllowedTransitions[$currentStatus], true)
        ) {
            throw new Exception("Head Mechanic can only update repair progress up to Ready for Pickup.");
        }
    }

    return true;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'])) {

    try {

        if ($_POST['action'] === 'create') {

            if (!$canCreateAndEditJobOrders) {
                throw new Exception("Only Owner or Cashier can create job orders.");
            }

            $vehicle_id = intval($_POST['vehicle_id']);
            $description = trim($_POST['description']);
            $created_by = $userId;
            $estimated_cost = floatval($_POST['estimated_cost'] ?? 0);
            $assigned_mechanic_id = !empty($_POST['assigned_mechanic_id']) ? intval($_POST['assigned_mechanic_id']) : null;
            $request_source = trim($_POST['request_source'] ?? 'Walk-in');
            $expected_completion_date = trim($_POST['expected_completion_date'] ?? '');

            if (!in_array($request_source, ['Walk-in', 'Online'], true)) {
                $request_source = 'Walk-in';
            }

            if ($vehicle_id <= 0) {
                throw new Exception("Please select a valid vehicle.");
            }

            if ($description === '') {
                throw new Exception("Job description is required.");
            }

            if ($estimated_cost <= 0) {
                throw new Exception("Estimated cost must be greater than zero.");
            }

            if ($expected_completion_date !== '' && $expected_completion_date < date('Y-m-d')) {
                throw new Exception("Target completion date cannot be in the past.");
            }

            if ($assigned_mechanic_id !== null) {
                $stmtMechanic = $db->prepare("
                    SELECT user_id
                    FROM user
                    WHERE user_id = ?
                      AND role = 'Head Mechanic'
                      AND is_active = 1
                    LIMIT 1
                ");
                $stmtMechanic->execute([$assigned_mechanic_id]);

                if (!$stmtMechanic->fetch()) {
                    throw new Exception("Selected mechanic is invalid or inactive.");
                }
            }

            $stmtC = $db->prepare("
                SELECT customer_id 
                FROM vehicle 
                WHERE vehicle_id = ?
                LIMIT 1
            ");
            $stmtC->execute([$vehicle_id]);
            $customer_id = $stmtC->fetchColumn();

            if (!$customer_id) {
                throw new Exception("Selected vehicle has no linked customer.");
            }

            $jo_number = 'JO-' . strtoupper(substr(uniqid(), -5));

            $data = [
                'vehicle_id' => $vehicle_id,
                'customer_id' => $customer_id,
                'created_by' => $created_by,
                'assigned_mechanic_id' => $assigned_mechanic_id,
                'job_order_number' => $jo_number,
                'description' => $description,
                'request_source' => $request_source,
                'estimated_cost' => $estimated_cost,
                'requires_down_payment' => 0,
                'down_payment_amount' => 0,
                'expected_completion_date' => $expected_completion_date !== '' ? $expected_completion_date : null
            ];

            $services = [];
            $parts = [];

            if ($jobOrder->createWithDetails($data, $services, $parts)) {
                $_SESSION['success_message'] = "Job Order {$jo_number} created successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to create Job Order.";
            }

            redirectJobOrders();
        }

        elseif ($_POST['action'] === 'update_details') {

            if (!$canCreateAndEditJobOrders) {
                throw new Exception("Only Owner or Cashier can edit job order details.");
            }

            $job_order_id = intval($_POST['job_order_id']);
            $description = trim($_POST['description']);
            $estimated_cost = floatval($_POST['estimated_cost'] ?? 0);
            $requires_down_payment = isset($_POST['requires_down_payment']) ? 1 : 0;
            $down_payment_amount = floatval($_POST['down_payment_amount'] ?? 0);

            if ($job_order_id <= 0) {
                throw new Exception("Invalid job order record.");
            }

            if ($description === '') {
                throw new Exception("Job description is required.");
            }

            if ($estimated_cost <= 0) {
                throw new Exception("Estimated cost must be greater than zero.");
            }

            if (!$requires_down_payment) {
                $down_payment_amount = 0;
            }

            if ($requires_down_payment && $down_payment_amount <= 0) {
                throw new Exception("Down payment amount must be greater than zero.");
            }

            if ($down_payment_amount > $estimated_cost) {
                throw new Exception("Down payment cannot be greater than estimated cost.");
            }

            $stmtCurrent = $db->prepare("
                SELECT status
                FROM job_order
                WHERE job_order_id = ?
                LIMIT 1
            ");
            $stmtCurrent->execute([$job_order_id]);
            $current_status = $stmtCurrent->fetchColumn();

            if (!$current_status) {
                throw new Exception("Job order not found.");
            }

            if (!in_array($current_status, ['Pending', 'In Progress'], true)) {
                throw new Exception("Only Pending or In Progress job orders can be edited.");
            }

            if ($jobOrder->updateBasicDetails(
                $job_order_id,
                $description,
                $estimated_cost,
                $requires_down_payment,
                $down_payment_amount
            )) {
                $_SESSION['success_message'] = "Job Order details updated successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to update Job Order details.";
            }

            redirectJobOrders();
        }

        elseif ($_POST['action'] === 'add_part_used') {

            if (!$canUpdateRepairProgress) {
                throw new Exception("You are not allowed to record parts used.");
            }

            $job_order_id = intval($_POST['job_order_id'] ?? 0);
            $part_id = intval($_POST['part_id'] ?? 0);
            $quantity_used = intval($_POST['quantity_used'] ?? 0);
            $notes = trim($_POST['notes'] ?? '');

            if ($job_order_id <= 0) {
                throw new Exception("Invalid job order record.");
            }

            if ($part_id <= 0) {
                throw new Exception("Please select a valid part.");
            }

            if ($quantity_used <= 0) {
                throw new Exception("Quantity used must be greater than zero.");
            }

            if (strlen($notes) > 500) {
                throw new Exception("Part notes must not exceed 500 characters.");
            }

            if ($jobOrder->addPartUsed($job_order_id, $part_id, $quantity_used, $userId, $notes)) {
                $_SESSION['success_message'] = "Part used has been recorded and inventory stock was deducted.";
            } else {
                $_SESSION['error_message'] = "Failed to record part used.";
            }

            redirectJobOrders();
        }

        elseif ($_POST['action'] === 'update_status') {

            if (!$canUpdateRepairProgress) {
                throw new Exception("You are not allowed to update job order status.");
            }

            $job_order_id = intval($_POST['job_order_id']);
            $status = trim($_POST['status']);
            $cancellation_reason = trim($_POST['cancellation_reason'] ?? '');

            $allowed_statuses = ['Pending', 'In Progress', 'Ready for Pickup', 'Completed', 'Cancelled'];

            if ($job_order_id <= 0) {
                throw new Exception("Invalid job order record.");
            }

            if (!in_array($status, $allowed_statuses, true)) {
                throw new Exception("Invalid job order status.");
            }

            if (in_array($status, ['Completed', 'Cancelled'], true) && !$canCompleteAndCancelJobOrders) {
                throw new Exception("Only Owner or Cashier can complete or cancel job orders.");
            }

            $stmtCurrent = $db->prepare("
                SELECT status
                FROM job_order
                WHERE job_order_id = ?
                LIMIT 1
            ");
            $stmtCurrent->execute([$job_order_id]);
            $current_status = $stmtCurrent->fetchColumn();

            if (!$current_status) {
                throw new Exception("Job order not found.");
            }

            if ($current_status === 'Completed') {
                throw new Exception("This job order is already completed.");
            }

            if ($current_status === 'Cancelled') {
                throw new Exception("Cancelled job orders cannot be updated.");
            }

            validateStatusTransition($current_status, $status, $userRole);

            if ($status === 'Completed') {
                $stmt = $db->prepare("
                    UPDATE job_order
                    SET status = 'Completed',
                        date_completed = NOW(),
                        completed_by = ?
                    WHERE job_order_id = ?
                ");
                $stmt->execute([$userId, $job_order_id]);

            } elseif ($status === 'Cancelled') {
                $stmt = $db->prepare("
                    UPDATE job_order
                    SET status = 'Cancelled',
                        cancellation_reason = ?,
                        cancelled_at = NOW()
                    WHERE job_order_id = ?
                ");
                $stmt->execute([
                    $cancellation_reason !== '' ? htmlspecialchars(strip_tags($cancellation_reason)) : null,
                    $job_order_id
                ]);

            } else {
                $stmt = $db->prepare("
                    UPDATE job_order
                    SET status = ?
                    WHERE job_order_id = ?
                ");
                $stmt->execute([$status, $job_order_id]);
            }

            $_SESSION['success_message'] = "Job Order updated to '{$status}'.";
            redirectJobOrders();
        }

    } catch (Exception $e) {
        $_SESSION['error_message'] = "System Error: " . $e->getMessage();
        redirectJobOrders();
    }
}

redirectJobOrders();
?>