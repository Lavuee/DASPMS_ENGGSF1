<?php
require_once __DIR__ . '/../includes/auth.php';

requirePermission('dashboard.owner');

require_once '../config/Database.php';
require_once '../includes/business_rules.php';
require_once '../includes/staff_ui.php';

$db = (new Database())->getConnection();

// Release unpaid web-order reservations older than RESERVATION_HOLD_DAYS (SRS)
require_once '../models/PartOrder.php';
$partOrderModel = new PartOrder($db);
$partOrderModel->expireStaleReservations();
$expiringOrders = $partOrderModel->listExpiringSoon(24);

$activeJobs = $db->query("
    SELECT COUNT(*)
    FROM job_order
    WHERE status NOT IN ('Completed', 'Cancelled')
")->fetchColumn() ?: 0;

$completedToday = $db->query("
    SELECT COUNT(*)
    FROM job_order
    WHERE status = 'Completed'
    AND DATE(date_completed) = CURDATE()
")->fetchColumn() ?: 0;

$revPay = $db->query("SELECT SUM(amount) FROM payment")->fetchColumn() ?: 0;
$revPOS = $db->query("SELECT SUM(total_amount) FROM pos_transaction WHERE status = 'Completed'")->fetchColumn() ?: 0;
// Online part orders are paid through invoices, so they are already included in $revPay.
$totalRevenue = $revPay + $revPOS;

$pendingQuery = "
    SELECT SUM(GREATEST(
        CASE
            WHEN COALESCE(jo.final_cost, 0) > 0 THEN jo.final_cost
            ELSE COALESCE(jo.estimated_cost, 0) + COALESCE(parts.parts_total, 0)
        END - COALESCE(i.amount_paid, 0),
        0
    ))
    FROM job_order jo
    LEFT JOIN (
        SELECT job_order_id, SUM(subtotal) AS parts_total
        FROM job_order_part
        GROUP BY job_order_id
    ) parts ON jo.job_order_id = parts.job_order_id
    LEFT JOIN invoice i ON jo.job_order_id = i.job_order_id
    WHERE jo.status IN ('Completed', 'Ready for Pickup')
    AND (i.payment_status IS NULL OR i.payment_status != 'Paid')
";
$pendingPayments = $db->query($pendingQuery)->fetchColumn() ?: 0;

/* Job Status Overview */
$pendingJobs = $db->query("
    SELECT COUNT(*)
    FROM job_order
    WHERE status = 'Pending'
")->fetchColumn() ?: 0;

$inProgressJobs = $db->query("
    SELECT COUNT(*)
    FROM job_order
    WHERE status = 'In Progress'
")->fetchColumn() ?: 0;

$readyForPickupJobs = $db->query("
    SELECT COUNT(*)
    FROM job_order
    WHERE status = 'Ready for Pickup'
")->fetchColumn() ?: 0;

$cancelledJobs = $db->query("
    SELECT COUNT(*)
    FROM job_order
    WHERE status = 'Cancelled'
")->fetchColumn() ?: 0;

$cancelledToday = $db->query("
    SELECT COUNT(*)
    FROM job_order
    WHERE status = 'Cancelled'
    AND DATE(cancelled_at) = CURDATE()
")->fetchColumn() ?: 0;

$lastCompletedAt = $db->query("
    SELECT MAX(date_completed)
    FROM job_order
    WHERE status = 'Completed'
    AND DATE(date_completed) = CURDATE()
")->fetchColumn();

/* Collected today, split by payment method (invoice payments + completed POS sales) */
$collectedByMethod = [];
$stmtCollected = $db->query("
    SELECT method, SUM(amount) AS total
    FROM (
        SELECT payment_method AS method, amount
        FROM payment
        WHERE DATE(payment_date) = CURDATE()
        UNION ALL
        SELECT payment_method AS method, total_amount AS amount
        FROM pos_transaction
        WHERE status = 'Completed'
        AND DATE(transaction_date) = CURDATE()
    ) collected
    GROUP BY method
");
foreach ($stmtCollected->fetchAll(PDO::FETCH_ASSOC) as $row) {
    // 'Bank' and 'Bank Transfer' are the same method stored two ways.
    $method = $row['method'] === 'Bank' ? 'Bank Transfer' : $row['method'];
    $collectedByMethod[$method] = ($collectedByMethod[$method] ?? 0) + floatval($row['total']);
}
$collectedToday = array_sum($collectedByMethod);
$methodOrder = ['Cash', 'GCash', 'Bank Transfer', 'Cheque'];
$methodTone = ['Cash' => 'cash', 'GCash' => 'gcash', 'Bank Transfer' => 'bank', 'Cheque' => 'cheque'];

/* Needs your attention: overdue repair bills, low stock, and requests waiting for approval */
$overdueBills = $db->query("
    SELECT i.invoice_number, i.balance_due, i.due_date, jo.job_order_number, c.first_name, c.last_name
    FROM invoice i
    LEFT JOIN job_order jo ON jo.job_order_id = i.job_order_id
    LEFT JOIN customer c ON c.customer_id = i.customer_id
    WHERE i.invoice_type = 'Service'
    AND i.payment_status <> 'Paid'
    AND i.is_voided = 0
    AND i.due_date IS NOT NULL
    AND i.due_date < CURDATE()
    ORDER BY i.due_date ASC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
$overdueCount = countOverdueServiceInvoices($db);

/* Low Stock Alerts */
$stmtStock = $db->query("
    SELECT *
    FROM part
    WHERE quantity_on_hand <= low_stock_threshold
    ORDER BY quantity_on_hand ASC
    LIMIT 5
");
$lowStockParts = $stmtStock->fetchAll(PDO::FETCH_ASSOC);

$pendingWebOrders = $db->query("
    SELECT po.order_id, po.preferred_pickup_date, c.first_name, c.last_name,
           GROUP_CONCAT(CONCAT(p.part_name, ' × ', poi.quantity) ORDER BY poi.order_item_id SEPARATOR ', ') AS items
    FROM part_order po
    LEFT JOIN customer c ON c.customer_id = po.customer_id
    LEFT JOIN part_order_item poi ON poi.order_id = po.order_id
    LEFT JOIN part p ON p.part_id = poi.part_id
    WHERE po.status = 'Pending'
    GROUP BY po.order_id
    ORDER BY po.order_date ASC
    LIMIT 3
")->fetchAll(PDO::FETCH_ASSOC);

$pendingRequests = $db->query("
    SELECT sr.request_number, sr.preferred_appointment_date, c.first_name, c.last_name, s.service_name
    FROM service_request sr
    LEFT JOIN customer c ON c.customer_id = sr.customer_id
    LEFT JOIN service s ON s.service_id = sr.service_id
    WHERE sr.status = 'Pending'
    ORDER BY sr.created_at ASC
    LIMIT 3
")->fetchAll(PDO::FETCH_ASSOC);

$attentionCount = count($overdueBills) + count($lowStockParts) + count($pendingWebOrders) + count($pendingRequests) + count($expiringOrders);

/* Recent / Active Job Orders, with the invoice (if any) for the payment columns */
$stmtJobs = $db->query("
    SELECT jo.*, c.first_name, c.last_name, v.plate_number, v.make, v.model,
           i.payment_status AS invoice_status, i.balance_due AS invoice_balance,
           COALESCE(parts.parts_total, 0) AS parts_total
    FROM job_order jo
    JOIN customer c ON jo.customer_id = c.customer_id
    JOIN vehicle v ON jo.vehicle_id = v.vehicle_id
    LEFT JOIN invoice i ON i.job_order_id = jo.job_order_id AND i.is_voided = 0
    LEFT JOIN (
        SELECT job_order_id, SUM(subtotal) AS parts_total
        FROM job_order_part
        GROUP BY job_order_id
    ) parts ON parts.job_order_id = jo.job_order_id
    WHERE jo.status != 'Completed'
    ORDER BY jo.date_created DESC
    LIMIT 5
");
$recentJobs = $stmtJobs->fetchAll(PDO::FETCH_ASSOC);

/* Collected this week: the last 7 days, today last */
$weekDays = [];
for ($i = 6; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i days"));
    $weekDays[$day] = ['total' => 0, 'service' => 0, 'parts' => 0];
}
$weekStart = array_key_first($weekDays);

$stmtWeek = $db->prepare("
    SELECT DATE(p.payment_date) AS day, i.invoice_type, SUM(p.amount) AS total
    FROM payment p
    JOIN invoice i ON i.invoice_id = p.invoice_id
    WHERE DATE(p.payment_date) >= ?
    GROUP BY DATE(p.payment_date), i.invoice_type
");
$stmtWeek->execute([$weekStart]);
foreach ($stmtWeek->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if (!isset($weekDays[$row['day']])) continue;
    $key = $row['invoice_type'] === 'Service' ? 'service' : 'parts';
    $weekDays[$row['day']][$key] += floatval($row['total']);
    $weekDays[$row['day']]['total'] += floatval($row['total']);
}

$stmtWeekPos = $db->prepare("
    SELECT DATE(transaction_date) AS day, SUM(total_amount) AS total
    FROM pos_transaction
    WHERE status = 'Completed'
    AND DATE(transaction_date) >= ?
    GROUP BY DATE(transaction_date)
");
$stmtWeekPos->execute([$weekStart]);
foreach ($stmtWeekPos->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if (!isset($weekDays[$row['day']])) continue;
    $weekDays[$row['day']]['parts'] += floatval($row['total']);
    $weekDays[$row['day']]['total'] += floatval($row['total']);
}

$weekTotal = array_sum(array_column($weekDays, 'total'));
$weekService = array_sum(array_column($weekDays, 'service'));
$weekMax = max(array_column($weekDays, 'total')) ?: 1;
$servicePercent = $weekTotal > 0 ? round($weekService / $weekTotal * 100) : 0;

$adminName = $_SESSION['first_name'] ?? 'Admin';
$hour = intval(date('H'));
$greeting = $hour < 12 ? 'Morning' : ($hour < 18 ? 'Afternoon' : 'Evening');

// Payment column for a job: the invoice status once billed, otherwise not billed yet.
function dashJobPayment(array $job) {
    if (!empty($job['invoice_status'])) {
        return cpBadge($job['invoice_status']);
    }

    return '<span class="st done">Not billed yet</span>';
}

// Balance for a job: the invoice balance once billed, otherwise the current estimate.
function dashJobBalance(array $job) {
    if ($job['invoice_balance'] !== null) {
        return floatval($job['invoice_balance']);
    }

    if (floatval($job['final_cost'] ?? 0) > 0) {
        return floatval($job['final_cost']);
    }

    return floatval($job['estimated_cost'] ?? 0) + floatval($job['parts_total'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Owner Dashboard - Norily's Repair Shop</title>
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
            'Good ' . $greeting . ', ' . $adminName,
            "Here's the shop today. Start with what needs your attention.",
            '<a href="job_orders.php?new=1" class="cp-btn cp-btn-dark">+ New job order</a>'
            . '<a href="reports.php" class="cp-btn cp-btn-line">View reports</a>'
        );
        ?>

        <!-- 01 Today at a glance -->
        <section class="sp-sec" aria-labelledby="glanceTitle">
            <?php spSectionHead('01', 'Today at a glance'); ?>

            <div class="sp-glance">
                <div class="sp-glance-cell">
                    <p class="cp-label">Collected today</p>
                    <strong class="sp-figure"><?= spPeso($collectedToday) ?></strong>
                    <?php if ($collectedToday > 0): ?>
                        <div class="sp-split" aria-hidden="true">
                            <?php foreach ($methodOrder as $method): ?>
                                <?php if (!empty($collectedByMethod[$method])): ?>
                                    <span class="sp-split-<?= $methodTone[$method] ?>" style="width: <?= round($collectedByMethod[$method] / $collectedToday * 100, 2) ?>%"></span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <p class="sp-glance-note">
                            <?php
                            $parts = [];
                            foreach ($methodOrder as $method) {
                                if (!empty($collectedByMethod[$method])) {
                                    $parts[] = '<span class="sp-key sp-key-' . $methodTone[$method] . '"></span>' . cpEscape($method) . ' ' . number_format($collectedByMethod[$method], 0);
                                }
                            }
                            echo implode(' · ', $parts);
                            ?>
                        </p>
                    <?php else: ?>
                        <div class="sp-split is-empty" aria-hidden="true"></div>
                        <p class="sp-glance-note">No payments recorded yet today</p>
                    <?php endif; ?>
                </div>

                <div class="sp-glance-cell">
                    <p class="cp-label">Active jobs</p>
                    <strong class="sp-figure"><?= str_pad((string) intval($activeJobs), 2, '0', STR_PAD_LEFT) ?></strong>
                    <p class="sp-glance-note"><?= intval($readyForPickupJobs) ?> ready for pickup</p>
                </div>

                <div class="sp-glance-cell">
                    <p class="cp-label">Completed today</p>
                    <strong class="sp-figure"><?= str_pad((string) intval($completedToday), 2, '0', STR_PAD_LEFT) ?></strong>
                    <p class="sp-glance-note">
                        <?= $lastCompletedAt ? 'Last at ' . date('g:i A', strtotime($lastCompletedAt)) : 'None finished yet today' ?>
                    </p>
                </div>

                <div class="sp-glance-cell">
                    <p class="cp-label">Unpaid balance</p>
                    <strong class="sp-figure"><?= spPeso($pendingPayments) ?></strong>
                    <?php if ($overdueCount > 0): ?>
                        <p class="sp-glance-note is-bad"><?= $overdueCount ?> past the <?= SERVICE_CREDIT_DAYS ?>-day limit</p>
                    <?php else: ?>
                        <p class="sp-glance-note">Finished jobs not yet fully paid</p>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <div class="sp-two">
            <!-- 02 Needs your attention -->
            <section class="sp-sec" aria-label="Needs your attention">
                <?php spSectionHead('02', 'Needs your attention', '<span class="sp-sec-meta">' . $attentionCount . ' item' . ($attentionCount === 1 ? '' : 's') . '</span>'); ?>

                <?php if ($attentionCount === 0): ?>
                    <div class="cp-empty">
                        <strong>All clear</strong>
                        No overdue bills, low stock or waiting requests right now.
                    </div>
                <?php endif; ?>

                <?php if (!empty($overdueBills)): ?>
                    <div class="sp-att-group">
                        <p class="cp-label">Overdue balances <span class="sp-att-count"><?= $overdueCount ?></span></p>
                        <?php foreach ($overdueBills as $bill): ?>
                            <a href="billing.php?q=<?= rawurlencode($bill['job_order_number'] ?: $bill['invoice_number']) ?>" class="sp-att-item">
                                <span>
                                    <strong><?= cpEscape(trim($bill['first_name'] . ' ' . $bill['last_name'])) ?> · <?= cpMoney($bill['balance_due']) ?></strong>
                                    <small><?= cpEscape($bill['job_order_number'] ?: $bill['invoice_number']) ?> · was due <?= date('d M', strtotime($bill['due_date'])) ?></small>
                                </span>
                                <span class="st bad solid">Overdue</span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($lowStockParts)): ?>
                    <div class="sp-att-group">
                        <p class="cp-label">Low stock <span class="sp-att-count"><?= count($lowStockParts) ?></span></p>
                        <?php foreach ($lowStockParts as $stock): ?>
                            <?php $qty = intval($stock['quantity_on_hand']); ?>
                            <a href="inventory.php?q=<?= rawurlencode($stock['part_name']) ?>" class="sp-att-item">
                                <span>
                                    <strong><?= cpEscape($stock['part_name']) ?></strong>
                                    <small><?= $qty ?> left<?= $qty > 0 ? ' · alert at ' . intval($stock['low_stock_threshold']) : '' ?><?= cpHasValue($stock['category']) ? ' · ' . cpEscape($stock['category']) : '' ?></small>
                                </span>
                                <?= $qty <= 0 ? cpBadge('Out of stock') : cpBadge('Low stock') ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($expiringOrders)): ?>
                    <div class="sp-att-group">
                        <p class="cp-label">Reservations expiring <span class="sp-att-count"><?= count($expiringOrders) ?></span></p>
                        <?php foreach ($expiringOrders as $order): ?>
                            <a href="online_orders.php?q=<?= rawurlencode('PO-' . $order['order_id']) ?>" class="sp-att-item">
                                <span>
                                    <strong>Web order PO-<?= intval($order['order_id']) ?> · <?= cpEscape(trim($order['first_name'] . ' ' . $order['last_name'])) ?></strong>
                                    <small>Unpaid · <?= cpMoney($order['total_amount']) ?> · parts go back on sale <?= date('d M, g:i A', strtotime($order['expires_at'])) ?></small>
                                </span>
                                <span class="st pend">Expires soon</span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($pendingWebOrders) || !empty($pendingRequests)): ?>
                    <div class="sp-att-group">
                        <p class="cp-label">Waiting for approval <span class="sp-att-count"><?= count($pendingWebOrders) + count($pendingRequests) ?></span></p>
                        <?php foreach ($pendingWebOrders as $order): ?>
                            <a href="online_orders.php?q=<?= rawurlencode('#' . $order['order_id']) ?>" class="sp-att-item">
                                <span>
                                    <strong>Web order #<?= intval($order['order_id']) ?> · <?= cpEscape(trim($order['first_name'] . ' ' . $order['last_name'])) ?></strong>
                                    <small><?= cpEscape($order['items'] ?: 'Parts order') ?><?= $order['preferred_pickup_date'] ? ' · pickup ' . date('d M', strtotime($order['preferred_pickup_date'])) : '' ?></small>
                                </span>
                                <?= cpBadge('Pending') ?>
                            </a>
                        <?php endforeach; ?>
                        <?php foreach ($pendingRequests as $request): ?>
                            <a href="service_requests.php?q=<?= rawurlencode($request['request_number']) ?>" class="sp-att-item">
                                <span>
                                    <strong>Service request <?= cpEscape($request['request_number']) ?> · <?= cpEscape(trim($request['first_name'] . ' ' . $request['last_name'])) ?></strong>
                                    <small><?= cpEscape($request['service_name'] ?: 'Service') ?><?= $request['preferred_appointment_date'] ? ' · prefers ' . date('d M', strtotime($request['preferred_appointment_date'])) : '' ?></small>
                                </span>
                                <?= cpBadge('Pending') ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <!-- 03 Workshop right now -->
            <section class="sp-sec" aria-label="Workshop right now">
                <?php spSectionHead('03', 'Workshop right now', '<a href="job_orders.php" class="sp-arrow-link">Job orders ↗</a>'); ?>

                <div class="sp-ws">
                    <a href="job_orders.php" class="sp-ws-row is-pend">
                        <span><strong>Pending</strong><small>Waiting to be started</small></span>
                        <b><?= intval($pendingJobs) ?></b>
                    </a>
                    <a href="job_orders.php" class="sp-ws-row is-prog">
                        <span><strong>In progress</strong><small>Being repaired now</small></span>
                        <b><?= intval($inProgressJobs) ?></b>
                    </a>
                    <a href="job_orders.php" class="sp-ws-row is-ready">
                        <span><strong>Ready for pickup</strong><small>Customer can claim the vehicle</small></span>
                        <b><?= intval($readyForPickupJobs) ?></b>
                    </a>
                    <a href="job_orders.php" class="sp-ws-row is-done">
                        <span><strong>Completed today</strong><small>Released to the customer</small></span>
                        <b><?= intval($completedToday) ?></b>
                    </a>
                    <a href="job_orders.php" class="sp-ws-row is-bad">
                        <span><strong>Cancelled today</strong><small><?= intval($cancelledJobs) ?> cancelled in total, each with a reason</small></span>
                        <b><?= intval($cancelledToday) ?></b>
                    </a>
                </div>
            </section>
        </div>

        <!-- 04 Recent job orders -->
        <section class="sp-sec" aria-label="Recent job orders">
            <?php spSectionHead('04', 'Recent job orders', '<a href="job_orders.php" class="sp-arrow-link">All job orders ↗</a>'); ?>

            <table class="cp-table sp-table" id="recentJobsTable">
                <thead>
                    <tr>
                        <th scope="col">Job no.</th>
                        <th scope="col">Customer / vehicle</th>
                        <th scope="col">Plate</th>
                        <th scope="col">Job status</th>
                        <th scope="col">Payment</th>
                        <th scope="col" class="cp-num">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($recentJobs)): ?>
                        <?php foreach ($recentJobs as $job): ?>
                            <tr>
                                <td data-label="Job no." class="sp-mono"><?= cpEscape($job['job_order_number']) ?></td>
                                <td data-label="Customer"><div>
                                    <span class="cp-cell-title"><?= cpEscape($job['first_name'] . ' ' . $job['last_name']) ?></span>
                                    <span class="cp-cell-sub">
                                        <?= cpEscape(trim(($job['make'] ?? '') . ' ' . ($job['model'] ?? ''))) ?>
                                        <?php if (cpHasValue($job['description'] ?? '')): ?>
                                            · <?= cpEscape(mb_strimwidth(plainText($job['description']), 0, 48, '…')) ?>
                                        <?php endif; ?>
                                    </span>
                                </div></td>
                                <td data-label="Plate"><span class="sp-plate"><?= cpEscape($job['plate_number'] ?: '—') ?></span></td>
                                <td data-label="Job status"><?= cpBadge($job['status']) ?></td>
                                <td data-label="Payment"><?= dashJobPayment($job) ?></td>
                                <td data-label="Balance" class="cp-num sp-mono"><?= number_format(dashJobBalance($job), 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr class="cp-table-empty">
                            <td colspan="6">No active job orders right now.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>

        <!-- 05 Collected this week -->
        <section class="sp-sec" aria-label="Collected this week">
            <?php spSectionHead('05', 'Collected this week', '<a href="reports.php" class="sp-arrow-link">Sales report ↗</a>'); ?>

            <div class="sp-week" role="img" aria-label="Amount collected per day for the last 7 days, <?= cpMoney($weekTotal) ?> in total">
                <?php foreach ($weekDays as $day => $data): ?>
                    <?php
                    $isToday = $day === date('Y-m-d');
                    $isSunday = date('w', strtotime($day)) === '0';
                    $height = $data['total'] > 0 ? max(4, round($data['total'] / $weekMax * 100)) : 0;
                    ?>
                    <div class="sp-week-day<?= $isSunday ? ' is-closed' : '' ?>">
                        <span class="sp-week-amount"><?= $data['total'] > 0 ? spPeso($data['total']) : '' ?></span>
                        <span class="sp-week-bar" style="height: <?= $height ?>%"></span>
                        <span class="sp-week-label"><?= $isToday ? 'Today' : strtoupper(date('D', strtotime($day))) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="sp-week-foot">
                <span>Sunday closed<?= $weekTotal <= 0 ? ' · No payments recorded in the last 7 days' : '' ?></span>
                <span class="sp-mono">
                    Week <?= cpMoney($weekTotal) ?>
                    <?php if ($weekTotal > 0): ?>
                        · repairs <?= $servicePercent ?>% · parts <?= 100 - $servicePercent ?>%
                    <?php endif; ?>
                    · all-time <?= cpMoney($totalRevenue) ?>
                </span>
            </div>
        </section>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
