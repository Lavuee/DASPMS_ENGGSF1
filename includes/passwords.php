<?php
/*
    Password policy, hashing and verification.
*/

const PASSWORD_MIN_LENGTH = 8;
const PASSWORD_MAX_BYTES = 72; // bcrypt ignores anything longer

// Returns an error message when $password does not meet the policy, otherwise null.
function passwordPolicyError($password) {
    $password = (string)$password;

    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        return "Password must be at least " . PASSWORD_MIN_LENGTH . " characters long.";
    }

    if (strlen($password) > PASSWORD_MAX_BYTES) {
        return "Password must not be longer than " . PASSWORD_MAX_BYTES . " characters.";
    }

    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        return "Password must contain at least one letter and one number.";
    }

    return null;
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/*
    Checks a login password. Seeded/legacy accounts may still store a plain-text
    password; those are accepted only when the stored value is not a password hash,
    and are upgraded to a hash on successful login.
*/
function verifyAndUpgradePassword(PDO $db, array $user, $password) {
    $stored = (string)$user['password_hash'];
    $isHash = password_get_info($stored)['algoName'] !== 'unknown';

    if ($isHash) {
        $valid = password_verify($password, $stored);
    } else {
        $valid = $stored !== '' && hash_equals($stored, $password);
    }

    if ($valid && (!$isHash || password_needs_rehash($stored, PASSWORD_DEFAULT))) {
        $stmt = $db->prepare("UPDATE user SET password_hash = ? WHERE user_id = ?");
        $stmt->execute([hashPassword($password), intval($user['user_id'])]);
    }

    return $valid;
}

// Spends the same time as a real check so unknown usernames can't be detected by timing.
function simulatePasswordCheck($password) {
    static $dummyHash = null;

    if ($dummyHash === null) {
        $dummyHash = password_hash('dummy-password-for-timing', PASSWORD_DEFAULT);
    }

    password_verify((string)$password, $dummyHash);
}

/*
    Sets a new password and records when it changed. Sessions that started
    before this moment are ended by enforceSessionPolicies().
*/
function updateUserPassword(PDO $db, $userId, $newPassword) {
    $stmt = $db->prepare("UPDATE user SET password_hash = ?, password_changed_at = NOW() WHERE user_id = ?");
    $stmt->execute([hashPassword($newPassword), intval($userId)]);
}
