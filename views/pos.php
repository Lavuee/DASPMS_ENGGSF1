<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] == 'Head Mechanic') {
    header("Location: login.php");
    exit;
}

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
    <title>Point of Sale - DASPMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4 shadow-sm">
    <div class="container-fluid">
        <span class="navbar-brand mb-0 h1">DASPMS - POS Terminal</span>
        <div class="d-flex">
            <a href="dashboard_<?php echo strtolower(explode(' ', $_SESSION['role'])[0]); ?>.php" class="btn btn-outline-light btn-sm me-2">Dashboard</a>
            <a href="../controllers/AuthController.php?action=logout" class="btn btn-outline-danger btn-sm">Logout</a>
        </div>
    </div>
</nav>

<div class="container-fluid px-4">
    <?php if(isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if(isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-7 mb-4">
            <div class="card shadow-sm border-primary">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">New Sale</h5>
                </div>
                <div class="card-body">
                    <form action="../controllers/POSController.php" method="POST" id="posForm">
                        <input type="hidden" name="action" value="checkout">
                        
                        <div class="mb-3">
                            <label class="form-label">Customer (Optional for Walk-ins)</label>
                            <select name="customer_id" class="form-select">
                                <option value="">-- Walk-in Customer --</option>
                                <?php while ($cRow = $stmtCustomers->fetch(PDO::FETCH_ASSOC)): ?>
                                    <option value="<?php echo $cRow['customer_id']; ?>">
                                        <?php echo htmlspecialchars($cRow['last_name'] . ', ' . $cRow['first_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <h6 class="border-bottom pb-2 mt-4">Cart Items</h6>
                        <div id="cart-container"></div>
                        <button type="button" class="btn btn-sm btn-outline-primary mb-4" onclick="addCartRow()">+ Add Item</button>

                        <div class="row bg-light p-3 rounded mb-4 align-items-center border">
                            <div class="col-md-6">
                                <h4 class="mb-0">Grand Total:</h4>
                            </div>
                            <div class="col-md-6 text-end">
                                <h2 class="mb-0 text-success" id="grandTotalDisplay">₱0.00</h2>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Payment Method *</label>
                                <select name="payment_method" class="form-select" required>
                                    <option value="Cash" selected>Cash</option>
                                    <option value="GCash">GCash</option>
                                    <option value="Bank">Bank Transfer</option>
                                    <option value="Cheque">Cheque</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Reference No. (For Digital/Cheque)</label>
                                <input type="text" name="reference_number" class="form-control" placeholder="e.g., Ref. #">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-success btn-lg w-100 mt-2">Process Payment</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0">Recent Sales History</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-striped mb-0 text-sm">
                        <thead>
                            <tr>
                                <th class="ps-3">Date</th>
                                <th>Method</th>
                                <th class="text-end pe-3">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $stmtHistory->fetch(PDO::FETCH_ASSOC)): ?>
                                <tr>
                                    <td class="ps-3"><?php echo date('M d, h:i A', strtotime($row['transaction_date'])); ?></td>
                                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($row['payment_method']); ?></span></td>
                                    <td class="text-end pe-3 text-success fw-bold">₱<?php echo number_format($row['total_amount'], 2); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const partsData = <?php echo json_encode($partsData); ?>;

function addCartRow() {
    let options = '<option value="" disabled selected>-- Scan or Select Part --</option>';
    partsData.forEach(p => {
        if (p.quantity_on_hand > 0) {
            options += `<option value="${p.part_id}" data-price="${p.unit_price}">[Stock: ${p.quantity_on_hand}] ${p.part_name}</option>`;
        }
    });

    const rowHTML = `
        <div class="row mb-2 cart-row">
            <div class="col-md-5">
                <select name="part_ids[]" class="form-select part-select" onchange="calculateTotal()" required>
                    ${options}
                </select>
            </div>
            <div class="col-md-3">
                <div class="input-group">
                    <span class="input-group-text">Qty</span>
                    <input type="number" name="part_qtys[]" class="form-control qty-input" min="1" value="1" onchange="calculateTotal()" required>
                </div>
            </div>
            <div class="col-md-3">
                <div class="input-group">
                    <span class="input-group-text">₱</span>
                    <input type="number" step="0.01" name="part_prices[]" class="form-control price-input" readonly>
                </div>
            </div>
            <div class="col-md-1">
                <button type="button" class="btn btn-danger btn-sm w-100" onclick="this.closest('.cart-row').remove(); calculateTotal();">X</button>
            </div>
        </div>
    `;
    document.getElementById('cart-container').insertAdjacentHTML('beforeend', rowHTML);
}

function calculateTotal() {
    let grandTotal = 0;
    const rows = document.querySelectorAll('.cart-row');
    
    rows.forEach(row => {
        const select = row.querySelector('.part-select');
        const qty = row.querySelector('.qty-input').value;
        const priceInput = row.querySelector('.price-input');
        
        if (select.selectedIndex > 0) {
            const price = select.options[select.selectedIndex].getAttribute('data-price');
            priceInput.value = price; // Update the locked price field
            grandTotal += (parseFloat(price) * parseInt(qty));
        }
    });

    document.getElementById('grandTotalDisplay').innerText = '₱' + grandTotal.toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>