<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Head Mechanic') { 
    header("Location: login.php"); 
    exit; 
}
require_once '../config/Database.php';

$db = (new Database())->getConnection();
$stmt = $db->prepare("SELECT * FROM part ORDER BY part_name ASC");
$stmt->execute();
$parts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalParts = count($parts);
$lowStockCount = 0;
foreach ($parts as $p) { if ($p['quantity_on_hand'] <= $p['low_stock_threshold']) $lowStockCount++; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Parts Availability - Norily's Repair Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .table-custom th { background-color: white; color: var(--text-muted); font-weight: 500; font-size: 0.85rem; border-bottom: 2px solid #e2e8f0; padding: 1rem; }
        .table-custom td { padding: 1rem; vertical-align: middle; border-bottom: 1px solid #f1f5f9; color: var(--text-main); font-size: 0.95rem; }
        .category-badge { background-color: #f1f5f9; color: var(--text-muted); padding: 4px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 600;}
    </style>
</head>
<body>
<div class="app-wrapper">
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-1">Parts Availability</h2>
                <p class="text-muted">Workshop View (Read-Only) • <?php echo $totalParts; ?> parts tracked</p>
            </div>
        </div>

        <div class="custom-search mb-4 shadow-sm">
            <i class="bi bi-search"></i>
            <input type="text" placeholder="Search parts...">
        </div>

        <div class="custom-card p-0 overflow-hidden">
            <table class="table table-custom mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Part Name</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Current Stock</th>
                        <th class="pe-4 text-end">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($parts as $p): 
                        $isLowStock = $p['quantity_on_hand'] <= $p['low_stock_threshold'];
                    ?>
                    <tr>
                        <td class="ps-4 fw-bold"><?php echo htmlspecialchars($p['part_name']); ?></td>
                        <td><span class="category-badge"><?php echo htmlspecialchars($p['category']); ?></span></td>
                        <td class="text-muted" style="font-size: 0.85rem;"><?php echo htmlspecialchars($p['description']); ?></td>
                        <td class="fw-bold fs-5 <?php echo $isLowStock ? 'text-danger' : 'text-success'; ?>"><?php echo htmlspecialchars($p['quantity_on_hand']); ?></td>
                        <td class="pe-4 text-end">
                            <?php if ($p['quantity_on_hand'] == 0): ?>
                                <span class="badge-soft-danger">OUT OF STOCK</span>
                            <?php elseif ($isLowStock): ?>
                                <span class="badge-soft-warning">Low Stock</span>
                            <?php else: ?>
                                <span class="badge-soft-success">In Stock</span>
                            <?php endif; ?>
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