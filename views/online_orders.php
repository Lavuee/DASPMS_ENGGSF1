<?php
session_start();

if (!isset($_SESSION['logged_in']) || ($_SESSION['role'] !== 'Cashier' && $_SESSION['role'] !== 'Owner')) {
    header("Location: login.php");
    exit;
}

require_once '../config/Database.php';
require_once '../models/PartOrder.php';

$db = (new Database())->getConnection();
$stmtOrders = (new PartOrder($db))->getAllOrders();
$orders = $stmtOrders->fetchAll(PDO::FETCH_ASSOC);

$counts = [
    'All' => count($orders),
    'Pending' => 0,
    'Approved' => 0,
    'Ready for Pickup' => 0,
    'Completed' => 0,
    'Cancelled' => 0
];

foreach ($orders as $order) {
    $status = $order['status'] ?? 'Pending';

    if (isset($counts[$status])) {
        $counts[$status]++;
    }
}

function isGcashOrder($paymentMethod) {
    $paymentMethod = trim((string) $paymentMethod);

    return in_array($paymentMethod, [
        'GCash Down Payment',
        'GCash Full Payment',
        'GCash Reservation'
    ]);
}

function getOrderStatusBadgeClass($status) {
    switch ($status) {
        case 'Pending':
            return 'badge-soft-danger';
        case 'Approved':
            return 'badge-soft-primary';
        case 'Ready for Pickup':
            return 'badge-soft-warning';
        case 'Completed':
            return 'badge-soft-success';
        case 'Cancelled':
            return 'badge-soft-secondary text-dark';
        default:
            return 'badge-soft-secondary';
    }
}

function getPaymentBadgeClass($paymentStatus) {
    switch ($paymentStatus) {
        case 'Paid':
            return 'badge-soft-success';
        case 'Partial':
            return 'badge-soft-warning';
        case 'Not Paid':
            return 'badge-soft-danger';
        case 'No Invoice':
            return 'badge-soft-secondary';
        default:
            return 'badge-soft-secondary';
    }
}

function getGcashVerificationBadgeClass($status) {
    switch ($status) {
        case 'Verified':
            return 'badge-soft-success';
        case 'Rejected':
            return 'badge-soft-danger';
        case 'Pending Verification':
            return 'badge-soft-warning';
        case 'Not Required':
            return 'badge-soft-secondary';
        default:
            return 'badge-soft-secondary';
    }
}

function getAllowedNextStatuses($status, $paymentStatus, $paymentMethod, $gcashVerificationStatus, $amountPaid = 0) {
    $isGcash = isGcashOrder($paymentMethod);
    $gcashIsVerified = $gcashVerificationStatus === 'Verified';
    $hasRecordedPayment = floatval($amountPaid) > 0 || $paymentStatus === 'Partial' || $paymentStatus === 'Paid';

    if ($status === 'Pending') {
        if ($isGcash) {
            if ($gcashIsVerified) {
                return ['Approved' => 'Approve'];
            }

            return ['Cancelled' => 'Cancel'];
        }

        return ['Approved' => 'Approve', 'Cancelled' => 'Cancel'];
    }

    if ($status === 'Approved') {
        if ($isGcash) {
            if ($gcashIsVerified) {
                return ['Ready for Pickup' => 'Ready for Pickup'];
            }

            return [];
        }

        return ['Ready for Pickup' => 'Ready for Pickup', 'Cancelled' => 'Cancel'];
    }

    if ($status === 'Ready for Pickup') {
        if ($paymentStatus === 'Paid') {
            return ['Completed' => 'Complete'];
        }

        if (!$hasRecordedPayment) {
            return ['Cancelled' => 'Cancel'];
        }
    }

    return [];
}

function cleanDisplay($value, $fallback = 'N/A') {
    $value = trim(html_entity_decode((string)($value ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    if ($value === '') {
        return htmlspecialchars($fallback, ENT_QUOTES, 'UTF-8');
    }

    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Web Orders - Norily's Repair Shop</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/style.css">

<style>
    .main-content {
        overflow-x: hidden;
    }

    .web-orders-page {
        width: 100%;
        max-width: 100%;
    }

    .wo-header {
        margin-bottom: 1.5rem;
    }

    .wo-header h2 {
        font-size: 2rem;
        font-weight: 900;
        color: var(--dashboard-text-main);
        margin-bottom: 0.25rem;
        line-height: 1.1;
    }

    .wo-count-text {
        color: var(--dashboard-text-muted);
        font-size: 0.95rem;
        font-weight: 500;
        margin-bottom: 0;
    }

    .wo-filter-area {
        display: grid;
        grid-template-columns: minmax(260px, 1fr) 240px 105px;
        gap: 0.85rem;
        align-items: end;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid #e5e7eb;
    }

    .wo-filter-label {
        display: block;
        color: var(--dashboard-text-muted);
        font-size: 0.76rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.35px;
        margin-bottom: 0.35rem;
    }

    .wo-search-bar {
        min-height: 44px;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        padding: 0.65rem 0.9rem;
        box-shadow: 0 4px 12px rgba(17, 17, 17, 0.035);
    }

    .wo-search-bar i {
        font-size: 1.05rem;
        color: var(--dashboard-text-muted);
        flex-shrink: 0;
    }

    .wo-search-bar input {
        width: 100%;
        border: none;
        outline: none;
        background: transparent;
        color: var(--dashboard-text-main);
        font-size: 0.92rem;
    }

    .wo-search-bar input::placeholder {
        color: #6b7280;
    }

    .wo-filter-select {
        height: 44px;
        border-radius: 12px;
        border: 1px solid #e5e7eb;
        padding: 0.55rem 0.85rem;
        font-size: 0.92rem;
        color: var(--dashboard-text-main);
        box-shadow: none;
    }

    .wo-filter-select:focus {
        border-color: var(--dashboard-primary);
        box-shadow: 0 0 0 4px rgba(245, 197, 24, 0.18);
    }

    .wo-clear-btn {
        height: 44px;
        border-radius: 999px;
        padding: 0.55rem 0.85rem;
        font-size: 0.9rem;
        font-weight: 800;
        border: 1px solid #e5e7eb;
        background: #fff;
        color: var(--dashboard-text-main);
        transition: 0.2s ease;
    }

    .wo-clear-btn:hover {
        background: #f8fafc;
        color: var(--black);
    }

    .wo-table-wrap {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .wo-table {
        width: 100%;
        min-width: 1240px;
        border-collapse: collapse;
        background: transparent;
        margin-bottom: 0;
    }

    .wo-table thead th {
        color: var(--dashboard-text-muted);
        font-size: 0.82rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.35px;
        padding: 0.9rem 0.75rem;
        border-bottom: 1px solid #dcdfe4;
        background: transparent;
        white-space: nowrap;
    }

    .wo-table tbody td {
        padding: 1rem 0.75rem;
        border-bottom: 1px solid #e8ebef;
        vertical-align: middle;
        color: var(--dashboard-text-main);
        font-size: 0.95rem;
        background: transparent;
    }

    .wo-table tbody tr:hover td {
        background: rgba(245, 197, 24, 0.035);
    }

    .wo-table th:last-child,
    .wo-table td:last-child {
        min-width: 175px;
    }

    .wo-order-id,
    .wo-customer,
    .order-items,
    .pickup-schedule {
        color: var(--dashboard-text-main);
        font-weight: 900;
    }

    .wo-order-id,
    .pickup-schedule,
    .wo-total {
        white-space: nowrap;
    }

    .wo-order-date,
    .wo-order-sub,
    .pickup-time,
    .payment-meta,
    .order-note {
        display: block;
        color: var(--dashboard-text-muted);
        font-size: 0.78rem;
        font-weight: 600;
        line-height: 1.45;
        margin-top: 0.25rem;
    }

    .wo-customer {
        margin-top: 0.35rem;
        max-width: 190px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .order-items {
        max-width: 250px;
        line-height: 1.45;
    }

    .pickup-note,
    .payment-detail-note,
    .verification-note {
        display: block;
        margin-top: 0.35rem;
        color: var(--dashboard-text-muted);
        font-size: 0.78rem;
        line-height: 1.45;
        max-width: 260px;
        white-space: normal;
    }

    .wo-total {
        color: #047857;
        font-size: 0.95rem;
        font-weight: 900;
    }

    .badge-soft-danger,
    .badge-soft-primary,
    .badge-soft-warning,
    .badge-soft-success,
    .badge-soft-secondary {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        padding: 0.42rem 0.72rem;
        font-size: 0.78rem;
        font-weight: 900;
        white-space: nowrap;
    }

    .badge-soft-danger {
        background: #fee2e2;
        color: #b91c1c;
    }

    .badge-soft-primary {
        background: #e9f2ff;
        color: #1d4ed8;
    }

    .badge-soft-warning {
        background: #fef3c7;
        color: #92400e;
    }

    .badge-soft-success {
        background: #dcfce7;
        color: #047857;
    }

    .badge-soft-secondary {
        background: #f1f5f9;
        color: #475569;
    }

    .payment-details-box {
        min-width: 310px;
        max-width: 370px;
    }

    .payment-summary-title {
        color: var(--dashboard-text-muted);
        font-size: 0.7rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        margin-bottom: 0.42rem;
    }

    .payment-badge-row {
        display: flex;
        align-items: center;
        gap: 0.45rem;
        flex-wrap: wrap;
        margin-bottom: 0.6rem;
    }

    .payment-status-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.28rem;
        border-radius: 999px;
        padding: 0.42rem 0.72rem;
        font-size: 0.76rem;
        font-weight: 900;
        white-space: nowrap;
    }

    .payment-chip-label {
        opacity: 0.82;
        font-size: 0.68rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.2px;
    }

    .payment-section {
        margin-top: 0.65rem;
        padding-top: 0.65rem;
        border-top: 1px solid #edf0f4;
    }

    .payment-label {
        display: block;
        color: var(--dashboard-text-muted);
        font-size: 0.7rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        margin-bottom: 0.18rem;
    }

    .payment-value {
        color: var(--dashboard-text-main);
        font-size: 0.86rem;
        font-weight: 900;
        line-height: 1.35;
    }

    .payment-amount-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.45rem;
        margin-top: 0.45rem;
    }

    .payment-mini-box {
        border: 1px solid #edf0f4;
        border-radius: 12px;
        padding: 0.45rem 0.55rem;
        background: rgba(248, 250, 252, 0.65);
    }

    .payment-mini-label {
        color: var(--dashboard-text-muted);
        font-size: 0.66rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.25px;
        line-height: 1.15;
    }

    .payment-mini-value {
        color: var(--dashboard-text-main);
        font-size: 0.78rem;
        font-weight: 900;
        white-space: nowrap;
        margin-top: 0.15rem;
    }

    .gcash-verification-form {
        margin: 0.7rem 0 0;
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 0.45rem;
        max-width: 300px;
    }

    .gcash-verification-select,
    .order-action-select {
        border-radius: 12px;
        border: 1px solid #e5e7eb;
        font-size: 0.84rem;
        padding: 0.48rem 0.65rem;
        box-shadow: none;
    }

    .gcash-verification-select:focus,
    .order-action-select:focus {
        border-color: var(--dashboard-primary);
        box-shadow: 0 0 0 4px rgba(245, 197, 24, 0.18);
    }

    .gcash-verification-btn,
    .order-update-btn {
        border-radius: 999px;
        background: var(--dashboard-primary);
        border: 1px solid var(--dashboard-primary);
        color: var(--black);
        font-size: 0.82rem;
        padding: 0.48rem 0.75rem;
        font-weight: 900;
        transition: 0.2s ease;
        white-space: nowrap;
    }

    .gcash-verification-btn:hover,
    .order-update-btn:hover {
        background: var(--black);
        border-color: var(--black);
        color: var(--white);
    }

    .order-action-form {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 0.45rem;
        margin: 0;
    }

    .order-action-select,
    .order-update-btn {
        width: 160px;
    }

    .order-no-action,
    .order-completed-text,
    .order-cancelled-text {
        display: block;
        color: var(--dashboard-text-muted);
        font-size: 0.85rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .order-completed-text {
        color: #047857;
        font-style: normal;
    }

    .order-cancelled-text {
        color: #6b7280;
        font-style: normal;
    }

    .wo-empty-state {
        text-align: center;
        color: var(--dashboard-text-muted);
        padding: 3rem 1rem;
    }

    .wo-pagination-wrap {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 0.35rem;
        margin-top: 1rem;
        flex-wrap: wrap;
    }

    .wo-page-btn {
        min-width: 38px;
        min-height: 38px;
        border: 1px solid #e5e7eb;
        background: #fff;
        color: var(--dashboard-text-muted);
        border-radius: 999px;
        font-size: 0.82rem;
        font-weight: 900;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.45rem 0.75rem;
        transition: 0.2s ease;
    }

    .wo-page-btn:hover:not(:disabled) {
        border-color: var(--dashboard-primary);
        background: #fffaf0;
        color: var(--black);
    }

    .wo-page-btn.active {
        background: var(--dashboard-primary);
        border-color: var(--dashboard-primary);
        color: var(--black);
    }

    .wo-page-btn:disabled {
        opacity: 0.45;
        cursor: not-allowed;
    }

    .alert {
        border-radius: 16px;
    }

    @media (max-width: 991.98px) {
        .wo-filter-area {
            grid-template-columns: 1fr;
        }

        .wo-clear-btn {
            width: 100%;
        }

        .order-action-form {
            align-items: stretch;
        }

        .order-action-select,
        .order-update-btn,
        .gcash-verification-form {
            width: 100%;
            max-width: 100%;
        }

        .gcash-verification-form {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 767.98px) {
        .wo-header h2 {
            font-size: 1.75rem;
        }

        .wo-search-bar,
        .wo-filter-select,
        .wo-clear-btn {
            height: 42px;
        }

        .wo-pagination-wrap {
            justify-content: center;
        }
    }
</style>
</head>

<body>

<div class="app-wrapper">
<?php include '../includes/sidebar.php'; ?>

<main class="main-content">
    <div class="web-orders-page">

        <div class="wo-header">
            <h2>Customer Web Orders</h2>
            <p class="wo-count-text">
                <?php echo $counts['All']; ?> total web orders from the customer portal
            </p>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show mb-4">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?php echo htmlspecialchars($_SESSION['success_message'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['success_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4">
                <i class="bi bi-exclamation-circle-fill me-2"></i>
                <?php echo htmlspecialchars($_SESSION['error_message'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['error_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="wo-filter-area">
            <div>
                <label class="wo-filter-label">Search</label>
                <div class="wo-search-bar">
                    <i class="bi bi-search"></i>
                    <input type="text" id="orderSearch" placeholder="Search order, customer, part, pickup date, payment method, status...">
                </div>
            </div>

            <div>
                <label class="wo-filter-label">Status</label>
                <select id="statusFilter" class="form-select wo-filter-select">
                    <option value="All">All (<?php echo $counts['All']; ?>)</option>
                    <option value="Pending">Pending (<?php echo $counts['Pending']; ?>)</option>
                    <option value="Approved">Approved (<?php echo $counts['Approved']; ?>)</option>
                    <option value="Ready for Pickup">Ready for Pickup (<?php echo $counts['Ready for Pickup']; ?>)</option>
                    <option value="Completed">Completed (<?php echo $counts['Completed']; ?>)</option>
                    <option value="Cancelled">Cancelled (<?php echo $counts['Cancelled']; ?>)</option>
                </select>
            </div>

            <button type="button" id="clearFilters" class="wo-clear-btn">Clear</button>
        </div>

        <div class="wo-table-wrap">
            <table class="wo-table" id="onlineOrdersTable">
                <thead>
                    <tr>
                        <th>Order / Customer</th>
                        <th>Item Details</th>
                        <th>Pickup Schedule</th>
                        <th>Total Cost</th>
                        <th>Order Status</th>
                        <th>Payment Details</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (count($orders) > 0): ?>
                        <?php foreach ($orders as $row): ?>
                            <?php
                                $status = trim($row['status'] ?? 'Pending');
                                $badge = getOrderStatusBadgeClass($status);

                                $paymentStatus = trim($row['payment_status'] ?? 'No Invoice');
                                $paymentBadge = getPaymentBadgeClass($paymentStatus);
                                $amountPaid = floatval($row['amount_paid'] ?? 0);

                                $customerName = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
                                $itemsRaw = $row['items'] ?? '';
                                $itemsPlain = strip_tags(str_replace('<br>', ' ', $itemsRaw));

                                $pickupDate = trim($row['preferred_pickup_date'] ?? '');
                                $pickupTime = trim($row['preferred_pickup_time'] ?? '');
                                $pickupNotes = trim($row['pickup_notes'] ?? '');

                                $pickupDateDisplay = $pickupDate !== '' ? date('M d, Y', strtotime($pickupDate)) : 'No pickup schedule';
                                $pickupTimeDisplay = $pickupTime !== '' ? date('h:i A', strtotime($pickupTime)) : '';

                                $paymentMethod = trim($row['payment_method'] ?? 'Cash on Pickup');
                                $gcashReference = trim($row['gcash_reference'] ?? '');
                                $gcashAmount = floatval($row['gcash_payment_amount'] ?? 0);
                                $paymentNotes = trim($row['payment_notes'] ?? '');

                                $gcashVerificationStatus = trim($row['gcash_verification_status'] ?? '');

                                if ($gcashVerificationStatus === '' && isGcashOrder($paymentMethod)) {
                                    $gcashVerificationStatus = 'Pending Verification';
                                }

                                if ($gcashVerificationStatus === '' && !isGcashOrder($paymentMethod)) {
                                    $gcashVerificationStatus = 'Not Required';
                                }

                                $gcashVerificationBadge = getGcashVerificationBadgeClass($gcashVerificationStatus);
                                $gcashVerifierName = trim(($row['gcash_verifier_first_name'] ?? '') . ' ' . ($row['gcash_verifier_last_name'] ?? ''));

                                $shopPaidAmount = 0;

                                if (isGcashOrder($paymentMethod)) {
                                    $shopPaidAmount = max($amountPaid - $gcashAmount, 0);
                                } else {
                                    $shopPaidAmount = $amountPaid;
                                }

                                $allowedNextStatuses = getAllowedNextStatuses(
                                    $status,
                                    $paymentStatus,
                                    $paymentMethod,
                                    $gcashVerificationStatus,
                                    $amountPaid
                                );

                                $searchText = strtolower(
                                    '#' . ($row['order_id'] ?? '') . ' ' .
                                    $customerName . ' ' .
                                    $itemsPlain . ' ' .
                                    $pickupDateDisplay . ' ' .
                                    $pickupTimeDisplay . ' ' .
                                    $pickupNotes . ' ' .
                                    $paymentMethod . ' ' .
                                    $gcashReference . ' ' .
                                    $gcashVerificationStatus . ' ' .
                                    $paymentNotes . ' ' .
                                    $status . ' ' .
                                    $paymentStatus
                                );
                            ?>

                            <tr class="order-row" data-status="<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>" data-search="<?php echo htmlspecialchars($searchText, ENT_QUOTES, 'UTF-8'); ?>">
                                <td>
                                    <div class="wo-order-id">#<?php echo intval($row['order_id']); ?></div>
                                    <span class="wo-order-date">
                                        <?php echo !empty($row['order_date']) ? date('M d, Y', strtotime($row['order_date'])) : 'N/A'; ?>
                                    </span>
                                    <div class="wo-customer" title="<?php echo cleanDisplay($customerName); ?>">
                                        <?php echo cleanDisplay($customerName); ?>
                                    </div>
                                    <div class="wo-order-sub">Customer web order</div>
                                </td>

                                <td>
                                    <div class="order-items"><?php echo $itemsRaw; ?></div>
                                </td>

                                <td>
                                    <?php if ($pickupDate !== ''): ?>
                                        <div class="pickup-schedule">
                                            <?php echo htmlspecialchars($pickupDateDisplay, ENT_QUOTES, 'UTF-8'); ?>
                                            <?php if ($pickupTimeDisplay !== ''): ?>
                                                <span class="pickup-time"><?php echo htmlspecialchars($pickupTimeDisplay, ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php endif; ?>
                                        </div>

                                        <?php if ($pickupNotes !== ''): ?>
                                            <span class="pickup-note"><?php echo cleanDisplay($pickupNotes, ''); ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="order-note">No pickup schedule</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <div class="wo-total">₱<?php echo number_format(floatval($row['total_amount'] ?? 0), 2); ?></div>
                                </td>

                                <td>
                                    <span class="<?php echo $badge; ?>">
                                        <?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>

                                <td>
                                    <div class="payment-details-box">
                                        <div class="payment-badge-row">
                                            <span class="payment-status-chip <?php echo $paymentBadge; ?>">
                                                <span class="payment-chip-label">Payment:</span>
                                                <?php echo htmlspecialchars($paymentStatus, ENT_QUOTES, 'UTF-8'); ?>
                                            </span>

                                            <?php if (isGcashOrder($paymentMethod)): ?>
                                                <span class="payment-status-chip <?php echo $gcashVerificationBadge; ?>">
                                                    <span class="payment-chip-label">GCash:</span>
                                                    <?php echo htmlspecialchars($gcashVerificationStatus, ENT_QUOTES, 'UTF-8'); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <div class="payment-section">
                                            <span class="payment-label">Payment Method</span>
                                            <div class="payment-value">
                                                <?php echo htmlspecialchars($paymentMethod, ENT_QUOTES, 'UTF-8'); ?>
                                            </div>
                                        </div>

                                        <div class="payment-section">
                                            <span class="payment-label">Amount Breakdown</span>

                                            <div class="payment-amount-grid">
                                                <?php if (isGcashOrder($paymentMethod)): ?>
                                                    <div class="payment-mini-box">
                                                        <div class="payment-mini-label">
                                                            <?php echo $paymentMethod === 'GCash Full Payment' ? 'GCash Paid' : 'GCash Down Payment'; ?>
                                                        </div>
                                                        <div class="payment-mini-value">₱<?php echo number_format($gcashAmount, 2); ?></div>
                                                    </div>

                                                    <div class="payment-mini-box">
                                                        <div class="payment-mini-label">Paid at Shop</div>
                                                        <div class="payment-mini-value">₱<?php echo number_format($shopPaidAmount, 2); ?></div>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="payment-mini-box">
                                                        <div class="payment-mini-label">Paid at Shop</div>
                                                        <div class="payment-mini-value">₱<?php echo number_format($shopPaidAmount, 2); ?></div>
                                                    </div>
                                                <?php endif; ?>

                                                <div class="payment-mini-box">
                                                    <div class="payment-mini-label">Total Paid</div>
                                                    <div class="payment-mini-value">₱<?php echo number_format($amountPaid, 2); ?></div>
                                                </div>

                                                <div class="payment-mini-box">
                                                    <div class="payment-mini-label">Balance</div>
                                                    <div class="payment-mini-value">₱<?php echo number_format(floatval($row['balance_due'] ?? 0), 2); ?></div>
                                                </div>
                                            </div>
                                        </div>

                                        <?php if (isGcashOrder($paymentMethod)): ?>
                                            <div class="payment-section">
                                                <span class="payment-label">GCash Verification</span>

                                                <div class="payment-meta">
                                                    Reference No.: <?php echo $gcashReference !== '' ? htmlspecialchars($gcashReference, ENT_QUOTES, 'UTF-8') : 'N/A'; ?>
                                                </div>

                                                <?php if (!empty($row['gcash_verified_at'])): ?>
                                                    <span class="verification-note">
                                                        Checked: <?php echo date('M d, Y h:i A', strtotime($row['gcash_verified_at'])); ?>
                                                        <?php if ($gcashVerifierName !== ''): ?>
                                                            <br>Verified By: <?php echo cleanDisplay($gcashVerifierName, ''); ?>
                                                        <?php endif; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>

                                            <?php if (!in_array($status, ['Completed', 'Cancelled']) && $gcashVerificationStatus !== 'Verified'): ?>
                                                <form action="../controllers/ManageOrderController.php" method="POST" class="gcash-verification-form" onsubmit="return confirm('Update GCash verification status for this order?');">
                                                    <input type="hidden" name="action" value="update_gcash_verification">
                                                    <input type="hidden" name="order_id" value="<?php echo intval($row['order_id']); ?>">

                                                    <select name="gcash_verification_status" class="form-select gcash-verification-select" required>
                                                        <option value="" disabled selected>Verify GCash</option>
                                                        <option value="Verified">Mark Verified</option>
                                                        <option value="Rejected">Reject Reference</option>
                                                        <option value="Pending Verification">Set Pending</option>
                                                    </select>

                                                    <button type="submit" class="gcash-verification-btn">Save</button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <?php if ($paymentNotes !== ''): ?>
                                            <span class="payment-detail-note"><?php echo cleanDisplay($paymentNotes, ''); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td class="text-end">
                                    <?php if (!empty($allowedNextStatuses)): ?>
                                        <form action="../controllers/ManageOrderController.php" method="POST" class="order-action-form" onsubmit="return confirm('Update this web order status?');">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="order_id" value="<?php echo intval($row['order_id']); ?>">

                                            <select name="status" class="form-select order-action-select" required>
                                                <option value="" disabled selected>Select action</option>
                                                <?php foreach ($allowedNextStatuses as $value => $label): ?>
                                                    <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>">
                                                        <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>

                                            <button type="submit" class="order-update-btn">Update</button>
                                        </form>

                                        <?php if (isGcashOrder($paymentMethod) && $gcashVerificationStatus !== 'Verified' && $status === 'Pending'): ?>
                                            <span class="order-note">Verify GCash before approval.</span>
                                        <?php elseif ($status === 'Ready for Pickup' && $paymentStatus !== 'Paid'): ?>
                                            <span class="order-note">Remaining payment is handled in Billing.</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?php if ($status === 'Completed'): ?>
                                            <span class="order-completed-text">Completed</span>
                                        <?php elseif ($status === 'Cancelled'): ?>
                                            <span class="order-cancelled-text">Cancelled</span>
                                        <?php else: ?>
                                            <span class="order-no-action">No action</span>

                                            <?php if (isGcashOrder($paymentMethod) && $gcashVerificationStatus !== 'Verified' && $status === 'Pending'): ?>
                                                <span class="order-note">Verify GCash before approval.</span>
                                            <?php elseif ($status === 'Ready for Pickup' && $paymentStatus === 'Partial'): ?>
                                                <span class="order-note">Cannot cancel after partial payment.</span>
                                            <?php elseif ($status === 'Ready for Pickup' && $paymentStatus === 'Paid'): ?>
                                                <span class="order-note">Paid. Complete once claimed.</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <tr id="noOrderResults" style="display:none;">
                            <td colspan="7">
                                <div class="wo-empty-state">No orders match your search/filter.</div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="7">
                                <div class="wo-empty-state">No web orders found.</div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="wo-pagination-wrap" id="onlineOrderPagination"></div>

    </div>
</main>
</div>

<script>
const ITEMS_PER_PAGE = 5;

const orderSearch = document.getElementById('orderSearch');
const statusFilter = document.getElementById('statusFilter');
const clearFilters = document.getElementById('clearFilters');
const rows = Array.from(document.querySelectorAll('.order-row'));
const noResults = document.getElementById('noOrderResults');
const pagination = document.getElementById('onlineOrderPagination');

let currentPage = 1;

function getFilteredRows() {
    const searchValue = orderSearch ? orderSearch.value.trim().toLowerCase() : '';
    const statusValue = statusFilter ? statusFilter.value : 'All';

    return rows.filter(row => {
        const rowSearch = row.dataset.search || '';
        const rowStatus = row.dataset.status || '';

        return rowSearch.includes(searchValue) && (statusValue === 'All' || rowStatus === statusValue);
    });
}

function renderPagination(totalPages) {
    if (!pagination) {
        return;
    }

    pagination.innerHTML = '';

    if (totalPages <= 1) {
        pagination.style.display = 'none';
        return;
    }

    pagination.style.display = 'flex';

    const prevButton = document.createElement('button');
    prevButton.type = 'button';
    prevButton.className = 'wo-page-btn';
    prevButton.innerHTML = '&laquo;';
    prevButton.disabled = currentPage === 1;
    prevButton.addEventListener('click', function () {
        if (currentPage > 1) {
            currentPage--;
            applyOrderFilters();
        }
    });
    pagination.appendChild(prevButton);

    for (let page = 1; page <= totalPages; page++) {
        const pageButton = document.createElement('button');
        pageButton.type = 'button';
        pageButton.className = 'wo-page-btn' + (page === currentPage ? ' active' : '');
        pageButton.textContent = page;
        pageButton.addEventListener('click', function () {
            currentPage = page;
            applyOrderFilters();
        });
        pagination.appendChild(pageButton);
    }

    const nextButton = document.createElement('button');
    nextButton.type = 'button';
    nextButton.className = 'wo-page-btn';
    nextButton.innerHTML = '&raquo;';
    nextButton.disabled = currentPage === totalPages;
    nextButton.addEventListener('click', function () {
        if (currentPage < totalPages) {
            currentPage++;
            applyOrderFilters();
        }
    });
    pagination.appendChild(nextButton);
}

function applyOrderFilters() {
    const filteredRows = getFilteredRows();
    const totalPages = Math.ceil(filteredRows.length / ITEMS_PER_PAGE) || 1;

    if (currentPage > totalPages) {
        currentPage = totalPages;
    }

    const start = (currentPage - 1) * ITEMS_PER_PAGE;
    const end = start + ITEMS_PER_PAGE;

    rows.forEach(row => {
        row.style.display = 'none';
    });

    filteredRows.slice(start, end).forEach(row => {
        row.style.display = '';
    });

    if (noResults) {
        noResults.style.display = filteredRows.length === 0 && rows.length > 0 ? '' : 'none';
    }

    renderPagination(totalPages);
}

if (orderSearch) {
    orderSearch.addEventListener('input', function () {
        currentPage = 1;
        applyOrderFilters();
    });
}

if (statusFilter) {
    statusFilter.addEventListener('change', function () {
        currentPage = 1;
        applyOrderFilters();
    });
}

if (clearFilters) {
    clearFilters.addEventListener('click', function () {
        if (orderSearch) {
            orderSearch.value = '';
        }

        if (statusFilter) {
            statusFilter.value = 'All';
        }

        currentPage = 1;
        applyOrderFilters();
    });
}

applyOrderFilters();
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>