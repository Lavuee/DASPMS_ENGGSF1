<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Owner') {
    header("Location: login.php");
    exit;
}

require_once '../config/Database.php';

$database = new Database();
$db = $database->getConnection();

$queryRepairs = "SELECT SUM(amount) as total_repairs FROM payment";
$stmt1 = $db->prepare($queryRepairs);
$stmt1->execute();
$repairsTotal = $stmt1->fetchColumn() ?: 0;

$queryPOS = "SELECT SUM(total_amount) as total_pos FROM pos_transaction WHERE status = 'Completed'";
$stmt2 = $db->prepare($queryPOS);
$stmt2->execute();
$posTotal = $stmt2->fetchColumn() ?: 0;

$grandTotal = $repairsTotal + $posTotal;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Financial Reports - DASPMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm">
    <div class="container-fluid">
        <span class="navbar-brand mb-0 h1">DASPMS - Owner Reports</span>
        <div class="d-flex">
            <a href="dashboard_owner.php" class="btn btn-outline-light btn-sm me-2">Back to Dashboard</a>
            <a href="../controllers/AuthController.php?action=logout" class="btn btn-outline-danger btn-sm">Logout</a>
        </div>
    </div>
</nav>

<div class="container">
    <h2 class="mb-4">Lifetime Revenue Overview</h2>

    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card text-white bg-primary shadow">
                <div class="card-body">
                    <h5 class="card-title">Repair Service Revenue</h5>
                    <p class="card-text fs-2 fw-bold">₱<?php echo number_format($repairsTotal, 2); ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card text-white bg-info shadow">
                <div class="card-body">
                    <h5 class="card-title">Over-the-Counter POS</h5>
                    <p class="card-text fs-2 fw-bold">₱<?php echo number_format($posTotal, 2); ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card text-white bg-success shadow">
                <div class="card-body">
                    <h5 class="card-title">Grand Total Revenue</h5>
                    <p class="card-text fs-2 fw-bold">₱<?php echo number_format($grandTotal, 2); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>