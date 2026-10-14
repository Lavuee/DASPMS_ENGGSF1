<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/activity_log.php';
require_once '../config/Database.php';

requirePermission('suppliers.manage', '../views/login.php');

// Values are stored as plain text and escaped when displayed.
function cleanInput($value) {
    return strip_tags(trim((string)($value ?? '')));
}

// Reads and validates the supplier form fields.
function readSupplierForm() {
    $supplier = [
        'name' => cleanInput($_POST['supplier_name'] ?? ''),
        'contact' => cleanInput($_POST['contact_person'] ?? ''),
        'email' => cleanInput($_POST['email'] ?? ''),
        'phone' => cleanInput($_POST['phone'] ?? ''),
        'address' => cleanInput($_POST['address'] ?? '')
    ];

    if ($supplier['name'] === '' || $supplier['email'] === '') {
        throw new Exception("Supplier Name and Email are required.");
    }

    if (!filter_var($supplier['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Please enter a valid supplier email address.");
    }

    return $supplier;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'])) {
    $db = (new Database())->getConnection();

    try {
        if ($_POST['action'] === 'add') {
            $s = readSupplierForm();

            $stmt = $db->prepare("INSERT INTO supplier (supplier_name, contact_person, email, phone, address, is_active) VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->execute([$s['name'], $s['contact'], $s['email'], $s['phone'], $s['address']]);

            $_SESSION['success_message'] = "Supplier added successfully.";
        }

        elseif ($_POST['action'] === 'edit') {
            $id = intval($_POST['supplier_id'] ?? 0);
            $s = readSupplierForm();

            $stmt = $db->prepare("UPDATE supplier SET supplier_name = ?, contact_person = ?, email = ?, phone = ?, address = ? WHERE supplier_id = ?");
            $stmt->execute([$s['name'], $s['contact'], $s['email'], $s['phone'], $s['address'], $id]);

            $_SESSION['success_message'] = "Supplier updated successfully.";
        }

        elseif ($_POST['action'] === 'archive') {
            $id = intval($_POST['supplier_id'] ?? 0);
            $stmt = $db->prepare("UPDATE supplier SET is_active = 0 WHERE supplier_id = ?");
            $stmt->execute([$id]);
            logActivity($db, 'Archived supplier', 'Supplier', $id, activityRef($db, 'Supplier', $id));
            $_SESSION['success_message'] = "Supplier archived.";
        }

    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
}

header("Location: ../views/suppliers.php");
exit;
