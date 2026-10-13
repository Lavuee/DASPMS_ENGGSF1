<?php
require_once __DIR__ . '/../includes/auth.php';

requirePermission('billing.manage');

require_once '../config/Database.php';
require_once '../includes/staff_ui.php';

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
        jo.status AS job_status,
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

$counts = ['Paid' => 0, 'Partial' => 0, 'Not Paid' => 0];
foreach ($invoices as $invoice) {
    if (isset($counts[$invoice['payment_status']])) {
        $counts[$invoice['payment_status']]++;
    }
}

// Chip key for a payment status: "Not Paid" -> "notpaid"
function historyStatusKey($status) {
    return strtolower(str_replace(' ', '', (string) $status));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Invoice History - Norily's Repair Shop</title>
<link rel="icon" type="image/png" href="../assets/images/logo.png">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>
<body>

<div class="app-wrapper">
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content sp-page" id="spMain">

        <?php
        $pageActions = '<a href="billing.php" class="cp-btn cp-btn-line">Billing &amp; invoices</a>';
        spPageHead(
            spPortalLabel() . ' · Counter',
            'Invoice History',
            'Every invoice the shop has issued. Open one to print it or save it as a PDF.'
        );
        ?>

        <div data-sp-filter="#invoiceRows">
            <div class="sp-toolbar">
                <div class="sp-search-field-inline">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                    <input type="search" id="invoiceSearch" class="cp-input" data-sp-search placeholder="Search invoice no., customer or reference" aria-label="Search invoice no., customer or reference" autocomplete="off">
                </div>
                <select id="typeFilter" class="cp-input" data-sp-type aria-label="Type">
                    <option value="all">All types</option>
                    <option value="Service">Service</option>
                    <option value="Part">Part</option>
                </select>
                <div class="sp-toolbar-actions"><?= $pageActions ?></div>
            </div>

            <div class="sp-chips" role="group" aria-label="Filter by status">
                <button type="button" class="sp-chip" data-sp-chip="all" aria-pressed="true">All <span class="sp-chip-count"><?= count($invoices) ?></span></button>
                <button type="button" class="sp-chip is-done" data-sp-chip="paid" aria-pressed="false"><span class="sp-chip-dot"></span>Paid <span class="sp-chip-count"><?= $counts['Paid'] ?></span></button>
                <button type="button" class="sp-chip is-pend" data-sp-chip="partial" aria-pressed="false"><span class="sp-chip-dot"></span>Partial <span class="sp-chip-count"><?= $counts['Partial'] ?></span></button>
                <button type="button" class="sp-chip is-bad" data-sp-chip="notpaid" aria-pressed="false"><span class="sp-chip-dot"></span>Not paid <span class="sp-chip-count"><?= $counts['Not Paid'] ?></span></button>
            </div>

            <p class="sp-showing" data-sp-showing aria-live="polite">Showing <?= count($invoices) ?> of <?= count($invoices) ?></p>
        </div>

        <table class="cp-table sp-table" id="invoiceHistoryTable">
            <thead>
                <tr>
                    <th scope="col">Invoice no.</th>
                    <th scope="col">Customer</th>
                    <th scope="col">Type</th>
                    <th scope="col">Reference</th>
                    <th scope="col">Amount</th>
                    <th scope="col">Status</th>
                    <th scope="col"><span class="visually-hidden">Open</span></th>
                </tr>
            </thead>
            <tbody id="invoiceRows" data-cp-paginate="10" data-cp-noun="invoice">
                <?php foreach ($invoices as $invoice): ?>
                    <?php
                    $status = $invoice['payment_status'];
                    $reference = $invoice['invoice_type'] === 'Service'
                        ? $invoice['job_order_number']
                        : 'PO-' . $invoice['part_order_id'];
                    $customerName = $invoice['first_name'] . ' ' . $invoice['last_name'];
                    ?>
                    <tr data-type="<?= cpEscape($invoice['invoice_type']) ?>"
                        data-status="<?= cpEscape(historyStatusKey($status)) ?>"
                        data-search="<?= cpEscape(strtolower($invoice['invoice_number'] . ' ' . $customerName . ' ' . $invoice['invoice_type'] . ' ' . $reference . ' ' . $status)) ?>">
                        <td data-label="Invoice no."><span class="sp-ref"><?= cpEscape($invoice['invoice_number']) ?></span></td>
                        <td data-label="Customer"><?= cpEscape($customerName) ?></td>
                        <td data-label="Type"><?= cpEscape($invoice['invoice_type']) ?></td>
                        <td data-label="Reference" class="sp-mono">
                            <div>
                                <?= cpEscape($reference) ?>
                                <?php if ($invoice['invoice_type'] === 'Service' && !in_array($invoice['job_status'] ?? '', ['Completed', 'Cancelled'], true)): ?>
                                    <span class="cp-cell-sub">Down payment · job in progress</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td data-label="Amount">
                            <div>
                                <span class="sp-amount">Total <strong><?= cpMoney($invoice['total_amount']) ?></strong></span>
                                <span class="sp-amount">Paid <?= cpMoney($invoice['amount_paid']) ?> · Balance <?= cpMoney($invoice['balance_due']) ?></span>
                            </div>
                        </td>
                        <td data-label="Status"><?= cpBadge($status) ?></td>
                        <td class="cp-num">
                            <a href="print_invoice.php?invoice_id=<?= intval($invoice['invoice_id']) ?>" target="_blank"
                               class="cp-btn cp-btn-line cp-btn-sm"
                               data-sp-doc data-sp-doc-kind="Invoice" data-sp-doc-ref="<?= cpEscape($invoice['invoice_number']) ?>"
                               data-sp-doc-status="<?= cpEscape($status) ?>" data-sp-doc-badge="<?= cpEscape(cpStatusClass($status)) ?>">View</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <tr class="sp-empty-row" data-sp-empty hidden>
                    <td colspan="7">No invoices match your search or filter.</td>
                </tr>
                <?php if (empty($invoices)): ?>
                    <tr class="sp-empty-row">
                        <td colspan="7">No invoices yet. They appear here once you generate one in Billing.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
