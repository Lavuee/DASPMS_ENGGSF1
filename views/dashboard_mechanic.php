<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Head Mechanic') {
    header("Location: login.php");
    exit;
}

require_once '../config/Database.php';

$db = (new Database())->getConnection();

$mechanicName = $_SESSION['first_name'] ?? 'Mechanic';

/* Workshop Overview */
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

$completedToday = $db->query("
    SELECT COUNT(*)
    FROM job_order
    WHERE status = 'Completed'
    AND DATE(date_created) = CURDATE()
")->fetchColumn() ?: 0;

$activeJobs = $pendingJobs + $inProgressJobs + $readyForPickupJobs;

/* Low Stock Parts */
$lowStockCount = $db->query("
    SELECT COUNT(*)
    FROM part
    WHERE quantity_on_hand <= low_stock_threshold
      AND is_active = 1
")->fetchColumn() ?: 0;

/* Recent Active Repairs */
$stmtActiveJobs = $db->query("
    SELECT 
        jo.job_order_id,
        jo.job_order_number,
        jo.status,
        jo.date_created,
        jo.description,
        c.first_name,
        c.last_name,
        v.plate_number,
        v.make,
        v.model
    FROM job_order jo
    JOIN customer c ON jo.customer_id = c.customer_id
    JOIN vehicle v ON jo.vehicle_id = v.vehicle_id
    WHERE jo.status IN ('Pending', 'In Progress', 'Ready for Pickup')
    ORDER BY jo.date_created DESC
    LIMIT 6
");

/* Parts Needing Attention */
$stmtLowStock = $db->query("
    SELECT 
        part_name,
        category,
        quantity_on_hand,
        low_stock_threshold
    FROM part
    WHERE quantity_on_hand <= low_stock_threshold
      AND is_active = 1
    ORDER BY quantity_on_hand ASC, part_name ASC
    LIMIT 5
");

function getMechanicJobBadgeClass($status) {
    switch ($status) {
        case 'Pending':
            return 'status-badge status-pending';
        case 'In Progress':
            return 'status-badge status-progress';
        case 'Ready for Pickup':
            return 'status-badge status-ready';
        case 'Completed':
            return 'status-badge status-completed';
        default:
            return 'status-badge status-muted';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Workshop - Norily's Repair Shop</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/style.css">

<style>
    .main-content {
        overflow-x: hidden;
    }

    .mechanic-dashboard {
        width: 100%;
        max-width: 100%;
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
    }

    .mechanic-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        margin-bottom: 0.15rem;
    }

    .mechanic-title h2 {
        font-size: 2rem;
        font-weight: 900;
        color: var(--dashboard-text-main);
        margin-bottom: 0.25rem;
        line-height: 1.1;
    }

    .mechanic-title p {
        color: var(--dashboard-text-muted);
        font-size: 0.95rem;
        font-weight: 500;
        margin-bottom: 0;
    }

    .mechanic-date-pill {
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

    .mechanic-actions-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0.9rem;
    }

    .mechanic-action-card {
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

    .mechanic-action-card::before {
        content: "";
        position: absolute;
        left: 0;
        top: 12px;
        bottom: 12px;
        width: 3px;
        border-radius: 999px;
        background: rgba(245, 197, 24, 0.85);
    }

    .mechanic-action-card:hover {
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

    .mechanic-overview-grid {
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

    .mechanic-main-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.35fr) minmax(320px, 0.75fr);
        gap: 1rem;
        align-items: start;
    }

    .mechanic-panel {
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

    .active-jobs-wrap {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .active-jobs-table {
        width: 100%;
        min-width: 920px;
        border-collapse: collapse;
        background: transparent;
        margin-bottom: 0;
    }

    .active-jobs-table th {
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

    .active-jobs-table td {
        padding: 0.95rem 1rem;
        color: var(--dashboard-text-main);
        vertical-align: middle;
        border-bottom: 1px solid rgba(15, 23, 42, 0.055);
        font-size: 0.92rem;
        white-space: nowrap;
        background: transparent;
    }

    .active-jobs-table tbody tr:hover td {
        background: rgba(245, 197, 24, 0.03);
    }

    .job-main {
        font-weight: 900;
        margin-bottom: 0.15rem;
    }

    .job-sub {
        color: var(--dashboard-text-muted);
        font-size: 0.78rem;
        max-width: 230px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .status-badge {
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
        background: #fff4cc;
        color: #7a5200;
    }

    .status-progress {
        background: #e9f2ff;
        color: #1d4ed8;
    }

    .status-ready {
        background: #dcfce7;
        color: #047857;
    }

    .status-completed {
        background: #dcfce7;
        color: #047857;
    }

    .status-muted {
        background: #f1f5f9;
        color: #475569;
    }

    .parts-list {
        display: grid;
        gap: 0;
    }

    .part-alert-row {
        padding: 0.95rem 1.15rem;
        border-bottom: 1px solid rgba(15, 23, 42, 0.05);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }

    .part-alert-row:last-child {
        border-bottom: none;
    }

    .part-alert-left {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        min-width: 0;
    }

    .part-alert-icon {
        width: 38px;
        height: 38px;
        border-radius: 12px;
        background: rgba(239, 68, 68, 0.10);
        color: #b91c1c;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .part-alert-title {
        font-size: 0.92rem;
        font-weight: 900;
        color: var(--dashboard-text-main);
        margin-bottom: 0.15rem;
        max-width: 170px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .part-alert-subtitle {
        color: var(--dashboard-text-muted);
        font-size: 0.78rem;
        margin-bottom: 0;
    }

    .part-left {
        font-size: 0.86rem;
        font-weight: 900;
        color: #b91c1c;
        white-space: nowrap;
    }

    .mechanic-note {
        background: rgba(245, 197, 24, 0.08);
        border: 1px solid rgba(245, 197, 24, 0.18);
        border-radius: 16px;
        padding: 1rem 1.15rem;
    }

    .mechanic-note h6 {
        font-size: 0.95rem;
        font-weight: 900;
        color: var(--dashboard-text-main);
        margin-bottom: 0.35rem;
    }

    .mechanic-note p {
        color: var(--dashboard-text-muted);
        font-size: 0.84rem;
        line-height: 1.55;
        margin-bottom: 0;
    }

    .empty-state {
        padding: 2rem 1.5rem;
        text-align: center;
        color: var(--dashboard-text-muted);
        font-size: 0.92rem;
    }

    @media (max-width: 1199.98px) {
        .mechanic-actions-grid,
        .mechanic-overview-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .mechanic-main-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 767.98px) {
        .mechanic-header {
            flex-direction: column;
            align-items: stretch;
        }

        .mechanic-title h2 {
            font-size: 1.75rem;
        }

        .mechanic-date-pill {
            width: 100%;
            justify-content: center;
        }

        .mechanic-actions-grid,
        .mechanic-overview-grid {
            grid-template-columns: 1fr;
        }

        .mechanic-action-card {
            min-height: 105px;
        }
    }
</style>
</head>

<body>
<div class="app-wrapper">
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="mechanic-dashboard">

            <div class="mechanic-header">
                <div class="mechanic-title">
                    <h2>
                        Good <?php echo (date('H') < 12) ? 'Morning' : ((date('H') < 18) ? 'Afternoon' : 'Evening'); ?>,
                        <?php echo htmlspecialchars($mechanicName); ?>!
                    </h2>
                    <p>Welcome to the Workshop Terminal.</p>
                </div>

                <div class="mechanic-date-pill">
                    <i class="bi bi-calendar3 me-2"></i>
                    <?php echo date('F d, Y'); ?>
                </div>
            </div>

            <div class="mechanic-actions-grid">
                <a href="job_orders.php" class="mechanic-action-card">
                    <div>
                        <div class="action-title">Active Repairs</div>
                        <p class="action-subtitle">Update job order progress and monitor ongoing repairs.</p>
                    </div>
                    <div class="action-icon">
                        <i class="bi bi-tools"></i>
                    </div>
                </a>

                <a href="inventory_view.php" class="mechanic-action-card">
                    <div>
                        <div class="action-title">Parts Availability</div>
                        <p class="action-subtitle">Check available parts before repair or release.</p>
                    </div>
                    <div class="action-icon">
                        <i class="bi bi-box-seam"></i>
                    </div>
                </a>

                <a href="services.php" class="mechanic-action-card">
                    <div>
                        <div class="action-title">Service Reference</div>
                        <p class="action-subtitle">Review repair services and service details.</p>
                    </div>
                    <div class="action-icon">
                        <i class="bi bi-wrench-adjustable"></i>
                    </div>
                </a>

                <a href="job_orders.php" class="mechanic-action-card">
                    <div>
                        <div class="action-title">Repair Queue</div>
                        <p class="action-subtitle">View pending, in-progress, and pickup-stage jobs.</p>
                    </div>
                    <div class="action-icon">
                        <i class="bi bi-list-check"></i>
                    </div>
                </a>
            </div>

            <div class="mechanic-overview-grid">
                <div class="overview-card">
                    <div class="overview-label">Pending Jobs</div>
                    <div class="overview-value"><?php echo intval($pendingJobs); ?></div>
                    <p class="overview-caption">Waiting to start repair</p>
                </div>

                <div class="overview-card">
                    <div class="overview-label">In Progress</div>
                    <div class="overview-value"><?php echo intval($inProgressJobs); ?></div>
                    <p class="overview-caption">Currently being repaired</p>
                </div>

                <div class="overview-card">
                    <div class="overview-label">Ready for Pickup</div>
                    <div class="overview-value"><?php echo intval($readyForPickupJobs); ?></div>
                    <p class="overview-caption">Completed but awaiting release</p>
                </div>

                <div class="overview-card">
                    <div class="overview-label">Low Stock Parts</div>
                    <div class="overview-value"><?php echo intval($lowStockCount); ?></div>
                    <p class="overview-caption">Parts needing attention</p>
                </div>
            </div>

            <div class="mechanic-main-grid">

                <div class="mechanic-panel">
                    <div class="panel-header">
                        <div>
                            <h5>Active Repair Monitoring</h5>
                            <p>Latest job orders that still need workshop attention.</p>
                        </div>

                        <a href="job_orders.php" class="section-link">
                            Manage <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>

                    <div class="active-jobs-wrap">
                        <table class="active-jobs-table">
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
                                <?php if ($stmtActiveJobs->rowCount() > 0): ?>
                                    <?php while ($job = $stmtActiveJobs->fetch(PDO::FETCH_ASSOC)): ?>
                                        <?php
                                            $customerName = trim(($job['first_name'] ?? '') . ' ' . ($job['last_name'] ?? ''));
                                            $vehicleName = trim(($job['make'] ?? '') . ' ' . ($job['model'] ?? ''));
                                            $status = $job['status'] ?? 'Pending';
                                            $badgeClass = getMechanicJobBadgeClass($status);
                                        ?>

                                        <tr>
                                            <td>
                                                <div class="job-main">
                                                    <?php echo htmlspecialchars($job['job_order_number']); ?>
                                                </div>
                                                <div class="job-sub" title="<?php echo htmlspecialchars($job['description'] ?? 'Repair record'); ?>">
                                                    <?php echo htmlspecialchars($job['description'] ?? 'Repair record'); ?>
                                                </div>
                                            </td>

                                            <td>
                                                <?php echo htmlspecialchars($customerName); ?>
                                            </td>

                                            <td>
                                                <?php echo htmlspecialchars($vehicleName); ?>
                                            </td>

                                            <td>
                                                <?php echo htmlspecialchars($job['plate_number']); ?>
                                            </td>

                                            <td>
                                                <span class="<?php echo $badgeClass; ?>">
                                                    <?php echo htmlspecialchars($status); ?>
                                                </span>
                                            </td>

                                            <td>
                                                <?php echo !empty($job['date_created']) ? date('M d, Y', strtotime($job['date_created'])) : 'N/A'; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6">
                                            <div class="empty-state">
                                                No active repair jobs right now.
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="d-flex flex-column gap-3">
                    <div class="mechanic-panel">
                        <div class="panel-header">
                            <div>
                                <h5>Parts Attention</h5>
                                <p>Low-stock parts that may affect repair work.</p>
                            </div>

                            <a href="inventory_view.php" class="section-link">
                                Check <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>

                        <div class="parts-list">
                            <?php if ($stmtLowStock->rowCount() > 0): ?>
                                <?php while ($part = $stmtLowStock->fetch(PDO::FETCH_ASSOC)): ?>
                                    <div class="part-alert-row">
                                        <div class="part-alert-left">
                                            <div class="part-alert-icon">
                                                <i class="bi bi-exclamation-triangle"></i>
                                            </div>

                                            <div class="min-w-0">
                                                <div class="part-alert-title" title="<?php echo htmlspecialchars($part['part_name']); ?>">
                                                    <?php echo htmlspecialchars($part['part_name']); ?>
                                                </div>
                                                <p class="part-alert-subtitle">
                                                    <?php echo htmlspecialchars($part['category']); ?>
                                                </p>
                                            </div>
                                        </div>

                                        <div class="part-left">
                                            <?php echo intval($part['quantity_on_hand']); ?> left
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="bi bi-check-circle fs-3 d-block mb-2"></i>
                                    Parts inventory looks okay.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mechanic-note">
                        <h6>
                            <i class="bi bi-info-circle me-1"></i>
                            Workshop Reminder
                        </h6>
                        <p>
                            Update job order status regularly, verify needed parts before repair,
                            and check availability before marking jobs as ready for pickup.
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