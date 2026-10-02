<?php
/*
    Database-backed attempt limiting (login, password reset, verification emails, registration).
    Each key allows $maxAttempts within $windowSeconds; reaching the limit locks the key
    for $lockSeconds.
*/

function rateLimitKey($scope, $value) {
    return $scope . ':' . hash('sha256', strtolower(trim((string)$value)));
}

function isRateLimited(PDO $db, $key, $maxAttempts, $windowSeconds) {
    $stmt = $db->prepare("SELECT attempts, window_start, locked_until FROM rate_limit WHERE rl_key = ? LIMIT 1");
    $stmt->execute([$key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return false;
    }

    if (!empty($row['locked_until']) && strtotime($row['locked_until']) > time()) {
        return true;
    }

    $windowActive = strtotime($row['window_start']) + $windowSeconds > time();

    return $windowActive && intval($row['attempts']) >= $maxAttempts;
}

function recordRateLimitAttempt(PDO $db, $key, $maxAttempts, $windowSeconds, $lockSeconds) {
    $stmt = $db->prepare("SELECT attempts, window_start FROM rate_limit WHERE rl_key = ? LIMIT 1");
    $stmt->execute([$key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || strtotime($row['window_start']) + $windowSeconds <= time()) {
        $stmt = $db->prepare("
            REPLACE INTO rate_limit (rl_key, attempts, window_start, locked_until)
            VALUES (?, 1, NOW(), NULL)
        ");
        $stmt->execute([$key]);
        return;
    }

    $attempts = intval($row['attempts']) + 1;
    $lockedUntil = $attempts >= $maxAttempts ? date('Y-m-d H:i:s', time() + $lockSeconds) : null;

    $stmt = $db->prepare("UPDATE rate_limit SET attempts = ?, locked_until = ? WHERE rl_key = ?");
    $stmt->execute([$attempts, $lockedUntil, $key]);
}

function clearRateLimit(PDO $db, $key) {
    $stmt = $db->prepare("DELETE FROM rate_limit WHERE rl_key = ?");
    $stmt->execute([$key]);
}
