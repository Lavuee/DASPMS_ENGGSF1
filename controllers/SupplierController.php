<?php
session_start();
require_once '../config/Database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] === 'Customer' || $_SESSION['role'] === 'Head Mechanic') {
    header("Location: ../views/login.php");
    exit;
}

function cleanInput($value) {
    return htmlspecialchars(strip_tags(trim((string)($value ?? ''))), ENT_QUOTES, 'UTF-8');
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'])) {
    $db = (new Database())->getConnection();

    try {
        if ($_POST['action'] === 'add') {
            $name = cleanInput($_POST['supplier_name']);
            $contact = cleanInput($_POST['contact_person']);
            $email = cleanInput($_POST['email']);
            $phone = cleanInput($_POST['phone']);
            $address = cleanInput($_POST['address']);

            if (empty($name) || empty($email)) {
                throw new Exception("Supplier Name and Email are required.");
            }

            $stmt = $db->prepare("INSERT INTO supplier (supplier_name, contact_person, email, phone, address, is_active) VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->execute([$name, $contact, $email, $phone, $address]);

            $_SESSION['success_message'] = "Supplier added successfully.";
        } 
        
        elseif ($_POST['action'] === 'edit') {
            $id = intval($_POST['supplier_id']);
            $name = cleanInput($_POST['supplier_name']);
            $contact = cleanInput($_POST['contact_person']);
            $email = cleanInput($_POST['email']);
            $phone = cleanInput($_POST['phone']);
            $address = cleanInput($_POST['address']);

            $stmt = $db->prepare("UPDATE supplier SET supplier_name = ?, contact_person = ?, email = ?, phone = ?, address = ? WHERE supplier_id = ?");
            $stmt->execute([$name, $contact, $email, $phone, $address, $id]);

            $_SESSION['success_message'] = "Supplier updated successfully.";
        }

        elseif ($_POST['action'] === 'archive') {
            $id = intval($_POST['supplier_id']);
            $stmt = $db->prepare("UPDATE supplier SET is_active = 0 WHERE supplier_id = ?");
            $stmt->execute([$id]);
            $_SESSION['success_message'] = "Supplier archived.";
        }

    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
}

header("Location: ../views/suppliers.php");
exit;
?>