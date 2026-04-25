<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Customer') { header("Location: login.php"); exit; }
require_once '../config/Database.php';

$db = (new Database())->getConnection();

$stmtC = $db->prepare("SELECT customer_id FROM customer WHERE user_id = ?");
$stmtC->execute([$_SESSION['user_id']]);
$customer_id = $stmtC->fetchColumn();

$stmtJO = $db->prepare("SELECT jo.job_order_number, v.plate_number, v.make, v.model, jo.status, i.payment_status, i.balance_due, jo.date_created 
                        FROM job_order jo 
                        JOIN vehicle v ON jo.vehicle_id = v.vehicle_id
                        LEFT JOIN invoice i ON jo.job_order_id = i.job_order_id
                        WHERE jo.customer_id = ? ORDER BY jo.date_created DESC");
$stmtJO->execute([$customer_id]);
$jobOrders = $stmtJO->fetchAll(PDO::FETCH_ASSOC);

$stmtParts = $db->prepare("SELECT * FROM part WHERE quantity_on_hand > 0 AND is_active = 1");
$stmtParts->execute();

$stmtOrders = $db->prepare("SELECT po.order_id, po.order_date, po.status, po.total_amount, p.part_name, poi.quantity 
                            FROM part_order po 
                            JOIN part_order_item poi ON po.order_id = poi.order_id 
                            JOIN part p ON poi.part_id = p.part_id 
                            WHERE po.customer_id = ? ORDER BY po.order_date DESC");
$stmtOrders->execute([$customer_id]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customer Portal - Norily's Repair Shop</title>
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
            <h2 class="fw-bold mb-1">Good <?php echo (date('H') < 12) ? 'Morning' : ((date('H') < 18) ? 'Afternoon' : 'Evening'); ?>, <?php echo htmlspecialchars($_SESSION['first_name']); ?>!</h2>
            <p class="text-muted">Welcome to your personal garage portal.</p>
        </div>

        <?php if(isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>
        <?php if(isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <div class="row g-4 mb-4">
            <div class="col-md-7">
                <div class="custom-card p-0 overflow-hidden h-100">
                    <div class="p-4 border-bottom">
                        <h5 class="fw-bold mb-0">My Vehicle Repairs</h5>
                    </div>
                    <div class="list-group list-group-flush">
                        <?php if (count($jobOrders) > 0): ?>
                            <?php foreach ($jobOrders as $row): 
                                $badge = 'badge-soft-secondary';
                                if($row['status'] == 'In Progress') $badge = 'badge-soft-primary';
                                if($row['status'] == 'Ready for Pickup') $badge = 'badge-soft-warning';
                                if($row['status'] == 'Completed') $badge = 'badge-soft-success';

                                $payStat = $row['payment_status'] ?? 'Not Paid';
                                $pColor = $payStat == 'Paid' ? 'text-success' : ($payStat == 'Partial' ? 'text-warning' : 'text-danger');
                            ?>
                            <div class="list-group-item p-4 d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="stat-icon icon-blue flex-shrink-0" style="width: 48px; height: 48px;"><i class="bi bi-car-front-fill fs-4"></i></div>
                                    <div>
                                        <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($row['plate_number']); ?> <span class="text-muted fw-normal fs-6 ms-1">• <?php echo htmlspecialchars($row['make'] . ' ' . $row['model']); ?></span></h6>
                                        <div class="text-muted" style="font-size: 0.85rem;">
                                            JO: <?php echo htmlspecialchars($row['job_order_number']); ?> • Date: <?php echo date('M d, Y', strtotime($row['date_created'])); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <span class="<?php echo $badge; ?> mb-2 d-inline-block"><?php echo htmlspecialchars($row['status']); ?></span>
                                    <div class="fw-bold <?php echo $pColor; ?>" style="font-size: 0.85rem;">
                                        <?php echo htmlspecialchars($payStat); ?>
                                        <?php if($payStat != 'Paid' && $row['balance_due']): ?>
                                            (₱<?php echo number_format($row['balance_due'], 2); ?>)
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="p-5 text-center text-muted">You have no active or past repairs.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-5">
                <div class="custom-card h-100" style="border-top: 4px solid var(--primary-blue);">
                    <h5 class="fw-bold mb-3">Order Auto Parts</h5>
                    <p class="text-muted mb-4" style="font-size: 0.9rem;">Need a part? Order it here and we'll reserve it for you to pick up and pay at the shop.</p>
                    
                    <form action="../controllers/OrderController.php" method="POST">
                        <input type="hidden" name="customer_id" value="<?php echo $customer_id; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label text-muted fw-bold" style="font-size: 0.85rem;">Select Part</label>
                            <select name="part_id" class="form-select" required style="border-radius: 8px;">
                                <option value="" disabled selected>-- Browse Parts Catalog --</option>
                                <?php while ($part = $stmtParts->fetch(PDO::FETCH_ASSOC)): ?>
                                    <option value="<?php echo $part['part_id']; ?>">
                                        <?php echo htmlspecialchars($part['part_name']); ?> - ₱<?php echo number_format($part['unit_price'], 2); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label text-muted fw-bold" style="font-size: 0.85rem;">Quantity</label>
                            <input type="number" name="quantity" class="form-control" min="1" value="1" required style="border-radius: 8px;">
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100 fw-bold py-2">Submit Order</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="custom-card p-0 overflow-hidden">
            <div class="p-4 border-bottom">
                <h5 class="fw-bold mb-0">My Ordered Parts (Tracking)</h5>
            </div>
            <table class="table table-custom mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Date Ordered</th>
                        <th>Item Details</th>
                        <th>Total Cost</th>
                        <th>Order Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($stmtOrders->rowCount() > 0): ?>
                        <?php while ($order = $stmtOrders->fetch(PDO::FETCH_ASSOC)): ?>
                            <tr>
                                <td class="ps-4 text-muted"><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($order['part_name']); ?></strong><br>
                                    <small class="text-muted">Qty: <?php echo htmlspecialchars($order['quantity']); ?></small>
                                </td>
                                <td class="fw-bold">₱<?php echo number_format($order['total_amount'], 2); ?></td>
                                <td>
                                    <?php 
                                    $oBadge = 'badge-soft-secondary';
                                    if($order['status'] == 'Pending') $oBadge = 'badge-soft-danger';
                                    if($order['status'] == 'Approved') $oBadge = 'badge-soft-primary';
                                    if($order['status'] == 'Ready for Pickup') $oBadge = 'badge-soft-warning';
                                    if($order['status'] == 'Completed') $oBadge = 'badge-soft-success';
                                    if($order['status'] == 'Cancelled') $oBadge = 'badge-soft-secondary text-dark';
                                    ?>
                                    <span class="<?php echo $oBadge; ?>"><?php echo htmlspecialchars($order['status']); ?></span>
                                    
                                    <?php if($order['status'] == 'Ready for Pickup'): ?>
                                        <br><small class="text-success fw-bold mt-1 d-inline-block"><i class="bi bi-geo-alt"></i> Please proceed to the shop.</small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-center py-5 text-muted">You haven't ordered any parts yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>