<?php
session_start();
if (!isset($_SESSION['logged_in']) || ($_SESSION['role'] !== 'Cashier' && $_SESSION['role'] !== 'Owner')) { header("Location: login.php"); exit; }
require_once '../config/Database.php';
require_once '../models/PartOrder.php';

$db = (new Database())->getConnection();
$stmtOrders = (new PartOrder($db))->getAllOrders();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Web Orders - Norily's Repair Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .table-custom th { background-color: white; color: var(--text-muted); font-weight: 500; font-size: 0.85rem; border-bottom: 2px solid #e2e8f0; padding: 1rem; }
        .table-custom td { padding: 1rem; vertical-align: middle; border-bottom: 1px solid #f1f5f9; color: var(--text-main); font-size: 0.95rem; }
    </style>
</head>
<body>
<div class="app-wrapper">
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="mb-4">
            <h2 class="fw-bold mb-1">Customer Web Orders</h2>
            <p class="text-muted">Manage incoming parts requests from the customer portal</p>
        </div>

        <?php if(isset($_SESSION['success_message'])): ?><div class="alert alert-success"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div><?php endif; ?>

        <div class="custom-card p-0 overflow-hidden">
            <table class="table table-custom mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Order ID</th>
                        <th>Customer</th>
                        <th>Item Details</th>
                        <th>Total Cost</th>
                        <th>Status</th>
                        <th class="pe-4 text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $stmtOrders->fetch(PDO::FETCH_ASSOC)): ?>
                        <tr>
                            <td class="ps-4 fw-bold">#<?php echo $row['order_id']; ?><br><small class="text-muted fw-normal"><?php echo date('M d, Y', strtotime($row['order_date'])); ?></small></td>
                            <td class="fw-bold"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($row['part_name']); ?></strong><br>
                                <span class="text-muted" style="font-size: 0.85rem;">Qty: <?php echo $row['quantity']; ?></span>
                            </td>
                            <td class="fw-bold text-success">₱<?php echo number_format($row['total_amount'], 2); ?></td>
                            <td>
                                <?php 
                                $badge = 'badge-soft-secondary';
                                if($row['status'] == 'Pending') $badge = 'badge-soft-danger';
                                if($row['status'] == 'Approved') $badge = 'badge-soft-primary';
                                if($row['status'] == 'Ready for Pickup') $badge = 'badge-soft-warning';
                                if($row['status'] == 'Completed') $badge = 'badge-soft-success';
                                ?>
                                <span class="<?php echo $badge; ?>"><?php echo htmlspecialchars($row['status']); ?></span>
                            </td>
                            <td class="pe-4 text-end">
                                <?php if ($row['status'] !== 'Completed' && $row['status'] !== 'Cancelled'): ?>
                                <form action="../controllers/ManageOrderController.php" method="POST" class="d-flex justify-content-end align-items-center">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="order_id" value="<?php echo $row['order_id']; ?>">
                                    <input type="hidden" name="part_id" value="<?php echo $row['part_id']; ?>">
                                    <input type="hidden" name="quantity" value="<?php echo $row['quantity']; ?>">
                                    
                                    <select name="status" class="form-select form-select-sm w-auto me-2" required>
                                        <option value="Pending" <?php if($row['status']=='Pending') echo 'selected'; ?>>Pending</option>
                                        <option value="Approved" <?php if($row['status']=='Approved') echo 'selected'; ?>>Approve</option>
                                        <option value="Ready for Pickup" <?php if($row['status']=='Ready for Pickup') echo 'selected'; ?>>Ready</option>
                                        <option value="Completed">Completed (Paid)</option>
                                    </select>
                                    <button type="submit" class="btn btn-primary btn-sm fw-bold">Update</button>
                                </form>
                                <?php else: ?>
                                    <span class="text-muted fst-italic" style="font-size: 0.85rem;">No action</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>