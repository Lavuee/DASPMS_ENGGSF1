<?php
/*
    Password reset by email.

    - A random 256-bit token is emailed; only its SHA-256 hash is stored in
      password_reset with an expiry (PASSWORD_RESET_TTL_MINUTES) and single use.
    - Requesting a new link invalidates older unused links for that account.
    - The request form always shows the same message so emails can't be enumerated.
    - Completing a reset sets user.password_changed_at, which ends all existing
      sessions for that account (includes/auth.php).
*/

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/email_verification.php';
require_once __DIR__ . '/passwords.php';

function buildPasswordResetLink($token) {
    return appBaseUrl() . '/views/reset_password.php?token=' . urlencode($token);
}

// Emails a reset link when $email belongs to an active account. Silent otherwise.
function requestPasswordReset(PDO $db, $email) {
    $stmt = $db->prepare("
        SELECT user_id, first_name, email
        FROM user
        WHERE email = ? AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        return;
    }

    $token = generateSecureToken();
    $expiresAt = date('Y-m-d H:i:s', time() + PASSWORD_RESET_TTL_MINUTES * 60);

    $db->prepare("UPDATE password_reset SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL")
        ->execute([intval($user['user_id'])]);

    $db->prepare("
        INSERT INTO password_reset (user_id, token_hash, expires_at, requested_ip)
        VALUES (?, ?, ?, ?)
    ")->execute([intval($user['user_id']), hashToken($token), $expiresAt, clientIp()]);

    $html = renderActionEmail(
        $user['first_name'],
        [
            "We received a request to reset the password for your DASPMS account.",
            "This link expires in " . PASSWORD_RESET_TTL_MINUTES . " minutes and can be used once."
        ],
        'Reset Password',
        buildPasswordResetLink($token),
        "If you did not request a password reset, you can ignore this email. Your password will not change."
    );

    if (!sendHtmlMail($user['email'], "Reset Your Password - Norily's Repair Shop", $html)) {
        error_log("DASPMS MAIL FAILED: password reset email to {$user['email']}. Error: " . mailLastError());
    }
}

// Returns the pending reset row for a valid, unexpired, unused token, or null.
function findValidPasswordReset(PDO $db, $token) {
    if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }

    $stmt = $db->prepare("
        SELECT pr.reset_id, pr.user_id
        FROM password_reset pr
        JOIN user u ON pr.user_id = u.user_id
        WHERE pr.token_hash = ?
          AND pr.used_at IS NULL
          AND pr.expires_at > NOW()
          AND u.is_active = 1
        LIMIT 1
    ");
    $stmt->execute([hashToken($token)]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/*
    Sets the new password for the token's account and consumes the token.
    A successful reset also proves ownership of the email, so the account is
    marked verified. Throws on invalid token or weak password.
*/
function completePasswordReset(PDO $db, $token, $newPassword) {
    $policyError = passwordPolicyError($newPassword);

    if ($policyError !== null) {
        throw new Exception($policyError);
    }

    $db->beginTransaction();

    try {
        $reset = findValidPasswordReset($db, $token);

        if (!$reset) {
            throw new Exception("This password reset link is invalid or has expired. Please request a new one.");
        }

        $consume = $db->prepare("UPDATE password_reset SET used_at = NOW() WHERE reset_id = ? AND used_at IS NULL");
        $consume->execute([intval($reset['reset_id'])]);

        if ($consume->rowCount() === 0) {
            throw new Exception("This password reset link has already been used.");
        }

        updateUserPassword($db, $reset['user_id'], $newPassword);

        $db->prepare("
            UPDATE user
            SET is_verified = 1,
                email_verified_at = COALESCE(email_verified_at, NOW()),
                verification_token = NULL,
                verification_expires_at = NULL
            WHERE user_id = ?
        ")->execute([intval($reset['user_id'])]);

        $db->commit();
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $e;
    }
}
