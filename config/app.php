<?php
/*
    Application-wide settings. Values come from .env (see .env.example).
*/

require_once __DIR__ . '/env.php';

define('APP_ENV', env('APP_ENV', 'local'));

// In production, PHP errors go to a private log instead of the page (logs/*.log is blocked by .htaccess).
if (APP_ENV === 'production') {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
}

// PHP and MySQL must agree on the time zone (see Database::getConnection()).
date_default_timezone_set(env('APP_TIMEZONE', 'Asia/Manila'));
define('APP_BASE_URL', env('APP_BASE_URL'));

define('MAIL_FROM_ADDRESS', env('MAIL_FROM_ADDRESS', 'daspms1998@gmail.com'));
define('MAIL_FROM_NAME', env('MAIL_FROM_NAME', "Norily's Repair Shop"));
// Where messages from the public Contact page are delivered (defaults to the sending address)
define('CONTACT_INBOX', env('CONTACT_INBOX', MAIL_FROM_ADDRESS));

define('SESSION_IDLE_TIMEOUT', intval(env('SESSION_IDLE_TIMEOUT', 1800)));
define('SESSION_ABSOLUTE_TIMEOUT', intval(env('SESSION_ABSOLUTE_TIMEOUT', 28800)));

define('EMAIL_VERIFICATION_TTL_HOURS', intval(env('EMAIL_VERIFICATION_TTL_HOURS', 24)));
define('PASSWORD_RESET_TTL_MINUTES', intval(env('PASSWORD_RESET_TTL_MINUTES', 60)));

// Roles that manage the shop (as opposed to Customer / Head Mechanic).
define('STAFF_ROLES', ['Owner', 'Cashier']);

function isProduction() {
    return APP_ENV === 'production';
}

function isHttpsRequest() {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

function appBaseUrl() {
    if (APP_BASE_URL) {
        return rtrim(APP_BASE_URL, '/');
    }

    $scheme = isHttpsRequest() ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    // The project root is the parent of /config; map it onto the request path.
    $projectRoot = str_replace('\\', '/', realpath(__DIR__ . '/..'));
    $docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '');
    $path = ($docRoot !== '' && strpos($projectRoot, $docRoot) === 0)
        ? substr($projectRoot, strlen($docRoot))
        : '/' . basename($projectRoot);

    return $scheme . '://' . $host . rtrim($path, '/');
}

function clientIp() {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}
