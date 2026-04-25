<?php
session_start();
require_once '../config/Database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] === 'Customer' || $_SESSION['role'] === 'Head Mechanic') {
    header("Location: ../views/login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $db = (new Database())->getConnection();

    try {
        if ($_POST['action'] == 'add') {
            $part_name = $_POST['part_name'];
            $category = $_POST['category'];
            $quantity = $_POST['quantity_on_hand'];
            $price = $_POST['unit_price'];
            
            $description = $_POST['description'] ?? "$category part";
            $low_stock = $_POST['low_stock_threshold'] ?? 5;
            $cost_price = $price * 0.7; 

            $query = "INSERT INTO part (part_name, category, description, quantity_on_hand, unit_price, cost_price, low_stock_threshold, is_active) 
                      VALUES (:name, :cat, :desc, :qty, :price, :cost, :low, 1)";
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':name' => $part_name,
                ':cat' => $category,
                ':desc' => $description,
                ':qty' => $quantity,
                ':price' => $price,
                ':cost' => $cost_price,
                ':low' => $low_stock
            ]);

            $_SESSION['success_message'] = "Part added to inventory successfully!";
        }

        elseif ($_POST['action'] == 'edit') {
            $part_id = $_POST['part_id'];
            $part_name = $_POST['part_name'];
            $quantity = $_POST['quantity_on_hand'];
            $price = $_POST['unit_price'];

            $query = "UPDATE part SET part_name = :name, quantity_on_hand = :qty, unit_price = :price WHERE part_id = :id";
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':name' => $part_name,
                ':qty' => $quantity,
                ':price' => $price,
                ':id' => $part_id
            ]);

            $_SESSION['success_message'] = "Part updated successfully!";
        }

        elseif ($_POST['action'] == 'delete') {
            $part_id = $_POST['part_id'];

            $query = "DELETE FROM part WHERE part_id = :id";
            $stmt = $db->prepare($query);
            $stmt->execute([':id' => $part_id]);

            $_SESSION['success_message'] = "Part deleted successfully!";
        }

    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            $_SESSION['error_message'] = "Action Denied: You cannot delete this part because it is linked to past sales or job orders.";
        } else {
            $_SESSION['error_message'] = "System Error: " . $e->getMessage();
        }
    }

    header("Location: ../views/inventory.php");
    exit;
}
?>