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

            // ADDED: Block login if the account is not verified
            if (isset($user['is_verified']) && intval($user['is_verified']) === 0) {
                throw new Exception("Your account is not verified yet. Please check your email for the verification link.");
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
            
            // ADDED: Generate secure verification token
            $token = bin2hex(random_bytes(16)); 

            // MODIFIED: Included is_verified (default 0) and verification_token
            $stmtUser = $db->prepare("
                INSERT INTO user (
                    username,
                    password_hash,
                    first_name,
                    last_name,
                    email,
                    role,
                    is_active,
                    is_verified,
                    verification_token
                ) VALUES (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    'Customer',
                    1,
                    0,
                    ?
                )
            ");
            $stmtUser->execute([
                $username,
                $password,
                $first_name,
                $last_name,
                $email,
                $token
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

            // ADDED: Dispatch Verification Email with Logging
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'];
            $temporary = "0rxd3vkf-443.asse.devtunnels.ms";
            
            // Adjust the path below if your project folder is named differently
            $verify_link = $protocol . "://" . $temporary . "/DASPMS_ENGGSF1-main/views/verify.php?token=" . $token; 
            
            $to = $email;
            $subject = "Verify Your Account - Norily's Repair Shop";
            
            // Premium, minimal HTML Email Template using native inline CSS
            $message = <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <style>
                    body {
                        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                        background-color: #f4f4f5;
                        margin: 0;
                        padding: 0;
                        -webkit-font-smoothing: antialiased;
                    }
                    .wrapper {
                        width: 100%;
                        background-color: #f4f4f5;
                        padding: 40px 0;
                    }
                    .container {
                        max-width: 540px;
                        margin: 0 auto;
                        background-color: #ffffff;
                        border-radius: 16px;
                        overflow: hidden;
                        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
                        border: 1px solid #e4e4e7;
                    }
                    .header {
                        background-color: #ffffff;
                        padding: 30px 40px 20px;
                        text-align: center;
                        border-bottom: 1px solid #f4f4f5;
                    }
                    .header h1 {
                        margin: 0;
                        color: #18181b;
                        font-size: 22px;
                        font-weight: 800;
                        letter-spacing: -0.5px;
                    }
                    .body {
                        padding: 40px;
                        color: #3f3f46;
                        font-size: 16px;
                        line-height: 1.6;
                    }
                    .body p {
                        margin: 0 0 20px 0;
                    }
                    .btn-wrapper {
                        text-align: center;
                        margin: 35px 0;
                    }
                    .btn {
                        display: inline-block;
                        padding: 14px 32px;
                        background-color: #f5c518; /* Updated DASPMS Yellow */
                        color: #111111 !important; /* Dark text for contrast */
                        text-decoration: none;
                        border-radius: 999px;
                        font-weight: 900;
                        font-size: 15px;
                        text-transform: uppercase;
                        letter-spacing: 0.5px;
                        box-shadow: 0 4px 12px rgba(245, 197, 24, 0.25);
                    }
                    .footer {
                        background-color: #fafafa;
                        padding: 24px 40px;
                        text-align: center;
                        font-size: 13px;
                        color: #a1a1aa;
                        border-top: 1px solid #e4e4e7;
                    }
                    .footer a {
                        color: #71717a;
                        text-decoration: underline;
                    }
                </style>
            </head>
            <body>
                <div class="wrapper">
                    <div class="container">
                        <div class="header">
                            <h1>Norily's Repair Shop</h1>
                        </div>
                        <div class="body">
                            <p>Hi <strong>{$first_name}</strong>,</p>
                            <p>Welcome to DASPMS! We are thrilled to have you on board. Before you can log in and start tracking your vehicle service records, we just need to verify your email address.</p>
                            
                            <div class="btn-wrapper">
                                <a href="{$verify_link}" class="btn">Verify Email Address</a>
                            </div>
                            
                            <p style="font-size: 14px; color: #71717a;">If the button above doesn't work, copy and paste this link into your web browser:</p>
                            <p style="font-size: 13px; color: #3b82f6; word-break: break-all;"><a href="{$verify_link}" style="color: #3b82f6;">{$verify_link}</a></p>
                        </div>
                        <div class="footer">
                            <p>If you did not create an account, you can safely ignore this email.</p>
                            <p>&copy; 2026 Norily's Vehicle Repair Shop. All rights reserved.</p>
                        </div>
                    </div>
                </div>
            </body>
            </html>
            HTML;
            
            // ANTI-SPAM FIX: The 'From' address MUST match the authenticated sendmail.ini account
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "From: Norily's Repair Shop <daspms1998@gmail.com>\r\n";
            $headers .= "Reply-To: daspms1998@gmail.com\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion();
            
            // Trigger email without suppression (@) to allow catching errors
            $mail_sent = mail($to, $subject, $message, $headers);

            if (!$mail_sent) {
                // Capture the exact PHP error
                $error = error_get_last();
                
                // Write it to XAMPP's PHP error log
                error_log("DASPMS MAIL FAILED: Could not send to $to. Error details: " . print_r($error, true));
                
                $_SESSION['error_message'] = "Account created, but the verification email failed to send. Check server logs.";
                header("Location: ../views/login.php");
                exit;
            } else {
                error_log("DASPMS MAIL SUCCESS: Verification email sent to $to");
                
                $_SESSION['success_message'] = "Account created! Please check your email to verify your account before logging in.";
                header("Location: ../views/login.php");
                exit;
            }

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