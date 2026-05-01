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

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'])) {

    try {

        if ($_POST['action'] === 'create') {

            $vehicle_id = intval($_POST['vehicle_id']);
            $description = trim($_POST['description']);
            $created_by = intval($_SESSION['user_id']);
            $estimated_cost = floatval($_POST['estimated_cost'] ?? 0);

            if ($vehicle_id <= 0) {
                throw new Exception("Please select a valid vehicle.");
            }

            if ($description === '') {
                throw new Exception("Job description is required.");
            }

            if ($estimated_cost <= 0) {
                throw new Exception("Estimated cost must be greater than zero.");
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
                'job_order_number' => $jo_number,
                'description' => $description,
                'estimated_cost' => $estimated_cost,
                'requires_down_payment' => 0
            ];

            /*
                Current DASPMS Job Order flow is service-based only.
                Parts inventory is handled separately by POS and Web Orders.
            */
            $services = [];
            $parts = [];

            if ($jobOrder->createWithDetails($data, $services, $parts)) {
                $_SESSION['success_message'] = "Job Order {$jo_number} created successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to create Job Order.";
            }

            header("Location: ../views/job_orders.php");
            exit;
        }

        elseif ($_POST['action'] === 'update_details') {

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

            if (!in_array($current_status, ['Pending', 'In Progress'])) {
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

            header("Location: ../views/job_orders.php");
            exit;
        }

        elseif ($_POST['action'] === 'update_status') {

            $job_order_id = intval($_POST['job_order_id']);
            $status = trim($_POST['status']);

            $allowed_statuses = ['Pending', 'In Progress', 'Completed', 'Cancelled'];

            if (!in_array($status, $allowed_statuses)) {
                throw new Exception("Invalid job order status.");
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
                $_SESSION['error_message'] = "This job order is already completed.";
                header("Location: ../views/job_orders.php");
                exit;
            }

            if ($current_status === 'Cancelled') {
                $_SESSION['error_message'] = "Cancelled job orders cannot be updated.";
                header("Location: ../views/job_orders.php");
                exit;
            }

            if ($status === 'Completed') {
                $stmt = $db->prepare("
                    UPDATE job_order
                    SET status = 'Completed',
                        date_completed = NOW()
                    WHERE job_order_id = ?
                ");
                $stmt->execute([$job_order_id]);
            } else {
                $stmt = $db->prepare("
                    UPDATE job_order
                    SET status = ?
                    WHERE job_order_id = ?
                ");
                $stmt->execute([$status, $job_order_id]);
            }

            $_SESSION['success_message'] = "Job Order updated to '{$status}'.";
            header("Location: ../views/job_orders.php");
            exit;
        }

    } catch (Exception $e) {
        $_SESSION['error_message'] = "System Error: " . $e->getMessage();
        header("Location: ../views/job_orders.php");
        exit;
    }
}

header("Location: ../views/job_orders.php");
exit;
?>