<?php
session_start();
require_once '../config/Database.php';
require_once '../models/User.php';

// Handle Logout
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_destroy();
    header("Location: ../views/login.php");
    exit;
}

// Handle Login
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $database = new Database();
    $db = $database->getConnection();
    $user = new User($db);

    $username = htmlspecialchars(strip_tags($_POST['username']));
    $password = $_POST['password'];

    if ($user->login($username, $password)) {
        // Set secure session variables
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $user->user_id;
        $_SESSION['username'] = $user->username;
        $_SESSION['role'] = $user->role;
        $_SESSION['first_name'] = $user->first_name;

        // Route to the correct dashboard
        if ($_SESSION['role'] == 'Owner') {
            header("Location: ../views/dashboard_owner.php");
        } else if ($_SESSION['role'] == 'Cashier') {
            header("Location: ../views/dashboard_cashier.php");
        } else if ($_SESSION['role'] == 'Head Mechanic') {
            header("Location: ../views/dashboard_mechanic.php");
        }
        exit;
    } else {
        $_SESSION['login_error'] = "Invalid username or password.";
        header("Location: ../views/login.php");
        exit;
    }
}
?>