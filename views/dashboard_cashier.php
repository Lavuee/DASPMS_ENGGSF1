<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Cashier') {
    header("Location: login.php");
    exit;
}

require_once '../config/Database.php';

$db = (new Database())->getConnection();

$cashierName = $_SESSION['first_name'] ?? 'Cashier';

$pendingBillingItems = $db->query("
    SELECT COUNT(*)
    FROM invoice
    WHERE payment_status IS NULL
       OR payment_status != 'Paid'
")->fetchColumn() ?: 0;

$pendingPaymentAmount = $db->query("
    SELECT SUM(balance_due)
    FROM invoice
    WHERE payment_status IS NULL
       OR payment_status != 'Paid'
")->fetchColumn() ?: 0;

$readyWebOrders = $db->query("
    SELECT COUNT(*)
    FROM part_order
    WHERE status = 'Ready for Pickup'
")->fetchColumn() ?: 0;

$activeJobs = $db->query("
    SELECT COUNT(*)
    FROM job_order
    WHERE status IN ('Pending', 'In Progress', 'Ready for Pickup')
")->fetchColumn() ?: 0;

$recentSalesStmt = $db->query("
    SELECT pos_id, total_amount, payment_method, transaction_date
    FROM pos_transaction
    WHERE status = 'Completed'
    ORDER BY transaction_date DESC
    LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Front Desk - Norily's Repair Shop</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/style.css">

<style>
    .main-content {
        overflow-x: hidden;
    }

    .cashier-dashboard {
        width: 100%;
        max-width: 100%;
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
    }

    .cashier-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        margin-bottom: 0.15rem;
    }

    .cashier-title h2 {
        font-size: 2rem;
        font-weight: 900;
        color: var(--dashboard-text-main);
        margin-bottom: 0.25rem;
        line-height: 1.1;
    }

    .cashier-title p {
        color: var(--dashboard-text-muted);
        font-size: 0.95rem;
        font-weight: 500;
        margin-bottom: 0;
    }

    .cashier-date-pill {
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

    .cashier-actions-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0.9rem;
    }

    .cashier-action-card {
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

    .cashier-action-card::before {
        content: "";
        position: absolute;
        left: 0;
        top: 12px;
        bottom: 12px;
        width: 3px;
        border-radius: 999px;
        background: rgba(245, 197, 24, 0.85);
    }

    .cashier-action-card:hover {
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

    .cashier-overview-grid {
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

    .cashier-main-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.2fr) minmax(320px, 0.8fr);
        gap: 1rem;
        align-items: start;
    }

    .cashier-panel {
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

    .monitoring-list {
        display: grid;
        gap: 0;
    }

    .monitoring-row {
        padding: 0.95rem 1.15rem;
        border-bottom: 1px solid rgba(15, 23, 42, 0.05);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }

    .monitoring-row:last-child {
        border-bottom: none;
    }

    .monitoring-left {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        min-width: 0;
    }

    .monitoring-icon {
        width: 38px;
        height: 38px;
        border-radius: 12px;
        background: rgba(245, 197, 24, 0.16);
        color: var(--black);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .monitoring-title {
        font-size: 0.92rem;
        font-weight: 900;
        color: var(--dashboard-text-main);
        margin-bottom: 0.15rem;
    }

    .monitoring-subtitle {
        color: var(--dashboard-text-muted);
        font-size: 0.78rem;
        margin-bottom: 0;
    }

    .monitoring-value {
        font-size: 1rem;
        font-weight: 900;
        color: var(--dashboard-text-main);
        white-space: nowrap;
    }

    .recent-sale-card {
        padding: 0.95rem 1.15rem;
        border-bottom: 1px solid rgba(15, 23, 42, 0.05);
        transition: background 0.2s ease;
    }

    .recent-sale-card:last-child {
        border-bottom: none;
    }

    .recent-sale-card:hover {
        background: rgba(245, 197, 24, 0.035);
    }

    .sale-title {
        font-size: 0.92rem;
        font-weight: 900;
        color: var(--dashboard-text-main);
        margin-bottom: 0.15rem;
    }

    .sale-meta {
        color: var(--dashboard-text-muted);
        font-size: 0.78rem;
        line-height: 1.45;
    }

    .sale-amount {
        font-weight: 900;
        color: var(--dashboard-text-main);
        font-size: 0.95rem;
        white-space: nowrap;
    }

    .empty-state {
        padding: 2rem 1.5rem;
        text-align: center;
        color: var(--dashboard-text-muted);
        font-size: 0.92rem;
    }

    .cashier-note {
        background: rgba(245, 197, 24, 0.08);
        border: 1px solid rgba(245, 197, 24, 0.18);
        border-radius: 16px;
        padding: 1rem 1.15rem;
    }

    .cashier-note h6 {
        font-size: 0.95rem;
        font-weight: 900;
        color: var(--dashboard-text-main);
        margin-bottom: 0.35rem;
    }

    .cashier-note p {
        color: var(--dashboard-text-muted);
        font-size: 0.84rem;
        line-height: 1.55;
        margin-bottom: 0;
    }

    @media (max-width: 1199.98px) {
        .cashier-actions-grid,
        .cashier-overview-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .cashier-main-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 767.98px) {
        .cashier-header {
            flex-direction: column;
            align-items: stretch;
        }

        .cashier-title h2 {
            font-size: 1.75rem;
        }

        .cashier-date-pill {
            width: 100%;
            justify-content: center;
        }

        .cashier-actions-grid,
        .cashier-overview-grid {
            grid-template-columns: 1fr;
        }

        .cashier-action-card {
            min-height: 105px;
        }
    }
</style>
</head>

<body>
<div class="app-wrapper">
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="cashier-dashboard">

            <div class="cashier-header">
                <div class="cashier-title">
                    <h2>
                        Good <?php echo (date('H') < 12) ? 'Morning' : ((date('H') < 18) ? 'Afternoon' : 'Evening'); ?>,
                        <?php echo htmlspecialchars($cashierName); ?>!
                    </h2>
                    <p>Welcome to the Front Desk Terminal.</p>
                </div>

                <div class="cashier-date-pill">
                    <i class="bi bi-calendar3 me-2"></i>
                    <?php echo date('F d, Y'); ?>
                </div>
            </div>

            <div class="cashier-actions-grid">
                <a href="pos.php" class="cashier-action-card">
                    <div>
                        <div class="action-title">POS Terminal</div>
                        <p class="action-subtitle">Process walk-in auto parts sales and issue receipts.</p>
                    </div>
                    <div class="action-icon">
                        <i class="bi bi-calculator"></i>
                    </div>
                </a>

                <a href="billing.php" class="cashier-action-card">
                    <div>
                        <div class="action-title">Billing & Invoices</div>
                        <p class="action-subtitle">Collect repair payments and manage invoice balances.</p>
                    </div>
                    <div class="action-icon">
                        <i class="bi bi-receipt"></i>
                    </div>
                </a>

                <a href="job_orders.php" class="cashier-action-card">
                    <div>
                        <div class="action-title">Job Orders</div>
                        <p class="action-subtitle">View repair records and monitor job progress.</p>
                    </div>
                    <div class="action-icon">
                        <i class="bi bi-tools"></i>
                    </div>
                </a>

                <a href="online_orders.php" class="cashier-action-card">
                    <div>
                        <div class="action-title">Web Orders</div>
                        <p class="action-subtitle">Monitor pickup orders and customer reservations.</p>
                    </div>
                    <div class="action-icon">
                        <i class="bi bi-globe"></i>
                    </div>
                </a>
            </div>

            <div class="cashier-overview-grid">
                <div class="overview-card">
                    <div class="overview-label">Pending Billing</div>
                    <div class="overview-value"><?php echo intval($pendingBillingItems); ?></div>
                    <p class="overview-caption">Invoices not fully paid</p>
                </div>

                <div class="overview-card">
                    <div class="overview-label">Pending Balance</div>
                    <div class="overview-value">₱<?php echo number_format(floatval($pendingPaymentAmount), 2); ?></div>
                    <p class="overview-caption">Unpaid or partial balances</p>
                </div>

                <div class="overview-card">
                    <div class="overview-label">Ready Web Orders</div>
                    <div class="overview-value"><?php echo intval($readyWebOrders); ?></div>
                    <p class="overview-caption">Waiting for pickup handling</p>
                </div>

                <div class="overview-card">
                    <div class="overview-label">Active Jobs</div>
                    <div class="overview-value"><?php echo intval($activeJobs); ?></div>
                    <p class="overview-caption">Jobs needing front desk monitoring</p>
                </div>
            </div>

            <div class="cashier-main-grid">

                <div class="cashier-panel">
                    <div class="panel-header">
                        <div>
                            <h5>Front Desk Monitoring</h5>
                            <p>Items that may need cashier attention today.</p>
                        </div>
                    </div>

                    <div class="monitoring-list">
                        <div class="monitoring-row">
                            <div class="monitoring-left">
                                <div class="monitoring-icon">
                                    <i class="bi bi-receipt"></i>
                                </div>
                                <div>
                                    <div class="monitoring-title">Unpaid or Partial Invoices</div>
                                    <p class="monitoring-subtitle">Open billing records for follow-up</p>
                                </div>
                            </div>
                            <div class="monitoring-value"><?php echo intval($pendingBillingItems); ?></div>
                        </div>

                        <div class="monitoring-row">
                            <div class="monitoring-left">
                                <div class="monitoring-icon">
                                    <i class="bi bi-cash-stack"></i>
                                </div>
                                <div>
                                    <div class="monitoring-title">Pending Payment Amount</div>
                                    <p class="monitoring-subtitle">Remaining unpaid balance</p>
                                </div>
                            </div>
                            <div class="monitoring-value">₱<?php echo number_format(floatval($pendingPaymentAmount), 2); ?></div>
                        </div>

                        <div class="monitoring-row">
                            <div class="monitoring-left">
                                <div class="monitoring-icon">
                                    <i class="bi bi-bag-check"></i>
                                </div>
                                <div>
                                    <div class="monitoring-title">Ready for Pickup Web Orders</div>
                                    <p class="monitoring-subtitle">Customer orders ready for release</p>
                                </div>
                            </div>
                            <div class="monitoring-value"><?php echo intval($readyWebOrders); ?></div>
                        </div>

                        <div class="monitoring-row">
                            <div class="monitoring-left">
                                <div class="monitoring-icon">
                                    <i class="bi bi-tools"></i>
                                </div>
                                <div>
                                    <div class="monitoring-title">Active Repair Jobs</div>
                                    <p class="monitoring-subtitle">Pending, in-progress, or pickup stage</p>
                                </div>
                            </div>
                            <div class="monitoring-value"><?php echo intval($activeJobs); ?></div>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-column gap-3">
                    <div class="cashier-panel">
                        <div class="panel-header">
                            <div>
                                <h5>Recent POS Sales</h5>
                                <p>Latest completed over-the-counter transactions.</p>
                            </div>

                            <a href="pos.php" class="section-link">
                                Open POS <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>

                        <div>
                            <?php if ($recentSalesStmt->rowCount() > 0): ?>
                                <?php while ($sale = $recentSalesStmt->fetch(PDO::FETCH_ASSOC)): ?>
                                    <div class="recent-sale-card">
                                        <div class="d-flex justify-content-between align-items-start gap-3">
                                            <div>
                                                <div class="sale-title">
                                                    Sale #<?php echo intval($sale['pos_id']); ?>
                                                </div>
                                                <div class="sale-meta">
                                                    <?php echo date('M d, h:i A', strtotime($sale['transaction_date'])); ?>
                                                    <br>
                                                    <?php echo htmlspecialchars($sale['payment_method']); ?>
                                                </div>
                                            </div>

                                            <div class="sale-amount">
                                                ₱<?php echo number_format(floatval($sale['total_amount']), 2); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="bi bi-receipt fs-3 d-block mb-2"></i>
                                    No recent POS sales recorded.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="cashier-note">
                        <h6>
                            <i class="bi bi-info-circle me-1"></i>
                            Cashier Reminder
                        </h6>
                        <p>
                            Check pending invoices, confirm payment references for non-cash transactions,
                            and make sure pickup orders are released only after payment status is verified.
                        </p>
                    </div>
                </div>

            </div>

        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>