<?php
session_start();

require_once '../config/Database.php';
require_once '../models/Billing.php';

if (!isset($_SESSION['logged_in'])) {
    header("Location: login.php");
    exit;
}

$db = (new Database())->getConnection();
$billing = new Billing($db);
$bills = $billing->getPendingBills();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>DASPMS - Billing</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/style.css">

<style>
    .main-content {
        overflow-x: hidden;
    }

    .billing-page {
        width: 100%;
        max-width: 100%;
    }

    .billing-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 1.5rem;
    }

    .billing-header h1 {
        font-size: 2rem;
        font-weight: 900;
        color: var(--dashboard-text-main);
        margin-bottom: 0.25rem;
        line-height: 1.1;
    }

    .billing-header p {
        margin-bottom: 0;
        color: var(--dashboard-text-muted);
        font-size: 0.95rem;
        font-weight: 500;
    }

    .billing-history-btn {
        background: transparent;
        border: 1px solid var(--dashboard-primary);
        color: var(--black);
        font-weight: 800;
        font-size: 0.9rem;
        min-height: 44px;
        padding: 0.55rem 1rem;
        border-radius: 999px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.45rem;
        transition: 0.2s ease;
        white-space: nowrap;
    }

    .billing-history-btn i {
        font-size: 0.95rem;
    }

    .billing-history-btn:hover {
        background: var(--dashboard-primary);
        color: var(--black);
        border-color: var(--dashboard-primary);
    }

    .billing-alert {
        border-radius: 16px;
        margin-bottom: 1.5rem;
        font-size: 0.92rem;
    }

    .billing-filters {
        margin-bottom: 1.5rem;
    }

    .billing-filter-grid {
        display: grid;
        grid-template-columns: minmax(260px, 1fr) 240px 240px 105px;
        gap: 0.85rem;
        align-items: end;
        padding-bottom: 1rem;
        border-bottom: 1px solid #e5e7eb;
    }

    .billing-filter-group {
        min-width: 0;
    }

    .billing-filter-label {
        display: block;
        margin-bottom: 0.35rem;
        font-size: 0.76rem;
        font-weight: 900;
        color: var(--dashboard-text-muted);
        text-transform: uppercase;
        letter-spacing: 0.35px;
    }

    .billing-search-wrap {
        width: 100%;
        min-height: 44px;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        padding: 0.65rem 0.9rem;
        box-shadow: 0 4px 12px rgba(17, 17, 17, 0.035);
    }

    .billing-search-wrap i {
        font-size: 1.05rem;
        color: var(--dashboard-text-muted);
        flex-shrink: 0;
    }

    .billing-search-input {
        width: 100%;
        border: none;
        outline: none;
        background: transparent;
        color: var(--dashboard-text-main);
        font-size: 0.92rem;
        padding: 0;
    }

    .billing-search-input::placeholder {
        color: #6b7280;
    }

    .billing-select,
    .billing-clear-btn {
        height: 44px;
        font-size: 0.92rem;
    }

    .billing-select {
        border-radius: 12px;
        border: 1px solid #e5e7eb;
        color: var(--dashboard-text-main);
        box-shadow: none;
        padding: 0.55rem 2.25rem 0.55rem 0.85rem;
    }

    .billing-select:focus,
    .payment-grid input:focus,
    .payment-grid select:focus {
        border-color: var(--dashboard-primary);
        box-shadow: 0 0 0 4px rgba(245, 197, 24, 0.18);
    }

    .billing-clear-btn {
        width: 100%;
        border-radius: 999px;
        border: 1px solid #e5e7eb;
        background: #fff;
        color: var(--dashboard-text-main);
        font-size: 0.9rem;
        font-weight: 800;
        padding: 0.55rem 0.85rem;
        transition: 0.2s ease;
    }

    .billing-clear-btn:hover {
        background: #f8fafc;
        color: var(--black);
    }

    .billing-table-wrap {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .billing-table {
        width: 100%;
        min-width: 1320px;
        border-collapse: collapse;
        background: transparent;
    }

    .billing-table thead th {
        color: var(--dashboard-text-muted);
        font-size: 0.82rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.35px;
        padding: 0.9rem 0.85rem;
        border-bottom: 1px solid #dcdfe4;
        background: transparent;
        white-space: nowrap;
    }

    .billing-table tbody td {
        padding: 1rem 0.85rem;
        vertical-align: middle;
        border-bottom: 1px solid #e8ebef;
        color: var(--dashboard-text-main);
        background: transparent;
        font-size: 0.95rem;
    }

    .billing-table tbody tr:hover td {
        background: rgba(245, 197, 24, 0.035);
    }

    .billing-ref strong {
        display: block;
        font-size: 0.95rem;
        font-weight: 900;
        color: var(--dashboard-text-main);
        line-height: 1.2;
    }

    .source-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-top: 0.35rem;
        padding: 0.42rem 0.72rem;
        border-radius: 999px;
        font-size: 0.78rem;
        font-weight: 900;
        background: #edf4f8;
        color: #16324f;
        white-space: nowrap;
    }

    .billing-customer {
        font-size: 0.95rem;
        font-weight: 900;
        white-space: nowrap;
    }

    .billing-money {
        font-size: 0.95rem;
        white-space: nowrap;
    }

    .billing-money.total {
        color: #047857;
        font-weight: 900;
    }

    .billing-money.balance {
        font-weight: 900;
        color: var(--dashboard-text-main);
    }

    .billing-status {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        padding: 0.42rem 0.72rem;
        font-size: 0.78rem;
        font-weight: 900;
        white-space: nowrap;
    }

    .status-no-invoice {
        background: #f1f5f9;
        color: #475569;
    }

    .status-not-paid {
        background: #fee2e2;
        color: #b91c1c;
    }

    .status-partial {
        background: #fef3c7;
        color: #92400e;
    }

    .status-paid {
        background: #dcfce7;
        color: #047857;
    }

    .billing-invoice-cell,
    .billing-payment-cell {
        min-width: 170px;
    }

    .billing-payment-cell {
        min-width: 405px;
    }

    .billing-action-btn,
    .billing-outline-btn,
    .billing-pay-btn {
        border-radius: 999px;
        font-weight: 900;
        font-size: 0.82rem;
        padding: 0.48rem 0.75rem;
        transition: 0.2s ease;
        white-space: nowrap;
    }

    .billing-action-btn {
        background: var(--dashboard-primary);
        border: 1px solid var(--dashboard-primary);
        color: var(--black);
    }

    .billing-action-btn:hover {
        background: var(--black);
        border-color: var(--black);
        color: var(--white);
    }

    .billing-outline-btn {
        background: #fff;
        border: 1px solid #e5e7eb;
        color: var(--dashboard-text-main);
    }

    .billing-outline-btn:hover {
        border-color: var(--dashboard-primary);
        background: #fffaf0;
        color: var(--black);
    }

    .billing-pay-btn {
        background: #15803d;
        border: 1px solid #15803d;
        color: #fff;
    }

    .billing-pay-btn:hover {
        background: #166534;
        border-color: #166534;
        color: #fff;
    }

    .payment-form {
        width: 100%;
        margin: 0;
    }

    .payment-grid {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
        align-items: center;
    }

    .payment-grid input,
    .payment-grid select {
        width: 125px;
        min-height: 38px;
        border-radius: 10px;
        border: 1px solid #d9dee6;
        font-size: 0.84rem;
        color: var(--dashboard-text-main);
        box-shadow: none;
    }

    .reference-number {
        width: 145px !important;
    }

    .billing-helper {
        display: block;
        color: var(--dashboard-text-muted);
        font-size: 0.78rem;
        font-style: italic;
    }

    .billing-empty-state {
        text-align: center;
        padding: 3rem 1rem;
        color: var(--dashboard-text-muted);
        font-size: 0.95rem;
    }

    .billing-empty-state i {
        font-size: 2.25rem;
        color: var(--dashboard-primary);
        display: block;
        margin-bottom: 0.7rem;
    }

    .billing-empty-state .fw-bold {
        color: var(--dashboard-text-main);
        font-size: 0.95rem;
        font-weight: 900 !important;
    }

    @media (max-width: 1199.98px) {
        .billing-filter-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 991.98px) {
        .billing-header h1 {
            font-size: 2rem;
        }
    }

    @media (max-width: 767.98px) {
        .billing-header {
            flex-direction: column;
            align-items: stretch;
        }

        .billing-history-btn {
            width: 100%;
            justify-content: center;
        }

        .billing-header h1 {
            font-size: 1.75rem;
        }

        .billing-filter-grid {
            grid-template-columns: 1fr;
        }

        .billing-search-wrap,
        .billing-select,
        .billing-clear-btn {
            height: 42px;
            min-height: 42px;
        }

        .payment-grid input,
        .payment-grid select,
        .reference-number,
        .payment-grid button {
            width: 100% !important;
        }

        .billing-payment-cell {
            min-width: 100%;
        }
    }
</style>
</head>

<body>

<div class="app-wrapper">
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="billing-page">

            <div class="billing-header">
                <div>
                    <h1>Billing & Payment</h1>
                    <p>Generate invoices for completed repair jobs and ready-for-pickup web orders.</p>
                </div>

                <a href="invoice_history.php" class="billing-history-btn">
                    <i class="bi bi-receipt"></i>
                    Invoice History
                </a>
            </div>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show billing-alert">
                    <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
                    <button class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show billing-alert">
                    <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
                    <button class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="billing-filters">
                <div class="billing-filter-grid">
                    <div class="billing-filter-group">
                        <label class="billing-filter-label" for="billingSearch">Search</label>
                        <div class="billing-search-wrap">
                            <i class="bi bi-search"></i>
                            <input
                                type="text"
                                id="billingSearch"
                                class="billing-search-input"
                                placeholder="Search reference no. or customer name"
                            >
                        </div>
                    </div>

                    <div class="billing-filter-group">
                        <label class="billing-filter-label" for="typeFilter">Type</label>
                        <select id="typeFilter" class="form-select billing-select">
                            <option value="all">All Types</option>
                            <option value="job">Repair Job</option>
                            <option value="part">Web Order</option>
                        </select>
                    </div>

                    <div class="billing-filter-group">
                        <label class="billing-filter-label" for="statusFilter">Status</label>
                        <select id="statusFilter" class="form-select billing-select">
                            <option value="all">All Status</option>
                            <option value="No Invoice">No Invoice</option>
                            <option value="Not Paid">Not Paid</option>
                            <option value="Partial">Partial</option>
                        </select>
                    </div>

                    <div class="billing-filter-group">
                        <label class="billing-filter-label d-block">&nbsp;</label>
                        <button type="button" id="clearFilters" class="btn billing-clear-btn">
                            Clear
                        </button>
                    </div>
                </div>
            </div>

            <div class="billing-table-wrap">
                <table class="billing-table" id="billingTable">
                    <thead>
                        <tr>
                            <th>Reference No.</th>
                            <th>Customer</th>
                            <th>Total</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th>Invoice</th>
                            <th>Payment</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if ($bills->rowCount() > 0): ?>
                            <?php while ($row = $bills->fetch(PDO::FETCH_ASSOC)): ?>

                                <?php
                                    $status = $row['payment_status'] ?? 'No Invoice';
                                    $balance = floatval($row['balance_due']);
                                    $total = floatval($row['total_amount']);
                                    $paid = floatval($row['amount_paid']);

                                    $statusClass =
                                        ($status === 'Paid') ? 'status-paid' :
                                        (($status === 'Partial') ? 'status-partial' :
                                        (($status === 'Not Paid') ? 'status-not-paid' :
                                        'status-no-invoice'));

                                    $sourceLabel = ($row['source'] === 'job') ? 'Repair Job' : 'Web Order';
                                    $customerName = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
                                ?>

                                <tr
                                    class="billing-row"
                                    data-search="<?php echo htmlspecialchars(strtolower(($row['ref_number'] ?? '') . ' ' . $customerName)); ?>"
                                    data-type="<?php echo htmlspecialchars($row['source']); ?>"
                                    data-status="<?php echo htmlspecialchars($status); ?>"
                                >
                                    <td class="billing-ref">
                                        <strong><?php echo htmlspecialchars($row['ref_number']); ?></strong>
                                        <span class="source-pill"><?php echo htmlspecialchars($sourceLabel); ?></span>
                                    </td>

                                    <td class="billing-customer">
                                        <?php echo htmlspecialchars($customerName); ?>
                                    </td>

                                    <td class="billing-money total">
                                        ₱<?php echo number_format($total, 2); ?>
                                    </td>

                                    <td class="billing-money">
                                        ₱<?php echo number_format($paid, 2); ?>
                                    </td>

                                    <td class="billing-money balance">
                                        ₱<?php echo number_format($balance, 2); ?>
                                    </td>

                                    <td>
                                        <span class="billing-status <?php echo $statusClass; ?>">
                                            <?php echo htmlspecialchars($status); ?>
                                        </span>
                                    </td>

                                    <td class="billing-invoice-cell">
                                        <?php if (empty($row['invoice_id'])): ?>

                                            <form action="../controllers/BillingController.php" method="POST" class="m-0">
                                                <input type="hidden" name="action" value="create_invoice">
                                                <input type="hidden" name="source" value="<?php echo htmlspecialchars($row['source']); ?>">
                                                <input type="hidden" name="ref_id" value="<?php echo intval($row['ref_id']); ?>">

                                                <button class="btn billing-action-btn">
                                                    Generate
                                                </button>
                                            </form>

                                        <?php else: ?>

                                            <a
                                                href="print_invoice.php?invoice_id=<?php echo intval($row['invoice_id']); ?>"
                                                class="btn billing-outline-btn"
                                                target="_blank"
                                            >
                                                <i class="bi bi-printer"></i> Print
                                            </a>

                                        <?php endif; ?>
                                    </td>

                                    <td class="billing-payment-cell">
                                        <?php if (!empty($row['invoice_id']) && $status !== 'Paid'): ?>

                                            <form action="../controllers/BillingController.php" method="POST" class="payment-form">
                                                <input type="hidden" name="action" value="pay">
                                                <input type="hidden" name="invoice_id" value="<?php echo intval($row['invoice_id']); ?>">

                                                <div class="payment-grid">
                                                    <input
                                                        type="number"
                                                        name="amount"
                                                        class="form-control form-control-sm payment-amount"
                                                        min="1"
                                                        step="0.01"
                                                        max="<?php echo htmlspecialchars($balance); ?>"
                                                        value="<?php echo htmlspecialchars($balance); ?>"
                                                        required
                                                    >

                                                    <select name="payment_method" class="form-select form-select-sm payment-method">
                                                        <option>Cash</option>
                                                        <option>GCash</option>
                                                        <option>Bank Transfer</option>
                                                        <option>Cheque</option>
                                                    </select>

                                                    <input
                                                        type="text"
                                                        name="reference_number"
                                                        class="form-control form-control-sm reference-number"
                                                        placeholder="Reference"
                                                    >

                                                    <button class="btn billing-pay-btn">
                                                        Pay
                                                    </button>
                                                </div>
                                            </form>

                                        <?php elseif (empty($row['invoice_id'])): ?>

                                            <span class="billing-helper">Generate invoice first</span>

                                        <?php else: ?>

                                            <span class="billing-status status-paid">Paid</span>

                                        <?php endif; ?>
                                    </td>
                                </tr>

                            <?php endwhile; ?>
                        <?php else: ?>

                            <tr>
                                <td colspan="8">
                                    <div class="billing-empty-state">
                                        <i class="bi bi-receipt-cutoff"></i>
                                        <div class="fw-bold mb-1">No pending billing items</div>
                                        <div>Completed repair jobs and ready-for-pickup web orders will appear here.</div>
                                    </div>
                                </td>
                            </tr>

                        <?php endif; ?>

                        <tr id="noBillingResults" style="display:none;">
                            <td colspan="8">
                                <div class="billing-empty-state">
                                    <i class="bi bi-search"></i>
                                    <div class="fw-bold mb-1">No billing records match your search/filter</div>
                                    <div>Try another keyword or change the selected filters.</div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.querySelectorAll('.payment-form').forEach(form => {
    form.addEventListener('submit', e => {
        const method = form.querySelector('.payment-method');
        const ref = form.querySelector('.reference-number');
        const amount = form.querySelector('.payment-amount');

        if (parseFloat(amount.value) <= 0) {
            alert('Invalid amount');
            e.preventDefault();
            return;
        }

        if (['GCash', 'Bank Transfer', 'Cheque'].includes(method.value) && ref.value.trim() === '') {
            alert('Reference required');
            e.preventDefault();
        }
    });
});

const searchInput = document.getElementById('billingSearch');
const typeFilter = document.getElementById('typeFilter');
const statusFilter = document.getElementById('statusFilter');
const clearFilters = document.getElementById('clearFilters');
const rows = document.querySelectorAll('.billing-row');
const noResults = document.getElementById('noBillingResults');

function applyBillingFilters() {
    const searchValue = searchInput.value.trim().toLowerCase();
    const typeValue = typeFilter.value;
    const statusValue = statusFilter.value;

    let visibleCount = 0;

    rows.forEach(row => {
        const rowSearch = row.dataset.search || '';
        const rowType = row.dataset.type || '';
        const rowStatus = row.dataset.status || '';

        const matchesSearch = rowSearch.includes(searchValue);
        const matchesType = typeValue === 'all' || rowType === typeValue;
        const matchesStatus = statusValue === 'all' || rowStatus === statusValue;

        if (matchesSearch && matchesType && matchesStatus) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    if (noResults) {
        noResults.style.display = (visibleCount === 0 && rows.length > 0) ? '' : 'none';
    }
}

if (searchInput) {
    searchInput.addEventListener('input', applyBillingFilters);
}

if (typeFilter) {
    typeFilter.addEventListener('change', applyBillingFilters);
}

if (statusFilter) {
    statusFilter.addEventListener('change', applyBillingFilters);
}

if (clearFilters) {
    clearFilters.addEventListener('click', () => {
        if (searchInput) searchInput.value = '';
        if (typeFilter) typeFilter.value = 'all';
        if (statusFilter) statusFilter.value = 'all';
        applyBillingFilters();
    });
}
</script>

</body>
</html>