<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Owner') {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Owner Dashboard - DASPMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow">
    <div class="container-fluid">
        <span class="navbar-brand mb-0 h1">DASPMS - Owner</span>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="customers.php">Customers</a></li>
                <li class="nav-item"><a class="nav-link" href="vehicles.php">Vehicles</a></li>
                <li class="nav-item"><a class="nav-link" href="job_orders.php">Job Orders</a></li>
                <li class="nav-item"><a class="nav-link" href="inventory.php">Inventory</a></li>
                <li class="nav-item"><a class="nav-link" href="services.php">Services</a></li>
                <li class="nav-item"><a class="nav-link" href="pos.php">POS</a></li>
                <li class="nav-item"><a class="nav-link text-warning" href="reports.php">Financial Reports</a></li>
                <li class="nav-item"><a class="nav-link" href="users.php">Staff Accounts</a></li>
            </ul>
            <div class="d-flex">
                <span class="navbar-text me-3 text-white">Welcome, <?php echo htmlspecialchars($_SESSION['first_name']); ?></span>
                <a href="../controllers/AuthController.php?action=logout" class="btn btn-outline-danger btn-sm">Logout</a>
            </div>
        </div>
    </div>
</nav>

<div class="container">
    <h2 class="mb-4">Shop Overview</h2>
    <div class="row">
        <div class="col-md-4 mb-3">
            <div class="card text-bg-primary shadow-sm h-100">
                <div class="card-body">
                    <h5 class="card-title">Active Job Orders</h5>
                    <p class="card-text fs-4">--</p>
                    <a href="job_orders.php" class="btn btn-light btn-sm">Manage Jobs</a>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card text-bg-warning shadow-sm h-100">
                <div class="card-body">
                    <h5 class="card-title">Low Stock Alerts</h5>
                    <p class="card-text fs-4">--</p>
                    <a href="inventory.php" class="btn btn-dark btn-sm">Check Inventory</a>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card text-bg-success shadow-sm h-100">
                <div class="card-body">
                    <h5 class="card-title">Today's Revenue</h5>
                    <p class="card-text fs-4">₱ --</p>
                    <a href="reports.php" class="btn btn-light btn-sm">View Reports</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>