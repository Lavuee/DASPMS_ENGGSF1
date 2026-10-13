<?php
/*
    Session bootstrap, session security, CSRF protection and access-control helpers.
    Include this at the top of every page/controller instead of calling session_start().

    On every request it:
      - sends security headers,
      - starts a hardened session (strict mode, HttpOnly/SameSite cookie, Secure on HTTPS),
      - expires sessions after idle/absolute timeouts, on browser fingerprint change,
        or when the account was deactivated / changed its password / lost verification,
      - rotates the session id periodically,
      - rejects POST requests without a valid CSRF token or from another origin.
*/

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/Database.php';

const SESSION_ID_ROTATE_SECONDS = 900;

function sendSecurityHeaders() {
    if (headers_sent()) {
        return;
    }

    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

    if (isHttpsRequest() && isProduction()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function startSecureSession() {
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.gc_maxlifetime', (string)max(SESSION_IDLE_TIMEOUT, 1440));

    session_name('DASPMS_SESSION');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => env('SESSION_SECURE_COOKIE', false) === true || isHttpsRequest() || isProduction()
    ]);

    session_start();
}

/* =========================
   LOGIN STATE
========================= */

function isLoggedIn() {
    return !empty($_SESSION['logged_in']);
}

function dashboardForRole($role) {
    $dashboardMap = [
        'Owner' => 'dashboard_owner.php',
        'Cashier' => 'dashboard_cashier.php',
        'Head Mechanic' => 'dashboard_mechanic.php',
        'Customer' => 'dashboard_customer.php'
    ];

    return $dashboardMap[$role] ?? 'login.php';
}

// Ties the session to the browser that created it (helps detect stolen session cookies).
function sessionFingerprint() {
    return hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
}

/*
    Stores the logged-in user in the session. Regenerates the session id to
    prevent session fixation and issues a fresh CSRF token.
*/
function loginUser(array $user, $customerId = null) {
    session_regenerate_id(true);

    $now = time();

    $_SESSION['logged_in'] = true;
    $_SESSION['user_id'] = intval($user['user_id']);
    $_SESSION['first_name'] = $user['first_name'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['login_time'] = $now;
    $_SESSION['last_activity'] = $now;
    $_SESSION['regenerated_at'] = $now;
    $_SESSION['fingerprint'] = sessionFingerprint();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    if ($customerId) {
        $_SESSION['customer_id'] = $customerId;
    }
}

// Destroys the session completely (server data and cookie).
function logoutUser() {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax'
        ]);
    }

    session_destroy();
}

// Ends the session and starts a clean guest session carrying a message for the login page.
function endSessionWithMessage($message) {
    logoutUser();
    session_start();
    session_regenerate_id(true);
    $_SESSION['error_message'] = $message;
}

/*
    Re-checks the account on every request so deactivation, role changes,
    password resets and lost verification take effect immediately.
*/
function sessionAccountIsValid() {
    $db = (new Database())->getConnection();
    $stmt = $db->prepare("
        SELECT role, is_active, is_verified, password_changed_at
        FROM user
        WHERE user_id = ?
        LIMIT 1
    ");
    $stmt->execute([intval($_SESSION['user_id'] ?? 0)]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || intval($user['is_active']) !== 1 || intval($user['is_verified']) !== 1) {
        return false;
    }

    if ($user['role'] !== ($_SESSION['role'] ?? null)) {
        return false;
    }

    if (!empty($user['password_changed_at']) && strtotime($user['password_changed_at']) > intval($_SESSION['login_time'] ?? 0)) {
        return false;
    }

    return true;
}

function enforceSessionPolicies() {
    if (!isLoggedIn()) {
        return;
    }

    $now = time();
    $message = null;

    if ($now - intval($_SESSION['last_activity'] ?? 0) > SESSION_IDLE_TIMEOUT) {
        $message = "Your session expired due to inactivity. Please log in again.";
    } elseif ($now - intval($_SESSION['login_time'] ?? 0) > SESSION_ABSOLUTE_TIMEOUT) {
        $message = "Your session has expired. Please log in again.";
    } elseif (!hash_equals((string)($_SESSION['fingerprint'] ?? ''), sessionFingerprint())) {
        $message = "Your session was ended for security reasons. Please log in again.";
    } elseif (!sessionAccountIsValid()) {
        $message = "Your account session is no longer valid. Please log in again.";
    }

    if ($message !== null) {
        endSessionWithMessage($message);
        return;
    }

    $_SESSION['last_activity'] = $now;

    if ($now - intval($_SESSION['regenerated_at'] ?? 0) > SESSION_ID_ROTATE_SECONDS) {
        session_regenerate_id(true);
        $_SESSION['regenerated_at'] = $now;
    }
}

/* =========================
   CSRF PROTECTION
========================= */

function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

// Hidden input to place inside every POST form.
function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function isValidCsrfToken($token) {
    return is_string($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/*
    Defense in depth: reject POST requests whose Origin/Referer points at another site.
    Requests without either header are allowed; the CSRF token still protects them.
*/
function isSameOriginRequest() {
    $source = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '');

    if ($source === '' || $source === 'null') {
        return true;
    }

    return isSameOriginUrl($source);
}

function isSameOriginUrl($url) {
    $sourceHost = parse_url($url, PHP_URL_HOST);

    if (!$sourceHost) {
        return false;
    }

    $sourcePort = parse_url($url, PHP_URL_PORT);
    $sourceAuthority = strtolower($sourceHost . ($sourcePort ? ':' . $sourcePort : ''));

    $allowedHosts = array_filter([
        $_SERVER['HTTP_HOST'] ?? '',
        $_SERVER['HTTP_X_FORWARDED_HOST'] ?? ''
    ]);

    foreach ($allowedHosts as $host) {
        $host = strtolower(trim(explode(',', $host)[0]));

        if ($host === $sourceAuthority || $host === strtolower($sourceHost)) {
            return true;
        }
    }

    return false;
}

function verifyPostRequest() {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return;
    }

    if (!isSameOriginRequest()) {
        require_once __DIR__ . '/error_page.php';
        renderErrorPage(403, 'Security check', 'Request blocked',
            'This form was sent from outside DASPMS, so it was stopped to protect your account. Please open the page again and resubmit.',
            [['label' => 'Back to home', 'href' => '', 'primary' => true], ['label' => '← Go back', 'href' => 'javascript:history.back()']]);
    }

    if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        $_SESSION['error_message'] = "Your session expired or the form was no longer valid. Please try again.";

        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $target = ($referer !== '' && isSameOriginUrl($referer)) ? $referer : appBaseUrl() . '/views/login.php';

        header("Location: " . $target);
        exit;
    }
}

/* =========================
   BOOTSTRAP
========================= */

sendSecurityHeaders();
startSecureSession();
enforceSessionPolicies();
verifyPostRequest();

require_once __DIR__ . '/rbac.php';
