<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] === 'Customer') {
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
        min-width: 1080px;
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
        padding: 1rem 0.9rem;
        border-bottom: 1px solid rgba(15, 23, 42, 0.12);
        background: transparent;
        white-space: nowrap;
    }

    .history-table td {
        padding: 1.05rem 0.9rem;
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

    .money-total {
        color: #047857;
        font-weight: 900;
        white-space: nowrap;
    }

    .money-paid {
        color: var(--dashboard-text-main);
        font-weight: 700;
        white-space: nowrap;
    }

    .money-balance {
        color: var(--black);
        font-weight: 900;
        white-space: nowrap;
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

    @media (max-width: 1199.98px) {
        .history-filters {
            grid-template-columns: 1fr 1fr;
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
            <table class="history-table">
                <thead>
                    <tr>
                        <th>Invoice No.</th>
                        <th>Customer</th>
                        <th>Type</th>
                        <th>Reference</th>
                        <th>Total</th>
                        <th>Paid</th>
                        <th>Balance</th>
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
                                    <div class="invoice-main">
                                        <?php echo htmlspecialchars($invoice['invoice_number']); ?>
                                    </div>

                                    <span class="type-pill <?php echo $typeClass; ?>">
                                        <?php echo htmlspecialchars($typeLabel); ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="customer-name">
                                        <?php echo htmlspecialchars($customerName); ?>
                                    </span>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($invoice['invoice_type']); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($reference); ?>
                                </td>

                                <td>
                                    <span class="money-total">
                                        ₱<?php echo number_format($invoice['total_amount'], 2); ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="money-paid">
                                        ₱<?php echo number_format($invoice['amount_paid'], 2); ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="money-balance">
                                        ₱<?php echo number_format($invoice['balance_due'], 2); ?>
                                    </span>
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
                            <td colspan="9">
                                <div class="empty-state">
                                    <i class="bi bi-search"></i>
                                    <div class="fw-bold mb-1">No results found</div>
                                    <div>Try another invoice, customer, type, or status.</div>
                                </div>
                            </td>
                        </tr>

                    <?php else: ?>

                        <tr>
                            <td colspan="9">
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

    </div>
</main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
const search = document.getElementById('invoiceSearch');
const type = document.getElementById('typeFilter');
const status = document.getElementById('statusFilter');
const clearFilters = document.getElementById('clearFilters');
const rows = document.querySelectorAll('.invoice-row');
const noResults = document.getElementById('noResults');

function filterInvoices() {
    let visible = 0;

    rows.forEach(row => {
        const matchSearch = row.dataset.search.includes(search.value.toLowerCase());
        const matchType = type.value === 'all' || row.dataset.type === type.value;
        const matchStatus = status.value === 'all' || row.dataset.status === status.value;

        if (matchSearch && matchType && matchStatus) {
            row.style.display = '';
            visible++;
        } else {
            row.style.display = 'none';
        }
    });

    if (noResults) {
        noResults.style.display = (visible === 0 && rows.length > 0) ? '' : 'none';
    }
}

search.addEventListener('input', filterInvoices);
type.addEventListener('change', filterInvoices);
status.addEventListener('change', filterInvoices);

if (clearFilters) {
    clearFilters.addEventListener('click', function () {
        search.value = '';
        type.value = 'all';
        status.value = 'all';
        filterInvoices();
    });
}
</script>

</body>
</html>