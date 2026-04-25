<?php
session_start();
require_once '../config/Database.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $db = (new Database())->getConnection();
    
    try {
        $db->beginTransaction();
        
        // 1. Create User Login
        $hash = password_hash($_POST['password'], PASSWORD_BCRYPT);
        $userStmt = $db->prepare("INSERT INTO user (first_name, last_name, username, password_hash, role) VALUES (?, ?, ?, ?, 'Customer')");
        $userStmt->execute([$_POST['first_name'], $_POST['last_name'], $_POST['username'], $hash]);
        $user_id = $db->lastInsertId();

        // 2. Create Customer Profile
        $custStmt = $db->prepare("INSERT INTO customer (user_id, first_name, last_name, contact_number, address) VALUES (?, ?, ?, ?, ?)");
        $custStmt->execute([$user_id, $_POST['first_name'], $_POST['last_name'], $_POST['contact_number'], $_POST['address']]);
        
        $db->commit();
        $_SESSION['login_error'] = "Registration successful! Please login."; // Reusing error session for quick alert
        header("Location: ../views/login.php");
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['login_error'] = "Registration failed. Username may be taken.";
        header("Location: ../views/register.php");
    }
}
?>