<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Cashier') { header("Location: login.php"); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Front Desk - Norily's Repair Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="app-wrapper">
    <?php include '../includes/sidebar.php'; ?>
    <main class="main-content">
        <div class="mb-4">
            <h2 class="fw-bold mb-1">Good <?php echo (date('H') < 12) ? 'Morning' : 'Afternoon'; ?>, <?php echo htmlspecialchars($_SESSION['first_name']); ?>!</h2>
            <p class="text-muted">Welcome to the Front Desk Terminal.</p>
        </div>
        
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="custom-card stat-card" style="cursor:pointer;" onclick="window.location='pos.php'">
                    <div><h4 class="fw-bold mb-1">POS Terminal</h4><p class="text-muted mb-0">Process walk-in parts sales</p></div>
                    <div class="stat-icon icon-blue" style="width: 50px; height: 50px;"><i class="bi bi-calculator fs-4"></i></div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="custom-card stat-card" style="cursor:pointer;" onclick="window.location='billing.php'">
                    <div><h4 class="fw-bold mb-1">Billing & Invoices</h4><p class="text-muted mb-0">Collect repair payments</p></div>
                    <div class="stat-icon icon-green" style="width: 50px; height: 50px;"><i class="bi bi-receipt fs-4"></i></div>
                </div>
            </div>
        </div>
    </main>
</div>
</body>
</html>