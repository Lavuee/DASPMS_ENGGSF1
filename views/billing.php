<?php
require_once __DIR__ . '/../includes/auth.php';

require_once '../config/Database.php';
require_once '../models/Billing.php';
require_once '../includes/staff_ui.php';

requirePermission('billing.manage');

$db = (new Database())->getConnection();

// Release unpaid web-order reservations older than RESERVATION_HOLD_DAYS (SRS)
require_once '../models/PartOrder.php';
(new PartOrder($db))->expireStaleReservations();
$billing = new Billing($db);
$bills = $billing->getPendingBills()->fetchAll(PDO::FETCH_ASSOC);

/* Extra display details (read-only): vehicle for repair jobs, items for web orders, invoice numbers */
$jobIds = [];
$orderIds = [];
$invoiceIds = [];
foreach ($bills as $row) {
    if ($row['source'] === 'job') $jobIds[] = intval($row['ref_id']);
    else $orderIds[] = intval($row['ref_id']);
    if (!empty($row['invoice_id'])) $invoiceIds[] = intval($row['invoice_id']);
}

function billingLookup(PDO $db, $sql, array $ids) {
    if (empty($ids)) return [];
    $stmt = $db->prepare(sprintf($sql, implode(',', array_fill(0, count($ids), '?'))));
    $stmt->execute(array_values($ids));
    return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

$jobVehicles = billingLookup($db, "
    SELECT jo.job_order_id, TRIM(CONCAT_WS(' ', v.make, v.model))
    FROM job_order jo
    JOIN vehicle v ON v.vehicle_id = jo.vehicle_id
    WHERE jo.job_order_id IN (%s)
", $jobIds);

$orderItems = billingLookup($db, "
    SELECT poi.order_id, GROUP_CONCAT(CONCAT(p.part_name, ' × ', poi.quantity) ORDER BY poi.order_item_id SEPARATOR ', ')
    FROM part_order_item poi
    JOIN part p ON p.part_id = poi.part_id
    WHERE poi.order_id IN (%s)
    GROUP BY poi.order_id
", $orderIds);

$invoiceNumbers = billingLookup($db, "
    SELECT invoice_id, invoice_number FROM invoice WHERE invoice_id IN (%s)
", $invoiceIds);

// Chip groups (a row can be in more than one)
$counts = ['all' => count($bills), 'open' => 0, 'notpaid' => 0, 'overdue' => 0];
foreach ($bills as $i => $row) {
    $status = $row['payment_status'] ?? 'No Invoice';
    $isOverdue = isInvoiceOverdue($row['source'] === 'job' ? 'Service' : 'Part', $status, $row['due_date'] ?? null);
    $groups = [];
    if ($status === 'No Invoice' || $status === 'Partial') $groups[] = 'open';
    if ($status === 'Not Paid') $groups[] = 'notpaid';
    if ($isOverdue) $groups[] = 'overdue';
    foreach ($groups as $g) $counts[$g]++;
    $bills[$i]['_groups'] = $groups;
    $bills[$i]['_overdue'] = $isOverdue;
}

$paymentMethods = ['Cash', 'GCash', 'Bank Transfer', 'Cheque'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Billing &amp; Invoices - Norily's Repair Shop</title>
<link rel="icon" type="image/png" href="../assets/images/logo.png">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>
<body>

<div class="app-wrapper">
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content sp-page" id="spMain">

        <?php
        $pageActions = '<a href="invoice_history.php" class="cp-btn cp-btn-line">Invoice history</a>';
        spPageHead(
            spPortalLabel() . ' · Counter',
            'Billing & Invoices',
            'Everything that still needs an invoice or a payment. Generate the invoice first, then record the payment.'
        );
        cpFlash();
        ?>

        <div data-sp-filter="#billingRows">
            <div class="sp-toolbar">
                <div class="sp-search-field-inline">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                    <input type="search" id="billingSearch" class="cp-input" data-sp-search placeholder="Search reference no. or customer" aria-label="Search reference no. or customer" autocomplete="off">
                </div>
                <select id="typeFilter" class="cp-input" data-sp-type aria-label="Type">
                    <option value="all">All types</option>
                    <option value="job">Repair jobs</option>
                    <option value="part">Web orders</option>
                </select>
                <div class="sp-toolbar-actions"><?= $pageActions ?></div>
            </div>

            <div class="sp-chips" role="group" aria-label="Filter by status">
                <button type="button" class="sp-chip" data-sp-chip="all" aria-pressed="true">All <span class="sp-chip-count"><?= $counts['all'] ?></span></button>
                <button type="button" class="sp-chip is-pend" data-sp-chip="open" aria-pressed="false"><span class="sp-chip-dot"></span>No invoice or partial <span class="sp-chip-count"><?= $counts['open'] ?></span></button>
                <button type="button" class="sp-chip is-bad" data-sp-chip="notpaid" aria-pressed="false"><span class="sp-chip-dot"></span>Not paid <span class="sp-chip-count"><?= $counts['notpaid'] ?></span></button>
                <?php if ($counts['overdue'] > 0): ?>
                    <button type="button" class="sp-chip is-bad" data-sp-chip="overdue" aria-pressed="false"><span class="sp-chip-dot"></span>Overdue <span class="sp-chip-count"><?= $counts['overdue'] ?></span></button>
                <?php endif; ?>
            </div>

            <p class="sp-showing" data-sp-showing aria-live="polite">Showing <?= count($bills) ?> of <?= count($bills) ?></p>
        </div>

        <table class="cp-table sp-table" id="billingTable">
            <thead>
                <tr>
                    <th scope="col">Reference</th>
                    <th scope="col">Customer</th>
                    <th scope="col">Amount</th>
                    <th scope="col">Status</th>
                    <th scope="col">Invoice</th>
                    <th scope="col" class="cp-num">Payment</th>
                </tr>
            </thead>
            <tbody id="billingRows" data-cp-paginate="10" data-cp-noun="bill">
                <?php foreach ($bills as $row): ?>
                    <?php
                    $status = $row['payment_status'] ?? 'No Invoice';
                    $balance = floatval($row['balance_due']);
                    $total = floatval($row['total_amount']);
                    $paid = floatval($row['amount_paid']);
                    $isJob = $row['source'] === 'job';
                    $customerName = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
                    $detail = $isJob
                        ? 'Repair job' . (!empty($jobVehicles[$row['ref_id']]) ? ' · ' . $jobVehicles[$row['ref_id']] : '')
                        : 'Web order' . (!empty($orderItems[$row['ref_id']]) ? ' · ' . $orderItems[$row['ref_id']] : '');
                    $dueDate = $row['due_date'] ?? null;
                    $invoiceNo = !empty($row['invoice_id']) ? ($invoiceNumbers[$row['invoice_id']] ?? ('Invoice #' . intval($row['invoice_id']))) : '';
                    ?>
                    <tr data-type="<?= cpEscape($row['source']) ?>"
                        data-status="<?= cpEscape(implode(' ', $row['_groups'])) ?>"
                        data-search="<?= cpEscape(strtolower(($row['ref_number'] ?? '') . ' ' . $customerName . ' ' . $invoiceNo . ' ' . $detail)) ?>">
                        <td data-label="Reference">
                            <div>
                                <span class="sp-ref"><?= cpEscape($row['ref_number']) ?></span>
                                <span class="cp-cell-sub"><?= cpEscape($detail) ?></span>
                            </div>
                        </td>
                        <td data-label="Customer"><?= cpEscape($customerName) ?></td>
                        <td data-label="Amount">
                            <div>
                                <span class="sp-amount">Total <strong><?= cpMoney($total) ?></strong></span>
                                <span class="sp-amount">Paid <?= cpMoney($paid) ?> · Balance <?= cpMoney($balance) ?></span>
                            </div>
                        </td>
                        <td data-label="Status">
                            <div>
                                <div class="sp-badges">
                                    <?= cpBadge($status) ?>
                                    <?php if ($row['_overdue']): ?><span class="st bad solid">Overdue</span><?php endif; ?>
                                </div>
                                <?php if (!empty($dueDate) && $status !== 'Paid'): ?>
                                    <span class="cp-cell-sub<?= $row['_overdue'] ? ' sp-text-bad' : '' ?>">Due <?= date('M d, Y', strtotime($dueDate)) ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td data-label="Invoice">
                            <?php if (empty($row['invoice_id'])): ?>
                                <form action="../controllers/BillingController.php" method="POST">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="create_invoice">
                                    <input type="hidden" name="source" value="<?= cpEscape($row['source']) ?>">
                                    <input type="hidden" name="ref_id" value="<?= intval($row['ref_id']) ?>">
                                    <button type="submit" class="cp-btn cp-btn-dark cp-btn-sm">Generate</button>
                                </form>
                            <?php else: ?>
                                <a href="print_invoice.php?invoice_id=<?= intval($row['invoice_id']) ?>" target="_blank" class="sp-inv-link"
                                   data-sp-doc data-sp-doc-kind="Invoice" data-sp-doc-ref="<?= cpEscape($invoiceNo) ?>"
                                   data-sp-doc-status="<?= cpEscape($status) ?>" data-sp-doc-badge="<?= cpEscape(cpStatusClass($status)) ?>"><?= cpEscape($invoiceNo) ?></a>
                            <?php endif; ?>
                        </td>
                        <td data-label="Payment" class="cp-num">
                            <?php if (!empty($row['invoice_id']) && $status !== 'Paid'): ?>
                                <button type="button" class="cp-btn cp-btn-dark cp-btn-sm" data-sp-open="#payDialog"
                                        data-invoice-id="<?= intval($row['invoice_id']) ?>"
                                        data-ref="<?= cpEscape($row['ref_number'] . ' · ' . $customerName) ?>"
                                        data-total="<?= cpEscape(number_format($total, 2, '.', '')) ?>"
                                        data-paid="<?= cpEscape(number_format($paid, 2, '.', '')) ?>"
                                        data-balance="<?= cpEscape(number_format($balance, 2, '.', '')) ?>">Pay</button>
                            <?php elseif (empty($row['invoice_id'])): ?>
                                <button type="button" class="cp-btn cp-btn-line cp-btn-sm" disabled title="Generate the invoice first">Pay</button>
                            <?php else: ?>
                                <?= cpBadge('Paid') ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <tr class="sp-empty-row" data-sp-empty hidden>
                    <td colspan="6">No bills match your search or filter.</td>
                </tr>
                <?php if (empty($bills)): ?>
                    <tr class="sp-empty-row">
                        <td colspan="6">Nothing to bill. Completed repair jobs and web orders that are ready for pickup show up here.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </main>
</div>

<!-- Record payment -->
<div class="sp-dialog" id="payDialog" role="dialog" aria-modal="true" aria-labelledby="payTitle" hidden>
    <div class="sp-dialog-box">
        <form action="../controllers/BillingController.php" method="POST" class="payment-form" id="payForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="pay">
            <input type="hidden" name="invoice_id" id="payInvoiceId" value="">

            <div class="cp-modal-head">
                <div>
                    <p class="cp-label" id="payRef"></p>
                    <h2 class="cp-modal-title" id="payTitle">Record payment</h2>
                </div>
                <button type="button" class="cp-modal-x" data-sp-close aria-label="Close">&times;</button>
            </div>

            <div class="sp-dialog-body">
                <dl class="sp-facts">
                    <div><dt>Total</dt><dd class="sp-mono" id="payTotal"></dd></div>
                    <div><dt>Paid</dt><dd class="sp-mono" id="payPaid"></dd></div>
                    <div><dt>Balance</dt><dd class="sp-mono" id="payBalance"></dd></div>
                </dl>

                <div class="sp-fgrid" style="margin-top: 22px">
                    <div class="sp-field">
                        <label for="payAmount">Amount <span class="sp-req">*</span></label>
                        <input type="number" name="amount" id="payAmount" class="cp-input payment-amount" min="1" step="0.01" required>
                        <p class="cp-hint">Enter less than the balance for a partial payment.</p>
                    </div>
                    <div class="sp-field">
                        <label for="payMethod">Payment method <span class="sp-req">*</span></label>
                        <select name="payment_method" id="payMethod" class="cp-input payment-method">
                            <?php foreach ($paymentMethods as $method): ?>
                                <option><?= cpEscape($method) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sp-field sp-span">
                        <label for="payReference">Reference no. <span class="sp-req" id="payRefStar" hidden>*</span></label>
                        <input type="text" name="reference_number" id="payReference" class="cp-input reference-number" placeholder="For GCash, bank or cheque">
                    </div>
                </div>
            </div>

            <div class="cp-modal-foot">
                <button type="button" class="cp-btn cp-btn-line" data-sp-close>Cancel</button>
                <button type="submit" class="cp-btn cp-btn-dark">Save payment</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
(function () {
    const dialog = document.getElementById('payDialog');
    const form = document.getElementById('payForm');
    const amount = document.getElementById('payAmount');
    const method = document.getElementById('payMethod');
    const ref = document.getElementById('payReference');
    const peso = v => '₱' + Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    function updateReferenceField() {
        const needsReference = ['GCash', 'Bank Transfer', 'Cheque'].includes(method.value);
        ref.required = needsReference;
        ref.disabled = !needsReference;
        ref.placeholder = needsReference ? method.value + ' reference' : 'Not needed for cash';
        if (!needsReference) ref.value = '';
        document.getElementById('payRefStar').hidden = !needsReference;
    }

    dialog.addEventListener('sp:open', function (event) {
        const btn = event.detail.trigger;
        if (!btn) return;
        document.getElementById('payInvoiceId').value = btn.dataset.invoiceId;
        document.getElementById('payRef').textContent = btn.dataset.ref;
        document.getElementById('payTotal').textContent = peso(btn.dataset.total);
        document.getElementById('payPaid').textContent = peso(btn.dataset.paid);
        document.getElementById('payBalance').textContent = peso(btn.dataset.balance);
        amount.max = btn.dataset.balance;
        amount.value = btn.dataset.balance;
        method.value = 'Cash';
        updateReferenceField();
    });

    method.addEventListener('change', updateReferenceField);

    form.addEventListener('submit', function (e) {
        if (parseFloat(amount.value) <= 0) {
            cpFieldError(amount, 'Enter an amount greater than zero.');
            e.preventDefault();
            return;
        }

        if (['GCash', 'Bank Transfer', 'Cheque'].includes(method.value) && ref.value.trim() === '') {
            cpFieldError(ref, 'Reference number is required for ' + method.value + ' payments.');
            e.preventDefault();
        }
    });
})();
</script>

</body>
</html>
