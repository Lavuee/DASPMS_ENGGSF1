<?php
require_once __DIR__ . '/../includes/auth.php';

requirePermission('dashboard.cashier');

require_once '../config/Database.php';
require_once '../includes/business_rules.php';
require_once '../includes/staff_ui.php';

$db = (new Database())->getConnection();

// Release unpaid web-order reservations older than RESERVATION_HOLD_DAYS (SRS)
require_once '../models/PartOrder.php';
(new PartOrder($db))->expireStaleReservations();

$cashierName = $_SESSION['first_name'] ?? 'Cashier';
$userId = intval($_SESSION['user_id'] ?? 0);

/* Column 1: ready for pickup (repair jobs and web orders) */
$readyJobs = $db->query("
    SELECT jo.job_order_number, c.first_name, c.last_name, v.plate_number
    FROM job_order jo
    JOIN customer c ON c.customer_id = jo.customer_id
    JOIN vehicle v ON v.vehicle_id = jo.vehicle_id
    WHERE jo.status = 'Ready for Pickup'
    ORDER BY jo.date_created ASC
")->fetchAll(PDO::FETCH_ASSOC);

$readyOrders = $db->query("
    SELECT po.order_id, po.payment_method, c.first_name, c.last_name,
           COALESCE(i.payment_status, 'No Invoice') AS payment_status, COALESCE(i.balance_due, po.total_amount) AS balance_due,
           GROUP_CONCAT(CONCAT(p.part_name, ' × ', poi.quantity) ORDER BY poi.order_item_id SEPARATOR ', ') AS items
    FROM part_order po
    JOIN customer c ON c.customer_id = po.customer_id
    LEFT JOIN invoice i ON i.part_order_id = po.order_id AND i.is_voided = 0
    LEFT JOIN part_order_item poi ON poi.order_id = po.order_id
    LEFT JOIN part p ON p.part_id = poi.part_id
    WHERE po.status = 'Ready for Pickup'
    GROUP BY po.order_id
    ORDER BY po.order_date ASC
")->fetchAll(PDO::FETCH_ASSOC);

/* Column 2: waiting for payment (open invoices, and finished jobs with no invoice yet) */
$openInvoices = $db->query("
    SELECT i.invoice_number, i.invoice_type, i.payment_status, i.balance_due, i.due_date,
           jo.job_order_number, i.part_order_id, c.first_name, c.last_name
    FROM invoice i
    JOIN customer c ON c.customer_id = i.customer_id
    LEFT JOIN job_order jo ON jo.job_order_id = i.job_order_id
    WHERE i.is_voided = 0 AND i.payment_status <> 'Paid'
      AND (i.job_order_id IS NULL OR jo.status = 'Completed') -- down-payment invoices wait until the job is done
    ORDER BY (i.due_date IS NULL), i.due_date ASC, i.date_issued ASC
")->fetchAll(PDO::FETCH_ASSOC);

$unbilledJobs = $db->query("
    SELECT jo.job_order_number, c.first_name, c.last_name
    FROM job_order jo
    JOIN customer c ON c.customer_id = jo.customer_id
    LEFT JOIN invoice i ON i.job_order_id = jo.job_order_id
    WHERE jo.status = 'Completed' AND i.invoice_id IS NULL
    ORDER BY jo.date_completed ASC
")->fetchAll(PDO::FETCH_ASSOC);

/* Column 3: new from the website */
$pendingOrders = $db->query("
    SELECT po.order_id, po.payment_method, po.preferred_pickup_date, c.first_name, c.last_name,
           GROUP_CONCAT(CONCAT(p.part_name, ' × ', poi.quantity) ORDER BY poi.order_item_id SEPARATOR ', ') AS items
    FROM part_order po
    JOIN customer c ON c.customer_id = po.customer_id
    LEFT JOIN part_order_item poi ON poi.order_id = po.order_id
    LEFT JOIN part p ON p.part_id = poi.part_id
    WHERE po.status = 'Pending'
    GROUP BY po.order_id
    ORDER BY po.order_date ASC
")->fetchAll(PDO::FETCH_ASSOC);

$pendingRequests = $db->query("
    SELECT sr.request_number, sr.preferred_appointment_date, c.first_name, c.last_name, s.service_name
    FROM service_request sr
    JOIN customer c ON c.customer_id = sr.customer_id
    LEFT JOIN service s ON s.service_id = sr.service_id
    WHERE sr.status = 'Pending'
    ORDER BY sr.created_at ASC
")->fetchAll(PDO::FETCH_ASSOC);

/* Section 03: this cashier's POS sales today */
$stmtSales = $db->prepare("
    SELECT pt.*, c.first_name, c.last_name
    FROM pos_transaction pt
    LEFT JOIN customer c ON c.customer_id = pt.customer_id
    WHERE pt.processed_by = ? AND DATE(pt.transaction_date) = CURDATE()
    ORDER BY pt.transaction_date DESC
");
$stmtSales->execute([$userId]);
$salesToday = $stmtSales->fetchAll(PDO::FETCH_ASSOC);

$saleItems = [];
if (!empty($salesToday)) {
    $ids = array_map('intval', array_column($salesToday, 'pos_id'));
    $stmtItems = $db->prepare("
        SELECT pi.pos_id, p.part_name, pi.quantity_sold
        FROM pos_item pi JOIN part p ON p.part_id = pi.part_id
        WHERE pi.pos_id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")
        ORDER BY pi.pos_item_id
    ");
    $stmtItems->execute($ids);
    foreach ($stmtItems->fetchAll(PDO::FETCH_ASSOC) as $item) {
        $saleItems[$item['pos_id']][] = $item['part_name'] . ' × ' . intval($item['quantity_sold']);
    }
}
$salesTotal = array_sum(array_map(function ($s) {
    return $s['status'] === 'Completed' ? floatval($s['total_amount']) : 0;
}, $salesToday));

$readyCount = count($readyJobs) + count($readyOrders);
$payCount = count($openInvoices) + count($unbilledJobs);
$webCount = count($pendingOrders) + count($pendingRequests);
$listLimit = 6; // rows shown per column; the link opens the full list

function deskName($row) {
    return trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
}

$hour = intval(date('H'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Front Desk - Norily's Repair Shop</title>
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
            strtoupper(date('l')) . ' · ' . strtoupper(date('d M Y')),
            'Front Desk',
            'Good ' . ($hour < 12 ? 'morning' : ($hour < 18 ? 'afternoon' : 'evening')) . ', ' . $cashierName . '. Start a task, or clear the three lists below from left to right.'
        );
        cpFlash();
        ?>

        <!-- 01 Start a task -->
        <section class="sp-sec" aria-label="Start a task">
            <?php spSectionHead('01', 'Start a task'); ?>
            <div class="sp-tasks">
                <a href="pos.php" class="sp-task">
                    <span class="sp-task-kicker sp-mono">POS Terminal</span>
                    <span class="sp-task-title">New sale <span aria-hidden="true">↗</span></span>
                    <span class="sp-task-note">Sell parts over the counter</span>
                </a>
                <a href="create_transaction.php" class="sp-task">
                    <span class="sp-task-kicker sp-mono">Transaction Form</span>
                    <span class="sp-task-title">Walk-in repair <span aria-hidden="true">↗</span></span>
                    <span class="sp-task-note">Customer, vehicle and services</span>
                </a>
                <a href="billing.php" class="sp-task">
                    <span class="sp-task-kicker sp-mono">Billing</span>
                    <span class="sp-task-title">Record payment <span aria-hidden="true">↗</span></span>
                    <span class="sp-task-note">Generate an invoice, then collect</span>
                </a>
                <a href="online_orders.php" class="sp-task">
                    <span class="sp-task-kicker sp-mono">Web Orders</span>
                    <span class="sp-task-title">Check GCash <span aria-hidden="true">↗</span></span>
                    <span class="sp-task-note">Verify reference numbers</span>
                </a>
            </div>
        </section>

        <!-- 02 To do today -->
        <section class="sp-sec" aria-label="To do today">
            <?php spSectionHead('02', 'To do today'); ?>
            <div class="sp-todo">

                <div class="sp-todo-col is-ok">
                    <div class="sp-todo-head">
                        <h3>Ready for pickup <span class="sp-todo-count"><?= $readyCount ?></span></h3>
                        <a href="job_orders.php?status=readyforpickup" class="sp-arrow-link">All ↗</a>
                    </div>
                    <p class="sp-todo-note">Collect any balance, then release.</p>
                    <?php $shown = 0; ?>
                    <?php foreach ($readyJobs as $job): if ($shown++ >= $listLimit) break; ?>
                        <div class="sp-todo-item">
                            <span>
                                <strong><?= cpEscape(deskName($job)) ?> · <span class="sp-plate sp-plate-sm"><?= cpEscape($job['plate_number'] ?: '—') ?></span></strong>
                                <small><?= cpEscape($job['job_order_number']) ?> · complete the job to bill it</small>
                            </span>
                            <a href="job_orders.php?q=<?= rawurlencode($job['job_order_number']) ?>" class="cp-btn cp-btn-line cp-btn-sm">Release</a>
                        </div>
                    <?php endforeach; ?>
                    <?php foreach ($readyOrders as $order): if ($shown++ >= $listLimit) break; ?>
                        <?php $paid = $order['payment_status'] === 'Paid'; ?>
                        <div class="sp-todo-item">
                            <span>
                                <strong>PO-<?= intval($order['order_id']) ?> · <?= cpEscape(deskName($order)) ?></strong>
                                <small><?= cpEscape($order['items'] ?: 'Parts order') ?> · <?= $paid ? 'paid in full' : 'balance ' . cpMoney($order['balance_due']) ?></small>
                            </span>
                            <?php if ($paid): ?>
                                <a href="online_orders.php?q=<?= rawurlencode('PO-' . $order['order_id']) ?>" class="cp-btn cp-btn-line cp-btn-sm">Release</a>
                            <?php else: ?>
                                <a href="billing.php?q=<?= rawurlencode('PO-' . $order['order_id']) ?>" class="cp-btn cp-btn-line cp-btn-sm">Collect</a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($readyCount === 0): ?><p class="sp-todo-empty">Nothing waiting for pickup.</p><?php endif; ?>
                </div>

                <div class="sp-todo-col is-bad">
                    <div class="sp-todo-head">
                        <h3>Waiting for payment <span class="sp-todo-count"><?= $payCount ?></span></h3>
                        <a href="billing.php" class="sp-arrow-link">Billing ↗</a>
                    </div>
                    <p class="sp-todo-note">Unpaid and partial invoices.</p>
                    <?php $shown = 0; ?>
                    <?php foreach ($openInvoices as $inv): if ($shown++ >= $listLimit) break; ?>
                        <?php
                        $ref = $inv['job_order_number'] ?: 'PO-' . intval($inv['part_order_id']);
                        $overdue = isInvoiceOverdue($inv['invoice_type'], $inv['payment_status'], $inv['due_date']);
                        ?>
                        <a href="billing.php?q=<?= rawurlencode($ref) ?>" class="sp-todo-item">
                            <span>
                                <strong><?= cpEscape(deskName($inv)) ?></strong>
                                <small>
                                    <?= cpEscape($ref) ?> ·
                                    <?php if ($overdue): ?><?= SERVICE_CREDIT_DAYS ?>-day limit ended <?= date('d M', strtotime($inv['due_date'])) ?>
                                    <?php else: ?>balance <?= cpMoney($inv['balance_due']) ?><?php endif; ?>
                                </small>
                            </span>
                            <?= $overdue ? '<span class="st bad solid">Overdue</span>' : cpBadge($inv['payment_status']) ?>
                        </a>
                    <?php endforeach; ?>
                    <?php foreach ($unbilledJobs as $job): if ($shown++ >= $listLimit) break; ?>
                        <a href="billing.php?q=<?= rawurlencode($job['job_order_number']) ?>" class="sp-todo-item">
                            <span>
                                <strong><?= cpEscape(deskName($job)) ?></strong>
                                <small><?= cpEscape($job['job_order_number']) ?> · no invoice yet</small>
                            </span>
                            <?= cpBadge('Not Paid', 'No invoice') ?>
                        </a>
                    <?php endforeach; ?>
                    <?php if ($payCount === 0): ?><p class="sp-todo-empty">Every invoice is paid.</p><?php endif; ?>
                </div>

                <div class="sp-todo-col is-pend">
                    <div class="sp-todo-head">
                        <h3>New from the website <span class="sp-todo-count"><?= $webCount ?></span></h3>
                        <a href="online_orders.php" class="sp-arrow-link">Web orders ↗</a>
                    </div>
                    <p class="sp-todo-note">Approve or reject each request.</p>
                    <?php $shown = 0; ?>
                    <?php foreach ($pendingOrders as $order): if ($shown++ >= $listLimit) break; ?>
                        <a href="online_orders.php?q=<?= rawurlencode('PO-' . $order['order_id']) ?>" class="sp-todo-item">
                            <span>
                                <strong>PO-<?= intval($order['order_id']) ?> · <?= cpEscape(deskName($order)) ?></strong>
                                <small>
                                    <?= cpEscape($order['items'] ?: 'Parts order') ?>
                                    <?php if (stripos((string) $order['payment_method'], 'gcash') !== false): ?> · <?= cpEscape($order['payment_method']) ?>
                                    <?php elseif (!empty($order['preferred_pickup_date'])): ?> · pickup <?= date('d M', strtotime($order['preferred_pickup_date'])) ?><?php endif; ?>
                                </small>
                            </span>
                            <?= cpBadge('Pending') ?>
                        </a>
                    <?php endforeach; ?>
                    <?php foreach ($pendingRequests as $request): if ($shown++ >= $listLimit) break; ?>
                        <a href="service_requests.php?q=<?= rawurlencode($request['request_number']) ?>" class="sp-todo-item">
                            <span>
                                <strong><?= cpEscape($request['request_number']) ?> · <?= cpEscape(deskName($request)) ?></strong>
                                <small><?= cpEscape($request['service_name'] ?: 'Service request') ?><?= $request['preferred_appointment_date'] ? ' · prefers ' . date('d M', strtotime($request['preferred_appointment_date'])) : '' ?></small>
                            </span>
                            <?= cpBadge('Pending') ?>
                        </a>
                    <?php endforeach; ?>
                    <?php if ($webCount === 0): ?><p class="sp-todo-empty">No new orders or requests.</p><?php endif; ?>
                </div>
            </div>
        </section>

        <!-- 03 Your sales today -->
        <section class="sp-sec" aria-label="Your sales today">
            <?php spSectionHead('03', 'Your sales today', '<a href="invoice_history.php" class="sp-arrow-link">Invoice history ↗</a>'); ?>
            <table class="cp-table sp-table">
                <thead>
                    <tr>
                        <th scope="col">Ref</th>
                        <th scope="col">Customer</th>
                        <th scope="col">Items</th>
                        <th scope="col">Method</th>
                        <th scope="col">Status</th>
                        <th scope="col">Amount</th>
                        <th scope="col"><span class="visually-hidden">Receipt</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($salesToday as $sale): ?>
                        <?php
                        $receiptNo = 'POS-' . str_pad($sale['pos_id'], 5, '0', STR_PAD_LEFT);
                        $customer = !empty($sale['customer_id']) ? deskName($sale) : ($sale['walkin_customer_name'] ?: 'Walk-in');
                        ?>
                        <tr>
                            <td data-label="Ref">
                                <div>
                                    <span class="sp-ref sp-mono"><?= cpEscape($receiptNo) ?></span>
                                    <span class="cp-cell-sub"><?= date('g:i A', strtotime($sale['transaction_date'])) ?></span>
                                </div>
                            </td>
                            <td data-label="Customer"><?= cpEscape($customer) ?></td>
                            <td data-label="Items"><?= cpEscape(implode(', ', $saleItems[$sale['pos_id']] ?? []) ?: '—') ?></td>
                            <td data-label="Method"><?= cpEscape($sale['payment_method']) ?><?= !empty($sale['reference_number']) ? ' · ref ' . cpEscape($sale['reference_number']) : '' ?></td>
                            <td data-label="Status"><?= cpBadge($sale['status']) ?></td>
                            <td data-label="Amount" class="sp-mono"><?= cpMoney($sale['total_amount']) ?></td>
                            <td class="cp-num">
                                <a href="print_pos_receipt.php?pos_id=<?= intval($sale['pos_id']) ?>" target="_blank" class="cp-btn cp-btn-line cp-btn-sm"
                                   data-sp-doc data-sp-doc-kind="Sales receipt" data-sp-doc-ref="<?= cpEscape($receiptNo) ?>">Receipt</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($salesToday)): ?>
                        <tr class="sp-empty-row"><td colspan="7">No sales yet today. <a href="pos.php" class="sp-inv-link">Open the POS</a> to start one.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php if (!empty($salesToday)): ?>
                <p class="sp-week-foot"><span><?= count($salesToday) ?> sale<?= count($salesToday) === 1 ? '' : 's' ?> today</span><span class="sp-mono">Completed total <?= cpMoney($salesTotal) ?></span></p>
            <?php endif; ?>
        </section>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
