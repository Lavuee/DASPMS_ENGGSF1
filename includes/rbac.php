<?php
/*
    Role-based access control.

    Roles are stored in user.role (Owner, Cashier, Head Mechanic, Customer).
    Pages and actions check a named permission instead of hard-coding roles,
    so changing who may do what only requires editing this map.
*/

const ROLE_PERMISSIONS = [
    'Owner' => [
        'dashboard.owner',
        'users.manage',
        'reports.view',
        'customers.manage', 'customers.archive',
        'vehicles.manage', 'vehicles.archive',
        'pos.use',
        'billing.manage',
        'inventory.manage',
        'suppliers.manage',
        'services.manage',
        'job_orders.view', 'job_orders.manage', 'job_orders.progress',
        'online_orders.manage',
        'service_requests.review',
        'pos.void',          // SRS: only the Owner may void a POS sale
        'activity_log.view',
        'inventory.archive', // SRS 3.2/3.3: Archive Item is an Administrator function
        'pricing.manage',    // SRS UC09 + pricing policy: only the Owner changes selling prices;
                             // SRS 3.4(8): staff cannot view procurement (cost) pricing
    ],
    'Cashier' => [
        'dashboard.cashier',
        'customers.manage',
        'vehicles.manage',
        'pos.use',
        'billing.manage',
        'inventory.manage',
        'suppliers.manage',
        'services.manage',
        'job_orders.view', 'job_orders.manage', 'job_orders.progress',
        'online_orders.manage',
        'service_requests.review',
    ],
    'Head Mechanic' => [
        'dashboard.mechanic',
        'inventory.view',
        'job_orders.view', 'job_orders.progress',
    ],
    'Customer' => [
        'dashboard.customer',
        'shop.purchase',
        'shop.pay_online',
        'vehicles.own',
        'service_requests.submit',
    ],
];

function roleHasPermission($role, $permission) {
    return in_array($permission, ROLE_PERMISSIONS[$role] ?? [], true);
}

// True when the logged-in user may perform $permission.
function can($permission) {
    return isLoggedIn() && roleHasPermission($_SESSION['role'] ?? '', $permission);
}

// True when the logged-in user has at least one of $permissions.
function canAny(array $permissions) {
    foreach ($permissions as $permission) {
        if (can($permission)) {
            return true;
        }
    }

    return false;
}

/*
    Stops the request unless the user has $permission.
    Guests are sent to the login page; logged-in users without access get a 403.
    $loginPath is relative to the calling script.
*/
function requirePermission($permission, $loginPath = 'login.php') {
    if (!isLoggedIn()) {
        header("Location: " . $loginPath);
        exit;
    }

    if (!can($permission)) {
        denyAccess();
    }
}

function requireAnyPermission(array $permissions, $loginPath = 'login.php') {
    if (!isLoggedIn()) {
        header("Location: " . $loginPath);
        exit;
    }

    if (!canAny($permissions)) {
        denyAccess();
    }
}

/*
    Sends the user back to the page they came from (or their dashboard when there
    is none, e.g. a typed URL) with a notice shown by the sidebar.
*/
function denyAccess() {
    $_SESSION['access_denied_message'] = "Your account does not have permission to open that page.";

    $target = appBaseUrl() . '/views/' . dashboardForRole($_SESSION['role'] ?? '');
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);

    // Only return to pages on this site, and never to the denied page itself (redirect loop).
    if ($referer !== '' && isSameOriginUrl($referer) && parse_url($referer, PHP_URL_PATH) !== $currentPath) {
        $target = $referer;
    }

    header("Location: " . $target, true, 303);
    exit;
}
