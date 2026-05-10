<?php
session_start();
require_once '../config/Database.php';

function cleanInput($value) {
    return trim((string)($value ?? ''));
}

function ensureCustomerProfile($db, $user) {
    if (($user['role'] ?? '') !== 'Customer') {
        return null;
    }

    $userId = intval($user['user_id']);
    $firstName = cleanInput($user['first_name'] ?? '');
    $lastName = cleanInput($user['last_name'] ?? '');
    $email = cleanInput($user['email'] ?? '');

    $stmtCustomer = $db->prepare("
        SELECT customer_id
        FROM customer
        WHERE user_id = ?
        LIMIT 1
    ");
    $stmtCustomer->execute([$userId]);
    $customerId = $stmtCustomer->fetchColumn();

    if ($customerId) {
        return intval($customerId);
    }

    /*
        If a customer profile already exists with the same email but is not linked
        to a user account yet, link it instead of creating a duplicate customer.
    */
    if ($email !== '') {
        $stmtExistingCustomer = $db->prepare("
            SELECT customer_id
            FROM customer
            WHERE email = ?
              AND (user_id IS NULL OR user_id = 0)
            LIMIT 1
        ");
        $stmtExistingCustomer->execute([$email]);
        $existingCustomerId = $stmtExistingCustomer->fetchColumn();

        if ($existingCustomerId) {
            $stmtLink = $db->prepare("
                UPDATE customer
                SET user_id = ?,
                    first_name = COALESCE(NULLIF(first_name, ''), ?),
                    last_name = COALESCE(NULLIF(last_name, ''), ?),
                    status = 'Active'
                WHERE customer_id = ?
            ");
            $stmtLink->execute([
                $userId,
                $firstName,
                $lastName,
                intval($existingCustomerId)
            ]);

            return intval($existingCustomerId);
        }
    }

    /*
        Fallback for old Customer user accounts that do not have a customer profile.
        This prevents 'Customer profile not found' after login.
    */
    $stmtCreateCustomer = $db->prepare("
        INSERT INTO customer (
            user_id,
            first_name,
            middle_name,
            last_name,
            email,
            contact_number,
            address,
            status,
            created_at
        ) VALUES (
            ?,
            ?,
            NULL,
            ?,
            ?,
            '',
            'no address provided',
            'Active',
            NOW()
        )
    ");

    $stmtCreateCustomer->execute([
        $userId,
        $firstName,
        $lastName,
        $email
    ]);

    return intval($db->lastInsertId());
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $db = (new Database())->getConnection();

    if ($_POST['action'] == 'login') {
        try {
            $login_id = cleanInput($_POST['login_id'] ?? '');
            $password = $_POST['password'] ?? '';

            if ($login_id === '' || $password === '') {
                throw new Exception("Please enter your username/email and password.");
            }

            $stmt = $db->prepare("
                SELECT *
                FROM user
                WHERE username = :id OR email = :id
                LIMIT 1
            ");
            $stmt->execute([':id' => $login_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (
                !$user ||
                intval($user['is_active']) !== 1 ||
                !(password_verify($password, $user['password_hash']) || $password === $user['password_hash'])
            ) {
                throw new Exception("Invalid username/email or password.");
            }

            $customerId = null;

            if ($user['role'] === 'Customer') {
                $customerId = ensureCustomerProfile($db, $user);

                if (!$customerId) {
                    throw new Exception("Unable to prepare customer profile. Please contact the shop.");
                }
            }

            $_SESSION['logged_in'] = true;
            $_SESSION['user_id'] = intval($user['user_id']);
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['role'] = $user['role'];

            if ($customerId) {
                $_SESSION['customer_id'] = $customerId;
            }

            $dashboardMap = [
                'Owner' => 'dashboard_owner.php',
                'Cashier' => 'dashboard_cashier.php',
                'Head Mechanic' => 'dashboard_mechanic.php',
                'Customer' => 'dashboard_customer.php'
            ];

            header("Location: ../views/" . ($dashboardMap[$user['role']] ?? 'login.php'));
            exit;

        } catch (Exception $e) {
            $_SESSION['error_message'] = $e->getMessage();
            header("Location: ../views/login.php");
            exit;
        }
    }

    elseif ($_POST['action'] == 'register') {
        try {
            $first_name = cleanInput($_POST['first_name'] ?? '');
            $last_name = cleanInput($_POST['last_name'] ?? '');
            $email = cleanInput($_POST['email'] ?? '');
            $phone = cleanInput($_POST['phone'] ?? '');
            $rawPassword = $_POST['password'] ?? '';

            if ($first_name === '' || $last_name === '' || $email === '' || $phone === '' || $rawPassword === '') {
                throw new Exception("Please complete all required fields.");
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Please enter a valid email address.");
            }

            if (!preg_match('/^\d{11}$/', $phone)) {
                throw new Exception("Phone number must be exactly 11 digits.");
            }

            $username = strtolower($email);

            $checkUser = $db->prepare("
                SELECT user_id
                FROM user
                WHERE username = ? OR email = ?
                LIMIT 1
            ");
            $checkUser->execute([$username, $email]);

            if ($checkUser->fetch()) {
                throw new Exception("An account with this email already exists.");
            }

            $checkCustomer = $db->prepare("
                SELECT customer_id
                FROM customer
                WHERE contact_number = ?
                LIMIT 1
            ");
            $checkCustomer->execute([$phone]);

            if ($checkCustomer->fetch()) {
                throw new Exception("A customer record with this phone number already exists. Please contact the shop for account linking.");
            }

            $db->beginTransaction();

            $password = password_hash($rawPassword, PASSWORD_DEFAULT);

            $stmtUser = $db->prepare("
                INSERT INTO user (
                    username,
                    password_hash,
                    first_name,
                    last_name,
                    email,
                    role,
                    is_active
                ) VALUES (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    'Customer',
                    1
                )
            ");
            $stmtUser->execute([
                $username,
                $password,
                $first_name,
                $last_name,
                $email
            ]);

            $user_id = $db->lastInsertId();

            $stmtCust = $db->prepare("
                INSERT INTO customer (
                    user_id,
                    first_name,
                    middle_name,
                    last_name,
                    email,
                    contact_number,
                    address,
                    status,
                    created_at
                ) VALUES (
                    ?,
                    ?,
                    NULL,
                    ?,
                    ?,
                    ?,
                    'no address provided',
                    'Active',
                    NOW()
                )
            ");
            $stmtCust->execute([
                $user_id,
                $first_name,
                $last_name,
                $email,
                $phone
            ]);

            $db->commit();

            $_SESSION['success_message'] = "Account created! You may now sign in using your email address.";
            header("Location: ../views/login.php");
            exit;

        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }

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