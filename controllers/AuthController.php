<?php
session_start();
require_once '../config/Database.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $db = (new Database())->getConnection();

    // --- LOGIN LOGIC ---
    if ($_POST['action'] == 'login') {
        $login_id = trim($_POST['login_id']); 
        $password = $_POST['password'];

        $stmt = $db->prepare("SELECT * FROM user WHERE username = :id OR email = :id LIMIT 1");
        $stmt->execute([':id' => $login_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Verification: Checks hashed passwords AND plain text for the manual reset accounts
        if ($user && (password_verify($password, $user['password_hash']) || $password === $user['password_hash'])) {
            $_SESSION['logged_in'] = true;
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['role'] = $user['role'];

            $dashboardMap = [
                'Owner' => 'dashboard_owner.php',
                'Cashier' => 'dashboard_cashier.php',
                'Head Mechanic' => 'dashboard_mechanic.php',
                'Customer' => 'dashboard_customer.php'
            ];
            header("Location: ../views/" . ($dashboardMap[$user['role']] ?? 'login.php'));
            exit;
        } else {
            $_SESSION['error_message'] = "Invalid username/email or password.";
            header("Location: ../views/login.php");
            exit;
        }
    }

    // --- REGISTRATION LOGIC ---
    elseif ($_POST['action'] == 'register') {
        try {
            $first_name = trim($_POST['first_name']);
            $last_name = trim($_POST['last_name']);
            $email = trim($_POST['email']);
            $username = trim($_POST['username']);
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $phone = trim($_POST['phone'] ?? '');

            $stmtUser = $db->prepare("INSERT INTO user (username, password_hash, first_name, last_name, email, role, is_active) VALUES (?, ?, ?, ?, ?, 'Customer', 1)");
            $stmtUser->execute([$username, $password, $first_name, $last_name, $email]);
            $user_id = $db->lastInsertId();

            $stmtCust = $db->prepare("INSERT INTO customer (user_id, first_name, last_name, email, contact_number, address) VALUES (?, ?, ?, ?, ?, '')");
            $stmtCust->execute([$user_id, $first_name, $last_name, $email, $phone]);

            $_SESSION['success_message'] = "Account created! You may now sign in.";
            header("Location: ../views/login.php");
            exit;
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Registration failed: " . $e->getMessage();
            header("Location: ../views/register.php");
            exit;
        }
    }
}

if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_destroy();
    header("Location: ../views/login.php");
    exit;
}
?>