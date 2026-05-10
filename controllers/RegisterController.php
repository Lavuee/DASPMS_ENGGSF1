<?php
session_start();

require_once '../config/Database.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $db = (new Database())->getConnection();

    try {
        $first_name = trim($_POST['first_name'] ?? '');
        $middle_name = trim($_POST['middle_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $contact_number = trim($_POST['contact_number'] ?? ($_POST['phone'] ?? ''));
        $address = trim($_POST['address'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($first_name === '' || $last_name === '' || $email === '' || $contact_number === '' || $password === '') {
            throw new Exception("Please complete all required fields.");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email address.");
        }

        if (!preg_match('/^\d{11}$/', $contact_number)) {
            throw new Exception("Contact number must be exactly 11 digits.");
        }

        $username = strtolower($email);

        $checkUser = $db->prepare("SELECT user_id FROM user WHERE username = ? OR email = ? LIMIT 1");
        $checkUser->execute([$username, $email]);

        if ($checkUser->fetch()) {
            throw new Exception("An account with this email already exists.");
        }

        $checkCustomer = $db->prepare("SELECT customer_id FROM customer WHERE contact_number = ? LIMIT 1");
        $checkCustomer->execute([$contact_number]);

        if ($checkCustomer->fetch()) {
            throw new Exception("A customer record with this contact number already exists. Please contact the shop for account linking.");
        }

        $db->beginTransaction();

        $hash = password_hash($password, PASSWORD_BCRYPT);

        $userStmt = $db->prepare("INSERT INTO user (first_name, last_name, username, password_hash, email, role, is_active) VALUES (?, ?, ?, ?, ?, 'Customer', 1)");
        $userStmt->execute([$first_name, $last_name, $username, $hash, $email]);

        $user_id = $db->lastInsertId();

        $custStmt = $db->prepare("INSERT INTO customer (user_id, first_name, middle_name, last_name, email, contact_number, address, credit_balance, credit_due_date, created_at, status) VALUES (?, ?, ?, ?, ?, ?, ?, 0, NULL, NOW(), 'Active')");
        $custStmt->execute([
            $user_id,
            $first_name,
            $middle_name !== '' ? $middle_name : null,
            $last_name,
            $email,
            $contact_number,
            $address !== '' ? $address : 'no address provided'
        ]);

        $db->commit();

        $_SESSION['login_error'] = "Registration successful! Please login using your email address.";
        header("Location: ../views/login.php");
        exit;

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        $_SESSION['login_error'] = "Registration failed: " . $e->getMessage();
        header("Location: ../views/register.php");
        exit;
    }
}

header("Location: ../views/register.php");
exit;
?>