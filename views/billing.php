<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] == 'Head Mechanic') {
    header("Location: login.php");
    exit;
}

require_once '../config/Database.php';
require_once '../models/Billing.php';

$database = new Database();
$db = $database->getConnection();
$billing = new Billing($db);
$stmtBills = $billing->getPendingBills();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Billing & Payments - DASPMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm">
    <div class="container-fluid">
        <span class="navbar-brand mb-0 h1">DASPMS - <?php echo $_SESSION['role']; ?></span>
        <div class="d-flex">
            <a href="dashboard_<?php echo strtolower(explode(' ', $_SESSION['role'])[0]); ?>.php" class="btn btn-outline-light btn-sm me-2">Dashboard</a>
            <a href="../controllers/AuthController.php?action=logout" class="btn btn-outline-danger btn-sm">Logout</a>
        </div>
    </div>
</nav>

<div class="container">
    <h2 class="mb-4">Pending Repair Bills</h2>

    <?php if(isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if(isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th class="ps-4">Job Order No.</th>
                        <th>Customer</th>
                        <th>Total Cost</th>
                        <th>Amount Paid</th>
                        <th>Balance Due</th>
                        <th class="pe-4 text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($stmtBills->rowCount() > 0): ?>
                        <?php while ($row = $stmtBills->fetch(PDO::FETCH_ASSOC)): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-primary"><?php echo htmlspecialchars($row['job_order_number']); ?></td>
                                <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                <td>₱<?php echo number_format($row['total_amount'], 2); ?></td>
                                <td class="text-success">₱<?php echo number_format($row['amount_paid'], 2); ?></td>
                                <td class="text-danger fw-bold">₱<?php echo number_format($row['balance_due'], 2); ?></td>
                                <td class="pe-4 text-end">
                                    <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#payModal<?php echo $row['job_order_id']; ?>">Receive Payment</button>
                                </td>
                            </tr>

                            <div class="modal fade" id="payModal<?php echo $row['job_order_id']; ?>" tabindex="-1">
                              <div class="modal-dialog">
                                <div class="modal-content">
                                  <form action="../controllers/BillingController.php" method="POST">
                                      <div class="modal-header bg-success text-white">
                                        <h5 class="modal-title">Process Payment</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                      </div>
                                      <div class="modal-body">
                                        <input type="hidden" name="action" value="pay">
                                        <input type="hidden" name="job_order_id" value="<?php echo $row['job_order_id']; ?>">
                                        <input type="hidden" name="customer_id" value="<?php echo $row['customer_id']; ?>">
                                        
                                        <h4 class="text-center text-danger mb-4">Amount Due: ₱<?php echo number_format($row['balance_due'], 2); ?></h4>

                                        <div class="mb-3">
                                            <label class="form-label">Payment Amount (₱) *</label>
                                            <input type="number" step="0.01" name="amount" class="form-control" value="<?php echo $row['balance_due']; ?>" max="<?php echo $row['balance_due']; ?>" required>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Method *</label>
                                            <select name="payment_method" class="form-select" required>
                                                <option value="Cash">Cash</option>
                                                <option value="GCash">GCash</option>
                                                <option value="Bank">Bank Transfer</option>
                                                <option value="Cheque">Cheque</option>
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Reference Number</label>
                                            <input type="text" name="reference_number" class="form-control" placeholder="Optional for Cash">
                                        </div>
                                      </div>
                                      <div class="modal-footer border-0">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-success">Confirm Payment</button>
                                      </div>
                                  </form>
                                </div>
                              </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">No pending bills at this time.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>