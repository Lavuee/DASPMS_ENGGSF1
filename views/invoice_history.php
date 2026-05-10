<?php 
session_start();

$allowedRoles = ['Owner', 'Cashier'];

if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['role'], $allowedRoles)) {
    header("Location: login.php");
    exit;
}

require_once '../config/Database.php';

$db = (new Database())->getConnection();

$query = "
    SELECT 
        i.invoice_id,
        i.invoice_number,
        i.invoice_type,
        i.total_amount,
        i.amount_paid,
        i.balance_due,
        i.payment_status,
        c.first_name,
        c.last_name,
        jo.job_order_number,
        po.order_id AS part_order_id
    FROM invoice i
    JOIN customer c ON i.customer_id = c.customer_id
    LEFT JOIN job_order jo ON i.job_order_id = jo.job_order_id
    LEFT JOIN part_order po ON i.part_order_id = po.order_id
    ORDER BY i.invoice_id DESC
";

$stmt = $db->prepare($query);
$stmt->execute();
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Invoice History - DASPMS</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/style.css">

<style>
    .main-content {
        overflow-x: hidden;
    }

    .invoice-history-page {
        width: 100%;
        max-width: 100%;
    }

    .history-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 1.7rem;
    }

    .history-header h1 {
        color: var(--dashboard-text-main);
        font-size: 2rem;
        font-weight: 900;
        line-height: 1.1;
        margin-bottom: 0.3rem;
    }

    .history-header p {
        color: var(--dashboard-text-muted);
        font-size: 0.95rem;
        font-weight: 500;
        margin-bottom: 0;
    }

    .back-billing-btn {
        min-height: 44px;
        border-radius: 999px;
        border: 1px solid var(--dashboard-primary);
        color: var(--dashboard-text-main);
        background: transparent;
        font-size: 0.9rem;
        font-weight: 900;
        padding: 0.65rem 1.1rem;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        text-decoration: none;
        transition: 0.2s ease;
        white-space: nowrap;
    }

    .back-billing-btn:hover {
        background: var(--dashboard-primary);
        color: var(--black);
    }

    .history-filters {
        display: grid;
        grid-template-columns: minmax(280px, 1fr) 220px 220px 115px;
        gap: 0.8rem;
        align-items: end;
        padding-bottom: 1.15rem;
        border-bottom: 1px solid rgba(15, 23, 42, 0.10);
        margin-bottom: 1.4rem;
    }

    .filter-group label {
        display: block;
        color: var(--dashboard-text-muted);
        font-size: 0.78rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        margin-bottom: 0.45rem;
    }

    .search-control {
        min-height: 46px;
        background: rgba(255, 255, 255, 0.62);
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 14px;
        display: flex;
        align-items: center;
        gap: 0.7rem;
        padding: 0 0.95rem;
    }

    .search-control i {
        color: var(--dashboard-text-muted);
        font-size: 1.05rem;
        flex-shrink: 0;
    }

    .search-control input {
        width: 100%;
        border: none;
        outline: none;
        background: transparent;
        color: var(--dashboard-text-main);
        font-size: 0.92rem;
        font-weight: 500;
    }

    .search-control input::placeholder {
        color: var(--dashboard-text-muted);
    }

    .filter-select {
        min-height: 46px;
        border-radius: 12px;
        border: 1px solid rgba(15, 23, 42, 0.08);
        background-color: rgba(255, 255, 255, 0.62);
        color: var(--dashboard-text-main);
        font-size: 0.9rem;
        font-weight: 600;
        padding: 0.6rem 0.85rem;
        box-shadow: none;
    }

    .filter-select:focus,
    .search-control:focus-within {
        border-color: rgba(245, 197, 24, 0.75);
        box-shadow: 0 0 0 4px rgba(245, 197, 24, 0.12);
    }

    .clear-filter-btn {
        min-height: 46px;
        width: 100%;
        border-radius: 999px;
        border: 1px solid rgba(15, 23, 42, 0.08);
        background: rgba(255, 255, 255, 0.62);
        color: var(--dashboard-text-main);
        font-size: 0.9rem;
        font-weight: 900;
        transition: 0.2s ease;
    }

    .clear-filter-btn:hover {
        background: var(--dashboard-primary);
        border-color: var(--dashboard-primary);
        color: var(--black);
    }

    .history-table-wrap {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .history-table {
        width: 100%;
        min-width: 980px;
        border-collapse: collapse;
        background: transparent;
        margin-bottom: 0;
    }

    .history-table th {
        color: var(--dashboard-text-muted);
        font-size: 0.78rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.35px;
        padding: 1rem 0.85rem;
        border-bottom: 1px solid rgba(15, 23, 42, 0.12);
        background: transparent;
        white-space: nowrap;
    }

    .history-table td {
        padding: 1.05rem 0.85rem;
        color: var(--dashboard-text-main);
        vertical-align: middle;
        border-bottom: 1px solid rgba(15, 23, 42, 0.065);
        font-size: 0.92rem;
        background: transparent;
    }

    .history-table tbody tr:hover td {
        background: rgba(245, 197, 24, 0.035);
    }

    .invoice-main {
        color: var(--dashboard-text-main);
        font-size: 0.94rem;
        font-weight: 900;
        margin-bottom: 0.35rem;
        max-width: 260px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .type-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        padding: 0.35rem 0.7rem;
        font-size: 0.74rem;
        font-weight: 900;
        white-space: nowrap;
    }

    .type-service {
        background: #eaf6ff;
        color: #075985;
    }

    .type-part {
        background: #fff7d6;
        color: #92400e;
    }

    .customer-name {
        font-weight: 900;
        color: var(--dashboard-text-main);
        white-space: nowrap;
    }

    .reference-text {
        color: var(--dashboard-text-main);
        font-size: 0.9rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .amount-summary {
        min-width: 230px;
    }

    .amount-summary-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.45rem;
    }

    .amount-box {
        border: 1px solid #edf0f4;
        border-radius: 12px;
        padding: 0.45rem 0.55rem;
        background: rgba(248, 250, 252, 0.65);
    }

    .amount-label {
        display: block;
        color: var(--dashboard-text-muted);
        font-size: 0.66rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.25px;
        line-height: 1.15;
    }

    .amount-value {
        display: block;
        color: var(--dashboard-text-main);
        font-size: 0.78rem;
        font-weight: 900;
        white-space: nowrap;
        margin-top: 0.15rem;
    }

    .amount-value.total {
        color: #047857;
    }

    .amount-value.balance {
        color: var(--black);
    }

    .invoice-status {
        border-radius: 999px;
        padding: 0.43rem 0.75rem;
        font-size: 0.78rem;
        font-weight: 900;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        white-space: nowrap;
    }

    .status-not-paid {
        background: #fee2e2;
        color: #991b1b;
    }

    .status-partial {
        background: #fef3c7;
        color: #92400e;
    }

    .status-paid {
        background: #dcfce7;
        color: #166534;
    }

    .print-btn {
        min-height: 38px;
        border-radius: 999px;
        border: 1px solid rgba(15, 23, 42, 0.10);
        background: rgba(255, 255, 255, 0.62);
        color: var(--dashboard-text-main);
        font-size: 0.83rem;
        font-weight: 900;
        padding: 0.48rem 0.85rem;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        text-decoration: none;
        white-space: nowrap;
        transition: 0.2s ease;
    }

    .print-btn:hover {
        background: var(--dashboard-primary);
        border-color: var(--dashboard-primary);
        color: var(--black);
    }

    .empty-state {
        padding: 3rem 1rem;
        text-align: center;
        color: var(--dashboard-text-muted);
        font-size: 0.92rem;
    }

    .empty-state i {
        display: block;
        color: var(--dashboard-primary);
        font-size: 2rem;
        margin-bottom: 0.65rem;
    }

    .history-pagination-wrap {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 0.35rem;
        margin-top: 1rem;
        flex-wrap: wrap;
    }

    .history-page-btn {
        min-width: 38px;
        min-height: 38px;
        border: 1px solid #e5e7eb;
        background: #fff;
        color: var(--dashboard-text-muted);
        border-radius: 999px;
        font-size: 0.82rem;
        font-weight: 900;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.45rem 0.75rem;
        transition: 0.2s ease;
    }

    .history-page-btn:hover:not(:disabled) {
        border-color: var(--dashboard-primary);
        background: #fffaf0;
        color: var(--black);
    }

    .history-page-btn.active {
        background: var(--dashboard-primary);
        border-color: var(--dashboard-primary);
        color: var(--black);
    }

    .history-page-btn:disabled {
        opacity: 0.45;
        cursor: not-allowed;
    }

    @media (max-width: 1199.98px) {
        .history-filters {
            grid-template-columns: 1fr 1fr;
        }

        .amount-summary-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 767.98px) {
        .history-header {
            flex-direction: column;
            align-items: stretch;
        }

        .back-billing-btn {
            width: 100%;
            justify-content: center;
        }

        .history-header h1 {
            font-size: 1.65rem;
        }

        .history-filters {
            grid-template-columns: 1fr;
        }

        .history-pagination-wrap {
            justify-content: center;
        }
    }
</style>
</head>

<body>

<div class="app-wrapper">
<?php include '../includes/sidebar.php'; ?>

<main class="main-content">
    <div class="invoice-history-page">

        <div class="history-header">
            <div>
                <h1>Invoice History</h1>
                <p>View and print all invoices from repair jobs and parts orders.</p>
            </div>

            <a href="billing.php" class="back-billing-btn">
                <i class="bi bi-arrow-left"></i>
                Back to Billing
            </a>
        </div>

        <div class="history-filters">
            <div class="filter-group">
                <label>Search</label>
                <div class="search-control">
                    <i class="bi bi-search"></i>
                    <input
                        type="text"
                        id="invoiceSearch"
                        placeholder="Search invoice, customer, reference..."
                    >
                </div>
            </div>

            <div class="filter-group">
                <label>Type</label>
                <select id="typeFilter" class="form-select filter-select">
                    <option value="all">All Types</option>
                    <option value="Service">Service</option>
                    <option value="Part">Part</option>
                </select>
            </div>

            <div class="filter-group">
                <label>Status</label>
                <select id="statusFilter" class="form-select filter-select">
                    <option value="all">All Status</option>
                    <option value="Not Paid">Not Paid</option>
                    <option value="Partial">Partial</option>
                    <option value="Paid">Paid</option>
                </select>
            </div>

            <div class="filter-group">
                <label>&nbsp;</label>
                <button type="button" id="clearFilters" class="clear-filter-btn">
                    Clear
                </button>
            </div>
        </div>

        <div class="history-table-wrap">
            <table class="history-table" id="invoiceHistoryTable">
                <thead>
                    <tr>
                        <th>Invoice No.</th>
                        <th>Customer</th>
                        <th>Type</th>
                        <th>Reference</th>
                        <th>Amount Summary</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (count($invoices) > 0): ?>
                        <?php foreach ($invoices as $invoice): ?>

                            <?php
                                $status = $invoice['payment_status'];

                                $statusClass =
                                    ($status === 'Paid') ? 'status-paid' :
                                    (($status === 'Partial') ? 'status-partial' : 'status-not-paid');

                                $reference = $invoice['invoice_type'] === 'Service'
                                    ? $invoice['job_order_number']
                                    : 'PO-' . $invoice['part_order_id'];

                                $customerName = $invoice['first_name'] . ' ' . $invoice['last_name'];

                                $typeClass = $invoice['invoice_type'] === 'Service'
                                    ? 'type-service'
                                    : 'type-part';

                                $typeLabel = $invoice['invoice_type'] === 'Service'
                                    ? 'Repair Job'
                                    : 'Parts Order';

                                $searchText = strtolower(
                                    $invoice['invoice_number'] . ' ' .
                                    $customerName . ' ' .
                                    $invoice['invoice_type'] . ' ' .
                                    $reference . ' ' .
                                    $status
                                );
                            ?>

                            <tr
                                class="invoice-row"
                                data-search="<?php echo htmlspecialchars($searchText); ?>"
                                data-type="<?php echo htmlspecialchars($invoice['invoice_type']); ?>"
                                data-status="<?php echo htmlspecialchars($status); ?>"
                            >
                                <td>
                                    <div class="invoice-main" title="<?php echo htmlspecialchars($invoice['invoice_number']); ?>">
                                        <?php echo htmlspecialchars($invoice['invoice_number']); ?>
                                    </div>
                                </td>

                                <td>
                                    <span class="customer-name">
                                        <?php echo htmlspecialchars($customerName); ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="type-pill <?php echo $typeClass; ?>">
                                        <?php echo htmlspecialchars($typeLabel); ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="reference-text">
                                        <?php echo htmlspecialchars($reference); ?>
                                    </span>
                                </td>

                                <td class="amount-summary">
                                    <div class="amount-summary-grid">
                                        <div class="amount-box">
                                            <span class="amount-label">Total</span>
                                            <span class="amount-value total">
                                                ₱<?php echo number_format($invoice['total_amount'], 2); ?>
                                            </span>
                                        </div>

                                        <div class="amount-box">
                                            <span class="amount-label">Paid</span>
                                            <span class="amount-value">
                                                ₱<?php echo number_format($invoice['amount_paid'], 2); ?>
                                            </span>
                                        </div>

                                        <div class="amount-box">
                                            <span class="amount-label">Balance</span>
                                            <span class="amount-value balance">
                                                ₱<?php echo number_format($invoice['balance_due'], 2); ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <span class="invoice-status <?php echo $statusClass; ?>">
                                        <?php echo htmlspecialchars($status); ?>
                                    </span>
                                </td>

                                <td>
                                    <a
                                        href="print_invoice.php?invoice_id=<?php echo intval($invoice['invoice_id']); ?>"
                                        target="_blank"
                                        class="print-btn"
                                    >
                                        <i class="bi bi-printer"></i>
                                        Print
                                    </a>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                        <tr id="noResults" style="display:none;">
                            <td colspan="7">
                                <div class="empty-state">
                                    <i class="bi bi-search"></i>
                                    <div class="fw-bold mb-1">No results found</div>
                                    <div>Try another invoice, customer, type, or status.</div>
                                </div>
                            </td>
                        </tr>

                    <?php else: ?>

                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <i class="bi bi-receipt"></i>
                                    <div class="fw-bold mb-1">No invoice records found</div>
                                    <div>Invoices will appear here once generated.</div>
                                </div>
                            </td>
                        </tr>

                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="history-pagination-wrap" id="invoicePagination"></div>

    </div>
</main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
const ITEMS_PER_PAGE = 5;

const search = document.getElementById('invoiceSearch');
const typeFilter = document.getElementById('typeFilter');
const statusFilter = document.getElementById('statusFilter');
const clearFilters = document.getElementById('clearFilters');
const rows = Array.from(document.querySelectorAll('.invoice-row'));
const noResults = document.getElementById('noResults');
const pagination = document.getElementById('invoicePagination');

let currentPage = 1;

function getFilteredRows() {
    const searchValue = search ? search.value.toLowerCase().trim() : '';
    const typeValue = typeFilter ? typeFilter.value : 'all';
    const statusValue = statusFilter ? statusFilter.value : 'all';

    return rows.filter(row => {
        const matchSearch = (row.dataset.search || '').includes(searchValue);
        const matchType = typeValue === 'all' || row.dataset.type === typeValue;
        const matchStatus = statusValue === 'all' || row.dataset.status === statusValue;

        return matchSearch && matchType && matchStatus;
    });
}

function renderPagination(totalPages) {
    if (!pagination) {
        return;
    }

    pagination.innerHTML = '';

    if (totalPages <= 1) {
        pagination.style.display = 'none';
        return;
    }

    pagination.style.display = 'flex';

    const prevButton = document.createElement('button');
    prevButton.type = 'button';
    prevButton.className = 'history-page-btn';
    prevButton.innerHTML = '&laquo;';
    prevButton.disabled = currentPage === 1;
    prevButton.addEventListener('click', function () {
        if (currentPage > 1) {
            currentPage--;
            applyInvoiceFilters();
        }
    });
    pagination.appendChild(prevButton);

    for (let page = 1; page <= totalPages; page++) {
        const pageButton = document.createElement('button');
        pageButton.type = 'button';
        pageButton.className = 'history-page-btn' + (page === currentPage ? ' active' : '');
        pageButton.textContent = page;
        pageButton.addEventListener('click', function () {
            currentPage = page;
            applyInvoiceFilters();
        });
        pagination.appendChild(pageButton);
    }

    const nextButton = document.createElement('button');
    nextButton.type = 'button';
    nextButton.className = 'history-page-btn';
    nextButton.innerHTML = '&raquo;';
    nextButton.disabled = currentPage === totalPages;
    nextButton.addEventListener('click', function () {
        if (currentPage < totalPages) {
            currentPage++;
            applyInvoiceFilters();
        }
    });
    pagination.appendChild(nextButton);
}

function applyInvoiceFilters() {
    const filteredRows = getFilteredRows();
    const totalPages = Math.ceil(filteredRows.length / ITEMS_PER_PAGE) || 1;

    if (currentPage > totalPages) {
        currentPage = totalPages;
    }

    const start = (currentPage - 1) * ITEMS_PER_PAGE;
    const end = start + ITEMS_PER_PAGE;

    rows.forEach(row => {
        row.style.display = 'none';
    });

    filteredRows.slice(start, end).forEach(row => {
        row.style.display = '';
    });

    if (noResults) {
        noResults.style.display = filteredRows.length === 0 && rows.length > 0 ? '' : 'none';
    }

    renderPagination(totalPages);
}

if (search) {
    search.addEventListener('input', function () {
        currentPage = 1;
        applyInvoiceFilters();
    });
}

if (typeFilter) {
    typeFilter.addEventListener('change', function () {
        currentPage = 1;
        applyInvoiceFilters();
    });
}

if (statusFilter) {
    statusFilter.addEventListener('change', function () {
        currentPage = 1;
        applyInvoiceFilters();
    });
}

if (clearFilters) {
    clearFilters.addEventListener('click', function () {
        if (search) {
            search.value = '';
        }

        if (typeFilter) {
            typeFilter.value = 'all';
        }

        if (statusFilter) {
            statusFilter.value = 'all';
        }

        currentPage = 1;
        applyInvoiceFilters();
    });
}

applyInvoiceFilters();
</script>

</body>
</html>