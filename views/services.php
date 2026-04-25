<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] === 'Customer' || $_SESSION['role'] === 'Head Mechanic') { header("Location: login.php"); exit; }
require_once '../config/Database.php';

$db = (new Database())->getConnection();
$stmt = $db->prepare("SELECT * FROM service ORDER BY category ASC, service_name ASC");
$stmt->execute();
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Services - Norily's Repair Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .table-custom th { background-color: white; color: var(--text-muted); border-bottom: 2px solid #e2e8f0; padding: 1rem; }
        .table-custom td { padding: 1rem; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
    </style>
</head>
<body>
<div class="app-wrapper">
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-1">Services Offered</h2>
                <p class="text-muted">Manage repair services and base pricing</p>
            </div>
            <button class="btn btn-primary px-4 py-2 fw-bold"><i class="bi bi-plus-lg"></i> Add Service</button>
        </div>

        <div class="custom-card p-0 overflow-hidden">
            <table class="table table-custom mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Service Name</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Base Price</th>
                        <th class="pe-4 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($services as $s): ?>
                    <tr>
                        <td class="ps-4 fw-bold"><?php echo htmlspecialchars($s['service_name']); ?></td>
                        <td><span class="badge-soft-primary"><?php echo htmlspecialchars($s['category']); ?></span></td>
                        <td class="text-muted" style="font-size: 0.85rem;"><?php echo htmlspecialchars($s['description']); ?></td>
                        <td class="fw-bold text-success">₱<?php echo number_format($s['base_price'], 2); ?></td>
                        <td class="pe-4 text-end text-muted">
                            <i class="bi bi-pencil" style="cursor: pointer;"></i>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>
</body>
</html>