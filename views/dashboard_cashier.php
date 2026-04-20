<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Cashier') {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Cashier Dashboard - DASPMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4 shadow">
    <div class="container-fluid">
        <span class="navbar-brand mb-0 h1">DASPMS - Cashier</span>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="customers.php">Customers</a></li>
                <li class="nav-item"><a class="nav-link" href="vehicles.php">Vehicles</a></li>
                <li class="nav-item"><a class="nav-link" href="job_orders.php">Job Orders</a></li>
                <li class="nav-item"><a class="nav-link" href="inventory.php">Inventory</a></li>
                <li class="nav-item"><a class="nav-link text-warning fw-bold" href="pos.php">Point of Sale (POS)</a></li>
                <li class="nav-item"><a class="nav-link" href="billing.php">Billing & Payments</a></li>
            </ul>
            <div class="d-flex">
                <span class="navbar-text me-3 text-white">Welcome, <?php echo htmlspecialchars($_SESSION['first_name']); ?></span>
                <a href="../controllers/AuthController.php?action=logout" class="btn btn-outline-light btn-sm">Logout</a>
            </div>
        </div>
    </div>
</nav>

<div class="container">
    <h2 class="mb-4">Front Desk Operations</h2>
    <div class="row">
        <div class="col-md-6 mb-3">
            <div class="card shadow-sm h-100 border-primary">
                <div class="card-body">
                    <h5 class="card-title text-primary">Process New Sale</h5>
                    <p class="card-text">Quickly ring up over-the-counter auto parts for walk-in customers.</p>
                    <a href="pos.php" class="btn btn-primary w-100">Open POS Register</a>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <div class="card shadow-sm h-100 border-info">
                <div class="card-body">
                    <h5 class="card-title text-info">Pending Bills & Down Payments</h5>
                    <p class="card-text">Collect payments for completed repairs or required down payments.</p>
                    <a href="billing.php" class="btn btn-info text-white w-100">View Invoices</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>