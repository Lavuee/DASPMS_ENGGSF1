<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] == 'Head Mechanic' || $_SESSION['role'] == 'Customer') { header("Location: login.php"); exit; }
require_once '../config/Database.php';
require_once '../models/Part.php';
require_once '../models/Customer.php';
require_once '../models/POS.php';

$database = new Database();
$db = $database->getConnection();
$stmtParts = (new Part($db))->readAll();
$partsData = $stmtParts->fetchAll(PDO::FETCH_ASSOC);
$stmtCustomers = (new Customer($db))->readAll();
$stmtHistory = (new POS($db))->readRecent();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>POS Terminal - Norily's Repair Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="app-wrapper">
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-1">Point of Sale</h2>
                <p class="text-muted">Process over-the-counter parts sales</p>
            </div>
        </div>

        <?php if(isset($_SESSION['success_message'])): ?><div class="alert alert-success"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div><?php endif; ?>
        <?php if(isset($_SESSION['error_message'])): ?><div class="alert alert-danger"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div><?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="custom-card">
                    <h5 class="fw-bold mb-4 border-bottom pb-3">New Transaction</h5>
                    <form action="../controllers/POSController.php" method="POST" id="posForm">
                        <input type="hidden" name="action" value="checkout">
                        
                        <div class="mb-3">
                            <label class="form-label text-muted fw-bold">Select Customer (Optional)</label>
                            <select name="customer_id" class="form-select">
                                <option value="">-- Walk-in Customer --</option>
                                <?php while ($cRow = $stmtCustomers->fetch(PDO::FETCH_ASSOC)): ?>
                                    <option value="<?php echo $cRow['customer_id']; ?>"><?php echo htmlspecialchars($cRow['last_name'] . ', ' . $cRow['first_name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-muted fw-bold">Cart Items</label>
                            <div id="cart-container"></div>
                            <button type="button" class="btn btn-outline-primary btn-sm mt-2 fw-bold" onclick="addCartRow()"><i class="bi bi-plus-lg"></i> Add Item</button>
                        </div>

                        <div class="bg-light p-3 rounded mb-4 d-flex justify-content-between align-items-center border">
                            <h5 class="mb-0 fw-bold text-muted">Grand Total</h5>
                            <h2 class="mb-0 text-success fw-bold" id="grandTotalDisplay">₱0.00</h2>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label class="form-label text-muted fw-bold">Payment Method</label>
                                <select name="payment_method" class="form-select" required>
                                    <option value="Cash">Cash</option>
                                    <option value="GCash">GCash</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted fw-bold">Reference No.</label>
                                <input type="text" name="reference_number" class="form-control" placeholder="Optional">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-3 fw-bold fs-5">Process Payment</button>
                    </form>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="custom-card p-0 overflow-hidden">
                    <div class="p-4 border-bottom">
                        <h5 class="fw-bold mb-0">Recent Sales</h5>
                    </div>
                    <div class="list-group list-group-flush">
                        <?php while ($row = $stmtHistory->fetch(PDO::FETCH_ASSOC)): ?>
                        <div class="list-group-item p-3 d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1 fw-bold">Sale #<?php echo $row['pos_id']; ?></h6>
                                <small class="text-muted"><?php echo date('M d, h:i A', strtotime($row['transaction_date'])); ?> • <?php echo $row['payment_method']; ?></small>
                            </div>
                            <span class="text-success fw-bold">₱<?php echo number_format($row['total_amount'], 2); ?></span>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
const partsData = <?php echo json_encode($partsData); ?>;
function addCartRow() {
    let options = '<option value="" disabled selected>-- Select Part --</option>';
    partsData.forEach(p => { if (p.quantity_on_hand > 0) options += `<option value="${p.part_id}" data-price="${p.unit_price}">[Stock: ${p.quantity_on_hand}] ${p.part_name}</option>`; });
    const rowHTML = `
        <div class="row mb-2 cart-row g-2">
            <div class="col-5"><select name="part_ids[]" class="form-select part-select" onchange="calculateTotal()" required>${options}</select></div>
            <div class="col-3"><input type="number" name="part_qtys[]" class="form-control qty-input" min="1" value="1" onchange="calculateTotal()" required placeholder="Qty"></div>
            <div class="col-3"><input type="number" step="0.01" name="part_prices[]" class="form-control price-input" readonly placeholder="Price"></div>
            <div class="col-1"><button type="button" class="btn btn-danger w-100" onclick="this.closest('.cart-row').remove(); calculateTotal();"><i class="bi bi-x"></i></button></div>
        </div>
    `;
    document.getElementById('cart-container').insertAdjacentHTML('beforeend', rowHTML);
}
function calculateTotal() {
    let grandTotal = 0;
    document.querySelectorAll('.cart-row').forEach(row => {
        const select = row.querySelector('.part-select');
        if (select.selectedIndex > 0) {
            const price = select.options[select.selectedIndex].getAttribute('data-price');
            row.querySelector('.price-input').value = price;
            grandTotal += (parseFloat(price) * parseInt(row.querySelector('.qty-input').value));
        }
    });
    document.getElementById('grandTotalDisplay').innerText = '₱' + grandTotal.toLocaleString('en-PH', {minimumFractionDigits: 2});
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>