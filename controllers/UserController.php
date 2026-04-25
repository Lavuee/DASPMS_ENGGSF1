<?php
session_start();
require_once '../config/Database.php';
require_once '../models/User.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Owner') {
    header("Location: ../views/login.php");
    exit;
}

$database = new Database();
$db = $database->getConnection();
$user = new User($db);

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add') {
    
    $user->first_name = $_POST['first_name'];
    $user->middle_name = $_POST['middle_name'] ?? '';
    $user->last_name = $_POST['last_name'];
    $user->username = $_POST['username'];
    $user->role = $_POST['role']; 
    
    $raw_password = $_POST['password'];

    if ($user->create($raw_password)) {
        $_SESSION['success_message'] = "Staff account for {$user->first_name} created successfully.";
    } else {
        $_SESSION['error_message'] = "Failed to create account. That username might already be taken.";
    }
    
    header("Location: ../views/users.php");
    exit;
}
?>