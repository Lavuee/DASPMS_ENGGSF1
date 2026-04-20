<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Head Mechanic') {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Mechanic Dashboard - DASPMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-secondary mb-4 shadow">
    <div class="container-fluid">
        <span class="navbar-brand mb-0 h1">DASPMS - Workshop</span>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link active" href="dashboard_mechanic.php">My Workspace</a></li>
                <li class="nav-item"><a class="nav-link" href="inventory_view.php">Check Parts Availability</a></li>
            </ul>
            <div class="d-flex">
                <span class="navbar-text me-3 text-white">Mechanic: <?php echo htmlspecialchars($_SESSION['first_name']); ?></span>
                <a href="../controllers/AuthController.php?action=logout" class="btn btn-outline-light btn-sm">Logout</a>
            </div>
        </div>
    </div>
</nav>

<div class="container">
    <div class="alert alert-info">
        <strong>Workshop Status:</strong> Please update your Job Orders to "Completed" as soon as repairs are finished so the Cashier can begin billing.
    </div>
    
    <h2 class="mb-4">Active Repair Jobs</h2>
    <div class="card shadow-sm">
        <div class="card-body text-center p-5 text-muted">
            <em>Your active repair queue will appear here.</em>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>