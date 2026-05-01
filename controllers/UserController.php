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

function cleanUserInput($value) {
    return trim($value ?? '');
}

function redirectToUsers() {
    header("Location: ../views/users.php");
    exit;
}

function validateStaffFields($firstName, $lastName, $username, $role, $password = null) {
    $allowedRoles = ['Cashier', 'Head Mechanic'];

    if ($firstName === '' || $lastName === '' || $username === '') {
        throw new Exception("First name, last name, and username are required.");
    }

    if (!in_array($role, $allowedRoles)) {
        throw new Exception("Invalid staff role selected.");
    }

    if ($password !== null && trim($password) === '') {
        throw new Exception("Temporary password is required.");
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'])) {
    $action = $_POST['action'];

    try {
        if ($action === 'add') {
            $firstName = cleanUserInput($_POST['first_name'] ?? '');
            $middleName = cleanUserInput($_POST['middle_name'] ?? '');
            $lastName = cleanUserInput($_POST['last_name'] ?? '');
            $username = cleanUserInput($_POST['username'] ?? '');
            $role = cleanUserInput($_POST['role'] ?? '');
            $rawPassword = $_POST['password'] ?? '';

            validateStaffFields($firstName, $lastName, $username, $role, $rawPassword);

            $user->first_name = $firstName;
            $user->middle_name = $middleName;
            $user->last_name = $lastName;
            $user->username = $username;
            $user->role = $role;

            if ($user->create($rawPassword)) {
                $_SESSION['success_message'] = "Staff account for {$user->first_name} created successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to create account. That username might already be taken.";
            }

            redirectToUsers();
        }

        if ($action === 'update') {
            $userId = intval($_POST['user_id'] ?? 0);
            $firstName = cleanUserInput($_POST['first_name'] ?? '');
            $middleName = cleanUserInput($_POST['middle_name'] ?? '');
            $lastName = cleanUserInput($_POST['last_name'] ?? '');
            $username = cleanUserInput($_POST['username'] ?? '');
            $role = cleanUserInput($_POST['role'] ?? '');

            if ($userId <= 0) {
                throw new Exception("Invalid staff account.");
            }

            validateStaffFields($firstName, $lastName, $username, $role);

            $user->user_id = $userId;
            $user->first_name = $firstName;
            $user->middle_name = $middleName;
            $user->last_name = $lastName;
            $user->username = $username;
            $user->role = $role;

            if ($user->update()) {
                $_SESSION['success_message'] = "Staff account updated successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to update account. Username may already be taken or Owner account cannot be edited.";
            }

            redirectToUsers();
        }

        if ($action === 'deactivate') {
            $userId = intval($_POST['user_id'] ?? 0);

            if ($userId <= 0) {
                throw new Exception("Invalid staff account.");
            }

            $user->user_id = $userId;

            if ($user->deactivate()) {
                $_SESSION['success_message'] = "Staff account deactivated successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to deactivate account. Owner account cannot be deactivated.";
            }

            redirectToUsers();
        }

        if ($action === 'reactivate') {
            $userId = intval($_POST['user_id'] ?? 0);

            if ($userId <= 0) {
                throw new Exception("Invalid staff account.");
            }

            $user->user_id = $userId;

            if ($user->reactivate()) {
                $_SESSION['success_message'] = "Staff account reactivated successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to reactivate account. Owner account cannot be modified here.";
            }

            redirectToUsers();
        }

        throw new Exception("Invalid action.");

    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
        redirectToUsers();
    }
}

redirectToUsers();
?>