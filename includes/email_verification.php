<?php
/*
    Customer email verification.

    - A random 256-bit token is emailed to the user; only its SHA-256 hash is stored
      (user.verification_token) together with an expiry (user.verification_expires_at).
    - views/verify.php shows a confirmation button (POST) so email link scanners
      that prefetch URLs cannot verify accounts on their own.
    - The same email carries a 6-digit code (hash stored in user.verification_code,
      same expiry) that can be typed on views/verify_code.php. This works when the
      email is read on another device, where a localhost link cannot open.
      Wrong guesses are rate limited per address and per IP (AuthController).
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

function generateVerificationCode() {
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

// The user id is mixed in so equal codes of different users never share a hash.
function hashVerificationCode($userId, $code) {
    return hash('sha256', intval($userId) . ':' . $code);
}

/*
    Creates a new verification token and code for the user (replacing any previous
    ones) and returns ['token' => ..., 'code' => ...] to be emailed.
*/
function issueVerificationToken(PDO $db, $userId) {
    $token = generateSecureToken();
    $code = generateVerificationCode();
    $expiresAt = date('Y-m-d H:i:s', time() + EMAIL_VERIFICATION_TTL_HOURS * 3600);

    $stmt = $db->prepare("
        UPDATE user
        SET verification_token = ?, verification_code = ?, verification_expires_at = ?
        WHERE user_id = ? AND is_verified = 0
    ");
    $stmt->execute([hashToken($token), hashVerificationCode($userId, $code), $expiresAt, intval($userId)]);

    return ['token' => $token, 'code' => $code];
}

function sendVerificationEmail($email, $firstName, array $verification) {
    $codeHtml = '<p style="margin:0 0 14px;">Your verification code is:</p>'
        . '<p style="margin:0 0 14px; font-size:28px; font-weight:bold; letter-spacing:6px; font-family:\'Courier New\', monospace;">'
        . mailEscape($verification['code']) . '</p>';

    $html = renderActionEmail(
        $firstName,
        [
            "Welcome to DASPMS! Before you can log in and start tracking your vehicle service records, we need to verify your email address.",
            "Click the link below, or enter the code shown under it on the verification page. Both expire in " . EMAIL_VERIFICATION_TTL_HOURS . " hours."
        ],
        'Verify your email address',
        buildVerificationLink($verification['token']),
        "If you did not create an account, you can safely ignore this email.",
        $codeHtml
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

    return markEmailVerified($db, $user['user_id']);
}

// Verifies the account and consumes its token and code. Returns true when it changed.
function markEmailVerified(PDO $db, $userId) {
    $stmt = $db->prepare("
        UPDATE user
        SET is_verified = 1,
            email_verified_at = NOW(),
            verification_token = NULL,
            verification_code = NULL,
            verification_expires_at = NULL
        WHERE user_id = ? AND is_verified = 0
    ");
    $stmt->execute([intval($userId)]);

    return $stmt->rowCount() > 0;
}

/*
    Verifies the unverified account registered with $email when $code matches its
    unexpired code. Returns true when an account was verified.
*/
function verifyEmailCode(PDO $db, $email, $code) {
    if (!is_string($code) || !preg_match('/^\d{6}$/', $code)) {
        return false;
    }

    $stmt = $db->prepare("
        SELECT user_id, verification_code
        FROM user
        WHERE email = ?
          AND is_verified = 0
          AND verification_code IS NOT NULL
          AND verification_expires_at > NOW()
        LIMIT 1
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !hash_equals($user['verification_code'], hashVerificationCode($user['user_id'], $code))) {
        return false;
    }

    return markEmailVerified($db, $user['user_id']);
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

    $verification = issueVerificationToken($db, $user['user_id']);
    sendVerificationEmail($user['email'], $user['first_name'], $verification);
}
