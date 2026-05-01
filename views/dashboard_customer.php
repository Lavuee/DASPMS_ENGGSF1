<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Customer') {
    header("Location: login.php");
    exit;
}

require_once '../config/Database.php';

$db = (new Database())->getConnection();

$stmtC = $db->prepare("
    SELECT customer_id
    FROM customer
    WHERE user_id = ?
    LIMIT 1
");
$stmtC->execute([$_SESSION['user_id']]);
$customer_id = $stmtC->fetchColumn();

if (!$customer_id) {
    die("Customer profile not found.");
}

$stmtJO = $db->prepare("
    SELECT 
        jo.job_order_number,
        v.plate_number,
        v.make,
        v.model,
        jo.status,
        i.payment_status,
        i.balance_due,
        jo.date_created
    FROM job_order jo
    JOIN vehicle v ON jo.vehicle_id = v.vehicle_id
    LEFT JOIN invoice i ON jo.job_order_id = i.job_order_id
    WHERE jo.customer_id = ?
    ORDER BY jo.date_created DESC
");
$stmtJO->execute([$customer_id]);
$jobOrders = $stmtJO->fetchAll(PDO::FETCH_ASSOC);

$stmtOrders = $db->prepare("
    SELECT 
        po.order_id,
        po.order_date,
        po.status,
        po.total_amount,
        GROUP_CONCAT(
            CONCAT(p.part_name, ' x', poi.quantity)
            ORDER BY p.part_name ASC
            SEPARATOR '<br>'
        ) AS items
    FROM part_order po
    JOIN part_order_item poi ON po.order_id = poi.order_id
    JOIN part p ON poi.part_id = p.part_id
    WHERE po.customer_id = ?
    GROUP BY 
        po.order_id,
        po.order_date,
        po.status,
        po.total_amount
    ORDER BY po.order_date DESC
");
$stmtOrders->execute([$customer_id]);
$orders = $stmtOrders->fetchAll(PDO::FETCH_ASSOC);

$activeRepairCount = 0;
$pendingPaymentCount = 0;
$pendingBalanceTotal = 0;
$readyPickupCount = 0;

foreach ($jobOrders as $repair) {
    if (($repair['status'] ?? '') !== 'Completed' && ($repair['status'] ?? '') !== 'Cancelled') {
        $activeRepairCount++;
    }

    $paymentStatus = $repair['payment_status'] ?? 'Not Paid';

    if ($paymentStatus !== 'Paid') {
        $pendingPaymentCount++;
        $pendingBalanceTotal += floatval($repair['balance_due'] ?? 0);
    }
}

foreach ($orders as $order) {
    if (($order['status'] ?? '') === 'Ready for Pickup') {
        $readyPickupCount++;
    }
}

function getCustomerRepairBadge($status) {
    switch ($status) {
        case 'Pending':
            return 'customer-status status-pending';
        case 'In Progress':
            return 'customer-status status-progress';
        case 'Ready for Pickup':
            return 'customer-status status-ready';
        case 'Completed':
            return 'customer-status status-completed';
        case 'Cancelled':
            return 'customer-status status-cancelled';
        default:
            return 'customer-status status-muted';
    }
}

function getCustomerOrderBadge($status) {
    switch ($status) {
        case 'Pending':
            return 'customer-status status-pending';
        case 'Approved':
            return 'customer-status status-progress';
        case 'Ready for Pickup':
            return 'customer-status status-ready';
        case 'Completed':
            return 'customer-status status-completed';
        case 'Cancelled':
            return 'customer-status status-cancelled';
        default:
            return 'customer-status status-muted';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Customer Portal - Norily's Repair Shop</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/style.css">

<style>
    .main-content {
        overflow-x: hidden;
    }

    .customer-dashboard {
        width: 100%;
        max-width: 100%;
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
    }

    .customer-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        margin-bottom: 0.15rem;
    }

    .customer-title h2 {
        font-size: 2rem;
        font-weight: 900;
        color: var(--dashboard-text-main);
        margin-bottom: 0.25rem;
        line-height: 1.1;
    }

    .customer-title p {
        color: var(--dashboard-text-muted);
        font-size: 0.95rem;
        font-weight: 500;
        margin-bottom: 0;
    }

    .customer-date-pill {
        height: 44px;
        background: rgba(255, 255, 255, 0.56);
        border: 1px solid rgba(15, 23, 42, 0.08);
        color: var(--dashboard-text-muted);
        border-radius: 999px;
        padding: 0.58rem 0.95rem;
        font-size: 0.9rem;
        font-weight: 800;
        white-space: nowrap;
        display: inline-flex;
        align-items: center;
        box-shadow: none;
    }

    .customer-alert {
        border-radius: 16px;
        font-size: 0.92rem;
        margin-bottom: 0;
    }

    .customer-actions-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0.9rem;
    }

    .customer-action-card {
        min-height: 120px;
        background: rgba(255, 255, 255, 0.34);
        border: 1px solid rgba(15, 23, 42, 0.05);
        border-radius: 16px;
        padding: 1.05rem 1.1rem;
        text-decoration: none;
        color: var(--dashboard-text-main);
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        box-shadow: none;
        position: relative;
        transition: all 0.2s ease;
    }

    .customer-action-card::before {
        content: "";
        position: absolute;
        left: 0;
        top: 12px;
        bottom: 12px;
        width: 3px;
        border-radius: 999px;
        background: rgba(245, 197, 24, 0.85);
    }

    .customer-action-card:hover {
        color: var(--dashboard-text-main);
        transform: translateY(-2px);
        background: rgba(255, 255, 255, 0.58);
        border-color: rgba(245, 197, 24, 0.45);
    }

    .action-title {
        font-size: 1rem;
        font-weight: 900;
        margin-bottom: 0.25rem;
        color: var(--dashboard-text-main);
    }

    .action-subtitle {
        color: var(--dashboard-text-muted);
        font-size: 0.84rem;
        line-height: 1.45;
        margin-bottom: 0;
    }

    .action-icon {
        width: 44px;
        height: 44px;
        border-radius: 13px;
        background: rgba(245, 197, 24, 0.16);
        color: var(--black);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.15rem;
        flex-shrink: 0;
    }

    .customer-overview-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0.9rem;
    }

    .overview-card {
        min-height: 105px;
        background: rgba(255, 255, 255, 0.34);
        border: 1px solid rgba(15, 23, 42, 0.05);
        border-radius: 16px;
        padding: 1rem 1.1rem;
        box-shadow: none;
    }

    .overview-label {
        color: var(--dashboard-text-muted);
        font-size: 0.84rem;
        font-weight: 800;
        margin-bottom: 0.35rem;
    }

    .overview-value {
        color: var(--dashboard-text-main);
        font-size: 1.45rem;
        font-weight: 900;
        margin-bottom: 0.25rem;
        line-height: 1.1;
    }

    .overview-caption {
        color: var(--dashboard-text-muted);
        font-size: 0.78rem;
        margin-bottom: 0;
        line-height: 1.35;
    }

    .customer-main-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.35fr) minmax(320px, 0.75fr);
        gap: 1rem;
        align-items: start;
    }

    .customer-panel {
        background: rgba(255, 255, 255, 0.34);
        border: 1px solid rgba(15, 23, 42, 0.05);
        border-radius: 16px;
        box-shadow: none;
        overflow: hidden;
    }

    .panel-header {
        padding: 1.1rem 1.15rem;
        border-bottom: 1px solid rgba(15, 23, 42, 0.06);
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
    }

    .panel-header h5 {
        font-size: 1rem;
        font-weight: 900;
        color: var(--dashboard-text-main);
        margin-bottom: 0.2rem;
    }

    .panel-header p {
        color: var(--dashboard-text-muted);
        font-size: 0.86rem;
        margin-bottom: 0;
    }

    .section-link {
        color: var(--dashboard-text-muted);
        text-decoration: none;
        font-weight: 800;
        font-size: 0.86rem;
        white-space: nowrap;
        transition: color 0.2s ease;
    }

    .section-link:hover {
        color: var(--black);
    }

    .repair-list {
        display: grid;
        gap: 0;
    }

    .repair-item {
        padding: 1rem 1.15rem;
        border-bottom: 1px solid rgba(15, 23, 42, 0.05);
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        transition: background 0.2s ease;
    }

    .repair-item:last-child {
        border-bottom: none;
    }

    .repair-item:hover {
        background: rgba(245, 197, 24, 0.03);
    }

    .repair-left {
        display: flex;
        align-items: center;
        gap: 0.8rem;
        min-width: 0;
    }

    .repair-icon {
        width: 42px;
        height: 42px;
        border-radius: 13px;
        background: rgba(245, 197, 24, 0.16);
        color: var(--black);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .repair-title {
        font-size: 0.95rem;
        font-weight: 900;
        color: var(--dashboard-text-main);
        margin-bottom: 0.18rem;
    }

    .repair-title span {
        color: var(--dashboard-text-muted);
        font-weight: 700;
    }

    .repair-sub {
        color: var(--dashboard-text-muted);
        font-size: 0.8rem;
        line-height: 1.4;
    }

    .repair-right {
        text-align: right;
        flex-shrink: 0;
    }

    .customer-status {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        padding: 0.42rem 0.72rem;
        font-size: 0.78rem;
        font-weight: 900;
        white-space: nowrap;
    }

    .status-pending {
        background: #fef3c7;
        color: #92400e;
    }

    .status-progress {
        background: #e9f2ff;
        color: #1d4ed8;
    }

    .status-ready {
        background: #f4ecff;
        color: #7c3aed;
    }

    .status-completed {
        background: #dcfce7;
        color: #047857;
    }

    .status-cancelled {
        background: #f1f5f9;
        color: #475569;
    }

    .status-muted {
        background: #f1f5f9;
        color: #475569;
    }

    .payment-text {
        margin-top: 0.45rem;
        font-size: 0.8rem;
        font-weight: 900;
    }

    .payment-paid {
        color: #047857;
    }

    .payment-partial {
        color: #b45309;
    }

    .payment-unpaid {
        color: #dc2626;
    }

    .orders-table-wrap {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .orders-table {
        width: 100%;
        min-width: 760px;
        border-collapse: collapse;
        background: transparent;
        margin-bottom: 0;
    }

    .orders-table th {
        color: var(--dashboard-text-muted);
        font-size: 0.8rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.35px;
        padding: 0.85rem 1rem;
        border-bottom: 1px solid rgba(15, 23, 42, 0.08);
        background: transparent;
        white-space: nowrap;
    }

    .orders-table td {
        padding: 0.95rem 1rem;
        color: var(--dashboard-text-main);
        vertical-align: middle;
        border-bottom: 1px solid rgba(15, 23, 42, 0.055);
        font-size: 0.92rem;
        background: transparent;
    }

    .orders-table tbody tr:hover td {
        background: rgba(245, 197, 24, 0.03);
    }

    .order-id {
        font-weight: 900;
        color: var(--dashboard-text-main);
        white-space: nowrap;
    }

    .order-items {
        font-weight: 800;
        line-height: 1.55;
    }

    .order-total {
        font-weight: 900;
        color: #047857;
        white-space: nowrap;
    }

    .order-note {
        display: block;
        margin-top: 0.3rem;
        color: var(--dashboard-text-muted);
        font-size: 0.76rem;
        font-weight: 600;
        line-height: 1.35;
    }

    .customer-note {
        background: rgba(245, 197, 24, 0.08);
        border: 1px solid rgba(245, 197, 24, 0.18);
        border-radius: 16px;
        padding: 1rem 1.15rem;
    }

    .customer-note h6 {
        font-size: 0.95rem;
        font-weight: 900;
        color: var(--dashboard-text-main);
        margin-bottom: 0.35rem;
    }

    .customer-note p {
        color: var(--dashboard-text-muted);
        font-size: 0.84rem;
        line-height: 1.55;
        margin-bottom: 0;
    }

    .pickup-list {
        display: grid;
        gap: 0;
    }

    .pickup-item {
        padding: 0.95rem 1.15rem;
        border-bottom: 1px solid rgba(15, 23, 42, 0.05);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }

    .pickup-item:last-child {
        border-bottom: none;
    }

    .pickup-title {
        font-size: 0.92rem;
        font-weight: 900;
        color: var(--dashboard-text-main);
        margin-bottom: 0.15rem;
    }

    .pickup-sub {
        color: var(--dashboard-text-muted);
        font-size: 0.78rem;
        margin-bottom: 0;
    }

    .pickup-price {
        color: #047857;
        font-weight: 900;
        font-size: 0.9rem;
        white-space: nowrap;
    }

    .empty-state {
        padding: 2.5rem 1.5rem;
        text-align: center;
        color: var(--dashboard-text-muted);
        font-size: 0.92rem;
    }

    .empty-state i {
        display: block;
        color: var(--dashboard-primary);
        font-size: 2rem;
        margin-bottom: 0.65rem;
    }

    @media (max-width: 1199.98px) {
        .customer-actions-grid,
        .customer-overview-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .customer-main-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 767.98px) {
        .customer-header {
            flex-direction: column;
            align-items: stretch;
        }

        .customer-title h2 {
            font-size: 1.75rem;
        }

        .customer-date-pill {
            width: 100%;
            justify-content: center;
        }

        .customer-actions-grid,
        .customer-overview-grid {
            grid-template-columns: 1fr;
        }

        .customer-action-card {
            min-height: 105px;
        }

        .repair-item {
            flex-direction: column;
            align-items: flex-start;
        }

        .repair-right {
            text-align: left;
            width: 100%;
        }
    }
</style>
</head>

<body>

<div class="app-wrapper">
<?php include '../includes/sidebar.php'; ?>

<main class="main-content">
    <div class="customer-dashboard">

        <div class="customer-header">
            <div class="customer-title">
                <h2>
                    Good <?php echo (date('H') < 12) ? 'Morning' : ((date('H') < 18) ? 'Afternoon' : 'Evening'); ?>,
                    <?php echo htmlspecialchars($_SESSION['first_name']); ?>!
                </h2>
                <p>Welcome to your customer portal.</p>
            </div>

            <div class="customer-date-pill">
                <i class="bi bi-calendar3 me-2"></i>
                <?php echo date('F d, Y'); ?>
            </div>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show customer-alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show customer-alert">
                <i class="bi bi-exclamation-circle-fill me-2"></i>
                <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="customer-actions-grid">
            <a href="browse_parts.php" class="customer-action-card">
                <div>
                    <div class="action-title">Browse Parts</div>
                    <p class="action-subtitle">View available auto parts and choose items for pickup.</p>
                </div>
                <div class="action-icon">
                    <i class="bi bi-box-seam"></i>
                </div>
            </a>

            <a href="cart.php" class="customer-action-card">
                <div>
                    <div class="action-title">My Cart</div>
                    <p class="action-subtitle">Review selected parts before submitting your order.</p>
                </div>
                <div class="action-icon">
                    <i class="bi bi-cart3"></i>
                </div>
            </a>

            <a href="#repairsSection" class="customer-action-card">
                <div>
                    <div class="action-title">My Repairs</div>
                    <p class="action-subtitle">Track your repair job status and payment progress.</p>
                </div>
                <div class="action-icon">
                    <i class="bi bi-car-front"></i>
                </div>
            </a>

            <a href="#ordersSection" class="customer-action-card">
                <div>
                    <div class="action-title">My Orders</div>
                    <p class="action-subtitle">Monitor submitted parts orders and pickup status.</p>
                </div>
                <div class="action-icon">
                    <i class="bi bi-bag-check"></i>
                </div>
            </a>
        </div>

        <div class="customer-overview-grid">
            <div class="overview-card">
                <div class="overview-label">Active Repairs</div>
                <div class="overview-value"><?php echo intval($activeRepairCount); ?></div>
                <p class="overview-caption">Repair jobs still in progress</p>
            </div>

            <div class="overview-card">
                <div class="overview-label">Pending Payments</div>
                <div class="overview-value"><?php echo intval($pendingPaymentCount); ?></div>
                <p class="overview-caption">Repair invoices not fully paid</p>
            </div>

            <div class="overview-card">
                <div class="overview-label">Balance Due</div>
                <div class="overview-value">₱<?php echo number_format(floatval($pendingBalanceTotal), 2); ?></div>
                <p class="overview-caption">Total unpaid repair balance</p>
            </div>

            <div class="overview-card">
                <div class="overview-label">Ready for Pickup</div>
                <div class="overview-value"><?php echo intval($readyPickupCount); ?></div>
                <p class="overview-caption">Parts orders ready at the shop</p>
            </div>
        </div>

        <div class="customer-main-grid">

            <div class="customer-panel" id="repairsSection">
                <div class="panel-header">
                    <div>
                        <h5>My Vehicle Repairs</h5>
                        <p>Repair records linked to your customer profile.</p>
                    </div>
                </div>

                <div class="repair-list">
                    <?php if (count($jobOrders) > 0): ?>
                        <?php foreach ($jobOrders as $row): ?>
                            <?php
                                $badge = getCustomerRepairBadge($row['status'] ?? 'Pending');

                                $payStat = $row['payment_status'] ?? 'Not Paid';

                                $pColor = $payStat === 'Paid'
                                    ? 'payment-paid'
                                    : ($payStat === 'Partial' ? 'payment-partial' : 'payment-unpaid');
                            ?>

                            <div class="repair-item">
                                <div class="repair-left">
                                    <div class="repair-icon">
                                        <i class="bi bi-car-front-fill"></i>
                                    </div>

                                    <div>
                                        <div class="repair-title">
                                            <?php echo htmlspecialchars($row['plate_number']); ?>
                                            <span>
                                                • <?php echo htmlspecialchars(trim(($row['make'] ?? '') . ' ' . ($row['model'] ?? ''))); ?>
                                            </span>
                                        </div>

                                        <div class="repair-sub">
                                            JO: <?php echo htmlspecialchars($row['job_order_number']); ?>
                                            • Date: <?php echo !empty($row['date_created']) ? date('M d, Y', strtotime($row['date_created'])) : 'N/A'; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="repair-right">
                                    <span class="<?php echo $badge; ?>">
                                        <?php echo htmlspecialchars($row['status']); ?>
                                    </span>

                                    <div class="payment-text <?php echo $pColor; ?>">
                                        <?php echo htmlspecialchars($payStat); ?>

                                        <?php if ($payStat !== 'Paid' && !empty($row['balance_due'])): ?>
                                            (₱<?php echo number_format(floatval($row['balance_due']), 2); ?>)
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="bi bi-car-front"></i>
                            <div class="fw-bold mb-1">No repair records yet</div>
                            <div>You have no active or past repairs.</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="d-flex flex-column gap-3">
                <div class="customer-panel">
                    <div class="panel-header">
                        <div>
                            <h5>Pickup Reminders</h5>
                            <p>Orders currently ready for shop pickup.</p>
                        </div>

                        <a href="#ordersSection" class="section-link">
                            View Orders <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>

                    <div class="pickup-list">
                        <?php
                            $hasPickup = false;
                            foreach ($orders as $order):
                                if (($order['status'] ?? '') === 'Ready for Pickup'):
                                    $hasPickup = true;
                        ?>
                                <div class="pickup-item">
                                    <div>
                                        <div class="pickup-title">
                                            Order #<?php echo intval($order['order_id']); ?>
                                        </div>
                                        <p class="pickup-sub">
                                            <?php echo !empty($order['order_date']) ? date('M d, Y', strtotime($order['order_date'])) : 'N/A'; ?>
                                        </p>
                                    </div>

                                    <div class="pickup-price">
                                        ₱<?php echo number_format(floatval($order['total_amount']), 2); ?>
                                    </div>
                                </div>
                        <?php
                                endif;
                            endforeach;
                        ?>

                        <?php if (!$hasPickup): ?>
                            <div class="empty-state">
                                <i class="bi bi-bag-check"></i>
                                <div class="fw-bold mb-1">No pickup reminders</div>
                                <div>No parts orders are ready for pickup right now.</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="customer-note">
                    <h6>
                        <i class="bi bi-info-circle me-1"></i>
                        Customer Reminder
                    </h6>
                    <p>
                        For ready-for-pickup orders, please visit the shop and confirm your payment status
                        before claiming the item. Repair payment balances are shown beside each job order.
                    </p>
                </div>
            </div>

        </div>

        <div class="customer-panel" id="ordersSection">
            <div class="panel-header">
                <div>
                    <h5>My Ordered Parts</h5>
                    <p>Parts orders submitted through the customer portal.</p>
                </div>

                <a href="browse_parts.php" class="section-link">
                    Browse Parts <i class="bi bi-arrow-right"></i>
                </a>
            </div>

            <div class="orders-table-wrap">
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Date Ordered</th>
                            <th>Item Details</th>
                            <th>Total Cost</th>
                            <th>Order Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (count($orders) > 0): ?>
                            <?php foreach ($orders as $order): ?>
                                <?php
                                    $oBadge = getCustomerOrderBadge($order['status'] ?? '');
                                ?>

                                <tr>
                                    <td>
                                        <div class="order-id">
                                            #<?php echo intval($order['order_id']); ?>
                                        </div>
                                    </td>

                                    <td>
                                        <?php echo !empty($order['order_date']) ? date('M d, Y', strtotime($order['order_date'])) : 'N/A'; ?>
                                    </td>

                                    <td>
                                        <div class="order-items">
                                            <?php echo $order['items']; ?>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="order-total">
                                            ₱<?php echo number_format(floatval($order['total_amount']), 2); ?>
                                        </div>
                                    </td>

                                    <td>
                                        <span class="<?php echo $oBadge; ?>">
                                            <?php echo htmlspecialchars($order['status']); ?>
                                        </span>

                                        <?php if ($order['status'] === 'Pending'): ?>
                                            <span class="order-note">
                                                Waiting for shop confirmation.
                                            </span>
                                        <?php endif; ?>

                                        <?php if ($order['status'] === 'Approved'): ?>
                                            <span class="order-note">
                                                Your order has been approved.
                                            </span>
                                        <?php endif; ?>

                                        <?php if ($order['status'] === 'Ready for Pickup'): ?>
                                            <span class="order-note">
                                                Please proceed to the shop for pickup and payment.
                                            </span>
                                        <?php endif; ?>

                                        <?php if ($order['status'] === 'Completed'): ?>
                                            <span class="order-note">
                                                Order completed. Thank you!
                                            </span>
                                        <?php endif; ?>

                                        <?php if ($order['status'] === 'Cancelled'): ?>
                                            <span class="order-note">
                                                This order was cancelled.
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                        <?php else: ?>
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <i class="bi bi-bag"></i>
                                        <div class="fw-bold mb-1">No parts orders yet</div>
                                        <div>You haven't ordered any parts yet.</div>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>