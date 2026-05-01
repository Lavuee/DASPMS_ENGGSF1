<?php
session_start();

if (!isset($_SESSION['logged_in']) || ($_SESSION['role'] !== 'Cashier' && $_SESSION['role'] !== 'Owner')) {
    header("Location: login.php");
    exit;
}

require_once '../config/Database.php';
require_once '../models/PartOrder.php';

$db = (new Database())->getConnection();
$stmtOrders = (new PartOrder($db))->getAllOrders();
$orders = $stmtOrders->fetchAll(PDO::FETCH_ASSOC);

$counts = [
    'All' => count($orders),
    'Pending' => 0,
    'Approved' => 0,
    'Ready for Pickup' => 0,
    'Completed' => 0,
    'Cancelled' => 0
];

foreach ($orders as $order) {
    $status = $order['status'] ?? 'Pending';

    if (isset($counts[$status])) {
        $counts[$status]++;
    }
}

function getOrderStatusBadgeClass($status) {
    switch ($status) {
        case 'Pending':
            return 'badge-soft-danger';
        case 'Approved':
            return 'badge-soft-primary';
        case 'Ready for Pickup':
            return 'badge-soft-warning';
        case 'Completed':
            return 'badge-soft-success';
        case 'Cancelled':
            return 'badge-soft-secondary text-dark';
        default:
            return 'badge-soft-secondary';
    }
}

function getPaymentBadgeClass($paymentStatus) {
    switch ($paymentStatus) {
        case 'Paid':
            return 'badge-soft-success';
        case 'Partial':
            return 'badge-soft-warning';
        case 'Not Paid':
            return 'badge-soft-danger';
        case 'No Invoice':
            return 'badge-soft-secondary';
        default:
            return 'badge-soft-secondary';
    }
}

function getAllowedNextStatuses($status, $paymentStatus) {
    if ($status === 'Pending') {
        return [
            'Approved' => 'Approve',
            'Cancelled' => 'Cancel'
        ];
    }

    if ($status === 'Approved') {
        return [
            'Ready for Pickup' => 'Ready for Pickup',
            'Cancelled' => 'Cancel'
        ];
    }

    if ($status === 'Ready for Pickup') {
        if ($paymentStatus !== 'Partial' && $paymentStatus !== 'Paid') {
            return [
                'Cancelled' => 'Cancel'
            ];
        }
    }

    return [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Web Orders - Norily's Repair Shop</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/style.css">

<style>
    .main-content {
        overflow-x: hidden;
    }

    .web-orders-page {
        width: 100%;
        max-width: 100%;
    }

    .wo-header {
        margin-bottom: 1.5rem;
    }

    .wo-header h2 {
        font-size: 2rem;
        font-weight: 900;
        color: var(--dashboard-text-main);
        margin-bottom: 0.25rem;
        line-height: 1.1;
    }

    .wo-count-text {
        color: var(--dashboard-text-muted);
        font-size: 0.95rem;
        font-weight: 500;
        margin-bottom: 0;
    }

    .wo-filter-area {
        display: grid;
        grid-template-columns: minmax(260px, 1fr) 240px 105px;
        gap: 0.85rem;
        align-items: end;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid #e5e7eb;
    }

    .wo-filter-label {
        display: block;
        color: var(--dashboard-text-muted);
        font-size: 0.76rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.35px;
        margin-bottom: 0.35rem;
    }

    .wo-search-bar {
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

    .wo-search-bar i {
        font-size: 1.05rem;
        color: var(--dashboard-text-muted);
        flex-shrink: 0;
    }

    .wo-search-bar input {
        width: 100%;
        border: none;
        outline: none;
        background: transparent;
        color: var(--dashboard-text-main);
        font-size: 0.92rem;
    }

    .wo-filter-select {
        height: 44px;
        border-radius: 12px;
        border: 1px solid #e5e7eb;
        padding: 0.55rem 0.85rem;
        font-size: 0.92rem;
        color: var(--dashboard-text-main);
        box-shadow: none;
    }

    .wo-filter-select:focus {
        border-color: var(--dashboard-primary);
        box-shadow: 0 0 0 4px rgba(245, 197, 24, 0.18);
    }

    .wo-clear-btn {
        height: 44px;
        border-radius: 999px;
        padding: 0.55rem 0.85rem;
        font-size: 0.9rem;
        font-weight: 800;
        border: 1px solid #e5e7eb;
        background: #fff;
        color: var(--dashboard-text-main);
        transition: 0.2s ease;
    }

    .wo-clear-btn:hover {
        background: #f8fafc;
        color: var(--black);
    }

    .wo-table-wrap {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .wo-table {
        width: 100%;
        min-width: 1180px;
        border-collapse: collapse;
        background: transparent;
    }

    .wo-table thead th {
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

    .wo-table tbody td {
        padding: 1rem 0.85rem;
        border-bottom: 1px solid #e8ebef;
        vertical-align: middle;
        color: var(--dashboard-text-main);
        font-size: 0.95rem;
        background: transparent;
    }

    .wo-table tbody tr:hover td {
        background: rgba(245, 197, 24, 0.035);
    }

    .wo-order-id {
        font-size: 0.95rem;
        font-weight: 900;
        color: var(--dashboard-text-main);
    }

    .wo-order-date {
        display: block;
        margin-top: 0.25rem;
        color: var(--dashboard-text-muted);
        font-size: 0.78rem;
        font-weight: 500;
    }

    .wo-customer {
        font-size: 0.95rem;
        font-weight: 900;
        white-space: nowrap;
    }

    .order-items {
        font-size: 0.95rem;
        font-weight: 800;
        line-height: 1.55;
        color: var(--dashboard-text-main);
    }

    .wo-total {
        color: #047857;
        font-size: 0.95rem;
        font-weight: 900;
        white-space: nowrap;
    }

    .badge-soft-danger,
    .badge-soft-primary,
    .badge-soft-warning,
    .badge-soft-success,
    .badge-soft-secondary {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        padding: 0.42rem 0.72rem;
        font-size: 0.78rem;
        font-weight: 900;
        white-space: nowrap;
    }

    .badge-soft-danger {
        background: #fee2e2;
        color: #b91c1c;
    }

    .badge-soft-primary {
        background: #e9f2ff;
        color: #1d4ed8;
    }

    .badge-soft-warning {
        background: #fef3c7;
        color: #92400e;
    }

    .badge-soft-success {
        background: #dcfce7;
        color: #047857;
    }

    .badge-soft-secondary {
        background: #f1f5f9;
        color: #475569;
    }

    .order-balance {
        display: block;
        margin-top: 0.35rem;
        color: var(--dashboard-text-muted);
        font-size: 0.78rem;
    }

    .order-action-form {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 0.45rem;
        margin: 0;
    }

    .order-action-select {
        width: 160px;
        border-radius: 12px;
        border: 1px solid #e5e7eb;
        font-size: 0.84rem;
        padding: 0.48rem 0.65rem;
        box-shadow: none;
    }

    .order-action-select:focus {
        border-color: var(--dashboard-primary);
        box-shadow: 0 0 0 4px rgba(245, 197, 24, 0.18);
    }

    .order-update-btn {
        width: 160px;
        border-radius: 999px;
        background: var(--dashboard-primary);
        border: 1px solid var(--dashboard-primary);
        color: var(--black);
        font-size: 0.82rem;
        padding: 0.48rem 0.75rem;
        font-weight: 900;
        transition: 0.2s ease;
    }

    .order-update-btn:hover {
        background: var(--black);
        border-color: var(--black);
        color: var(--white);
    }

    .order-no-action {
        display: block;
        color: var(--dashboard-text-muted);
        font-size: 0.85rem;
        font-style: italic;
    }

    .order-note {
        display: block;
        margin-top: 0.25rem;
        color: var(--dashboard-text-muted);
        font-size: 0.78rem;
    }

    .wo-empty-state {
        text-align: center;
        color: var(--dashboard-text-muted);
        padding: 3rem 1rem;
    }

    .alert {
        border-radius: 16px;
    }

    @media (max-width: 991.98px) {
        .wo-filter-area {
            grid-template-columns: 1fr;
        }

        .wo-clear-btn {
            width: 100%;
        }

        .order-action-form {
            align-items: stretch;
        }

        .order-action-select,
        .order-update-btn {
            width: 100%;
        }
    }

    @media (max-width: 767.98px) {
        .wo-header h2 {
            font-size: 1.75rem;
        }

        .wo-search-bar,
        .wo-filter-select,
        .wo-clear-btn {
            height: 42px;
        }
    }
</style>
</head>

<body>

<div class="app-wrapper">
<?php include '../includes/sidebar.php'; ?>

<main class="main-content">
    <div class="web-orders-page">

        <div class="wo-header">
            <h2>Customer Web Orders</h2>
            <p class="wo-count-text">
                <?php echo $counts['All']; ?> total web orders from the customer portal
            </p>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show mb-4">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4">
                <i class="bi bi-exclamation-circle-fill me-2"></i>
                <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="wo-filter-area">
            <div>
                <label class="wo-filter-label">Search</label>
                <div class="wo-search-bar">
                    <i class="bi bi-search"></i>
                    <input
                        type="text"
                        id="orderSearch"
                        placeholder="Search order, customer, part, status, or payment..."
                    >
                </div>
            </div>

            <div>
                <label class="wo-filter-label">Status</label>
                <select id="statusFilter" class="form-select wo-filter-select">
                    <option value="All">All (<?php echo $counts['All']; ?>)</option>
                    <option value="Pending">Pending (<?php echo $counts['Pending']; ?>)</option>
                    <option value="Approved">Approved (<?php echo $counts['Approved']; ?>)</option>
                    <option value="Ready for Pickup">Ready for Pickup (<?php echo $counts['Ready for Pickup']; ?>)</option>
                    <option value="Completed">Completed (<?php echo $counts['Completed']; ?>)</option>
                    <option value="Cancelled">Cancelled (<?php echo $counts['Cancelled']; ?>)</option>
                </select>
            </div>

            <button type="button" id="clearFilters" class="wo-clear-btn">
                Clear
            </button>
        </div>

        <div class="wo-table-wrap">
            <table class="wo-table" id="onlineOrdersTable">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Item Details</th>
                        <th>Total Cost</th>
                        <th>Order Status</th>
                        <th>Payment</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (count($orders) > 0): ?>
                        <?php foreach ($orders as $row): ?>
                            <?php
                                $status = $row['status'] ?? 'Pending';
                                $badge = getOrderStatusBadgeClass($status);

                                $paymentStatus = $row['payment_status'] ?? 'No Invoice';
                                $paymentBadge = getPaymentBadgeClass($paymentStatus);

                                $customerName = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
                                $itemsRaw = $row['items'] ?? '';
                                $itemsPlain = strip_tags(str_replace('<br>', ' ', $itemsRaw));

                                $allowedNextStatuses = getAllowedNextStatuses($status, $paymentStatus);

                                $searchText = strtolower(
                                    '#' . ($row['order_id'] ?? '') . ' ' .
                                    $customerName . ' ' .
                                    $itemsPlain . ' ' .
                                    $status . ' ' .
                                    $paymentStatus
                                );
                            ?>

                            <tr
                                class="order-row"
                                data-status="<?php echo htmlspecialchars($status); ?>"
                                data-search="<?php echo htmlspecialchars($searchText); ?>"
                            >
                                <td>
                                    <div class="wo-order-id">
                                        #<?php echo intval($row['order_id']); ?>
                                    </div>
                                    <span class="wo-order-date">
                                        <?php echo !empty($row['order_date']) ? date('M d, Y', strtotime($row['order_date'])) : 'N/A'; ?>
                                    </span>
                                </td>

                                <td>
                                    <div class="wo-customer">
                                        <?php echo htmlspecialchars($customerName); ?>
                                    </div>
                                </td>

                                <td>
                                    <div class="order-items">
                                        <?php echo $itemsRaw; ?>
                                    </div>
                                </td>

                                <td>
                                    <div class="wo-total">
                                        ₱<?php echo number_format(floatval($row['total_amount'] ?? 0), 2); ?>
                                    </div>
                                </td>

                                <td>
                                    <span class="<?php echo $badge; ?>">
                                        <?php echo htmlspecialchars($status); ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="<?php echo $paymentBadge; ?>">
                                        <?php echo htmlspecialchars($paymentStatus); ?>
                                    </span>

                                    <?php if (!empty($row['balance_due']) && floatval($row['balance_due']) > 0): ?>
                                        <span class="order-balance">
                                            Balance: ₱<?php echo number_format(floatval($row['balance_due']), 2); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td class="text-end">
                                    <?php if (!empty($allowedNextStatuses)): ?>
                                        <form
                                            action="../controllers/ManageOrderController.php"
                                            method="POST"
                                            class="order-action-form"
                                            onsubmit="return confirm('Update this web order status?');"
                                        >
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="order_id" value="<?php echo intval($row['order_id']); ?>">

                                            <select name="status" class="form-select order-action-select" required>
                                                <option value="" disabled selected>Select action</option>

                                                <?php foreach ($allowedNextStatuses as $value => $label): ?>
                                                    <option value="<?php echo htmlspecialchars($value); ?>">
                                                        <?php echo htmlspecialchars($label); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>

                                            <button type="submit" class="order-update-btn">
                                                Update
                                            </button>
                                        </form>

                                        <?php if ($status === 'Ready for Pickup' && $paymentStatus === 'Not Paid'): ?>
                                            <span class="order-note">
                                                Payment is handled in Billing.
                                            </span>
                                        <?php endif; ?>

                                    <?php else: ?>
                                        <span class="order-no-action">
                                            No action
                                        </span>

                                        <?php if ($status === 'Ready for Pickup' && $paymentStatus === 'Partial'): ?>
                                            <span class="order-note">
                                                Cannot cancel after partial payment.
                                            </span>
                                        <?php elseif ($status === 'Completed'): ?>
                                            <span class="order-note">
                                                Completed after full payment.
                                            </span>
                                        <?php elseif ($status === 'Cancelled'): ?>
                                            <span class="order-note">
                                                Order is cancelled.
                                            </span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <tr id="noOrderResults" style="display:none;">
                            <td colspan="7">
                                <div class="wo-empty-state">
                                    No orders match your search/filter.
                                </div>
                            </td>
                        </tr>

                    <?php else: ?>
                        <tr>
                            <td colspan="7">
                                <div class="wo-empty-state">
                                    No web orders found.
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

<script>
const orderSearch = document.getElementById('orderSearch');
const statusFilter = document.getElementById('statusFilter');
const clearFilters = document.getElementById('clearFilters');
const rows = document.querySelectorAll('.order-row');
const noResults = document.getElementById('noOrderResults');

function applyOrderFilters() {
    const searchValue = orderSearch.value.trim().toLowerCase();
    const statusValue = statusFilter.value;

    let visibleCount = 0;

    rows.forEach(row => {
        const rowSearch = row.dataset.search || '';
        const rowStatus = row.dataset.status || '';

        const matchesSearch = rowSearch.includes(searchValue);
        const matchesStatus = statusValue === 'All' || rowStatus === statusValue;

        if (matchesSearch && matchesStatus) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    if (noResults) {
        noResults.style.display = visibleCount === 0 && rows.length > 0 ? '' : 'none';
    }
}

if (orderSearch) {
    orderSearch.addEventListener('input', applyOrderFilters);
}

if (statusFilter) {
    statusFilter.addEventListener('change', applyOrderFilters);
}

if (clearFilters) {
    clearFilters.addEventListener('click', function () {
        orderSearch.value = '';
        statusFilter.value = 'All';
        applyOrderFilters();
    });
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>