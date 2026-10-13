<?php
require_once '../includes/auth.php';
require_once '../includes/passwords.php';
require_once '../includes/rate_limit.php';
require_once '../includes/email_verification.php';
require_once '../includes/password_reset.php';
require_once '../includes/cart_store.php';

// Login throttling: per account+IP, and a looser per-IP limit.
const LOGIN_MAX_ATTEMPTS = 3; // SRS UC01: lock the account after 3 failed attempts
const LOGIN_IP_MAX_ATTEMPTS = 30;
const LOGIN_WINDOW_SECONDS = 900;
const LOGIN_LOCK_SECONDS = 900;

// Email-sending actions (reset/resend/register) per IP and per address.
const EMAIL_ACTION_MAX_ATTEMPTS = 5;
const EMAIL_ACTION_WINDOW_SECONDS = 3600;

function cleanInput($value) {
    return trim((string)($value ?? ''));
}

function redirectTo($path, $messageKey = null, $message = null) {
    if ($messageKey !== null) {
        $_SESSION[$messageKey] = $message;
    }

    header("Location: " . $path);
    exit;
}

/*
    Applies the per-IP and per-address limits for actions that send email.
    Returns false when the caller should stop (limit reached).
*/
function allowEmailAction(PDO $db, $scope, $email) {
    $keys = [rateLimitKey($scope . '_ip', clientIp()), rateLimitKey($scope . '_email', $email)];

    foreach ($keys as $key) {
        if (isRateLimited($db, $key, EMAIL_ACTION_MAX_ATTEMPTS, EMAIL_ACTION_WINDOW_SECONDS)) {
            return false;
        }
    }

    foreach ($keys as $key) {
        recordRateLimitAttempt($db, $key, EMAIL_ACTION_MAX_ATTEMPTS, EMAIL_ACTION_WINDOW_SECONDS, EMAIL_ACTION_WINDOW_SECONDS);
    }

    return true;
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
            user_id, first_name, middle_name, last_name, email,
            contact_number, address, status, created_at
        ) VALUES (?, ?, NULL, ?, ?, '', 'no address provided', 'Active', NOW())
    ");

    $stmtCreateCustomer->execute([
        $userId,
        $firstName,
        $lastName,
        $email
    ]);

    return intval($db->lastInsertId());
}

/* =========================
   ACTIONS
========================= */

function handleLogin(PDO $db) {
    $login_id = cleanInput($_POST['login_id'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if ($login_id === '' || $password === '') {
        throw new Exception("Please enter your username/email and password.");
    }

    $accountKey = rateLimitKey('login', $login_id . '|' . clientIp());
    $ipKey = rateLimitKey('login_ip', clientIp());

    if (
        isRateLimited($db, $accountKey, LOGIN_MAX_ATTEMPTS, LOGIN_WINDOW_SECONDS) ||
        isRateLimited($db, $ipKey, LOGIN_IP_MAX_ATTEMPTS, LOGIN_WINDOW_SECONDS)
    ) {
        throw new Exception("Too many failed login attempts. Please wait 15 minutes and try again.");
    }

    $stmt = $db->prepare("
        SELECT *
        FROM user
        WHERE username = :id OR email = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $login_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $passwordOk = verifyAndUpgradePassword($db, $user, $password);
    } else {
        simulatePasswordCheck($password);
        $passwordOk = false;
    }

    if (!$user || intval($user['is_active']) !== 1 || !$passwordOk) {
        recordRateLimitAttempt($db, $accountKey, LOGIN_MAX_ATTEMPTS, LOGIN_WINDOW_SECONDS, LOGIN_LOCK_SECONDS);
        recordRateLimitAttempt($db, $ipKey, LOGIN_IP_MAX_ATTEMPTS, LOGIN_WINDOW_SECONDS, LOGIN_LOCK_SECONDS);
        throw new Exception("Invalid username/email or password.");
    }

    clearRateLimit($db, $accountKey);

    if (requiresEmailVerification($user)) {
        $_SESSION['show_resend_verification'] = true;
        throw new Exception("Your email is not verified yet. Please use the link we emailed you, or request a new one.");
    }

    $customerId = null;

    if ($user['role'] === 'Customer') {
        $customerId = ensureCustomerProfile($db, $user);

        if (!$customerId) {
            throw new Exception("Unable to prepare customer profile. Please contact the shop.");
        }
    }

    $db->prepare("UPDATE user SET last_login_at = NOW() WHERE user_id = ?")->execute([intval($user['user_id'])]);

    loginUser($user, $customerId);

    // Bring back the cart this customer had before logging out.
    if ($customerId) {
        cartRestoreFromDb($db, $customerId);
    }

    redirectTo("../views/" . dashboardForRole($user['role']));
}

function handleRegister(PDO $db) {
    $first_name = cleanInput($_POST['first_name'] ?? '');
    $last_name = cleanInput($_POST['last_name'] ?? '');
    $email = strtolower(cleanInput($_POST['email'] ?? ''));
    $phone = cleanInput($_POST['phone'] ?? '');
    $rawPassword = (string)($_POST['password'] ?? '');

    if ($first_name === '' || $last_name === '' || $email === '' || $phone === '' || $rawPassword === '') {
        throw new Exception("Please complete all required fields.");
    }

    if (mb_strlen($first_name) > 20 || mb_strlen($last_name) > 20) {
        throw new Exception("First and last name must be 20 characters or fewer.");
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
        throw new Exception("Please enter a valid email address.");
    }

    if (!preg_match('/^\d{11}$/', $phone)) {
        throw new Exception("Phone number must be exactly 11 digits.");
    }

    $policyError = passwordPolicyError($rawPassword);

    if ($policyError !== null) {
        throw new Exception($policyError);
    }

    if (!allowEmailAction($db, 'register', $email)) {
        throw new Exception("Too many registration attempts. Please try again later.");
    }

    $checkUser = $db->prepare("SELECT user_id FROM user WHERE username = ? OR email = ? LIMIT 1");
    $checkUser->execute([$email, $email]);

    if ($checkUser->fetch()) {
        throw new Exception("An account with this email already exists.");
    }

    $checkCustomer = $db->prepare("SELECT customer_id FROM customer WHERE contact_number = ? LIMIT 1");
    $checkCustomer->execute([$phone]);

    if ($checkCustomer->fetch()) {
        throw new Exception("A customer record with this phone number already exists. Please contact the shop for account linking.");
    }

    $db->beginTransaction();

    $stmtUser = $db->prepare("
        INSERT INTO user (
            username, password_hash, first_name, last_name, email,
            role, is_active, is_verified
        ) VALUES (?, ?, ?, ?, ?, 'Customer', 1, 0)
    ");
    $stmtUser->execute([$email, hashPassword($rawPassword), $first_name, $last_name, $email]);

    $user_id = $db->lastInsertId();

    $stmtCust = $db->prepare("
        INSERT INTO customer (
            user_id, first_name, middle_name, last_name, email,
            contact_number, address, status, created_at
        ) VALUES (?, ?, NULL, ?, ?, ?, 'no address provided', 'Active', NOW())
    ");
    $stmtCust->execute([$user_id, $first_name, $last_name, $email, $phone]);

    $token = issueVerificationToken($db, $user_id);

    $db->commit();

    if (sendVerificationEmail($email, $first_name, $token)) {
        redirectTo("../views/login.php", 'success_message', "Account created! Please check your email and verify your address before logging in.");
    }

    $_SESSION['show_resend_verification'] = true;
    redirectTo("../views/login.php", 'error_message', "Account created, but we could not send the verification email. Please request a new link below.");
}

function handleResendVerification(PDO $db) {
    $email = strtolower(cleanInput($_POST['email'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        redirectTo("../views/resend_verification.php", 'error_message', "Please enter a valid email address.");
    }

    if (allowEmailAction($db, 'resend', $email)) {
        resendVerificationEmail($db, $email);
    }

    redirectTo("../views/login.php", 'success_message', "If that email belongs to an unverified account, a new verification link has been sent.");
}

function handleConfirmEmail(PDO $db) {
    $token = cleanInput($_POST['token'] ?? '');

    if (verifyEmailToken($db, $token)) {
        redirectTo("../views/login.php", 'success_message', "Your email has been verified! You can now log in.");
    }

    $_SESSION['show_resend_verification'] = true;
    redirectTo("../views/login.php", 'error_message', "This verification link is invalid or has expired. Please request a new one.");
}

function handleRequestPasswordReset(PDO $db) {
    $email = strtolower(cleanInput($_POST['email'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        redirectTo("../views/forgot_password.php", 'error_message', "Please enter a valid email address.");
    }

    if (allowEmailAction($db, 'reset', $email)) {
        requestPasswordReset($db, $email);
    }

    redirectTo("../views/login.php", 'success_message', "If an account exists for that email, a password reset link has been sent. The link expires in " . PASSWORD_RESET_TTL_MINUTES . " minutes.");
}

function handleResetPassword(PDO $db) {
    $token = cleanInput($_POST['token'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['password_confirm'] ?? '');

    try {
        if (!hash_equals($password, $confirm)) {
            throw new Exception("The passwords do not match.");
        }

        completePasswordReset($db, $token, $password);
    } catch (Exception $e) {
        redirectTo("../views/reset_password.php?token=" . urlencode($token), 'error_message', $e->getMessage());
    }

    // Any session in this browser belongs to the old credentials.
    if (isLoggedIn()) {
        endSessionWithMessage('');
        unset($_SESSION['error_message']);
    }

    redirectTo("../views/login.php", 'success_message', "Your password has been reset. Please log in with your new password.");
}

/* =========================
   ROUTING
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'])) {
    $db = (new Database())->getConnection();
    $action = $_POST['action'];

    try {
        switch ($action) {
            case 'login':
                handleLogin($db);
                break;
            case 'register':
                handleRegister($db);
                break;
            case 'logout':
                logoutUser();
                session_start();
                redirectTo("../views/login.php", 'success_message', "You have been logged out.");
                break;
            case 'resend_verification':
                handleResendVerification($db);
                break;
            case 'confirm_email':
                handleConfirmEmail($db);
                break;
            case 'request_password_reset':
                handleRequestPasswordReset($db);
                break;
            case 'reset_password':
                handleResetPassword($db);
                break;
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        if ($action === 'register') {
            $message = $e instanceof PDOException ? "Registration failed. Please try again." : "Registration failed: " . $e->getMessage();
            redirectTo("../views/register.php", 'error_message', $message);
        }

        if ($e instanceof PDOException) {
            error_log("DASPMS AUTH ERROR: " . $e->getMessage());
            redirectTo("../views/login.php", 'error_message', "Something went wrong. Please try again.");
        }

        redirectTo("../views/login.php", 'error_message', $e->getMessage());
    }
}

header("Location: ../views/login.php");
exit;
