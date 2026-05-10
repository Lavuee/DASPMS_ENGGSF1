<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] == 'Head Mechanic' || $_SESSION['role'] == 'Customer') {
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
<title>POS Terminal - Norily's Repair Shop</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/style.css">

<style>
    .main-content {
        overflow-x: hidden;
    }

    .pos-page {
        width: 100%;
        max-width: 100%;
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
    }

    .pos-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        margin-bottom: 0.15rem;
    }

    .pos-header h2 {
        font-size: 2rem;
        font-weight: 900;
        color: var(--dashboard-text-main);
        margin-bottom: 0.25rem;
        line-height: 1.1;
    }

    .pos-header p {
        color: var(--dashboard-text-muted);
        font-size: 0.95rem;
        font-weight: 500;
        margin-bottom: 0;
    }

    .pos-date-pill {
        height: 44px;
        background: rgba(255, 255, 255, 0.56);
        border: 1px solid rgba(15, 23, 42, 0.08);
        color: var(--dashboard-text-muted);
        border-radius: 999px;
        padding: 0.58rem 0.95rem;
        font-size: 0.9rem;
        font-weight: 800;
        white-space: nowrap;
        display: inline-flex;
        align-items: center;
        box-shadow: none;
    }

    .pos-alert {
        border-radius: 16px;
        font-size: 0.92rem;
        margin-bottom: 0;
    }

    .pos-layout {
        display: grid;
        grid-template-columns: minmax(0, 1.45fr) minmax(340px, 0.75fr);
        gap: 1rem;
        align-items: start;
    }

    .pos-panel {
        background: rgba(255, 255, 255, 0.34);
        border: 1px solid rgba(15, 23, 42, 0.05);
        border-radius: 16px;
        box-shadow: none;
        overflow: hidden;
    }

    .pos-panel-header {
        padding: 1.1rem 1.15rem;
        border-bottom: 1px solid rgba(15, 23, 42, 0.06);
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
    }

    .pos-panel-header h5 {
        font-size: 1rem;
        font-weight: 900;
        color: var(--dashboard-text-main);
        margin-bottom: 0.2rem;
    }

    .pos-panel-header p {
        color: var(--dashboard-text-muted);
        margin-bottom: 0;
        font-size: 0.86rem;
    }

    .pos-panel-body {
        padding: 1.15rem;
    }

    .checkout-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        padding: 0.42rem 0.72rem;
        background: rgba(245, 197, 24, 0.16);
        color: var(--black);
        font-size: 0.78rem;
        font-weight: 900;
        white-space: nowrap;
    }

    .pos-section-title {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        font-size: 0.95rem;
        font-weight: 900;
        color: var(--dashboard-text-main);
        margin-bottom: 1rem;
    }

    .pos-section-title span {
        width: 34px;
        height: 34px;
        border-radius: 10px;
        background: rgba(245, 197, 24, 0.16);
        color: var(--black);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        flex-shrink: 0;
    }

    .pos-form-block {
        border: 1px solid rgba(15, 23, 42, 0.06);
        border-radius: 16px;
        padding: 1.15rem;
        background: rgba(255, 255, 255, 0.34);
        margin-bottom: 1rem;
        box-shadow: none;
    }

    .pos-form-block:last-child {
        margin-bottom: 0;
    }

    .form-label {
        color: var(--dashboard-text-main);
        font-weight: 800;
        font-size: 0.82rem;
        margin-bottom: 0.42rem;
    }

    .form-control,
    .form-select {
        min-height: 44px;
        border-radius: 12px;
        border: 1px solid rgba(15, 23, 42, 0.10);
        padding: 0.62rem 0.85rem;
        color: var(--dashboard-text-main);
        background-color: rgba(255, 255, 255, 0.62);
        font-size: 0.92rem;
        box-shadow: none;
    }

    .form-control:focus,
    .form-select:focus {
        border-color: rgba(245, 197, 24, 0.65);
        box-shadow: 0 0 0 4px rgba(245, 197, 24, 0.12);
        background-color: rgba(255, 255, 255, 0.92);
    }

    .customer-type-box {
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 14px;
        padding: 0.85rem;
        background: rgba(255, 255, 255, 0.42);
    }

    .customer-type-help {
        color: var(--dashboard-text-muted);
        font-size: 0.78rem;
        line-height: 1.45;
        margin-top: 0.35rem;
    }

    .walkin-fields {
        display: none;
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid rgba(15, 23, 42, 0.08);
    }

    .registered-customer-box {
        display: none;
    }

    .cart-header-row {
        display: grid;
        grid-template-columns: minmax(0, 2fr) 90px 115px 44px;
        gap: 0.75rem;
        color: var(--dashboard-text-muted);
        font-size: 0.78rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.35px;
        margin-bottom: 0.55rem;
        padding: 0 0.15rem;
    }

    .cart-row {
        display: grid;
        grid-template-columns: minmax(0, 2fr) 90px 115px 44px;
        gap: 0.75rem;
        align-items: center;
        margin-bottom: 0.65rem;
        padding: 0.78rem;
        border: 1px solid rgba(15, 23, 42, 0.055);
        border-radius: 14px;
        background: rgba(255, 255, 255, 0.45);
    }

    .cart-row:last-child {
        margin-bottom: 0;
    }

    .remove-item-btn {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0;
        font-size: 0.88rem;
    }

    .add-item-btn {
        min-height: 38px;
        border-radius: 999px;
        font-weight: 800;
        font-size: 0.86rem;
        padding: 0.45rem 0.8rem;
    }

    .pos-total-card {
        background: rgba(245, 197, 24, 0.08);
        border: 1px solid rgba(245, 197, 24, 0.18);
        border-radius: 16px;
        padding: 1rem 1.1rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .pos-total-label {
        color: var(--dashboard-text-muted);
        font-size: 0.86rem;
        font-weight: 800;
        margin-bottom: 0.2rem;
    }

    .pos-total-value {
        color: var(--dashboard-text-main);
        font-size: 1.85rem;
        font-weight: 900;
        margin-bottom: 0;
        line-height: 1.1;
    }

    .pos-total-icon {
        width: 50px;
        height: 50px;
        border-radius: 14px;
        background: rgba(245, 197, 24, 0.82);
        color: var(--black);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.35rem;
        flex-shrink: 0;
    }

    .pos-reference-note {
        font-size: 0.78rem;
        color: var(--dashboard-text-muted);
        margin-top: 0.35rem;
    }

    .payment-submit-btn {
        border-radius: 999px;
        padding: 0.75rem 1rem;
        font-weight: 900;
        font-size: 0.92rem;
        letter-spacing: 0.2px;
    }

    .recent-sale-card {
        padding: 0.95rem 1.15rem;
        border-bottom: 1px solid rgba(15, 23, 42, 0.05);
        transition: background 0.2s ease;
    }

    .recent-sale-card:last-child {
        border-bottom: none;
    }

    .recent-sale-card:hover {
        background: rgba(245, 197, 24, 0.035);
    }

    .sale-icon {
        width: 38px;
        height: 38px;
        border-radius: 12px;
        background: rgba(245, 197, 24, 0.16);
        color: var(--black);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .sale-title {
        font-weight: 900;
        color: var(--dashboard-text-main);
        margin-bottom: 0.15rem;
        font-size: 0.92rem;
    }

    .sale-meta {
        color: var(--dashboard-text-muted);
        font-size: 0.78rem;
        line-height: 1.45;
    }

    .sale-amount {
        color: var(--dashboard-text-main);
        font-weight: 900;
        margin-bottom: 0.4rem;
        font-size: 0.95rem;
    }

    .receipt-btn {
        border-radius: 999px;
        font-weight: 800;
        font-size: 0.82rem;
        padding: 0.38rem 0.65rem;
    }

    .pos-info-card {
        background: rgba(245, 197, 24, 0.08);
        border: 1px solid rgba(245, 197, 24, 0.18);
        border-radius: 16px;
        padding: 1rem;
        margin-top: 1rem;
    }

    .pos-info-card h6 {
        font-size: 0.95rem;
        font-weight: 900;
        margin-bottom: 0.35rem;
        color: var(--dashboard-text-main);
    }

    .pos-info-card p {
        color: var(--dashboard-text-muted);
        font-size: 0.84rem;
        line-height: 1.55;
        margin-bottom: 0;
    }

    .empty-history {
        padding: 2rem 1.5rem;
        text-align: center;
        color: var(--dashboard-text-muted);
        font-size: 0.92rem;
    }

    @media (max-width: 1199.98px) {
        .pos-layout {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 767.98px) {
        .pos-header {
            flex-direction: column;
            align-items: stretch;
        }

        .pos-header h2 {
            font-size: 1.75rem;
        }

        .pos-date-pill {
            width: 100%;
            justify-content: center;
        }

        .pos-panel-header {
            flex-direction: column;
            align-items: flex-start;
        }

        .cart-header-row {
            display: none;
        }

        .cart-row {
            grid-template-columns: 1fr;
        }

        .remove-item-btn {
            width: 100%;
        }

        .pos-total-card {
            align-items: flex-start;
        }
    }
</style>
</head>

<body>
<div class="app-wrapper">
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="pos-page">

            <div class="pos-header">
                <div>
                    <h2>POS Terminal</h2>
                    <p>Process over-the-counter auto parts sales and record payment details.</p>
                </div>

                <div class="pos-date-pill">
                    <i class="bi bi-calendar3 me-2"></i>
                    <?php echo date('F d, Y'); ?>
                </div>
            </div>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show pos-alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <?php
                        echo htmlspecialchars($_SESSION['success_message']);
                        unset($_SESSION['success_message']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show pos-alert">
                    <i class="bi bi-exclamation-circle-fill me-2"></i>
                    <?php
                        echo htmlspecialchars($_SESSION['error_message']);
                        unset($_SESSION['error_message']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="pos-layout">

                <!-- LEFT SIDE: NEW TRANSACTION -->
                <div class="pos-panel">
                    <div class="pos-panel-header">
                        <div>
                            <h5>New Transaction</h5>
                            <p>Select customer type, add parts, and confirm payment.</p>
                        </div>

                        <span class="checkout-pill">
                            <i class="bi bi-cash-register me-1"></i>
                            Checkout
                        </span>
                    </div>

                    <div class="pos-panel-body">
                        <form action="../controllers/POSController.php" method="POST" id="posForm">
                            <input type="hidden" name="action" value="checkout">

                            <!-- TRANSACTION DETAILS -->
                            <div class="pos-form-block">
                                <div class="pos-section-title">
                                    <span><i class="bi bi-person"></i></span>
                                    Transaction Details
                                </div>

                                <div class="row g-3">
                                    <div class="col-md-7">
                                        <label class="form-label">Customer Type</label>
                                        <select name="customer_type" class="form-select" id="customerType" required>
                                            <option value="Walk-in">Walk-in Guest / One-time Buyer</option>
                                            <option value="Registered">Existing Registered Customer</option>
                                        </select>

                                        <div class="customer-type-help">
                                            Walk-in guest details are saved only in this POS sale and will not be added to the Customers list.
                                        </div>
                                    </div>

                                    <div class="col-md-5">
                                        <label class="form-label">Transaction Type</label>
                                        <input type="text" class="form-control" value="Parts Sale" readonly>
                                    </div>
                                </div>

                                <div class="registered-customer-box" id="registeredCustomerBox">
                                    <div class="row g-3 mt-1">
                                        <div class="col-12">
                                            <label class="form-label">Registered Customer</label>
                                            <select name="customer_id" class="form-select" id="customerId">
                                                <option value="">Select registered customer</option>
                                                <?php while ($cRow = $stmtCustomers->fetch(PDO::FETCH_ASSOC)): ?>
                                                    <option value="<?php echo intval($cRow['customer_id']); ?>">
                                                        <?php echo htmlspecialchars($cRow['last_name'] . ', ' . $cRow['first_name']); ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="walkin-fields" id="walkinFields">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Walk-in Name</label>
                                            <input
                                                type="text"
                                                name="walkin_customer_name"
                                                id="walkinCustomerName"
                                                class="form-control"
                                                placeholder="Optional e.g. Juan Dela Cruz"
                                                maxlength="150"
                                            >
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">Contact Number</label>
                                            <input
                                                type="text"
                                                name="walkin_contact_number"
                                                id="walkinContactNumber"
                                                class="form-control"
                                                placeholder="Optional"
                                                maxlength="30"
                                            >
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label">Address / Note</label>
                                            <input
                                                type="text"
                                                name="walkin_address"
                                                id="walkinAddress"
                                                class="form-control"
                                                placeholder="Optional address or short note"
                                                maxlength="255"
                                            >
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- CART ITEMS -->
                            <div class="pos-form-block">
                                <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
                                    <div class="pos-section-title mb-0">
                                        <span><i class="bi bi-cart3"></i></span>
                                        Cart Items
                                    </div>

                                    <button type="button" class="btn btn-outline-primary btn-sm add-item-btn" onclick="addCartRow()">
                                        <i class="bi bi-plus-lg me-1"></i>
                                        Add Item
                                    </button>
                                </div>

                                <div class="cart-header-row">
                                    <div>Part / Item</div>
                                    <div>Quantity</div>
                                    <div>Unit Price</div>
                                    <div></div>
                                </div>

                                <div id="cart-container"></div>
                            </div>

                            <!-- ORDER SUMMARY -->
                            <div class="pos-form-block">
                                <div class="pos-section-title">
                                    <span><i class="bi bi-receipt"></i></span>
                                    Payment & Summary
                                </div>

                                <div class="pos-total-card">
                                    <div>
                                        <p class="pos-total-label">Grand Total</p>
                                        <h2 class="pos-total-value" id="grandTotalDisplay">₱0.00</h2>
                                    </div>

                                    <div class="pos-total-icon">
                                        <i class="bi bi-cash-stack"></i>
                                    </div>
                                </div>

                                <div class="row g-3 mb-4">
                                    <div class="col-md-6">
                                        <label class="form-label">Payment Method</label>
                                        <select name="payment_method" class="form-select" id="paymentMethod" required>
                                            <option value="Cash">Cash</option>
                                            <option value="GCash">GCash</option>
                                            <option value="Bank Transfer">Bank Transfer</option>
                                            <option value="Cheque">Cheque</option>
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Reference No.</label>
                                        <input type="text" name="reference_number" id="referenceNumber" class="form-control" placeholder="Optional for Cash">
                                        <div class="pos-reference-note" id="referenceNote">
                                            Required for GCash, Bank Transfer, and Cheque.
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary w-100 payment-submit-btn">
                                    <i class="bi bi-check2-circle me-2"></i>
                                    Process Payment
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- RIGHT SIDE: RECENT SALES -->
                <div>
                    <div class="pos-panel">
                        <div class="pos-panel-header">
                            <div>
                                <h5>Recent Sales</h5>
                                <p>Latest completed POS transactions.</p>
                            </div>

                            <i class="bi bi-clock-history text-muted fs-5"></i>
                        </div>

                        <div>
                            <?php
                                $hasHistory = false;
                                while ($row = $stmtHistory->fetch(PDO::FETCH_ASSOC)):
                                    $hasHistory = true;

                                    $customerDisplay = 'Walk-in Customer';

                                    if (!empty($row['customer_id'])) {
                                        $customerDisplay = trim(($row['customer_first_name'] ?? '') . ' ' . ($row['customer_last_name'] ?? ''));
                                        $customerDisplay = $customerDisplay !== '' ? $customerDisplay : 'Registered Customer';
                                    } elseif (!empty($row['walkin_customer_name'])) {
                                        $customerDisplay = $row['walkin_customer_name'];
                                    }

                                    $customerTypeDisplay = !empty($row['customer_type']) ? $row['customer_type'] : 'Walk-in';
                            ?>
                                <div class="recent-sale-card">
                                    <div class="d-flex justify-content-between align-items-start gap-3">
                                        <div class="d-flex gap-3 min-w-0">
                                            <div class="sale-icon">
                                                <i class="bi bi-receipt"></i>
                                            </div>

                                            <div>
                                                <div class="sale-title">
                                                    Sale #<?php echo intval($row['pos_id']); ?>
                                                </div>

                                                <div class="sale-meta">
                                                    <?php echo date('M d, h:i A', strtotime($row['transaction_date'])); ?>
                                                    <br>
                                                    <?php echo htmlspecialchars($customerTypeDisplay); ?>:
                                                    <?php echo htmlspecialchars($customerDisplay); ?>
                                                    <br>
                                                    <?php echo htmlspecialchars($row['payment_method']); ?>
                                                    <?php if (!empty($row['reference_number'])): ?>
                                                        · Ref: <?php echo htmlspecialchars($row['reference_number']); ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="text-end">
                                            <div class="sale-amount">
                                                ₱<?php echo number_format(floatval($row['total_amount']), 2); ?>
                                            </div>

                                            <a
                                                href="print_pos_receipt.php?pos_id=<?php echo intval($row['pos_id']); ?>"
                                                class="btn btn-outline-primary btn-sm receipt-btn"
                                                target="_blank"
                                            >
                                                <i class="bi bi-printer me-1"></i>
                                                Receipt
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>

                            <?php if (!$hasHistory): ?>
                                <div class="empty-history">
                                    <i class="bi bi-receipt fs-3 d-block mb-2"></i>
                                    No recent sales recorded.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="pos-info-card">
                        <h6>
                            <i class="bi bi-info-circle me-1"></i>
                            POS Reminder
                        </h6>
                        <p>
                            For one-time buyers, use Walk-in Guest so the sale is recorded without adding them to the Customers list.
                            Reference number is required for GCash, Bank Transfer, and Cheque transactions.
                        </p>
                    </div>
                </div>

            </div>
        </div>
    </main>
</div>

<script>
const partsData = <?php echo json_encode($partsData); ?>;

function addCartRow() {
    let options = '<option value="" disabled selected>Select part</option>';

    partsData.forEach(p => {
        const stock = parseInt(p.quantity_on_hand);
        const lowStock = parseInt(p.low_stock_threshold || 4);

        if (stock > 0) {
            const stockLabel = stock <= lowStock ? `Low Stock: ${stock}` : `Stock: ${stock}`;

            options += `
                <option
                    value="${p.part_id}"
                    data-price="${p.unit_price}"
                    data-stock="${stock}"
                >
                    [${stockLabel}] ${p.part_name}
                </option>
            `;
        }
    });

    const rowHTML = `
        <div class="cart-row">
            <div>
                <select name="part_ids[]" class="form-select part-select" onchange="onPartChange(this)" required>
                    ${options}
                </select>
            </div>

            <div>
                <input
                    type="number"
                    name="part_qtys[]"
                    class="form-control qty-input"
                    min="1"
                    value="1"
                    onchange="calculateTotal()"
                    oninput="calculateTotal()"
                    required
                    placeholder="Qty"
                >
            </div>

            <div>
                <input
                    type="number"
                    step="0.01"
                    name="part_prices[]"
                    class="form-control price-input"
                    readonly
                    placeholder="0.00"
                >
            </div>

            <div>
                <button type="button" class="btn btn-danger remove-item-btn" onclick="this.closest('.cart-row').remove(); calculateTotal();">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>
    `;

    document.getElementById('cart-container').insertAdjacentHTML('beforeend', rowHTML);
}

function onPartChange(select) {
    const row = select.closest('.cart-row');
    const selected = select.options[select.selectedIndex];
    const stock = parseInt(selected.getAttribute('data-stock') || 0);

    const qtyInput = row.querySelector('.qty-input');
    qtyInput.max = stock;

    if (parseInt(qtyInput.value) > stock) {
        qtyInput.value = stock;
    }

    calculateTotal();
}

function calculateTotal() {
    let grandTotal = 0;

    document.querySelectorAll('.cart-row').forEach(row => {
        const select = row.querySelector('.part-select');
        const qtyInput = row.querySelector('.qty-input');

        if (select.selectedIndex > 0) {
            const selected = select.options[select.selectedIndex];
            const price = parseFloat(selected.getAttribute('data-price'));
            const stock = parseInt(selected.getAttribute('data-stock'));
            let qty = parseInt(qtyInput.value);

            if (isNaN(qty) || qty < 1) {
                qty = 1;
                qtyInput.value = 1;
            }

            if (qty > stock) {
                qty = stock;
                qtyInput.value = stock;
                alert('Quantity cannot exceed available stock.');
            }

            row.querySelector('.price-input').value = price.toFixed(2);
            grandTotal += price * qty;
        }
    });

    document.getElementById('grandTotalDisplay').innerText =
        '₱' + grandTotal.toLocaleString('en-PH', { minimumFractionDigits: 2 });
}

function toggleCustomerFields() {
    const customerType = document.getElementById('customerType').value;
    const registeredBox = document.getElementById('registeredCustomerBox');
    const walkinFields = document.getElementById('walkinFields');
    const customerId = document.getElementById('customerId');

    if (customerType === 'Registered') {
        registeredBox.style.display = 'block';
        walkinFields.style.display = 'none';
        customerId.required = true;
    } else {
        registeredBox.style.display = 'none';
        walkinFields.style.display = 'block';
        customerId.required = false;
        customerId.value = '';
    }
}

function toggleReferenceField() {
    const method = document.getElementById('paymentMethod').value;
    const reference = document.getElementById('referenceNumber');

    if (method === 'Cash') {
        reference.placeholder = 'Optional for Cash';
        reference.required = false;
    } else {
        reference.placeholder = 'Required';
        reference.required = true;
    }
}

document.getElementById('posForm').addEventListener('submit', function(e) {
    const cartRows = document.querySelectorAll('.cart-row');
    const method = document.getElementById('paymentMethod').value;
    const reference = document.getElementById('referenceNumber').value.trim();
    const customerType = document.getElementById('customerType').value;
    const customerId = document.getElementById('customerId').value;

    if (cartRows.length === 0) {
        e.preventDefault();
        alert('Please add at least one item.');
        return;
    }

    if (customerType === 'Registered' && customerId === '') {
        e.preventDefault();
        alert('Please select a registered customer.');
        document.getElementById('customerId').focus();
        return;
    }

    if (['GCash', 'Bank Transfer', 'Cheque'].includes(method) && reference === '') {
        e.preventDefault();
        alert('Reference number is required for ' + method + ' payments.');
        document.getElementById('referenceNumber').focus();
        return;
    }
});

document.getElementById('customerType').addEventListener('change', toggleCustomerFields);
document.getElementById('paymentMethod').addEventListener('change', toggleReferenceField);

toggleCustomerFields();
toggleReferenceField();
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>