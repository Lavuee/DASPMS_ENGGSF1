<?php
require_once __DIR__ . '/../includes/auth.php';

requirePermission('online_orders.manage');

require_once '../config/Database.php';
require_once '../models/PartOrder.php';
require_once '../includes/staff_ui.php';

$db = (new Database())->getConnection();

// Release unpaid web-order reservations older than RESERVATION_HOLD_DAYS (SRS)
(new PartOrder($db))->expireStaleReservations();
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

function isGcashOrder($paymentMethod) {
    $paymentMethod = trim((string) $paymentMethod);

    return in_array($paymentMethod, [
        'GCash Down Payment',
        'GCash Full Payment',
        'GCash Reservation'
    ]);
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

function getGcashVerificationBadgeClass($status) {
    switch ($status) {
        case 'Verified':
            return 'badge-soft-success';
        case 'Rejected':
            return 'badge-soft-danger';
        case 'Pending Verification':
        case 'Awaiting Payment':
            return 'badge-soft-warning';
        case 'Not Required':
            return 'badge-soft-secondary';
        default:
            return 'badge-soft-secondary';
    }
}

function getAllowedNextStatuses($status, $paymentStatus, $paymentMethod, $gcashVerificationStatus, $amountPaid = 0) {
    $isGcash = isGcashOrder($paymentMethod);
    $gcashIsVerified = $gcashVerificationStatus === 'Verified';
    $hasRecordedPayment = floatval($amountPaid) > 0 || $paymentStatus === 'Partial' || $paymentStatus === 'Paid';

    if ($status === 'Pending') {
        if ($isGcash) {
            if ($gcashIsVerified) {
                return ['Approved' => 'Approve'];
            }

            return ['Cancelled' => 'Cancel'];
        }

        return ['Approved' => 'Approve', 'Cancelled' => 'Cancel'];
    }

    if ($status === 'Approved') {
        if ($isGcash) {
            if ($gcashIsVerified) {
                return ['Ready for Pickup' => 'Ready for Pickup'];
            }

            return [];
        }

        return ['Ready for Pickup' => 'Ready for Pickup', 'Cancelled' => 'Cancel'];
    }

    if ($status === 'Ready for Pickup') {
        if ($paymentStatus === 'Paid') {
            return ['Completed' => 'Complete'];
        }

        if (!$hasRecordedPayment) {
            return ['Cancelled' => 'Cancel'];
        }
    }

    return [];
}

function cleanDisplay($value, $fallback = 'N/A') {
    $value = trim(html_entity_decode((string)($value ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    if ($value === '') {
        return htmlspecialchars($fallback, ENT_QUOTES, 'UTF-8');
    }

    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

// Cancellation reasons for cancelled orders (read-only; shown in the order popup)
$cancelInfo = [];
$cancelledIds = array_map('intval', array_column(array_filter($orders, function ($o) {
    return ($o['status'] ?? '') === 'Cancelled';
}), 'order_id'));
if (!empty($cancelledIds)) {
    $stmtCancel = $db->prepare(sprintf("
        SELECT order_id, cancellation_reason, cancelled_at
        FROM part_order
        WHERE order_id IN (%s)
    ", implode(',', array_fill(0, count($cancelledIds), '?'))));
    $stmtCancel->execute($cancelledIds);
    foreach ($stmtCancel->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $cancelInfo[$c['order_id']] = $c;
    }
}

// "Capacitors x1<br>Switches x2" (from the model) -> ["Capacitors × 1", "Switches × 2"], as plain text
function webOrderItems($raw) {
    $parts = preg_split('/<br\s*\/?>/i', (string) $raw);
    $items = [];
    foreach ($parts as $part) {
        $part = trim(html_entity_decode(strip_tags($part), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($part === '') continue;
        $items[] = preg_replace('/\sx(\d+)$/', ' × $1', $part);
    }
    return $items;
}

function webOrderStatusKey($status) {
    return strtolower(str_replace(' ', '', (string) $status));
}

$statusChips = [
    'Pending' => 'is-pend',
    'Approved' => 'is-prog',
    'Ready for Pickup' => 'is-ok',
    'Completed' => 'is-done',
    'Cancelled' => 'is-bad',
];
$gcashLabels = [
    'Pending Verification' => 'GCash to verify',
    'Awaiting Payment' => 'Awaiting GCash payment',
    'Verified' => 'GCash verified',
    'Rejected' => 'GCash rejected',
];
$gcashTone = [
    'Pending Verification' => 'pend',
    'Awaiting Payment' => 'pend',
    'Verified' => 'done',
    'Rejected' => 'bad',
];
$orderDialogs = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Web Orders - Norily's Repair Shop</title>
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
        spPageHead(
            spPortalLabel() . ' · Workshop',
            'Web Orders',
            'Parts customers reserved online. Approve them, check GCash references, and release them at pickup.'
        );
        cpFlash();
        ?>

        <div data-sp-filter="#orderRows">
            <div class="sp-toolbar">
                <div class="sp-search-field-inline">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                    <input type="search" id="orderSearch" class="cp-input" data-sp-search placeholder="Search order no. or customer" aria-label="Search order no. or customer" autocomplete="off">
                </div>
            </div>

            <div class="sp-chips" role="group" aria-label="Filter by status">
                <button type="button" class="sp-chip" data-sp-chip="all" aria-pressed="true">All <span class="sp-chip-count"><?= $counts['All'] ?></span></button>
                <?php foreach ($statusChips as $label => $tone): ?>
                    <button type="button" class="sp-chip <?= $tone ?>" data-sp-chip="<?= webOrderStatusKey($label) ?>" aria-pressed="false"><span class="sp-chip-dot"></span><?= cpEscape($label) ?> <span class="sp-chip-count"><?= $counts[$label] ?></span></button>
                <?php endforeach; ?>
            </div>

            <p class="sp-showing" data-sp-showing aria-live="polite">Showing <?= count($orders) ?> of <?= count($orders) ?></p>
        </div>

        <table class="cp-table sp-table" id="onlineOrdersTable">
            <thead>
                <tr>
                    <th scope="col">Order / customer</th>
                    <th scope="col">Items</th>
                    <th scope="col">Pickup</th>
                    <th scope="col">Total</th>
                    <th scope="col">Order status</th>
                    <th scope="col">Payment</th>
                    <th scope="col"><span class="visually-hidden">Update</span></th>
                </tr>
            </thead>
            <tbody id="orderRows" data-cp-paginate="10" data-cp-noun="order">
                <?php foreach ($orders as $row): ?>
                    <?php
                    $orderId = intval($row['order_id']);
                    $orderNo = 'PO-' . $orderId;
                    $status = trim($row['status'] ?? 'Pending');
                    $paymentStatus = trim($row['payment_status'] ?? 'No Invoice');
                    $amountPaid = floatval($row['amount_paid'] ?? 0);
                    $customerName = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
                    $items = webOrderItems($row['items'] ?? '');
                    $pickupDate = trim($row['preferred_pickup_date'] ?? '');
                    $pickupTime = trim($row['preferred_pickup_time'] ?? '');
                    $pickupNotes = trim($row['pickup_notes'] ?? '');
                    $pickupText = $pickupDate !== '' ? date('d M', strtotime($pickupDate)) . ($pickupTime !== '' ? ' · ' . date('g:i A', strtotime($pickupTime)) : '') : 'No pickup schedule';
                    $paymentMethod = trim($row['payment_method'] ?? '') ?: 'Cash on Pickup';
                    $gcashReference = trim($row['gcash_reference'] ?? '');
                    $gcashAmount = floatval($row['gcash_payment_amount'] ?? 0);
                    $paymentNotes = trim($row['payment_notes'] ?? '');
                    $isGcash = isGcashOrder($paymentMethod);
                    $gcashVerificationStatus = trim($row['gcash_verification_status'] ?? '');
                    if ($gcashVerificationStatus === '') {
                        $gcashVerificationStatus = $isGcash ? 'Pending Verification' : 'Not Required';
                    }
                    $gcashVerifierName = trim(($row['gcash_verifier_first_name'] ?? '') . ' ' . ($row['gcash_verifier_last_name'] ?? ''));
                    $shopPaidAmount = $isGcash ? max($amountPaid - $gcashAmount, 0) : $amountPaid;
                    $allowedNextStatuses = getAllowedNextStatuses($status, $paymentStatus, $paymentMethod, $gcashVerificationStatus, $amountPaid);
                    $canVerifyGcash = $isGcash && !in_array($status, ['Completed', 'Cancelled'], true) && !in_array($gcashVerificationStatus, ['Verified', 'Awaiting Payment'], true);
                    $hasActions = !empty($allowedNextStatuses) || $canVerifyGcash;
                    $gcashBadge = $isGcash ? '<span class="st ' . ($gcashTone[$gcashVerificationStatus] ?? 'done') . '">' . cpEscape($gcashLabels[$gcashVerificationStatus] ?? $gcashVerificationStatus) . '</span>' : '';

                    // Short note on what's blocking the next step
                    $orderNote = '';
                    if ($isGcash && $gcashVerificationStatus !== 'Verified' && $status === 'Pending') {
                        $orderNote = $gcashVerificationStatus === 'Awaiting Payment' ? 'Waiting for the online GCash payment (confirmed automatically by PayMongo).' : 'Verify the GCash reference before approving.';
                    } elseif ($status === 'Ready for Pickup' && $paymentStatus === 'Paid') {
                        $orderNote = 'Paid. Complete it once the customer claims the parts.';
                    } elseif ($status === 'Ready for Pickup' && $paymentStatus === 'Partial') {
                        $orderNote = 'Partly paid, so it can no longer be cancelled. The rest is paid in Billing.';
                    } elseif ($status === 'Ready for Pickup') {
                        $orderNote = 'The remaining payment is recorded in Billing.';
                    }

                    $searchText = strtolower('#' . $orderId . ' ' . $orderNo . ' ' . $customerName . ' ' . implode(' ', $items) . ' ' . $pickupText . ' ' . $pickupNotes . ' ' . $paymentMethod . ' ' . $gcashReference . ' ' . $gcashVerificationStatus . ' ' . $status . ' ' . $paymentStatus);
                    ?>
                    <tr data-status="<?= webOrderStatusKey($status) ?>" data-search="<?= cpEscape($searchText) ?>">
                        <td data-label="Order">
                            <div>
                                <span class="sp-ref"><?= cpEscape($orderNo) ?></span>
                                <span class="cp-cell-sub"><?= cpEscape($customerName) ?></span>
                            </div>
                        </td>
                        <td data-label="Items"><?= cpEscape(implode(', ', $items) ?: '—') ?></td>
                        <td data-label="Pickup" class="sp-nowrap"><?= cpEscape($pickupText) ?></td>
                        <td data-label="Total" class="sp-mono"><?= cpMoney($row['total_amount'] ?? 0) ?></td>
                        <td data-label="Order status"><?= cpBadge($status) ?></td>
                        <td data-label="Payment">
                            <div>
                                <span class="sp-cell-line"><?= cpEscape($paymentMethod) ?></span>
                                <?php if ($gcashReference !== ''): ?>
                                    <span class="cp-cell-sub">Ref <?= cpEscape($gcashReference) ?></span>
                                <?php endif; ?>
                                <?php if ($gcashBadge !== ''): ?><div class="sp-badge-gap"><?= $gcashBadge ?></div><?php endif; ?>
                            </div>
                        </td>
                        <td class="cp-num">
                            <button type="button" class="cp-btn <?= $hasActions ? 'cp-btn-dark' : 'cp-btn-line' ?> cp-btn-sm" data-sp-open="#orderDialog<?= $orderId ?>"><?= $hasActions ? 'Update' : 'View' ?></button>
                        </td>
                    </tr>

                    <?php ob_start(); ?>
                    <div class="sp-dialog" id="orderDialog<?= $orderId ?>" role="dialog" aria-modal="true" aria-labelledby="orderTitle<?= $orderId ?>" hidden>
                        <div class="sp-dialog-box">
                            <div class="cp-modal-head">
                                <div>
                                    <p class="cp-label"><span class="sp-mono"><?= cpEscape($orderNo) ?></span> · <?= cpEscape($customerName) ?></p>
                                    <h2 class="cp-modal-title" id="orderTitle<?= $orderId ?>"><?= $hasActions ? 'Update web order' : 'Web order' ?></h2>
                                </div>
                                <button type="button" class="cp-modal-x" data-sp-close aria-label="Close">&times;</button>
                            </div>

                            <div class="sp-dialog-body">
                                <dl class="sp-facts">
                                    <div><dt>Items</dt><dd><?= cpEscape(implode(', ', $items) ?: '—') ?></dd></div>
                                    <div><dt>Pickup</dt><dd><?= cpEscape($pickupText) ?></dd></div>
                                    <div><dt>Total</dt><dd class="sp-mono"><?= cpMoney($row['total_amount'] ?? 0) ?></dd></div>
                                    <div><dt>Payment</dt><dd><?= cpEscape($paymentMethod) ?></dd></div>
                                    <div><dt>Reference</dt><dd class="sp-mono"><?= cpEscape($gcashReference !== '' ? $gcashReference : '—') ?></dd></div>
                                    <div><dt>Order status</dt><dd><?= cpBadge($status) ?></dd></div>
                                    <div><dt>Invoice</dt><dd><?= cpBadge($paymentStatus) ?></dd></div>
                                    <div><dt><?= $isGcash ? 'Paid (GCash / shop)' : 'Paid at shop' ?></dt><dd class="sp-mono"><?= $isGcash ? cpMoney($gcashAmount) . ' / ' . cpMoney($shopPaidAmount) : cpMoney($shopPaidAmount) ?></dd></div>
                                    <div><dt>Balance</dt><dd class="sp-mono"><?= cpMoney($row['balance_due'] ?? 0) ?></dd></div>
                                    <?php if ($pickupNotes !== ''): ?>
                                        <div class="is-wide"><dt>Pickup note</dt><dd class="sp-fact-text"><?= cpEscape($pickupNotes) ?></dd></div>
                                    <?php endif; ?>
                                    <?php if ($paymentNotes !== ''): ?>
                                        <div class="is-wide"><dt>Payment note</dt><dd class="sp-fact-text"><?= cpEscape($paymentNotes) ?></dd></div>
                                    <?php endif; ?>
                                    <?php if ($isGcash): ?>
                                        <div class="is-wide">
                                            <dt>GCash check</dt>
                                            <dd class="sp-fact-text">
                                                <?= $gcashBadge ?>
                                                <?php if ($gcashVerificationStatus === 'Verified' && $gcashVerifierName === ''): ?> Confirmed by PayMongo.<?php endif; ?>
                                                <?php if (!empty($row['gcash_verified_at'])): ?>
                                                    Checked <?= date('d M Y, g:i A', strtotime($row['gcash_verified_at'])) ?><?= $gcashVerifierName !== '' ? ' by ' . cpEscape($gcashVerifierName) : '' ?>.
                                                <?php endif; ?>
                                            </dd>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($status === 'Cancelled'): ?>
                                        <?php $reason = trim($cancelInfo[$orderId]['cancellation_reason'] ?? ''); ?>
                                        <div class="is-wide"><dt>Cancellation reason</dt><dd class="sp-fact-text sp-text-bad"><?= $reason !== '' ? nl2br(cpEscape($reason)) : 'No reason recorded (cancelled before reasons were required).' ?></dd></div>
                                    <?php endif; ?>
                                </dl>

                                <?php if ($canVerifyGcash): ?>
                                    <form action="../controllers/ManageOrderController.php" method="POST" class="sp-inline-form"
                                          data-cp-confirm="This updates the GCash check on this order." data-cp-confirm-title="Save the GCash check?"
                                          data-cp-confirm-yes="Save" data-cp-confirm-no="Go back" data-cp-confirm-tone="dark">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="update_gcash_verification">
                                        <input type="hidden" name="order_id" value="<?= $orderId ?>">
                                        <div class="sp-field">
                                            <label for="gcash<?= $orderId ?>">GCash reference check</label>
                                            <select name="gcash_verification_status" id="gcash<?= $orderId ?>" class="cp-input" required>
                                                <option value="" disabled selected>Choose a result</option>
                                                <option value="Verified">Verified, the payment came in</option>
                                                <option value="Rejected">Rejected, reference not found</option>
                                                <option value="Pending Verification">Still checking</option>
                                            </select>
                                        </div>
                                        <button type="submit" class="cp-btn cp-btn-line">Save check</button>
                                    </form>
                                <?php endif; ?>

                                <?php if (!empty($allowedNextStatuses)): ?>
                                    <form action="../controllers/ManageOrderController.php" method="POST" id="orderStatusForm<?= $orderId ?>" class="sp-order-status-form" data-order-status-form>
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="order_id" value="<?= $orderId ?>">
                                        <div class="sp-field" style="margin-top: 22px">
                                            <label for="orderStatus<?= $orderId ?>">Order status</label>
                                            <select name="status" id="orderStatus<?= $orderId ?>" class="cp-input" required>
                                                <option value="" disabled selected>Select the next step</option>
                                                <?php foreach ($allowedNextStatuses as $value => $label): ?>
                                                    <option value="<?= cpEscape($value) ?>"><?= cpEscape($label) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <p class="cp-hint">Approve to hold the parts. Mark ready when they're packed.</p>
                                        </div>
                                        <div class="sp-field" data-cancel-reason hidden style="margin-top: 18px">
                                            <label for="orderReason<?= $orderId ?>">Reason for cancelling <span class="sp-req">*</span></label>
                                            <textarea name="cancellation_reason" id="orderReason<?= $orderId ?>" class="cp-input" rows="3" maxlength="500" placeholder="Shown on the order"></textarea>
                                        </div>
                                    </form>
                                <?php endif; ?>

                                <?php if ($orderNote !== ''): ?>
                                    <p class="cp-hint" style="margin-top: 16px"><?= cpEscape($orderNote) ?></p>
                                <?php endif; ?>
                            </div>

                            <div class="cp-modal-foot">
                                <button type="button" class="cp-btn cp-btn-line" data-sp-close><?= !empty($allowedNextStatuses) ? 'Cancel' : 'Close' ?></button>
                                <?php if (!empty($allowedNextStatuses)): ?>
                                    <button type="submit" class="cp-btn cp-btn-dark" form="orderStatusForm<?= $orderId ?>">Update</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php $orderDialogs .= ob_get_clean(); ?>
                <?php endforeach; ?>

                <tr class="sp-empty-row" data-sp-empty hidden>
                    <td colspan="7">No web orders match your search or filter.</td>
                </tr>
                <?php if (empty($orders)): ?>
                    <tr class="sp-empty-row"><td colspan="7">No web orders yet. Parts reserved on the website show up here.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </main>
</div>

<?= $orderDialogs ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Choosing "Cancel" asks for the reason (required, SRS UC16); other steps don't need one.
document.querySelectorAll('[data-order-status-form]').forEach(function (form) {
    const select = form.querySelector('select[name="status"]');
    const reasonBox = form.querySelector('[data-cancel-reason]');
    const reason = reasonBox.querySelector('textarea');

    select.addEventListener('change', function () {
        const cancelling = select.value === 'Cancelled';
        reasonBox.hidden = !cancelling;
        reason.required = cancelling;
        if (cancelling) reason.focus();
    });

    form.addEventListener('submit', function (e) {
        if (select.value === 'Cancelled') {
            reason.value = reason.value.trim();
            if (reason.value === '') {
                e.preventDefault();
                reason.focus();
            }
        } else {
            reason.value = '';
        }
    });
});
</script>

</body>
</html>
