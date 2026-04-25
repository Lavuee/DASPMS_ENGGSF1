<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Owner') { header("Location: login.php"); exit; }
require_once '../config/Database.php';

$db = (new Database())->getConnection();

$activeJobs = $db->query("SELECT COUNT(*) FROM job_order WHERE status != 'Completed'")->fetchColumn() ?: 0;

$completedToday = $db->query("SELECT COUNT(*) FROM job_order WHERE status = 'Completed' AND DATE(date_created) = CURDATE()")->fetchColumn() ?: 0;

$revPay = $db->query("SELECT SUM(amount) FROM payment")->fetchColumn() ?: 0;
$revPOS = $db->query("SELECT SUM(total_amount) FROM pos_transaction WHERE status = 'Completed'")->fetchColumn() ?: 0;
$revWeb = $db->query("SELECT SUM(total_amount) FROM part_order WHERE status = 'Completed'")->fetchColumn() ?: 0;
$totalRevenue = $revPay + $revPOS + $revWeb;

$pendingQuery = "SELECT SUM(jo.estimated_cost - COALESCE(i.amount_paid, 0)) 
                 FROM job_order jo 
                 LEFT JOIN invoice i ON jo.job_order_id = i.job_order_id 
                 WHERE jo.status IN ('Completed', 'Ready for Pickup') AND (i.payment_status IS NULL OR i.payment_status != 'Paid')";
$pendingPayments = $db->query($pendingQuery)->fetchColumn() ?: 0;

$stmtJobs = $db->query("SELECT jo.*, c.first_name, c.last_name, v.plate_number 
                        FROM job_order jo 
                        JOIN customer c ON jo.customer_id = c.customer_id 
                        JOIN vehicle v ON jo.vehicle_id = v.vehicle_id 
                        WHERE jo.status != 'Completed' 
                        ORDER BY jo.date_created DESC LIMIT 3");

$stmtStock = $db->query("SELECT * FROM part WHERE quantity_on_hand <= low_stock_threshold ORDER BY quantity_on_hand ASC LIMIT 3");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Norily's Repair Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="app-wrapper">
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="mb-4">
            <h2 class="fw-bold mb-1">Good <?php echo (date('H') < 12) ? 'Morning' : ((date('H') < 18) ? 'Afternoon' : 'Evening'); ?>, <?php echo htmlspecialchars($_SESSION['first_name']); ?>!</h2>
            <p class="text-muted">Here's what's happening at the shop today</p>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="custom-card stat-card">
                    <div>
                        <p class="text-muted mb-1 fs-6">Active Jobs</p>
                        <h3 class="fw-bold mb-0"><?php echo $activeJobs; ?></h3>
                    </div>
                    <div class="stat-icon icon-blue"><i class="bi bi-wrench"></i></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="custom-card stat-card">
                    <div>
                        <p class="text-muted mb-1 fs-6">Completed Today</p>
                        <h3 class="fw-bold mb-0"><?php echo $completedToday; ?></h3>
                    </div>
                    <div class="stat-icon icon-green"><i class="bi bi-check-circle"></i></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="custom-card stat-card">
                    <div>
                        <p class="text-muted mb-1 fs-6">Total Revenue</p>
                        <h3 class="fw-bold mb-0">₱<?php echo number_format($totalRevenue, 2); ?></h3>
                    </div>
                    <div class="stat-icon icon-purple"><i class="bi bi-currency-dollar"></i></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="custom-card stat-card">
                    <div>
                        <p class="text-muted mb-1 fs-6">Pending Payments</p>
                        <h3 class="fw-bold mb-0">₱<?php echo number_format($pendingPayments, 2); ?></h3>
                    </div>
                    <div class="stat-icon icon-yellow"><i class="bi bi-receipt"></i></div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-md-8">
                <div class="custom-card p-0 overflow-hidden h-100">
                    <div class="d-flex justify-content-between align-items-center p-4 border-bottom">
                        <h5 class="fw-bold mb-0">Active Job Orders</h5>
                        <a href="job_orders.php" class="text-decoration-none text-muted" style="font-size: 0.9rem;">View All <i class="bi bi-arrow-right"></i></a>
                    </div>
                    
                    <div class="list-group list-group-flush">
                        <?php if ($stmtJobs->rowCount() > 0): ?>
                            <?php while ($job = $stmtJobs->fetch(PDO::FETCH_ASSOC)): 
                                $badgeClass = 'badge-soft-warning';
                                if ($job['status'] == 'In Progress') $badgeClass = 'badge-soft-primary';
                                if ($job['status'] == 'Ready for Pickup') $badgeClass = 'badge-soft-success';
                            ?>
                            <div class="list-group-item p-4 d-flex justify-content-between align-items-center border-bottom">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="stat-icon icon-blue flex-shrink-0" style="width: 40px; height: 40px;"><i class="bi bi-wrench"></i></div>
                                    <div>
                                        <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($job['job_order_number']); ?></h6>
                                        <small class="text-muted"><?php echo htmlspecialchars($job['first_name'] . ' ' . $job['last_name']); ?> • <?php echo htmlspecialchars($job['plate_number']); ?></small>
                                    </div>
                                </div>
                                <span class="<?php echo $badgeClass; ?>"><?php echo htmlspecialchars($job['status']); ?></span>
                            </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="p-4 text-center text-muted">No active jobs right now.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="custom-card p-0 overflow-hidden h-100">
                    <div class="d-flex justify-content-between align-items-center p-4 border-bottom">
                        <h5 class="fw-bold mb-0">Low Stock Alerts</h5>
                        <a href="inventory.php" class="text-decoration-none text-muted" style="font-size: 0.9rem;">Manage <i class="bi bi-arrow-right"></i></a>
                    </div>
                    
                    <div class="list-group list-group-flush">
                        <?php if ($stmtStock->rowCount() > 0): ?>
                            <?php while ($stock = $stmtStock->fetch(PDO::FETCH_ASSOC)): ?>
                            <div class="list-group-item p-4 d-flex justify-content-between align-items-center border-bottom">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="stat-icon badge-soft-danger flex-shrink-0" style="width: 32px; height: 32px;"><i class="bi bi-exclamation-triangle"></i></div>
                                    <div>
                                        <h6 class="mb-0 fw-bold text-truncate" style="font-size: 0.9rem; max-width: 150px;" title="<?php echo htmlspecialchars($stock['part_name']); ?>">
                                            <?php echo htmlspecialchars($stock['part_name']); ?>
                                        </h6>
                                        <small class="text-muted" style="font-size: 0.75rem;"><?php echo htmlspecialchars($stock['category']); ?></small>
                                    </div>
                                </div>
                                <span class="text-danger fw-bold" style="font-size: 0.85rem;"><?php echo $stock['quantity_on_hand']; ?> left</span>
                            </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="p-4 text-center text-muted">Inventory levels look good!</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>