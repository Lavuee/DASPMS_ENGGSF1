<?php
session_start();

require_once '../config/Database.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $db = (new Database())->getConnection();

    try {
        $first_name = trim($_POST['first_name'] ?? '');
        $middle_name = trim($_POST['middle_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $contact_number = trim($_POST['contact_number'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $password = $_POST['password'] ?? '';

        if (
            $first_name === '' ||
            $last_name === '' ||
            $username === '' ||
            $contact_number === '' ||
            $password === ''
        ) {
            throw new Exception("Please complete all required fields.");
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email address.");
        }

        $checkUser = $db->prepare("
            SELECT user_id
            FROM user
            WHERE username = ?
            LIMIT 1
        ");
        $checkUser->execute([$username]);

        if ($checkUser->fetch()) {
            throw new Exception("Username already exists.");
        }

        $db->beginTransaction();

        $hash = password_hash($password, PASSWORD_BCRYPT);

        $userStmt = $db->prepare("
            INSERT INTO user (
                first_name,
                last_name,
                username,
                password_hash,
                role
            ) VALUES (
                ?,
                ?,
                ?,
                ?,
                'Customer'
            )
        ");

        $userStmt->execute([
            $first_name,
            $last_name,
            $username,
            $hash
        ]);

        $user_id = $db->lastInsertId();

        $custStmt = $db->prepare("
            INSERT INTO customer (
                user_id,
                first_name,
                middle_name,
                last_name,
                email,
                contact_number,
                address,
                credit_balance,
                credit_due_date,
                created_at
            ) VALUES (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                0,
                NULL,
                NOW()
            )
        ");

        $custStmt->execute([
            $user_id,
            $first_name,
            $middle_name !== '' ? $middle_name : null,
            $last_name,
            $email !== '' ? $email : null,
            $contact_number,
            $address !== '' ? $address : 'no address provided'
        ]);

        $db->commit();

        $_SESSION['login_error'] = "Registration successful! Please login.";
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