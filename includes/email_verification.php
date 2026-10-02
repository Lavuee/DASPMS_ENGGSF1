<?php
/*
    Customer email verification.

    - A random 256-bit token is emailed to the user; only its SHA-256 hash is stored
      (user.verification_token) together with an expiry (user.verification_expires_at).
    - views/verify.php shows a confirmation button (POST) so email link scanners
      that prefetch URLs cannot verify accounts on their own.
    - Unverified accounts cannot log in (AuthController) and any session whose
      account becomes unverified is ended (includes/auth.php).
    - Staff accounts are created already verified by the Owner (User::create()).
*/

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/mailer.php';

function generateSecureToken() {
    return bin2hex(random_bytes(32));
}

function hashToken($token) {
    return hash('sha256', (string)$token);
}

function requiresEmailVerification(array $user) {
    return isset($user['is_verified']) && intval($user['is_verified']) === 0;
}

function buildVerificationLink($token) {
    return appBaseUrl() . '/views/verify.php?token=' . urlencode($token);
}

/*
    Creates a new verification token for the user (replacing any previous one)
    and returns the raw token to be emailed.
*/
function issueVerificationToken(PDO $db, $userId) {
    $token = generateSecureToken();
    $expiresAt = date('Y-m-d H:i:s', time() + EMAIL_VERIFICATION_TTL_HOURS * 3600);

    $stmt = $db->prepare("
        UPDATE user
        SET verification_token = ?, verification_expires_at = ?
        WHERE user_id = ? AND is_verified = 0
    ");
    $stmt->execute([hashToken($token), $expiresAt, intval($userId)]);

    return $token;
}

function sendVerificationEmail($email, $firstName, $token) {
    $html = renderActionEmail(
        $firstName,
        [
            "Welcome to DASPMS! Before you can log in and start tracking your vehicle service records, we need to verify your email address.",
            "This link expires in " . EMAIL_VERIFICATION_TTL_HOURS . " hours."
        ],
        'Verify Email Address',
        buildVerificationLink($token),
        "If you did not create an account, you can safely ignore this email."
    );

    $sent = sendHtmlMail($email, "Verify Your Account - Norily's Repair Shop", $html);

    if (!$sent) {
        error_log("DASPMS MAIL FAILED: verification email to $email. Error: " . mailLastError());
    }

    return $sent;
}

// Returns the unverified user that owns a valid, unexpired token, or null.
function findUserByVerificationToken(PDO $db, $token) {
    if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }

    $stmt = $db->prepare("
        SELECT user_id, first_name, email
        FROM user
        WHERE verification_token = ?
          AND is_verified = 0
          AND verification_expires_at > NOW()
        LIMIT 1
    ");
    $stmt->execute([hashToken($token)]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/*
    Marks the account that owns $token as verified and consumes the token.
    Returns true when an account was verified.
*/
function verifyEmailToken(PDO $db, $token) {
    $user = findUserByVerificationToken($db, $token);

    if (!$user) {
        return false;
    }

    $stmt = $db->prepare("
        UPDATE user
        SET is_verified = 1,
            email_verified_at = NOW(),
            verification_token = NULL,
            verification_expires_at = NULL
        WHERE user_id = ? AND is_verified = 0
    ");
    $stmt->execute([intval($user['user_id'])]);

    return $stmt->rowCount() > 0;
}

/*
    Sends a fresh verification link if $email belongs to an unverified, active account.
    Always behaves the same from the caller's view so emails can't be enumerated.
*/
function resendVerificationEmail(PDO $db, $email) {
    $stmt = $db->prepare("
        SELECT user_id, first_name, email
        FROM user
        WHERE email = ? AND is_verified = 0 AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        return;
    }

    $token = issueVerificationToken($db, $user['user_id']);
    sendVerificationEmail($user['email'], $user['first_name'], $token);
}
