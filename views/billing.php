<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] == 'Head Mechanic' || $_SESSION['role'] == 'Customer') {
    header("Location: login.php");
    exit;
}
require_once '../config/Database.php';

$db = (new Database())->getConnection();

$query = "SELECT jo.job_order_id, jo.job_order_number, i.total_amount, 
                 c.customer_id, c.first_name, c.last_name, 
                 i.amount_paid, 
                 (i.total_amount - i.amount_paid) as balance_due,
                 i.payment_status, jo.date_created
          FROM invoice i
          JOIN job_order jo ON i.job_order_id = jo.job_order_id
          JOIN customer c ON jo.customer_id = c.customer_id
          ORDER BY jo.date_created DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalPendingAmount = 0;
foreach($bills as $b) { 
    if($b['balance_due'] > 0) $totalPendingAmount += $b['balance_due']; 
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Billing & Invoices - Norily's Repair Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .tabs { display: flex; gap: 20px; border-bottom: 1px solid #e2e8f0; margin-bottom: 1.5rem; padding-bottom: 10px; }
        .tab-item { color: var(--text-muted); font-weight: 500; cursor: pointer; padding: 5px 10px; border-radius: 20px; transition: all 0.2s;}
        .tab-item.active { background-color: #f1f5f9; color: var(--text-main); font-weight: 600; }
        .tab-item:hover { color: var(--primary-blue); }
        .modal-content { border-radius: 12px; border: none; box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
    </style>
</head>
<body>

<div class="app-wrapper">
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-1">Billing & Invoices</h2>
                <p class="text-muted"><?php echo count($bills); ?> invoices • ₱<?php echo number_format($totalPendingAmount, 2); ?> pending</p>
            </div>
            <button class="btn btn-primary px-4 py-2 fw-bold" data-bs-toggle="modal" data-bs-target="#createInvoiceModal"><i class="bi bi-plus-lg"></i> Create Invoice</button>
        </div>

        <?php if(isset($_SESSION['success_message'])): ?><div class="alert alert-success alert-dismissible fade show"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
        <?php if(isset($_SESSION['error_message'])): ?><div class="alert alert-danger alert-dismissible fade show"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

        <div class="tabs" id="billingTabs">
            <div class="tab-item active" data-filter="All">All</div>
            <div class="tab-item" data-filter="Pending">Pending</div>
            <div class="tab-item" data-filter="Partial">Partial</div>
            <div class="tab-item" data-filter="Paid">Paid</div>
        </div>

        <div class="custom-search mb-4 shadow-sm">
            <i class="bi bi-search"></i>
            <input type="text" id="searchInput" placeholder="Search invoices by customer name or JO number...">
        </div>

        <div class="row g-3" id="invoiceList">
            <?php if (count($bills) > 0): ?>
                <?php foreach ($bills as $bill): ?>
                <div class="col-12 invoice-card" data-status="<?php echo htmlspecialchars($bill['payment_status']); ?>">
                    <div class="custom-card p-4 d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-box mb-0"><i class="bi bi-receipt"></i></div>
                            <div>
                                <h6 class="fw-bold mb-1 invoice-search-target"><?php echo htmlspecialchars($bill['first_name'] . ' ' . $bill['last_name']); ?> • <?php echo htmlspecialchars($bill['job_order_number']); ?></h6>
                                <small class="text-muted"><?php echo date('M d, Y', strtotime($bill['date_created'])); ?></small>
                            </div>
                        </div>
                        
                        <div class="d-flex align-items-center gap-5">
                            <div class="text-end">
                                <h5 class="fw-bold mb-0">₱<?php echo number_format($bill['total_amount'], 2); ?></h5>
                                <?php if($bill['balance_due'] > 0): ?>
                                    <small class="text-muted">Paid: ₱<?php echo number_format($bill['amount_paid'], 2); ?></small>
                                    <br><small class="text-warning fw-bold">Balance: ₱<?php echo number_format($bill['balance_due'], 2); ?></small>
                                <?php else: ?>
                                    <small class="text-success fw-bold">Paid in full</small>
                                <?php endif; ?>
                            </div>
                            <?php if($bill['balance_due'] > 0): ?>
                                <button class="btn btn-primary px-4 py-2 fw-bold" style="background: var(--sidebar-bg); border: none;" data-bs-toggle="modal" data-bs-target="#payModal<?php echo $bill['job_order_id']; ?>"><i class="bi bi-currency-dollar"></i> Record Payment</button>
                            <?php else: ?>
                                <button class="btn btn-outline-secondary px-4 py-2 fw-bold" disabled><i class="bi bi-check-circle"></i> Paid</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if($bill['balance_due'] > 0): ?>
                <div class="modal fade" id="payModal<?php echo $bill['job_order_id']; ?>" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content p-2">
                            <form action="../controllers/BillingController.php" method="POST">
                                <input type="hidden" name="action" value="pay">
                                <input type="hidden" name="job_order_id" value="<?php echo $bill['job_order_id']; ?>">
                                <input type="hidden" name="customer_id" value="<?php echo $bill['customer_id']; ?>">
                                
                                <div class="modal-header border-0 pb-0">
                                    <h5 class="modal-title fw-bold">Record Payment</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                
                                <div class="modal-body">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted">Total Amount</span>
                                        <span class="fw-bold">₱<?php echo number_format($bill['total_amount'], 2); ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted">Already Paid</span>
                                        <span class="fw-bold">₱<?php echo number_format($bill['amount_paid'], 2); ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-4">
                                        <span class="text-warning fw-bold">Remaining Balance</span>
                                        <span class="text-warning fw-bold">₱<?php echo number_format($bill['balance_due'], 2); ?></span>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label fw-bold text-muted">Payment Amount (₱) *</label>
                                        <input type="number" step="0.01" name="amount" class="form-control" value="<?php echo $bill['balance_due']; ?>" max="<?php echo $bill['balance_due']; ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold text-muted">Payment Method</label>
                                        <select name="payment_method" class="form-select" required>
                                            <option value="Cash">Cash</option>
                                            <option value="GCash">GCash</option>
                                            <option value="Bank">Bank</option>
                                        </select>
                                    </div>
                                    <div class="mb-4">
                                        <label class="form-label fw-bold text-muted">Notes</label>
                                        <input type="text" name="notes" class="form-control" placeholder="Optional reference #">
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold fs-5">Record ₱<?php echo number_format($bill['balance_due'], 2); ?> Payment</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center py-5 text-muted">No generated invoices found. Click "Create Invoice" to start billing.</div>
            <?php endif; ?>
        </div>
    </main>
</div>

<div class="modal fade" id="createInvoiceModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius: 12px; border: none;">
      <form action="../controllers/BillingController.php" method="POST">
          <div class="modal-header border-0 pb-0">
            <h5 class="modal-title fw-bold">Generate New Invoice</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body p-4">
            <input type="hidden" name="action" value="create_invoice">
            
            <div class="mb-4">
                <label class="form-label text-muted fw-bold">Select Completed Job Order</label>
                <select name="job_order_id" class="form-select" required>
                    <option value="" disabled selected>-- Select an uninvoiced job --</option>
                    <?php 
                    $stmtUnbilled = $db->query("SELECT jo.job_order_id, jo.job_order_number, jo.estimated_cost, c.first_name, c.last_name 
                                                FROM job_order jo 
                                                JOIN customer c ON jo.customer_id = c.customer_id 
                                                LEFT JOIN invoice i ON jo.job_order_id = i.job_order_id 
                                                WHERE jo.status IN ('Completed', 'Ready for Pickup') AND i.invoice_id IS NULL");
                    
                    while ($unbilled = $stmtUnbilled->fetch(PDO::FETCH_ASSOC)): ?>
                        <option value="<?php echo $unbilled['job_order_id']; ?>">
                            <?php echo htmlspecialchars($unbilled['job_order_number'] . ' - ' . $unbilled['first_name'] . ' ' . $unbilled['last_name'] . ' (₱' . number_format($unbilled['estimated_cost']) . ')'); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">Generate Invoice</button>
          </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const tabs = document.querySelectorAll('.tab-item');
    const cards = document.querySelectorAll('.invoice-card');

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');

            const filter = tab.getAttribute('data-filter');
            cards.forEach(card => {
                if (filter === 'All' || card.getAttribute('data-status') === filter) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    });

    document.getElementById('searchInput').addEventListener('input', function(e) {
        const term = e.target.value.toLowerCase();
        cards.forEach(card => {
            const text = card.querySelector('.invoice-search-target').innerText.toLowerCase();
            card.style.display = text.includes(term) ? 'block' : 'none';
        });
    });
</script>
</body>
</html>