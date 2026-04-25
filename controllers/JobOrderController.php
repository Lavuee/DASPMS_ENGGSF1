<?php
session_start();
require_once '../config/Database.php';

if (!isset($_SESSION['logged_in'])) {
    header("Location: ../views/login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $db = (new Database())->getConnection();

    try {
        if ($_POST['action'] == 'create') {
            $vehicle_id = $_POST['vehicle_id'];
            $description = $_POST['description'];
            $estimated_cost = $_POST['estimated_cost'];
            $created_by = $_SESSION['user_id']; 

            $stmtC = $db->prepare("SELECT customer_id FROM vehicle WHERE vehicle_id = ?");
            $stmtC->execute([$vehicle_id]);
            $customer_id = $stmtC->fetchColumn();

            $jo_number = 'JO-' . strtoupper(substr(uniqid(), -5));

            $query = "INSERT INTO job_order (job_order_number, customer_id, vehicle_id, description, estimated_cost, status, created_by, date_created) 
                      VALUES (:jo_num, :cid, :vid, :desc, :cost, 'Pending', :uid, NOW())";
            
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':jo_num' => $jo_number,
                ':cid' => $customer_id,
                ':vid' => $vehicle_id,
                ':desc' => $description,
                ':cost' => $estimated_cost,
                ':uid' => $created_by
            ]);

            $_SESSION['success_message'] = "Job Order $jo_number created successfully!";
            header("Location: ../views/job_orders.php");
            exit;
        }


        elseif ($_POST['action'] == 'update_status') {
            $job_order_id = $_POST['job_order_id'];
            $status = $_POST['status'];

            $stmt = $db->prepare("UPDATE job_order SET status = ? WHERE job_order_id = ?");
            $stmt->execute([$status, $job_order_id]);

            $_SESSION['success_message'] = "Job Order status successfully updated to '$status'.";
            header("Location: ../views/job_orders.php");
            exit;
        }

    } catch (Exception $e) {
        $_SESSION['error_message'] = "System Error: " . $e->getMessage();
        header("Location: ../views/job_orders.php");
        exit;
    }
}
?>