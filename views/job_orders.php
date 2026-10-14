<?php
require_once __DIR__ . '/../includes/auth.php';

requirePermission('job_orders.view');

require_once '../config/Database.php';
require_once '../includes/business_rules.php';
require_once '../includes/staff_ui.php';

$db = (new Database())->getConnection();

$userRole = $_SESSION['role'] ?? '';

$canCreateJobOrders = can('job_orders.manage');
$canEditJobOrders = can('job_orders.manage');
$canUpdateRepairProgress = can('job_orders.progress');
$canCompleteJobOrders = can('job_orders.manage');
$canCancelJobOrders = can('job_orders.manage');
$canAccessBilling = in_array($userRole, ['Owner', 'Cashier'], true);
$canRecordDownPayment = can('billing.manage'); // SRS UC20: Owner and Cashier

$query = "
    SELECT 
        jo.*, 
        c.first_name, 
        c.last_name, 
        v.plate_number,
        v.make,
        v.model,
        CONCAT(am.first_name, ' ', am.last_name) AS assigned_mechanic_name,
        CONCAT(cb.first_name, ' ', cb.last_name) AS completed_by_name,
        inv.invoice_id,
        inv.invoice_number,
        COALESCE(inv.amount_paid, 0) AS amount_paid
    FROM job_order jo
    JOIN customer c ON jo.customer_id = c.customer_id
    JOIN vehicle v ON jo.vehicle_id = v.vehicle_id
    LEFT JOIN user am ON jo.assigned_mechanic_id = am.user_id
    LEFT JOIN user cb ON jo.completed_by = cb.user_id
    LEFT JOIN invoice inv ON inv.job_order_id = jo.job_order_id AND inv.is_voided = 0
    ORDER BY jo.date_created DESC
";

$stmt = $db->prepare($query);
$stmt->execute();
$jobOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$availablePartsStmt = $db->prepare("
    SELECT 
        part_id,
        part_name,
        brand,
        specification,
        unit,
        unit_price,
        quantity_on_hand,
        low_stock_threshold
    FROM part
    WHERE is_active = 1
    ORDER BY part_name ASC
");
$availablePartsStmt->execute();
$availableParts = $availablePartsStmt->fetchAll(PDO::FETCH_ASSOC);

$jobPartsStmt = $db->prepare("
    SELECT
        jop.*,
        p.part_name,
        p.unit
    FROM job_order_part jop
    JOIN part p ON jop.part_id = p.part_id
    ORDER BY jop.job_part_id DESC
");
$jobPartsStmt->execute();
$allJobParts = $jobPartsStmt->fetchAll(PDO::FETCH_ASSOC);

$jobPartsByOrder = [];
$jobPartsTotals = [];

foreach ($allJobParts as $partUsed) {
    $joId = intval($partUsed['job_order_id']);

    if (!isset($jobPartsByOrder[$joId])) {
        $jobPartsByOrder[$joId] = [];
    }

    if (!isset($jobPartsTotals[$joId])) {
        $jobPartsTotals[$joId] = 0;
    }

    $jobPartsByOrder[$joId][] = $partUsed;
    $jobPartsTotals[$joId] += floatval($partUsed['subtotal']);
}

// Payments already made on each job (down payments are recorded while the job is still open)
$jobPaymentsByOrder = [];
$jobPaymentsStmt = $db->query("
    SELECT i.job_order_id, p.amount, p.payment_method, p.reference_number, p.payment_date, p.is_down_payment
    FROM payment p
    JOIN invoice i ON i.invoice_id = p.invoice_id
    WHERE i.job_order_id IS NOT NULL AND i.is_voided = 0
    ORDER BY p.payment_date ASC
");
foreach ($jobPaymentsStmt->fetchAll(PDO::FETCH_ASSOC) as $jobPayment) {
    $jobPaymentsByOrder[intval($jobPayment['job_order_id'])][] = $jobPayment;
}

$vehicles = [];
$mechanics = [];

if ($canCreateJobOrders) {
    $stmtV = $db->prepare("
        SELECT 
            v.*, 
            c.first_name, 
            c.last_name 
        FROM vehicle v 
        JOIN customer c ON v.customer_id = c.customer_id 
        WHERE v.status = 'Active'
        ORDER BY v.plate_number ASC
    ");
    $stmtV->execute();
    $vehicles = $stmtV->fetchAll(PDO::FETCH_ASSOC);

    $stmtM = $db->prepare("
        SELECT user_id, first_name, last_name
        FROM user
        WHERE role = 'Head Mechanic'
          AND is_active = 1
        ORDER BY first_name ASC, last_name ASC
    ");
    $stmtM->execute();
    $mechanics = $stmtM->fetchAll(PDO::FETCH_ASSOC);
}

$counts = [
    'All' => count($jobOrders),
    'Pending' => 0,
    'In Progress' => 0,
    'Ready for Pickup' => 0,
    'Completed' => 0,
    'Cancelled' => 0
];

foreach ($jobOrders as $jo) {
    $status = $jo['status'] ?? 'Pending';

    if (isset($counts[$status])) {
        $counts[$status]++;
    }
}

function getJobStatusBadgeClass($status) {
    switch ($status) {
        case 'Pending':
            return 'jo-status jo-status-pending';
        case 'In Progress':
            return 'jo-status jo-status-progress';
        case 'Ready for Pickup':
            return 'jo-status jo-status-ready';
        case 'Completed':
            return 'jo-status jo-status-completed';
        case 'Cancelled':
            return 'jo-status jo-status-cancelled';
        default:
            return 'jo-status jo-status-pending';
    }
}

function getRequestSourceBadgeClass($source) {
    return $source === 'Online' ? 'jo-source jo-source-online' : 'jo-source jo-source-walkin';
}

function getDisplayCost($jo, $partsUsedTotal = 0) {
    $finalCost = isset($jo['final_cost']) ? floatval($jo['final_cost']) : 0;
    $estimatedCost = isset($jo['estimated_cost']) ? floatval($jo['estimated_cost']) : 0;

    if ($finalCost > 0) {
        return $finalCost;
    }

    return $estimatedCost + floatval($partsUsedTotal);
}

function formatDateValue($value) {
    if (empty($value)) {
        return 'N/A';
    }

    return date('M d, Y', strtotime($value));
}

function formatDateTimeValue($value) {
    if (empty($value)) {
        return 'N/A';
    }

    return date('M d, Y h:i A', strtotime($value));
}

$jobOrderModals = '';

// Chip key for a job status: "In Progress" -> "inprogress"
function jobStatusKey($status) {
    return strtolower(str_replace(' ', '', (string) $status));
}

// "Start repair" / "Mark ready" / "Complete" form for a job, or '' when the role can't move it on.
function jobNextStepForm($jobOrderId, $status, $canUpdateRepairProgress, $canCompleteJobOrders, $buttonClass) {
    $next = null;
    if ($status === 'Pending' && $canUpdateRepairProgress) $next = ['In Progress', 'Start'];
    elseif ($status === 'In Progress' && $canUpdateRepairProgress) $next = ['Ready for Pickup', 'Mark ready'];
    elseif ($status === 'Ready for Pickup' && $canCompleteJobOrders) $next = ['Completed', 'Complete'];
    if (!$next) return '';

    return '<form action="../controllers/JobOrderController.php" method="POST">'
        . csrfField()
        . '<input type="hidden" name="action" value="update_status">'
        . '<input type="hidden" name="job_order_id" value="' . intval($jobOrderId) . '">'
        . '<input type="hidden" name="status" value="' . cpEscape($next[0]) . '">'
        . '<button type="submit" class="' . $buttonClass . '">' . cpEscape($next[1]) . '</button>'
        . '</form>';
}

$statusChips = [
    'Pending' => 'is-pend',
    'In Progress' => 'is-prog',
    'Ready for Pickup' => 'is-ok',
    'Completed' => 'is-done',
    'Cancelled' => 'is-bad',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Job Orders - Norily's Repair Shop</title>
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
        $pageActions = $canCreateJobOrders ? '<button type="button" class="cp-btn cp-btn-dark" data-sp-open="#createJobDialog">+ New job order</button>' : '';
        spPageHead(
            spPortalLabel() . ' · Workshop',
            $canCreateJobOrders ? 'Job Orders' : 'Active Jobs',
            'Every repair from pending to completed. Use the buttons to move a job to its next step.'
        );
        cpFlash();
        ?>

        <div data-sp-filter="#jobRows">
            <div class="sp-toolbar">
                <div class="sp-search-field-inline">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                    <input type="search" id="searchInput" class="cp-input" data-sp-search placeholder="Search job no., customer, plate or mechanic" aria-label="Search job no., customer, plate or mechanic" autocomplete="off">
                </div>
                <div class="sp-toolbar-actions"><?= $pageActions ?></div>
            </div>

            <div class="sp-chips" role="group" aria-label="Filter by status">
                <button type="button" class="sp-chip" data-sp-chip="all" aria-pressed="true">All <span class="sp-chip-count"><?= $counts['All'] ?></span></button>
                <?php foreach ($statusChips as $label => $tone): ?>
                    <button type="button" class="sp-chip <?= $tone ?>" data-sp-chip="<?= jobStatusKey($label) ?>" aria-pressed="false"><span class="sp-chip-dot"></span><?= cpEscape($label) ?> <span class="sp-chip-count"><?= $counts[$label] ?></span></button>
                <?php endforeach; ?>
            </div>

            <p class="sp-showing" data-sp-showing aria-live="polite">Showing <?= count($jobOrders) ?> of <?= count($jobOrders) ?></p>
        </div>

        <table class="cp-table sp-table" id="jobOrdersTable">
            <thead>
                <tr>
                    <th scope="col">Job no.</th>
                    <th scope="col">Date</th>
                    <th scope="col">Customer</th>
                    <th scope="col">Vehicle</th>
                    <th scope="col">Source</th>
                    <th scope="col">Mechanic</th>
                    <th scope="col">Total</th>
                    <th scope="col">Status</th>
                    <th scope="col"><span class="visually-hidden">Actions</span></th>
                </tr>
            </thead>
            <tbody id="jobRows" data-cp-paginate="10" data-cp-noun="job order">
                <?php foreach ($jobOrders as $jo): ?>
                    <?php
                    $jobOrderId = intval($jo['job_order_id']);
                    $jobNumber = $jo['job_order_number'] ?? ('JO-' . $jobOrderId);
                    $status = $jo['status'] ?? 'Pending';
                    $customerName = trim(($jo['first_name'] ?? '') . ' ' . ($jo['last_name'] ?? ''));
                    $plateNumber = $jo['plate_number'] ?? '';
                    $vehicleLabel = trim(($jo['make'] ?? '') . ' ' . ($jo['model'] ?? ''));
                    $description = $jo['description'] ?? 'No description provided';
                    $requestSource = !empty($jo['request_source']) ? $jo['request_source'] : 'Walk-in';
                    $assignedMechanicName = trim($jo['assigned_mechanic_name'] ?? '');
                    $completedByName = trim($jo['completed_by_name'] ?? '');
                    $displayMechanic = $assignedMechanicName !== '' ? $assignedMechanicName : 'Unassigned';
                    $partsUsedForJob = $jobPartsByOrder[$jobOrderId] ?? [];
                    $partsUsedTotal = $jobPartsTotals[$jobOrderId] ?? 0;
                    $displayCost = getDisplayCost($jo, $partsUsedTotal);
                    $canEditThis = ($status === 'Pending' || $status === 'In Progress') && $canEditJobOrders;
                    $canCancelThis = in_array($status, ['Pending', 'In Progress', 'Ready for Pickup'], true) && $canCancelJobOrders;
                    $nextStepRow = jobNextStepForm($jobOrderId, $status, $canUpdateRepairProgress, $canCompleteJobOrders, 'cp-btn cp-btn-dark cp-btn-sm');
                    $amountPaid = floatval($jo['amount_paid'] ?? 0);
                    $remainingBalance = max($displayCost - $amountPaid, 0);
                    $requiredDown = !empty($jo['requires_down_payment']) ? floatval($jo['down_payment_amount'] ?? 0) : 0;
                    $paymentsForJob = $jobPaymentsByOrder[$jobOrderId] ?? [];
                    $canTakeDownPayment = $canRecordDownPayment && in_array($status, ['Pending', 'In Progress'], true) && $remainingBalance > 0.01;
                    // Row shortcut only while a required down payment is still missing
                    $downPaymentDue = $canTakeDownPayment && $requiredDown > 0 && $amountPaid < $requiredDown;
                    ?>
                    <tr data-status="<?= jobStatusKey($status) ?>"
                        data-search="<?= cpEscape(strtolower($jobNumber . ' ' . $customerName . ' ' . $plateNumber . ' ' . $vehicleLabel . ' ' . $description . ' ' . $requestSource . ' ' . $displayMechanic . ' ' . $status)) ?>">
                        <td data-label="Job no."><span class="sp-ref"><?= cpEscape($jobNumber) ?></span></td>
                        <td data-label="Date" class="sp-nowrap"><?= !empty($jo['date_created']) ? date('d M', strtotime($jo['date_created'])) : '—' ?></td>
                        <td data-label="Customer"><?= cpEscape($customerName) ?></td>
                        <td data-label="Vehicle">
                            <div>
                                <span class="sp-cell-line"><?= cpEscape($vehicleLabel !== '' ? $vehicleLabel : 'Vehicle') ?></span>
                                <span class="sp-plate sp-plate-sm"><?= cpEscape($plateNumber !== '' ? $plateNumber : '—') ?></span>
                            </div>
                        </td>
                        <td data-label="Source"><?= cpEscape($requestSource) ?></td>
                        <td data-label="Mechanic">
                            <div>
                                <span class="<?= $assignedMechanicName === '' && in_array($status, ['Pending', 'In Progress'], true) ? 'sp-text-warn' : '' ?>"><?= cpEscape($status === 'Cancelled' && $assignedMechanicName === '' ? '—' : $displayMechanic) ?></span>
                                <?php if ($status === 'Completed' && $completedByName !== '' && $completedByName !== $assignedMechanicName): ?>
                                    <span class="cp-cell-sub">Completed by <?= cpEscape($completedByName) ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td data-label="Total" class="sp-mono sp-nowrap">
                            <div>
                                <?= cpMoney($displayCost) ?>
                                <?php if ($partsUsedTotal > 0): ?>
                                    <span class="cp-cell-sub">incl. parts <?= cpMoney($partsUsedTotal) ?></span>
                                <?php endif; ?>
                                <?php if ($amountPaid > 0 && $status !== 'Completed'): ?>
                                    <span class="cp-cell-sub">Paid <?= cpMoney($amountPaid) ?> · due <?= cpMoney($remainingBalance) ?></span>
                                <?php elseif ($downPaymentDue): ?>
                                    <span class="cp-cell-sub sp-text-warn">Down payment <?= cpMoney($requiredDown) ?> not yet paid</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td data-label="Status"><?= cpBadge($status) ?></td>
                        <td class="cp-num">
                            <div class="sp-row-actions">
                                <button type="button" class="cp-btn cp-btn-line cp-btn-sm" data-sp-open="#jobView<?= $jobOrderId ?>">View</button>
                                <?php if ($canEditThis): ?>
                                    <button type="button" class="cp-btn cp-btn-line cp-btn-sm" data-sp-open="#jobEdit<?= $jobOrderId ?>">Edit</button>
                                <?php endif; ?>
                                <?php if ($downPaymentDue): ?>
                                    <button type="button" class="cp-btn cp-btn-line cp-btn-sm" data-sp-open="#jobDown<?= $jobOrderId ?>">Down payment</button>
                                <?php endif; ?>
                                <?= $nextStepRow ?>
                                <?php if ($canCancelThis): ?>
                                    <button type="button" class="cp-btn cp-btn-line cp-btn-sm sp-btn-cancel" data-sp-open="#cancelJobDialog"
                                            data-job-id="<?= $jobOrderId ?>" data-job-number="<?= cpEscape($jobNumber) ?>">Cancel</button>
                                <?php endif; ?>
                                <?php if ($status === 'Completed' && $canAccessBilling): ?>
                                    <a href="billing.php?q=<?= rawurlencode($jobNumber) ?>" class="cp-btn cp-btn-line cp-btn-sm">Billing</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>

                    <?php ob_start(); ?>
                    <!-- Job order details -->
                    <div class="sp-dialog" id="jobView<?= $jobOrderId ?>" role="dialog" aria-modal="true" aria-labelledby="jobViewTitle<?= $jobOrderId ?>" hidden>
                        <div class="sp-dialog-box is-wide">
                            <div class="cp-modal-head">
                                <div>
                                    <p class="cp-label">Job order · <span class="sp-mono"><?= cpEscape($jobNumber) ?></span> <?= cpBadge($status) ?></p>
                                    <h2 class="cp-modal-title" id="jobViewTitle<?= $jobOrderId ?>"><?= cpEscape($vehicleLabel !== '' ? $vehicleLabel : $jobNumber) ?></h2>
                                </div>
                                <button type="button" class="cp-modal-x" data-sp-close aria-label="Close">&times;</button>
                            </div>

                            <div class="sp-dialog-body">
                                <dl class="sp-facts">
                                    <div><dt>Customer</dt><dd><?= cpEscape($customerName) ?></dd></div>
                                    <div><dt>Plate</dt><dd class="sp-mono"><?= cpEscape($plateNumber !== '' ? $plateNumber : '—') ?></dd></div>
                                    <div><dt>Source</dt><dd><?= cpEscape($requestSource) ?></dd></div>
                                    <div><dt>Mechanic</dt><dd><?= cpEscape($displayMechanic) ?></dd></div>
                                    <div><dt>Target date</dt><dd><?= cpEscape(formatDateValue($jo['expected_completion_date'] ?? null)) ?></dd></div>
                                    <div><dt>Date</dt><dd><?= cpEscape(formatDateValue($jo['date_created'] ?? null)) ?></dd></div>
                                    <div class="is-wide"><dt>Repair description</dt><dd class="sp-fact-text"><?= nl2br(cpEscape($description)) ?></dd></div>
                                    <div><dt>Estimated cost</dt><dd class="sp-mono"><?= cpMoney($jo['estimated_cost'] ?? 0) ?></dd></div>
                                    <div><dt>Parts used</dt><dd class="sp-mono"><?= cpMoney($partsUsedTotal) ?></dd></div>
                                    <div><dt>Total</dt><dd class="sp-mono"><?= cpMoney($displayCost) ?></dd></div>
                                    <div><dt>Down payment</dt><dd><?= $requiredDown > 0 ? 'Required · <span class="sp-mono">' . cpMoney($requiredDown) . '</span>' : 'Not required' ?></dd></div>
                                    <div><dt>Paid so far</dt><dd class="sp-mono"><?= cpMoney($amountPaid) ?></dd></div>
                                    <div><dt>Remaining balance</dt><dd class="sp-mono"><strong><?= cpMoney($remainingBalance) ?></strong></dd></div>
                                    <div><dt>Date completed</dt><dd><?= cpEscape(formatDateValue($jo['date_completed'] ?? null)) ?></dd></div>
                                    <div><dt>Completed by</dt><dd><?= cpEscape($completedByName !== '' ? $completedByName : 'N/A') ?></dd></div>
                                    <?php if ($status === 'Cancelled' && !empty($jo['cancellation_reason'])): ?>
                                        <div class="is-wide"><dt>Cancellation reason</dt><dd class="sp-fact-text"><?= nl2br(cpEscape($jo['cancellation_reason'])) ?></dd></div>
                                    <?php endif; ?>
                                </dl>

                                <?php if (!empty($paymentsForJob)): ?>
                                    <p class="sp-dialog-section">Payments
                                        <?php if (!empty($jo['invoice_id']) && $canAccessBilling): ?>
                                            <a href="print_invoice.php?invoice_id=<?= intval($jo['invoice_id']) ?>" target="_blank" class="sp-arrow-link" style="float: right"
                                               data-sp-doc data-sp-doc-kind="Invoice" data-sp-doc-ref="<?= cpEscape($jo['invoice_number']) ?>">Invoice ↗</a>
                                        <?php endif; ?>
                                    </p>
                                    <table class="cp-table sp-table sp-table-compact">
                                        <thead>
                                            <tr>
                                                <th scope="col">Date</th>
                                                <th scope="col">Type</th>
                                                <th scope="col">Method</th>
                                                <th scope="col" class="cp-num">Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($paymentsForJob as $jobPayment): ?>
                                                <tr>
                                                    <td data-label="Date"><?= cpEscape(formatDateTimeValue($jobPayment['payment_date'])) ?></td>
                                                    <td data-label="Type"><?= !empty($jobPayment['is_down_payment']) ? 'Down payment' : 'Payment' ?></td>
                                                    <td data-label="Method">
                                                        <div>
                                                            <?= cpEscape($jobPayment['payment_method']) ?>
                                                            <?php if (!empty($jobPayment['reference_number'])): ?><span class="cp-cell-sub">Ref <?= cpEscape($jobPayment['reference_number']) ?></span><?php endif; ?>
                                                        </div>
                                                    </td>
                                                    <td data-label="Amount" class="cp-num sp-mono"><?= cpMoney($jobPayment['amount']) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <?php if ($status === 'Cancelled'): ?>
                                        <p class="cp-hint" style="margin-top: 10px">This job was cancelled after a down payment. Refunds are handled by the Owner outside the system (SRS UC16).</p>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <p class="sp-dialog-section">Parts used</p>
                                <table class="cp-table sp-table sp-table-compact">
                                    <thead>
                                        <tr>
                                            <th scope="col">Part</th>
                                            <th scope="col">Qty</th>
                                            <th scope="col">Unit price</th>
                                            <th scope="col" class="cp-num">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($partsUsedForJob as $usedPart): ?>
                                            <tr>
                                                <td data-label="Part">
                                                    <div>
                                                        <?= cpEscape($usedPart['part_name']) ?>
                                                        <?php $partMeta = array_filter([!empty($usedPart['used_at']) ? formatDateTimeValue($usedPart['used_at']) : '', trim($usedPart['notes'] ?? '')]); ?>
                                                        <?php if ($partMeta): ?><span class="cp-cell-sub"><?= cpEscape(implode(' · ', $partMeta)) ?></span><?php endif; ?>
                                                    </div>
                                                </td>
                                                <td data-label="Qty"><?= intval($usedPart['quantity_used']) ?> <?= cpEscape($usedPart['unit'] ?? '') ?></td>
                                                <td data-label="Unit price" class="sp-mono"><?= cpMoney($usedPart['unit_price_at_use']) ?></td>
                                                <td data-label="Subtotal" class="cp-num sp-mono"><?= cpMoney($usedPart['subtotal']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($partsUsedForJob)): ?>
                                            <tr class="sp-empty-row sp-empty-left"><td colspan="4">No parts used yet.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>

                                <?php if (in_array($status, ['Pending', 'In Progress'], true) && $canUpdateRepairProgress): ?>
                                    <?php if (empty($availableParts)): ?>
                                        <p class="cp-hint" style="margin-top: 14px">No active parts are available in inventory.</p>
                                    <?php else: ?>
                                        <form action="../controllers/JobOrderController.php" method="POST" class="sp-add-part"
                                              data-cp-confirm="This records the part on the job and takes it out of stock."
                                              data-cp-confirm-title="Add this part?" data-cp-confirm-yes="Add part" data-cp-confirm-no="Go back" data-cp-confirm-tone="dark">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="add_part_used">
                                            <input type="hidden" name="job_order_id" value="<?= $jobOrderId ?>">

                                            <div class="sp-field">
                                                <label for="addPart<?= $jobOrderId ?>">Add a part</label>
                                                <select name="part_id" id="addPart<?= $jobOrderId ?>" class="cp-input" required>
                                                    <option value="" disabled selected>Select part</option>
                                                    <?php foreach ($availableParts as $partOption): ?>
                                                        <?php
                                                        $stock = intval($partOption['quantity_on_hand']);
                                                        if ($stock <= 0) continue;
                                                        $partLabel = $partOption['part_name'];
                                                        if (!empty($partOption['brand'])) $partLabel .= ' - ' . $partOption['brand'];
                                                        ?>
                                                        <option value="<?= intval($partOption['part_id']) ?>"><?= cpEscape($partLabel) ?> · <?= cpMoney($partOption['unit_price']) ?> · <?= $stock ?> left</option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="sp-field">
                                                <label for="addPartQty<?= $jobOrderId ?>">Qty</label>
                                                <input type="number" name="quantity_used" id="addPartQty<?= $jobOrderId ?>" class="cp-input" min="1" value="1" required>
                                            </div>
                                            <div class="sp-field">
                                                <label for="addPartNote<?= $jobOrderId ?>">Note</label>
                                                <input type="text" name="notes" id="addPartNote<?= $jobOrderId ?>" class="cp-input" maxlength="500" placeholder="Optional">
                                            </div>
                                            <button type="submit" class="cp-btn cp-btn-line">Add part</button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>

                            <div class="cp-modal-foot">
                                <button type="button" class="cp-btn cp-btn-line" data-sp-close>Close</button>
                                <?php if ($canEditThis): ?>
                                    <button type="button" class="cp-btn cp-btn-line" data-sp-open="#jobEdit<?= $jobOrderId ?>">Edit job order</button>
                                <?php endif; ?>
                                <?php if ($canTakeDownPayment): ?>
                                    <button type="button" class="cp-btn cp-btn-line" data-sp-open="#jobDown<?= $jobOrderId ?>">Record down payment</button>
                                <?php endif; ?>
                                <?php if ($status === 'In Progress' && $canCompleteJobOrders): ?>
                                    <?php // Kept from the old page: an in-progress job can be completed straight away ?>
                                    <form action="../controllers/JobOrderController.php" method="POST">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="job_order_id" value="<?= $jobOrderId ?>">
                                        <input type="hidden" name="status" value="Completed">
                                        <button type="submit" class="cp-btn cp-btn-line">Complete job</button>
                                    </form>
                                <?php endif; ?>
                                <?= str_replace(['>Start<', '>Mark ready<', '>Complete<'], ['>Start repair<', '>Mark ready for pickup<', '>Complete job<'], jobNextStepForm($jobOrderId, $status, $canUpdateRepairProgress, $canCompleteJobOrders, 'cp-btn cp-btn-dark')) ?>
                            </div>
                        </div>
                    </div>

                    <?php if ($canTakeDownPayment): ?>
                        <?php
                        $maxDown = max(round($remainingBalance - 0.01, 2), 0);
                        $suggestedDown = $requiredDown > $amountPaid ? $requiredDown - $amountPaid : minimumDownPayment($displayCost);
                        $suggestedDown = min(round($suggestedDown, 2), $maxDown);
                        ?>
                        <!-- Record down payment (SRS UC20) -->
                        <div class="sp-dialog" id="jobDown<?= $jobOrderId ?>" role="dialog" aria-modal="true" aria-labelledby="jobDownTitle<?= $jobOrderId ?>" hidden>
                            <div class="sp-dialog-box">
                                <form action="../controllers/JobOrderController.php" method="POST" class="js-down-form" novalidate
                                      data-cp-confirm="This saves the down payment on the job's invoice. The final bill will only show what is left."
                                      data-cp-confirm-title="Record this down payment?" data-cp-confirm-yes="Record payment" data-cp-confirm-no="Go back" data-cp-confirm-tone="dark">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="record_down_payment">
                                    <input type="hidden" name="job_order_id" value="<?= $jobOrderId ?>">

                                    <div class="cp-modal-head">
                                        <div>
                                            <p class="cp-label"><span class="sp-mono"><?= cpEscape($jobNumber) ?></span> · <?= cpEscape($customerName) ?></p>
                                            <h2 class="cp-modal-title" id="jobDownTitle<?= $jobOrderId ?>">Record down payment</h2>
                                        </div>
                                        <button type="button" class="cp-modal-x" data-sp-close aria-label="Close">&times;</button>
                                    </div>

                                    <div class="sp-dialog-body">
                                        <dl class="sp-facts">
                                            <div><dt>Job total</dt><dd class="sp-mono"><?= cpMoney($displayCost) ?></dd></div>
                                            <div><dt>Required down payment</dt><dd class="sp-mono"><?= $requiredDown > 0 ? cpMoney($requiredDown) : 'Not set' ?></dd></div>
                                            <div><dt>Paid so far</dt><dd class="sp-mono"><?= cpMoney($amountPaid) ?></dd></div>
                                            <div><dt>Remaining balance</dt><dd class="sp-mono"><strong><?= cpMoney($remainingBalance) ?></strong></dd></div>
                                        </dl>

                                        <div class="sp-fgrid" style="margin-top: 18px">
                                            <div class="sp-field">
                                                <label for="downAmount<?= $jobOrderId ?>">Amount (₱) <span class="sp-req">*</span></label>
                                                <input type="number" step="0.01" min="0.01" max="<?= cpEscape($maxDown) ?>" name="amount" id="downAmount<?= $jobOrderId ?>"
                                                       class="cp-input js-down-amount" value="<?= cpEscape(number_format($suggestedDown, 2, '.', '')) ?>" required>
                                                <p class="cp-hint">Up to <?= cpMoney($maxDown) ?>. The rest is paid in Billing when the job is done.</p>
                                            </div>
                                            <div class="sp-field">
                                                <label for="downMethod<?= $jobOrderId ?>">Payment method <span class="sp-req">*</span></label>
                                                <select name="payment_method" id="downMethod<?= $jobOrderId ?>" class="cp-input js-down-method" required>
                                                    <option value="Cash">Cash</option>
                                                    <option value="GCash">GCash</option>
                                                    <option value="Bank Transfer">Bank Transfer</option>
                                                    <option value="Cheque">Cheque</option>
                                                </select>
                                            </div>
                                            <div class="sp-field sp-span">
                                                <label for="downRef<?= $jobOrderId ?>">Reference no.</label>
                                                <input type="text" name="reference_number" id="downRef<?= $jobOrderId ?>" class="cp-input js-down-ref" maxlength="50" placeholder="Not needed for cash">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="cp-modal-foot">
                                        <button type="button" class="cp-btn cp-btn-line" data-sp-close>Cancel</button>
                                        <button type="submit" class="cp-btn cp-btn-dark">Save down payment</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($canEditThis): ?>
                        <!-- Edit job order -->
                        <div class="sp-dialog" id="jobEdit<?= $jobOrderId ?>" role="dialog" aria-modal="true" aria-labelledby="jobEditTitle<?= $jobOrderId ?>" hidden>
                            <div class="sp-dialog-box">
                                <form action="../controllers/JobOrderController.php" method="POST">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="update_details">
                                    <input type="hidden" name="job_order_id" value="<?= $jobOrderId ?>">

                                    <div class="cp-modal-head">
                                        <div>
                                            <p class="cp-label"><span class="sp-mono"><?= cpEscape($jobNumber) ?></span> · <?= cpEscape($customerName) ?></p>
                                            <h2 class="cp-modal-title" id="jobEditTitle<?= $jobOrderId ?>">Edit job order</h2>
                                        </div>
                                        <button type="button" class="cp-modal-x" data-sp-close aria-label="Close">&times;</button>
                                    </div>

                                    <div class="sp-dialog-body">
                                        <div class="sp-fgrid">
                                            <div class="sp-field sp-span">
                                                <label for="editDesc<?= $jobOrderId ?>">Repair description <span class="sp-req">*</span></label>
                                                <textarea name="description" id="editDesc<?= $jobOrderId ?>" class="cp-input" rows="4" required><?= cpEscape($description) ?></textarea>
                                            </div>
                                            <div class="sp-field">
                                                <label for="editCost<?= $jobOrderId ?>">Estimated cost (₱) <span class="sp-req">*</span></label>
                                                <input type="number" step="0.01" min="0" name="estimated_cost" id="editCost<?= $jobOrderId ?>" class="cp-input" value="<?= cpEscape(floatval($jo['estimated_cost'] ?? 0)) ?>" required>
                                                <p class="cp-hint">Minimum service fee is <?= cpMoney(MIN_SERVICE_FEE) ?>. Only the Owner can go lower.</p>
                                            </div>
                                            <div class="sp-field">
                                                <label for="editDown<?= $jobOrderId ?>">Down payment amount (₱)</label>
                                                <input type="number" step="0.01" min="0" name="down_payment_amount" id="editDown<?= $jobOrderId ?>" class="cp-input" value="<?= cpEscape(floatval($jo['down_payment_amount'] ?? 0)) ?>">
                                                <p class="cp-hint">At least <?= intval(DOWN_PAYMENT_RATE * 100) ?>% of the estimate. Leave 0 to fill it in automatically.</p>
                                            </div>
                                            <label class="sp-check sp-span" for="requiresDownPayment<?= $jobOrderId ?>">
                                                <input type="checkbox" name="requires_down_payment" id="requiresDownPayment<?= $jobOrderId ?>" <?= !empty($jo['requires_down_payment']) ? 'checked' : '' ?>>
                                                <span>Requires a down payment</span>
                                            </label>
                                        </div>
                                        <p class="cp-hint" style="margin-top: 18px">Vehicle, mechanic and target date are set when the job is created. Parts are added from the job's details.</p>
                                    </div>

                                    <div class="cp-modal-foot">
                                        <button type="button" class="cp-btn cp-btn-line" data-sp-close>Cancel</button>
                                        <button type="submit" class="cp-btn cp-btn-dark">Save changes</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php $jobOrderModals .= ob_get_clean(); ?>
                <?php endforeach; ?>

                <tr class="sp-empty-row" data-sp-empty hidden>
                    <td colspan="9">No job orders match your search or filter.</td>
                </tr>
                <?php if (empty($jobOrders)): ?>
                    <tr class="sp-empty-row"><td colspan="9">No job orders yet. Create one to start tracking repairs.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </main>
</div>

<?= $jobOrderModals ?>

<?php if ($canCancelJobOrders): ?>
    <!-- Cancel job order (reason required, SRS UC16) -->
    <div class="sp-dialog" id="cancelJobDialog" role="dialog" aria-modal="true" aria-labelledby="cancelJobTitle" hidden>
        <div class="sp-dialog-box">
            <form action="../controllers/JobOrderController.php" method="POST" id="cancelJobForm">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="job_order_id" id="cancelJobId" value="">
                <input type="hidden" name="status" value="Cancelled">

                <div class="cp-modal-head">
                    <div>
                        <p class="cp-label sp-mono" id="cancelJobNumber"></p>
                        <h2 class="cp-modal-title" id="cancelJobTitle">Cancel job order</h2>
                    </div>
                    <button type="button" class="cp-modal-x" data-sp-close aria-label="Close">&times;</button>
                </div>

                <div class="sp-dialog-body">
                    <p class="sp-callout is-bad">The job moves to Cancelled and can no longer be edited. The reason is saved on the job order.</p>
                    <div class="sp-field">
                        <label for="cancelJobReason">Reason for cancelling <span class="sp-req">*</span></label>
                        <textarea name="cancellation_reason" id="cancelJobReason" class="cp-input" rows="4" maxlength="500" placeholder="Shown on the job order" required></textarea>
                    </div>
                </div>

                <div class="cp-modal-foot">
                    <button type="button" class="cp-btn cp-btn-line" data-sp-close>Keep job</button>
                    <button type="submit" class="cp-btn cp-btn-dark">Cancel job order</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php if ($canCreateJobOrders): ?>
    <!-- New job order -->
    <div class="sp-dialog" id="createJobDialog" role="dialog" aria-modal="true" aria-labelledby="createJobTitle" hidden>
        <div class="sp-dialog-box">
            <form action="../controllers/JobOrderController.php" method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="create">

                <div class="cp-modal-head">
                    <div>
                        <p class="cp-label">Job orders</p>
                        <h2 class="cp-modal-title" id="createJobTitle">New job order</h2>
                    </div>
                    <button type="button" class="cp-modal-x" data-sp-close aria-label="Close">&times;</button>
                </div>

                <div class="sp-dialog-body">
                    <?php if (count($vehicles) === 0): ?>
                        <p class="sp-callout is-bad">No registered vehicles yet. Add the customer's vehicle first under Records › Vehicles.</p>
                    <?php endif; ?>
                    <div class="sp-fgrid">
                        <div class="sp-field sp-span">
                            <label for="createVehicle">Vehicle and customer <span class="sp-req">*</span></label>
                            <select name="vehicle_id" id="createVehicle" class="cp-input" required <?= count($vehicles) === 0 ? 'disabled' : '' ?>>
                                <option value="" disabled selected>Select vehicle by plate number</option>
                                <?php foreach ($vehicles as $v): ?>
                                    <option value="<?= intval($v['vehicle_id']) ?>" data-customer="<?= intval($v['customer_id']) ?>"><?= cpEscape($v['plate_number'] . ' · ' . $v['make'] . ' ' . $v['model'] . ' · ' . $v['first_name'] . ' ' . $v['last_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="sp-field">
                            <label for="createSource">Request source</label>
                            <select name="request_source" id="createSource" class="cp-input" required>
                                <option value="Walk-in" selected>Walk-in</option>
                                <option value="Online">Online</option>
                            </select>
                        </div>
                        <div class="sp-field">
                            <label for="createMechanic">Assigned mechanic</label>
                            <select name="assigned_mechanic_id" id="createMechanic" class="cp-input">
                                <option value="">Unassigned</option>
                                <?php foreach ($mechanics as $mechanic): ?>
                                    <option value="<?= intval($mechanic['user_id']) ?>"><?= cpEscape($mechanic['first_name'] . ' ' . $mechanic['last_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="sp-field sp-span">
                            <label for="createDesc">Repair description <span class="sp-req">*</span></label>
                            <textarea name="description" id="createDesc" class="cp-input" rows="4" placeholder="Symptoms, diagnosis or the requested repair" required></textarea>
                        </div>
                        <div class="sp-field">
                            <label for="createCost">Estimated cost (₱) <span class="sp-req">*</span></label>
                            <input type="number" step="0.01" min="0" name="estimated_cost" id="createCost" class="cp-input" placeholder="0.00" required>
                            <p class="cp-hint">Minimum service fee is <?= cpMoney(MIN_SERVICE_FEE) ?>. Only the Owner can go lower.</p>
                        </div>
                        <div class="sp-field">
                            <label for="createTarget">Target completion date</label>
                            <input type="date" name="expected_completion_date" id="createTarget" class="cp-input">
                            <p class="cp-hint">Optional</p>
                        </div>
                    </div>
                </div>

                <div class="cp-modal-foot">
                    <button type="button" class="cp-btn cp-btn-line" data-sp-close>Cancel</button>
                    <button type="submit" class="cp-btn cp-btn-dark" <?= count($vehicles) === 0 ? 'disabled' : '' ?>>Save job order</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    // Cancel: fill in which job the reason is for
    const cancelDialog = document.getElementById('cancelJobDialog');
    if (cancelDialog) {
        cancelDialog.addEventListener('sp:open', function (event) {
            const btn = event.detail.trigger;
            if (!btn) return;
            document.getElementById('cancelJobId').value = btn.dataset.jobId;
            document.getElementById('cancelJobNumber').textContent = btn.dataset.jobNumber;
            document.getElementById('cancelJobReason').value = '';
        });
        document.getElementById('cancelJobForm').addEventListener('submit', function (e) {
            const reason = document.getElementById('cancelJobReason');
            reason.value = reason.value.trim();
            if (reason.value === '') {
                e.preventDefault();
                reason.focus();
            }
        });
    }

    // Down payment: reference number is required for GCash, bank transfer and cheque
    document.querySelectorAll('.js-down-form').forEach(function (form) {
        const amount = form.querySelector('.js-down-amount');
        const method = form.querySelector('.js-down-method');
        const ref = form.querySelector('.js-down-ref');
        const syncRef = function () {
            const needsRef = method.value !== 'Cash';
            ref.required = needsRef;
            ref.placeholder = needsRef ? 'Required for ' + method.value : 'Not needed for cash';
        };
        method.addEventListener('change', syncRef);
        syncRef();

        // Checked before the confirm popup opens: window capture runs before the popup's document listener
        window.addEventListener('submit', function (e) {
            if (e.target !== form) return;
            const value = parseFloat(amount.value);
            const max = parseFloat(amount.max);
            let problem = null;
            if (!(value > 0)) {
                problem = [amount, 'Enter an amount greater than zero.'];
            } else if (value > max) {
                problem = [amount, 'A down payment must leave part of the bill unpaid. The most you can record is \u20b1' + max.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '.'];
            } else if (ref.required && ref.value.trim() === '') {
                problem = [ref, 'Reference number is required for ' + method.value + ' payments.'];
            }
            if (problem) {
                e.preventDefault();
                e.stopImmediatePropagation();
                cpFieldError(problem[0], problem[1]);
            }
        }, true);
    });

    // job_orders.php?new=1 opens the New job order popup (used by the dashboard button)
    if (new URLSearchParams(window.location.search).get('new') === '1' && document.getElementById('createJobDialog')) {
        window.addEventListener('load', function () {
            // ...and ?customer=ID (from Customers › New job) picks that customer's vehicle
            const customer = new URLSearchParams(window.location.search).get('customer');
            const option = customer && document.querySelector('#createVehicle option[data-customer="' + CSS.escape(customer) + '"]');
            if (option) option.selected = true;
            window.spDialog.open('#createJobDialog');
        });
    }
})();
</script>

</body>
</html>
