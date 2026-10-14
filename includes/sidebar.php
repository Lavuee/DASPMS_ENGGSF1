<?php 
$currentPage = basename($_SERVER['PHP_SELF']);
$userInitial = strtoupper(substr($_SESSION['first_name'], 0, 1));
$userRole = $_SESSION['role'];

$dashLink = dashboardForRole($userRole);

/*
    Financial modules such as Billing, Payment, and Invoice History
    should only be accessible to roles assigned to payment/billing work.
    Head Mechanic should not see these modules.
*/
$canAccessFinancialModules = can('billing.manage');

$cartCount = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cartCount += intval($item['qty'] ?? 0);
    }
}

// Every role now uses a top header instead of the old left sidebar:
// customers get the customer portal header, staff (Owner, Cashier, Head Mechanic) the staff header.
if ($userRole === 'Customer') {
    include __DIR__ . '/customer_header.php';
} else {
    include __DIR__ . '/staff_header.php';
}
