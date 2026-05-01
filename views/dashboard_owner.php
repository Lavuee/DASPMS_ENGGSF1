<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Owner') {
    header("Location: login.php");
    exit;
}

require_once '../config/Database.php';

$db = (new Database())->getConnection();

$activeJobs = $db->query("
    SELECT COUNT(*)
    FROM job_order
    WHERE status != 'Completed'
")->fetchColumn() ?: 0;

$completedToday = $db->query("
    SELECT COUNT(*)
    FROM job_order
    WHERE status = 'Completed'
    AND DATE(date_created) = CURDATE()
")->fetchColumn() ?: 0;

$revPay = $db->query("SELECT SUM(amount) FROM payment")->fetchColumn() ?: 0;
$revPOS = $db->query("SELECT SUM(total_amount) FROM pos_transaction WHERE status = 'Completed'")->fetchColumn() ?: 0;
$revWeb = $db->query("SELECT SUM(total_amount) FROM part_order WHERE status = 'Completed'")->fetchColumn() ?: 0;
$totalRevenue = $revPay + $revPOS + $revWeb;

$pendingQuery = "
    SELECT SUM(jo.estimated_cost - COALESCE(i.amount_paid, 0))
    FROM job_order jo
    LEFT JOIN invoice i ON jo.job_order_id = i.job_order_id
    WHERE jo.status IN ('Completed', 'Ready for Pickup')
    AND (i.payment_status IS NULL OR i.payment_status != 'Paid')
";
$pendingPayments = $db->query($pendingQuery)->fetchColumn() ?: 0;

/* Job Status Overview */
$pendingJobs = $db->query("
    SELECT COUNT(*)
    FROM job_order
    WHERE status = 'Pending'
")->fetchColumn() ?: 0;

$inProgressJobs = $db->query("
    SELECT COUNT(*)
    FROM job_order
    WHERE status = 'In Progress'
")->fetchColumn() ?: 0;

$readyForPickupJobs = $db->query("
    SELECT COUNT(*)
    FROM job_order
    WHERE status = 'Ready for Pickup'
")->fetchColumn() ?: 0;

$cancelledJobs = $db->query("
    SELECT COUNT(*)
    FROM job_order
    WHERE status = 'Cancelled'
")->fetchColumn() ?: 0;

$totalStatusJobs = $pendingJobs + $inProgressJobs + $readyForPickupJobs + $completedToday + $cancelledJobs;

function getStatusPercent($value, $total) {
    return ($total > 0) ? round(($value / $total) * 100) : 0;
}

/* Recent / Active Job Orders */
$stmtJobs = $db->query("
    SELECT jo.*, c.first_name, c.last_name, v.plate_number, v.make, v.model
    FROM job_order jo
    JOIN customer c ON jo.customer_id = c.customer_id
    JOIN vehicle v ON jo.vehicle_id = v.vehicle_id
    WHERE jo.status != 'Completed'
    ORDER BY jo.date_created DESC
    LIMIT 5
");

/* Low Stock Alerts */
$stmtStock = $db->query("
    SELECT *
    FROM part
    WHERE quantity_on_hand <= low_stock_threshold
    ORDER BY quantity_on_hand ASC
    LIMIT 5
");

$adminName = $_SESSION['first_name'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Norily's Repair Shop</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .main-content {
            overflow-x: hidden;
        }

        .owner-dashboard {
            width: 100%;
            max-width: 100%;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        .dashboard-topbar {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 0.2rem;
        }

        .dashboard-title-block h2 {
            font-size: 2rem;
            font-weight: 900;
            margin-bottom: 0.25rem;
            color: var(--dashboard-text-main);
            line-height: 1.1;
        }

        .dashboard-title-block p {
            margin-bottom: 0;
            color: var(--dashboard-text-muted);
            font-size: 0.95rem;
            font-weight: 500;
        }

        .dashboard-tools {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .dashboard-search {
            min-width: 270px;
            height: 44px;
            background: rgba(255, 255, 255, 0.62);
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 999px;
            padding: 0.58rem 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            color: var(--dashboard-text-muted);
            box-shadow: none;
        }

        .dashboard-search input {
            border: none;
            outline: none;
            background: transparent;
            width: 100%;
            font-size: 0.9rem;
            color: var(--dashboard-text-main);
        }

        .dashboard-search:focus-within {
            border-color: rgba(245, 197, 24, 0.65);
            box-shadow: 0 0 0 4px rgba(245, 197, 24, 0.12);
            background: rgba(255, 255, 255, 0.90);
        }

        .dashboard-date-pill {
            height: 44px;
            background: rgba(255, 255, 255, 0.56);
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 999px;
            padding: 0.58rem 0.95rem;
            color: var(--dashboard-text-muted);
            font-size: 0.9rem;
            font-weight: 800;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            box-shadow: none;
        }

        .dashboard-main-btn,
        .dashboard-outline-btn {
            min-height: 44px;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 900;
            padding: 0.55rem 0.9rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            text-decoration: none;
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .dashboard-main-btn {
            background: var(--dashboard-primary);
            border: 1px solid var(--dashboard-primary);
            color: var(--black);
        }

        .dashboard-main-btn:hover {
            background: var(--black);
            border-color: var(--black);
            color: var(--white);
        }

        .dashboard-outline-btn {
            background: transparent;
            border: 1px solid var(--dashboard-primary);
            color: var(--black);
        }

        .dashboard-outline-btn:hover {
            background: var(--dashboard-primary);
            border-color: var(--dashboard-primary);
            color: var(--black);
        }

        .stat-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0.9rem;
        }

        .dashboard-stat-card {
            min-height: 112px;
            background: rgba(255, 255, 255, 0.42);
            border: 1px solid rgba(15, 23, 42, 0.06);
            border-radius: 16px;
            padding: 1.05rem 1.1rem;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            box-shadow: none;
            position: relative;
        }

        .dashboard-stat-card::before {
            content: "";
            position: absolute;
            left: 0;
            top: 12px;
            bottom: 12px;
            width: 3px;
            border-radius: 999px;
            background: rgba(245, 197, 24, 0.85);
        }

        .stat-title {
            color: var(--dashboard-text-muted);
            font-size: 0.88rem;
            font-weight: 700;
            margin-bottom: 0.35rem;
        }

        .stat-value {
            font-size: 1.55rem;
            font-weight: 900;
            color: var(--dashboard-text-main);
            line-height: 1.1;
            margin-bottom: 0.35rem;
        }

        .stat-caption {
            color: var(--dashboard-text-muted);
            font-size: 0.78rem;
            margin-bottom: 0;
            line-height: 1.35;
        }

        .stat-icon {
            width: 42px;
            height: 42px;
            border-radius: 13px;
            background: rgba(245, 197, 24, 0.16);
            color: var(--black);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.12rem;
            flex-shrink: 0;
        }

        .dashboard-main-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.6fr) minmax(320px, 0.8fr);
            gap: 1rem;
            align-items: start;
        }

        .panel-card {
            background: rgba(255, 255, 255, 0.34);
            border: 1px solid rgba(15, 23, 42, 0.05);
            border-radius: 16px;
            box-shadow: none;
            overflow: hidden;
        }

        .panel-card-body {
            padding: 1.15rem;
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
            margin: 0;
            color: var(--dashboard-text-main);
        }

        .panel-header p {
            margin: 0.2rem 0 0 0;
            color: var(--dashboard-text-muted);
            font-size: 0.86rem;
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

        .status-list {
            display: flex;
            flex-direction: column;
            gap: 0.95rem;
        }

        .status-row {
            display: grid;
            grid-template-columns: 135px minmax(0, 1fr) 38px;
            gap: 0.9rem;
            align-items: center;
        }

        .status-name {
            font-weight: 800;
            color: var(--dashboard-text-main);
            font-size: 0.9rem;
        }

        .status-count {
            text-align: right;
            font-size: 1.05rem;
            font-weight: 900;
            color: var(--dashboard-text-main);
        }

        .progress {
            height: 8px;
            border-radius: 999px;
            background: rgba(15, 23, 42, 0.06);
            overflow: hidden;
        }

        .progress-bar-yellow {
            background: var(--dashboard-primary);
        }

        .progress-bar-dark {
            background: #111827;
        }

        .progress-bar-green {
            background: #10b981;
        }

        .progress-bar-red {
            background: #ef4444;
        }

        .alert-item {
            padding: 0.95rem 1.15rem;
            border-bottom: 1px solid rgba(15, 23, 42, 0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .alert-item:last-child {
            border-bottom: none;
        }

        .alert-left {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            min-width: 0;
        }

        .alert-icon {
            width: 36px;
            height: 36px;
            border-radius: 12px;
            background: rgba(239, 68, 68, 0.10);
            color: #b91c1c;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .alert-title {
            margin: 0;
            font-weight: 900;
            font-size: 0.9rem;
            color: var(--dashboard-text-main);
            max-width: 170px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .alert-subtitle {
            margin: 0.15rem 0 0 0;
            color: var(--dashboard-text-muted);
            font-size: 0.76rem;
        }

        .stock-left {
            color: #b91c1c;
            font-weight: 900;
            font-size: 0.84rem;
            white-space: nowrap;
        }

        .reminder-card {
            background: rgba(245, 197, 24, 0.08);
            border: 1px solid rgba(245, 197, 24, 0.18);
            border-radius: 16px;
            padding: 1.15rem;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
        }

        .reminder-card h6 {
            font-size: 0.98rem;
            font-weight: 900;
            margin-bottom: 0.4rem;
            color: var(--dashboard-text-main);
        }

        .reminder-card p {
            color: var(--dashboard-text-muted);
            margin-bottom: 0.9rem;
            font-size: 0.88rem;
            line-height: 1.55;
        }

        .reminder-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: rgba(245, 197, 24, 0.18);
            color: var(--black);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            flex-shrink: 0;
        }

        .recent-table-wrap {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .recent-table {
            width: 100%;
            min-width: 980px;
            margin-bottom: 0;
            border-collapse: collapse;
            background: transparent;
        }

        .recent-table th {
            background: transparent;
            color: var(--dashboard-text-muted);
            font-size: 0.8rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.35px;
            padding: 0.85rem 1rem;
            border-bottom: 1px solid rgba(15, 23, 42, 0.08);
            white-space: nowrap;
        }

        .recent-table td {
            padding: 0.95rem 1rem;
            color: var(--dashboard-text-main);
            vertical-align: middle;
            border-bottom: 1px solid rgba(15, 23, 42, 0.055);
            font-size: 0.92rem;
            white-space: nowrap;
            background: transparent;
        }

        .recent-table tbody tr:hover td {
            background: rgba(245, 197, 24, 0.03);
        }

        .job-main {
            font-weight: 900;
            margin-bottom: 0.15rem;
        }

        .job-sub {
            color: var(--dashboard-text-muted);
            font-size: 0.78rem;
        }

        .empty-state {
            padding: 2rem;
            text-align: center;
            color: var(--dashboard-text-muted);
            font-size: 0.92rem;
        }

        .quick-actions-section {
            margin-top: 0.25rem;
        }

        .quick-actions-title {
            font-size: 1rem;
            font-weight: 900;
            margin-bottom: 0.15rem;
            color: var(--dashboard-text-main);
        }

        .quick-actions-subtitle {
            color: var(--dashboard-text-muted);
            font-size: 0.88rem;
            margin-bottom: 0;
        }

        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 0.85rem;
        }

        .quick-action-card {
            min-height: 102px;
            background: rgba(255, 255, 255, 0.34);
            border: 1px solid rgba(15, 23, 42, 0.05);
            border-radius: 16px;
            padding: 0.95rem;
            text-decoration: none;
            color: var(--dashboard-text-main);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 0.75rem;
            box-shadow: none;
            transition: all 0.2s ease;
        }

        .quick-action-card:hover {
            color: var(--dashboard-text-main);
            transform: translateY(-2px);
            background: rgba(255, 255, 255, 0.58);
            border-color: rgba(245, 197, 24, 0.45);
        }

        .quick-action-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: rgba(245, 197, 24, 0.16);
            color: var(--black);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
        }

        .quick-action-title {
            font-size: 0.9rem;
            font-weight: 900;
            margin: 0 0 0.22rem 0;
            line-height: 1.2;
        }

        .quick-action-subtitle {
            color: var(--dashboard-text-muted);
            font-size: 0.75rem;
            margin: 0;
            line-height: 1.35;
        }

        .dashboard-small-btn {
            border-radius: 10px;
            font-size: 0.82rem;
            font-weight: 900;
            padding: 0.45rem 0.7rem;
        }

        @media (max-width: 1399.98px) {
            .quick-actions-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 1199.98px) {
            .stat-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .dashboard-main-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 991.98px) {
            .dashboard-topbar {
                flex-direction: column;
                align-items: stretch;
            }

            .dashboard-tools {
                justify-content: flex-start;
            }

            .dashboard-search {
                min-width: 100%;
            }
        }

        @media (max-width: 767.98px) {
            .dashboard-title-block h2 {
                font-size: 1.75rem;
            }

            .quick-actions-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .status-row {
                grid-template-columns: 1fr;
                gap: 0.45rem;
            }

            .status-count {
                text-align: left;
            }

            .dashboard-main-btn,
            .dashboard-outline-btn {
                width: 100%;
            }

            .dashboard-tools {
                width: 100%;
            }

            .dashboard-date-pill {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 575.98px) {
            .stat-grid,
            .quick-actions-grid {
                grid-template-columns: 1fr;
            }

            .reminder-card {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>

<div class="app-wrapper">
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="owner-dashboard">

            <div class="dashboard-topbar">
                <div class="dashboard-title-block">
                    <h2>
                        Good <?php echo (date('H') < 12) ? 'Morning' : ((date('H') < 18) ? 'Afternoon' : 'Evening'); ?>,
                        <?php echo htmlspecialchars($adminName); ?>!
                    </h2>
                    <p>Here's what's happening at the shop today.</p>
                </div>

                <div class="dashboard-tools">
                    <div class="dashboard-search">
                        <i class="bi bi-search"></i>
                        <input type="text" id="dashboardSearch" placeholder="Search recent job orders...">
                    </div>

                    <div class="dashboard-date-pill">
                        <i class="bi bi-calendar3 me-2"></i>
                        <?php echo date('F d, Y'); ?>
                    </div>

                    <a href="job_orders.php" class="dashboard-main-btn">
                        <i class="bi bi-plus-circle"></i>
                        New Job
                    </a>

                    <a href="pos.php" class="dashboard-outline-btn">
                        <i class="bi bi-calculator"></i>
                        POS
                    </a>
                </div>
            </div>

            <div class="stat-grid">
                <div class="dashboard-stat-card">
                    <div>
                        <p class="stat-title">Active Jobs</p>
                        <h3 class="stat-value"><?php echo $activeJobs; ?></h3>
                        <p class="stat-caption">Ongoing repair records</p>
                    </div>
                    <div class="stat-icon">
                        <i class="bi bi-wrench"></i>
                    </div>
                </div>

                <div class="dashboard-stat-card">
                    <div>
                        <p class="stat-title">Completed Today</p>
                        <h3 class="stat-value"><?php echo $completedToday; ?></h3>
                        <p class="stat-caption">Jobs finished today</p>
                    </div>
                    <div class="stat-icon">
                        <i class="bi bi-check-circle"></i>
                    </div>
                </div>

                <div class="dashboard-stat-card">
                    <div>
                        <p class="stat-title">Total Revenue</p>
                        <h3 class="stat-value">₱<?php echo number_format($totalRevenue, 2); ?></h3>
                        <p class="stat-caption">Payments, POS, and completed web orders</p>
                    </div>
                    <div class="stat-icon">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                </div>

                <div class="dashboard-stat-card">
                    <div>
                        <p class="stat-title">Pending Payments</p>
                        <h3 class="stat-value">₱<?php echo number_format($pendingPayments, 2); ?></h3>
                        <p class="stat-caption">Unpaid or partial balances</p>
                    </div>
                    <div class="stat-icon">
                        <i class="bi bi-receipt"></i>
                    </div>
                </div>
            </div>

            <div class="dashboard-main-grid">

                <div class="panel-card">
                    <div class="panel-header">
                        <div>
                            <h5>Job Status Overview</h5>
                            <p>Current repair workflow summary</p>
                        </div>

                        <a href="job_orders.php" class="section-link">
                            Manage <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>

                    <div class="panel-card-body">
                        <div class="status-list">
                            <div class="status-row">
                                <div class="status-name">Pending</div>
                                <div class="progress">
                                    <div class="progress-bar progress-bar-yellow" style="width: <?php echo getStatusPercent($pendingJobs, $totalStatusJobs); ?>%;"></div>
                                </div>
                                <div class="status-count"><?php echo $pendingJobs; ?></div>
                            </div>

                            <div class="status-row">
                                <div class="status-name">In Progress</div>
                                <div class="progress">
                                    <div class="progress-bar progress-bar-dark" style="width: <?php echo getStatusPercent($inProgressJobs, $totalStatusJobs); ?>%;"></div>
                                </div>
                                <div class="status-count"><?php echo $inProgressJobs; ?></div>
                            </div>

                            <div class="status-row">
                                <div class="status-name">Ready for Pickup</div>
                                <div class="progress">
                                    <div class="progress-bar progress-bar-green" style="width: <?php echo getStatusPercent($readyForPickupJobs, $totalStatusJobs); ?>%;"></div>
                                </div>
                                <div class="status-count"><?php echo $readyForPickupJobs; ?></div>
                            </div>

                            <div class="status-row">
                                <div class="status-name">Completed Today</div>
                                <div class="progress">
                                    <div class="progress-bar progress-bar-green" style="width: <?php echo getStatusPercent($completedToday, $totalStatusJobs); ?>%;"></div>
                                </div>
                                <div class="status-count"><?php echo $completedToday; ?></div>
                            </div>

                            <div class="status-row">
                                <div class="status-name">Cancelled</div>
                                <div class="progress">
                                    <div class="progress-bar progress-bar-red" style="width: <?php echo getStatusPercent($cancelledJobs, $totalStatusJobs); ?>%;"></div>
                                </div>
                                <div class="status-count"><?php echo $cancelledJobs; ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-column gap-3">

                    <div class="panel-card">
                        <div class="panel-header">
                            <div>
                                <h5>Low Stock Alerts</h5>
                                <p>Parts needing attention</p>
                            </div>

                            <a href="inventory.php" class="section-link">
                                Manage <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>

                        <div>
                            <?php if ($stmtStock->rowCount() > 0): ?>
                                <?php while ($stock = $stmtStock->fetch(PDO::FETCH_ASSOC)): ?>
                                    <div class="alert-item">
                                        <div class="alert-left">
                                            <div class="alert-icon">
                                                <i class="bi bi-exclamation-triangle"></i>
                                            </div>

                                            <div class="min-w-0">
                                                <p class="alert-title" title="<?php echo htmlspecialchars($stock['part_name']); ?>">
                                                    <?php echo htmlspecialchars($stock['part_name']); ?>
                                                </p>
                                                <p class="alert-subtitle">
                                                    <?php echo htmlspecialchars($stock['category']); ?>
                                                </p>
                                            </div>
                                        </div>

                                        <div class="stock-left">
                                            <?php echo htmlspecialchars($stock['quantity_on_hand']); ?> left
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="bi bi-check-circle fs-3 d-block mb-2"></i>
                                    Inventory levels look good.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="reminder-card">
                        <div>
                            <h6>Daily Monitoring</h6>
                            <p>
                                Review job progress, pending balances, and low-stock items before closing the shop.
                            </p>

                            <div class="d-flex flex-wrap gap-2">
                                <a href="billing.php" class="btn btn-primary btn-sm dashboard-small-btn">
                                    <i class="bi bi-receipt me-1"></i>
                                    Billing
                                </a>

                                <a href="reports.php" class="btn btn-outline-primary btn-sm dashboard-small-btn">
                                    <i class="bi bi-bar-chart me-1"></i>
                                    Reports
                                </a>
                            </div>
                        </div>

                        <div class="reminder-icon">
                            <i class="bi bi-clipboard-data"></i>
                        </div>
                    </div>

                </div>
            </div>

            <div class="panel-card">
                <div class="panel-header">
                    <div>
                        <h5>Recent Job Orders</h5>
                        <p>Latest repair records that still need monitoring</p>
                    </div>

                    <a href="job_orders.php" class="section-link">
                        View All <i class="bi bi-arrow-right"></i>
                    </a>
                </div>

                <div class="recent-table-wrap">
                    <table class="recent-table" id="recentJobsTable">
                        <thead>
                            <tr>
                                <th>Job Order</th>
                                <th>Customer</th>
                                <th>Vehicle</th>
                                <th>Plate No.</th>
                                <th>Status</th>
                                <th>Date Created</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if ($stmtJobs->rowCount() > 0): ?>
                                <?php while ($job = $stmtJobs->fetch(PDO::FETCH_ASSOC)):
                                    $badgeClass = 'badge-soft-warning';

                                    if ($job['status'] == 'In Progress') {
                                        $badgeClass = 'badge-soft-primary';
                                    }

                                    if ($job['status'] == 'Ready for Pickup') {
                                        $badgeClass = 'badge-soft-success';
                                    }

                                    if ($job['status'] == 'Cancelled') {
                                        $badgeClass = 'badge-soft-danger';
                                    }
                                ?>
                                    <tr>
                                        <td>
                                            <div class="job-main">
                                                <?php echo htmlspecialchars($job['job_order_number']); ?>
                                            </div>
                                            <div class="job-sub">
                                                Repair record
                                            </div>
                                        </td>

                                        <td>
                                            <?php echo htmlspecialchars($job['first_name'] . ' ' . $job['last_name']); ?>
                                        </td>

                                        <td>
                                            <?php echo htmlspecialchars(trim(($job['make'] ?? '') . ' ' . ($job['model'] ?? ''))); ?>
                                        </td>

                                        <td>
                                            <?php echo htmlspecialchars($job['plate_number']); ?>
                                        </td>

                                        <td>
                                            <span class="<?php echo $badgeClass; ?>">
                                                <?php echo htmlspecialchars($job['status']); ?>
                                            </span>
                                        </td>

                                        <td>
                                            <?php echo date('M d, Y', strtotime($job['date_created'])); ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6">
                                        <div class="empty-state">
                                            No active job orders right now.
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="quick-actions-section">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="quick-actions-title">Quick Actions</h5>
                        <p class="quick-actions-subtitle">
                            Shortcuts for common owner and shop operations
                        </p>
                    </div>
                </div>

                <div class="quick-actions-grid">
                    <a href="job_orders.php" class="quick-action-card">
                        <div class="quick-action-icon">
                            <i class="bi bi-plus-circle"></i>
                        </div>
                        <div>
                            <p class="quick-action-title">New Job Order</p>
                            <p class="quick-action-subtitle">Create repair record</p>
                        </div>
                    </a>

                    <a href="customers.php" class="quick-action-card">
                        <div class="quick-action-icon">
                            <i class="bi bi-person-plus"></i>
                        </div>
                        <div>
                            <p class="quick-action-title">Add Customer</p>
                            <p class="quick-action-subtitle">Register customer info</p>
                        </div>
                    </a>

                    <a href="vehicles.php" class="quick-action-card">
                        <div class="quick-action-icon">
                            <i class="bi bi-car-front"></i>
                        </div>
                        <div>
                            <p class="quick-action-title">Register Vehicle</p>
                            <p class="quick-action-subtitle">Link vehicle to customer</p>
                        </div>
                    </a>

                    <a href="pos.php" class="quick-action-card">
                        <div class="quick-action-icon">
                            <i class="bi bi-calculator"></i>
                        </div>
                        <div>
                            <p class="quick-action-title">Open POS</p>
                            <p class="quick-action-subtitle">Process parts sale</p>
                        </div>
                    </a>

                    <a href="inventory.php" class="quick-action-card">
                        <div class="quick-action-icon">
                            <i class="bi bi-box-seam"></i>
                        </div>
                        <div>
                            <p class="quick-action-title">Add Inventory</p>
                            <p class="quick-action-subtitle">Manage auto parts</p>
                        </div>
                    </a>

                    <a href="reports.php" class="quick-action-card">
                        <div class="quick-action-icon">
                            <i class="bi bi-bar-chart"></i>
                        </div>
                        <div>
                            <p class="quick-action-title">Generate Report</p>
                            <p class="quick-action-subtitle">View summaries</p>
                        </div>
                    </a>
                </div>
            </div>

        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    const dashboardSearch = document.getElementById('dashboardSearch');
    const recentJobsTable = document.getElementById('recentJobsTable');

    if (dashboardSearch && recentJobsTable) {
        dashboardSearch.addEventListener('keyup', function () {
            const keyword = this.value.toLowerCase();
            const rows = recentJobsTable.querySelectorAll('tbody tr');

            rows.forEach(function (row) {
                const rowText = row.textContent.toLowerCase();
                row.style.display = rowText.includes(keyword) ? '' : 'none';
            });
        });
    }
</script>

</body>
</html>