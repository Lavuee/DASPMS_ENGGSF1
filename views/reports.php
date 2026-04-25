<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Owner') { header("Location: login.php"); exit; }
require_once '../config/Database.php';

$db = (new Database())->getConnection();

$filter = $_GET['filter'] ?? 'week';

$datePay = ""; $datePOS = ""; $dateOrd = "";
if ($filter === 'today') {
    $datePay = "WHERE DATE(payment_date) = CURDATE()";
    $datePOS = "WHERE status = 'Completed' AND DATE(transaction_date) = CURDATE()";
    $dateOrd = "WHERE status = 'Completed' AND DATE(order_date) = CURDATE()";
} elseif ($filter === 'month') {
    $datePay = "WHERE YEAR(payment_date) = YEAR(CURDATE()) AND MONTH(payment_date) = MONTH(CURDATE())";
    $datePOS = "WHERE status = 'Completed' AND YEAR(transaction_date) = YEAR(CURDATE()) AND MONTH(transaction_date) = MONTH(CURDATE())";
    $dateOrd = "WHERE status = 'Completed' AND YEAR(order_date) = YEAR(CURDATE()) AND MONTH(order_date) = MONTH(CURDATE())";
} else { 
    $datePay = "WHERE YEARWEEK(payment_date, 1) = YEARWEEK(CURDATE(), 1)";
    $datePOS = "WHERE status = 'Completed' AND YEARWEEK(transaction_date, 1) = YEARWEEK(CURDATE(), 1)";
    $dateOrd = "WHERE status = 'Completed' AND YEARWEEK(order_date, 1) = YEARWEEK(CURDATE(), 1)";
}

$repairsTotal = $db->query("SELECT SUM(amount) FROM payment $datePay")->fetchColumn() ?: 0;
$posTotal = $db->query("SELECT SUM(total_amount) FROM pos_transaction $datePOS")->fetchColumn() ?: 0;
$onlineTotal = $db->query("SELECT SUM(total_amount) FROM part_order $dateOrd")->fetchColumn() ?: 0;

$jobDate = ($filter === 'today') ? "AND DATE(date_completed) = CURDATE()" : (($filter === 'month') ? "AND YEAR(date_completed) = YEAR(CURDATE()) AND MONTH(date_completed) = MONTH(CURDATE())" : "AND YEARWEEK(date_completed, 1) = YEARWEEK(CURDATE(), 1)");
$jobsCompleted = $db->query("SELECT COUNT(*) FROM job_order WHERE status = 'Completed' $jobDate")->fetchColumn() ?: 0;

$grandTotal = $repairsTotal + $posTotal + $onlineTotal;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reports - Norily's Repair Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .tabs { display: flex; gap: 10px; margin-bottom: 1.5rem; }
        .tab-item { color: var(--text-muted); font-weight: 500; cursor: pointer; padding: 6px 16px; border-radius: 20px; font-size: 0.9rem; text-decoration: none;}
        .tab-item.active { background-color: white; color: var(--text-main); font-weight: 600; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid #e2e8f0;}
        .tab-item:hover { color: var(--primary-blue); }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<div class="app-wrapper">
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="mb-4">
            <h2 class="fw-bold mb-1">Reports</h2>
            <p class="text-muted">Revenue and performance analytics</p>
        </div>

        <div class="tabs">
            <a href="reports.php?filter=today" class="tab-item <?php echo ($filter=='today') ? 'active' : ''; ?>">Today</a>
            <a href="reports.php?filter=week" class="tab-item <?php echo ($filter=='week') ? 'active' : ''; ?>">This Week</a>
            <a href="reports.php?filter=month" class="tab-item <?php echo ($filter=='month') ? 'active' : ''; ?>">This Month</a>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="custom-card stat-card">
                    <div>
                        <p class="text-muted mb-1 fs-6">Total Revenue</p>
                        <h3 class="fw-bold mb-0">₱<?php echo number_format($grandTotal); ?></h3>
                    </div>
                    <div class="stat-icon icon-green"><i class="bi bi-currency-dollar"></i></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="custom-card stat-card">
                    <div>
                        <p class="text-muted mb-1 fs-6">Service Revenue</p>
                        <h3 class="fw-bold mb-0">₱<?php echo number_format($repairsTotal); ?></h3>
                    </div>
                    <div class="stat-icon icon-blue"><i class="bi bi-wrench"></i></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="custom-card stat-card">
                    <div>
                        <p class="text-muted mb-1 fs-6">Parts Revenue</p>
                        <h3 class="fw-bold mb-0">₱<?php echo number_format($posTotal + $onlineTotal); ?></h3>
                    </div>
                    <div class="stat-icon icon-yellow"><i class="bi bi-box-seam"></i></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="custom-card stat-card">
                    <div>
                        <p class="text-muted mb-1 fs-6">Jobs Completed</p>
                        <h3 class="fw-bold mb-0"><?php echo $jobsCompleted; ?></h3>
                    </div>
                    <div class="stat-icon icon-purple"><i class="bi bi-graph-up-arrow"></i></div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-md-8">
                <div class="custom-card">
                    <h5 class="fw-bold mb-4">Revenue Over Time</h5>
                    <div style="height: 300px;"><canvas id="revenueChart"></canvas></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="custom-card">
                    <h5 class="fw-bold mb-4">Payment Breakdown</h5>
                    <div style="height: 250px; display: flex; justify-content: center;"><canvas id="paymentChart"></canvas></div>
                    <div class="d-flex justify-content-center gap-4 mt-3">
                        <div class="d-flex align-items-center gap-2"><div style="width:12px; height:12px; background:#1e40af; border-radius:2px;"></div> <span class="fw-bold text-muted">Services</span></div>
                        <div class="d-flex align-items-center gap-2"><div style="width:12px; height:12px; background:#10b981; border-radius:2px;"></div> <span class="fw-bold text-muted">Parts</span></div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
// Bar Chart
const ctxRevenue = document.getElementById('revenueChart').getContext('2d');
new Chart(ctxRevenue, {
    type: 'bar',
    data: {
        labels: ['Data Entry 1', 'Data Entry 2', 'Data Entry 3', 'Data Entry 4', 'Data Entry 5', 'Data Entry 6', 'Current Total'],
        datasets: [{
            label: 'Revenue (₱)',
            data: [0, 0, 0, 0, 0, 0, <?php echo $grandTotal > 0 ? $grandTotal : 0; ?>], 
            backgroundColor: '#1d4ed8',
            borderRadius: 4,
            barThickness: 40
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, grid: { borderDash: [5, 5], color: '#e2e8f0' }, ticks: { callback: function(value) { return '₱' + value; }, color: '#64748b' } },
            x: { grid: { display: false }, ticks: { color: '#64748b' } }
        }
    }
});

const svcRev = <?php echo $repairsTotal; ?>;
const prtsRev = <?php echo ($posTotal + $onlineTotal); ?>;
const ctxPayment = document.getElementById('paymentChart').getContext('2d');
new Chart(ctxPayment, {
    type: 'doughnut',
    data: {
        labels: ['Services', 'Parts'],
        datasets: [{
            data: [svcRev > 0 ? svcRev : 1, prtsRev > 0 ? prtsRev : 1],
            backgroundColor: ['#1e40af', '#10b981'],
            borderWidth: 4,
            borderColor: '#ffffff',
            hoverOffset: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '70%',
        plugins: { legend: { display: false } }
    }
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>