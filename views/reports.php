<?php
require_once __DIR__ . '/../includes/auth.php';

requirePermission('reports.view');

require_once '../config/Database.php';
require_once '../includes/staff_ui.php';
require_once '../includes/receipt_ui.php';
$db = (new Database())->getConnection();

$filter = $_GET['filter'] ?? 'week';
$reportType = $_GET['report_type'] ?? 'all';

$allowedFilters = ['today', 'week', 'month', 'custom'];
$allowedReportTypes = ['all', 'sales', 'inventory', 'jobs'];

if (!in_array($filter, $allowedFilters)) {
    $filter = 'week';
}

if (!in_array($reportType, $allowedReportTypes)) {
    $reportType = 'all';
}

$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

$customDateError = '';
$dateFilter = "";
$dateParams = [];
$reportTitle = "This Week's Report";

/* =========================
   DATE FILTER
========================= */
if ($filter === 'today') {
    $dateFilter = "DATE(p.payment_date) = CURDATE()";
    $jobCreatedFilter = "DATE(date_created) = CURDATE()";
    $jobCompletedFilter = "DATE(date_completed) = CURDATE()";
    $reportTitle = "Today's Report";
} elseif ($filter === 'month') {
    $dateFilter = "YEAR(p.payment_date) = YEAR(CURDATE()) AND MONTH(p.payment_date) = MONTH(CURDATE())";
    $jobCreatedFilter = "YEAR(date_created) = YEAR(CURDATE()) AND MONTH(date_created) = MONTH(CURDATE())";
    $jobCompletedFilter = "YEAR(date_completed) = YEAR(CURDATE()) AND MONTH(date_completed) = MONTH(CURDATE())";
    $reportTitle = "This Month's Report";
} elseif ($filter === 'custom') {
    if ($startDate === '' || $endDate === '') {
        $customDateError = "Please select both start date and end date for a custom report.";

        $filter = 'week';
        $dateFilter = "YEARWEEK(p.payment_date, 1) = YEARWEEK(CURDATE(), 1)";
        $jobCreatedFilter = "YEARWEEK(date_created, 1) = YEARWEEK(CURDATE(), 1)";
        $jobCompletedFilter = "YEARWEEK(date_completed, 1) = YEARWEEK(CURDATE(), 1)";
        $reportTitle = "This Week's Report";
    } elseif (strtotime($startDate) > strtotime($endDate)) {
        $customDateError = "Invalid date range. Start date must not be later than end date.";

        $filter = 'week';
        $dateFilter = "YEARWEEK(p.payment_date, 1) = YEARWEEK(CURDATE(), 1)";
        $jobCreatedFilter = "YEARWEEK(date_created, 1) = YEARWEEK(CURDATE(), 1)";
        $jobCompletedFilter = "YEARWEEK(date_completed, 1) = YEARWEEK(CURDATE(), 1)";
        $reportTitle = "This Week's Report";
    } else {
        $dateFilter = "DATE(p.payment_date) BETWEEN ? AND ?";
        $jobCreatedFilter = "DATE(date_created) BETWEEN " . $db->quote($startDate) . " AND " . $db->quote($endDate);
        $jobCompletedFilter = "DATE(date_completed) BETWEEN " . $db->quote($startDate) . " AND " . $db->quote($endDate);
        $dateParams = [$startDate, $endDate];
        $reportTitle = "Custom Report (" . date('M d, Y', strtotime($startDate)) . " - " . date('M d, Y', strtotime($endDate)) . ")";
    }
} else {
    $filter = 'week';
    $dateFilter = "YEARWEEK(p.payment_date, 1) = YEARWEEK(CURDATE(), 1)";
    $jobCreatedFilter = "YEARWEEK(date_created, 1) = YEARWEEK(CURDATE(), 1)";
    $jobCompletedFilter = "YEARWEEK(date_completed, 1) = YEARWEEK(CURDATE(), 1)";
    $reportTitle = "This Week's Report";
}

/* =========================
   REVENUE
========================= */
$query = "
    SELECT i.invoice_type, SUM(p.amount) AS total
    FROM payment p
    JOIN invoice i ON p.invoice_id = i.invoice_id
    WHERE $dateFilter
    GROUP BY i.invoice_type
";

$stmt = $db->prepare($query);
$stmt->execute($dateParams);

$serviceRevenue = 0;
$partsRevenue = 0;

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if ($row['invoice_type'] === 'Service') {
        $serviceRevenue = floatval($row['total']);
    } else {
        $partsRevenue = floatval($row['total']);
    }
}

/* POS walk-in sales are not invoices, so they are added from pos_transaction.
   Same date range (on transaction_date); voided sales (status Cancelled) are left out. */
$posDateFilter = str_replace('p.payment_date', 'pt.transaction_date', $dateFilter);

$stmtPos = $db->prepare("
    SELECT COUNT(*) AS sales, COALESCE(SUM(pt.total_amount), 0) AS total
    FROM pos_transaction pt
    WHERE pt.status = 'Completed' AND $posDateFilter
");
$stmtPos->execute($dateParams);
$posRow = $stmtPos->fetch(PDO::FETCH_ASSOC);
$posRevenue = floatval($posRow['total']);
$posSalesCount = intval($posRow['sales']);

$onlinePartsRevenue = $partsRevenue;      // part invoices (web orders)
$partsRevenue = $onlinePartsRevenue + $posRevenue;

$grandTotal = $serviceRevenue + $partsRevenue;

/* =========================
   PAYMENT METHODS
========================= */
$queryMethod = "
    SELECT payment_method, SUM(amount) AS total
    FROM payment p
    WHERE $dateFilter
    GROUP BY payment_method
";

$stmtMethod = $db->prepare($queryMethod);
$stmtMethod->execute($dateParams);

$methods = [];

while ($row = $stmtMethod->fetch(PDO::FETCH_ASSOC)) {
    $methods[$row['payment_method']] = floatval($row['total']);
}

$stmtPosMethod = $db->prepare("
    SELECT pt.payment_method, SUM(pt.total_amount) AS total
    FROM pos_transaction pt
    WHERE pt.status = 'Completed' AND $posDateFilter
    GROUP BY pt.payment_method
");
$stmtPosMethod->execute($dateParams);

while ($row = $stmtPosMethod->fetch(PDO::FETCH_ASSOC)) {
    $methods[$row['payment_method']] = ($methods[$row['payment_method']] ?? 0) + floatval($row['total']);
}

/* =========================
   JOB STATUS SUMMARY
========================= */
$pendingJobs = $db->query("
    SELECT COUNT(*)
    FROM job_order
    WHERE status = 'Pending'
      AND $jobCreatedFilter
")->fetchColumn();

$completedJobs = $db->query("
    SELECT COUNT(*)
    FROM job_order
    WHERE status = 'Completed'
      AND $jobCompletedFilter
")->fetchColumn();

$cancelledJobs = $db->query("
    SELECT COUNT(*)
    FROM job_order
    WHERE status = 'Cancelled'
      AND $jobCreatedFilter
")->fetchColumn();

$jobsCompleted = $completedJobs;

/* =========================
   LOW STOCK
========================= */
$lowStock = $db->query("
    SELECT part_name, quantity_on_hand, low_stock_threshold
    FROM part
    WHERE is_active = 1
      AND quantity_on_hand <= low_stock_threshold
    ORDER BY quantity_on_hand ASC
")->fetchAll(PDO::FETCH_ASSOC);

function reportUrl($filterValue, $reportTypeValue, $startDate = '', $endDate = '') {
    $params = [
        'filter' => $filterValue,
        'report_type' => $reportTypeValue
    ];

    if ($filterValue === 'custom' && $startDate !== '' && $endDate !== '') {
        $params['start_date'] = $startDate;
        $params['end_date'] = $endDate;
    }

    return '?' . http_build_query($params);
}

$showSales = ($reportType === 'all' || $reportType === 'sales');
$showInventory = ($reportType === 'all' || $reportType === 'inventory');
$showJobs = ($reportType === 'all' || $reportType === 'jobs');

$pdfFilename = 'DASPMS-' . preg_replace("/[^A-Za-z0-9_-]/", "-", $reportTitle) . '.pdf';

/* The dates the report covers, so the date fields always show the range in use */
if ($filter === 'today') {
    $rangeStart = $rangeEnd = date('Y-m-d');
} elseif ($filter === 'month') {
    $rangeStart = date('Y-m-01');
    $rangeEnd = date('Y-m-t');
} elseif ($filter === 'custom') {
    $rangeStart = $startDate;
    $rangeEnd = $endDate;
} else {
    $rangeStart = date('Y-m-d', strtotime('monday this week'));
    $rangeEnd = date('Y-m-d', strtotime('sunday this week'));
}
$rangeLabel = date('d M', strtotime($rangeStart)) . ' – ' . date('d M Y', strtotime($rangeEnd));
if ($rangeStart === $rangeEnd) {
    $rangeLabel = date('d M Y', strtotime($rangeStart));
}

/* Extra read-only figures for the layout */
$stmtCount = $db->prepare("SELECT COUNT(*) FROM payment p WHERE $dateFilter");
$stmtCount->execute($dateParams);
$paymentCount = intval($stmtCount->fetchColumn());
$transactionCount = $paymentCount + $posSalesCount;

$inProgressJobs = $db->query("SELECT COUNT(*) FROM job_order WHERE status = 'In Progress' AND $jobCreatedFilter")->fetchColumn();
$readyJobs = $db->query("SELECT COUNT(*) FROM job_order WHERE status = 'Ready for Pickup' AND $jobCreatedFilter")->fetchColumn();

$stockList = $db->query("
    SELECT part_name, quantity_on_hand, low_stock_threshold
    FROM part
    WHERE is_active = 1
    ORDER BY (quantity_on_hand <= low_stock_threshold) DESC, quantity_on_hand ASC, part_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// 'Bank' and 'Bank Transfer' are the same method stored two ways
$methodTotals = ['Cash' => 0, 'GCash' => 0, 'Bank Transfer' => 0, 'Cheque' => 0];
foreach ($methods as $name => $total) {
    $key = $name === 'Bank' ? 'Bank Transfer' : $name;
    $methodTotals[$key] = ($methodTotals[$key] ?? 0) + $total;
}
$methodMax = max($methodTotals) ?: 1;
$revenueRows = [
    'Services' => $serviceRevenue,
    'Web orders' => $onlinePartsRevenue,
    'POS sales' => $posRevenue,
];
$revenueMax = max($revenueRows) ?: 1;
$methodTone = ['Cash' => 'cash', 'GCash' => 'gcash', 'Bank Transfer' => 'bank', 'Cheque' => 'cheque'];

$reportTypes = [
    'all' => 'All reports',
    'sales' => 'Sales and revenue',
    'inventory' => 'Inventory',
    'jobs' => 'Job status',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Reports - Norily's Repair Shop</title>
<link rel="icon" type="image/png" href="../assets/images/logo.png">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
<link rel="stylesheet" href="../assets/css/receipt.css?v=<?= filemtime(__DIR__ . '/../assets/css/receipt.css') ?>">
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<style>
    /* The receipt copy is only for Print and Download PDF */
    .report-receipt { display: none; width: 440px; max-width: 100%; }

    @media print {
        body { background: #fff !important; }
        .app-wrapper, .cp-sheet, .cp-skip { display: none !important; }
        .report-receipt { display: block !important; width: 110mm; margin: 0 auto; }
        .report-receipt .rc-receipt { padding: 0; background: #fff; }
        .report-receipt .rc-tear { display: none; }
    }
</style>
</head>
<body>

<div class="app-wrapper">
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content sp-page" id="spMain">

        <div class="sp-no-print">
            <?php
            spPageHead(
                spPortalLabel() . ' · Owner',
                'Reports',
                'Pick a report and a date range, then print or download it.'
            );
            ?>

            <?php if ($customDateError !== ''): ?>
                <div class="cp-flash cp-flash-bad" role="alert">
                    <span><?= cpEscape($customDateError) ?></span>
                    <button type="button" class="cp-flash-close" aria-label="Dismiss" onclick="this.parentElement.remove()">&times;</button>
                </div>
            <?php endif; ?>

            <form method="GET" class="sp-toolbar sp-report-bar">
                <input type="hidden" name="filter" value="custom">
                <select name="report_type" class="cp-input" aria-label="Report">
                    <?php foreach ($reportTypes as $value => $label): ?>
                        <option value="<?= $value ?>"<?= $reportType === $value ? ' selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="date" name="start_date" class="cp-input" value="<?= cpEscape($rangeStart) ?>" aria-label="Start date" required>
                <input type="date" name="end_date" class="cp-input" value="<?= cpEscape($rangeEnd) ?>" aria-label="End date" required>
                <button type="submit" class="cp-btn cp-btn-dark">Apply</button>
                <div class="sp-report-actions">
                    <button type="button" class="cp-btn cp-btn-line" onclick="window.print()">Print</button>
                    <button type="button" class="cp-btn cp-btn-line" id="downloadPdfBtn">Download PDF</button>
                </div>
            </form>

            <div class="sp-chips" role="group" aria-label="Quick ranges">
                <?php foreach (['today' => 'Today', 'week' => 'This week', 'month' => 'This month'] as $value => $label): ?>
                    <a href="<?= reportUrl($value, $reportType) ?>" class="sp-chip" aria-pressed="<?= $filter === $value ? 'true' : 'false' ?>"><?= $label ?></a>
                <?php endforeach; ?>
            </div>
        </div>

        <div id="reportContent">

            <?php $sectionNo = 0; ?>

            <?php if ($showSales): ?>
                <section class="sp-sec sp-report-sec" aria-label="Sales and revenue">
                    <?php spSectionHead(sprintf('%02d', ++$sectionNo), 'Sales and revenue', '<span class="sp-sec-meta sp-sec-range">' . cpEscape($rangeLabel) . '</span>'); ?>

                    <div class="sp-glance">
                        <div class="sp-glance-cell">
                            <p class="cp-label">Total revenue</p>
                            <strong class="sp-figure"><?= spPeso($grandTotal) ?></strong>
                        </div>
                        <div class="sp-glance-cell">
                            <p class="cp-label">Service revenue</p>
                            <strong class="sp-figure"><?= spPeso($serviceRevenue) ?></strong>
                        </div>
                        <div class="sp-glance-cell">
                            <p class="cp-label">Parts revenue</p>
                            <strong class="sp-figure"><?= spPeso($partsRevenue) ?></strong>
                            <p class="sp-glance-note">Incl. <?= spPeso($posRevenue) ?> from POS walk-in sales</p>
                        </div>
                        <div class="sp-glance-cell">
                            <p class="cp-label">Transactions</p>
                            <strong class="sp-figure"><?= $transactionCount ?></strong>
                            <p class="sp-glance-note"><?= $paymentCount ?> invoice payment<?= $paymentCount === 1 ? '' : 's' ?> · <?= $posSalesCount ?> POS sale<?= $posSalesCount === 1 ? '' : 's' ?> · <?= intval($jobsCompleted) ?> job<?= intval($jobsCompleted) === 1 ? '' : 's' ?> completed</p>
                        </div>
                    </div>

                    <div class="sp-report-bars">
                        <div>
                            <p class="cp-label">Revenue breakdown</p>
                            <?php foreach ($revenueRows as $label => $amount): ?>
                                <div class="sp-hbar">
                                    <span><?= $label ?></span>
                                    <span class="sp-hbar-track"><span class="sp-hbar-fill" style="width: <?= round($amount / $revenueMax * 100, 2) ?>%"></span></span>
                                    <span class="sp-mono"><?= spPeso($amount) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div>
                            <p class="cp-label">Payment methods</p>
                            <?php foreach ($methodTotals as $label => $amount): ?>
                                <div class="sp-hbar">
                                    <span><?= $label ?></span>
                                    <span class="sp-hbar-track"><span class="sp-hbar-fill sp-split-<?= $methodTone[$label] ?? 'cash' ?>" style="width: <?= round($amount / $methodMax * 100, 2) ?>%"></span></span>
                                    <span class="sp-mono"><?= spPeso($amount) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($showJobs): ?>
                <section class="sp-sec sp-report-sec" aria-label="Job status">
                    <?php spSectionHead(sprintf('%02d', ++$sectionNo), 'Job status', '<span class="sp-sec-meta sp-sec-range">' . cpEscape($rangeLabel) . '</span>'); ?>
                    <div class="sp-ws sp-ws-row-5">
                        <div class="sp-ws-row is-pend"><span><strong>Pending</strong></span><b><?= intval($pendingJobs) ?></b></div>
                        <div class="sp-ws-row is-prog"><span><strong>In progress</strong></span><b><?= intval($inProgressJobs) ?></b></div>
                        <div class="sp-ws-row is-ready"><span><strong>Ready for pickup</strong></span><b><?= intval($readyJobs) ?></b></div>
                        <div class="sp-ws-row is-done"><span><strong>Completed</strong></span><b><?= intval($completedJobs) ?></b></div>
                        <div class="sp-ws-row is-bad"><span><strong>Cancelled</strong></span><b><?= intval($cancelledJobs) ?></b></div>
                    </div>
                    <p class="cp-hint" style="margin-top: 12px">Completed counts jobs finished in this range; the others count jobs created in this range.</p>
                </section>
            <?php endif; ?>

            <?php if ($showInventory): ?>
                <section class="sp-sec sp-report-sec" aria-label="Inventory">
                    <?php spSectionHead(sprintf('%02d', ++$sectionNo), 'Inventory', '<a href="inventory.php" class="sp-arrow-link sp-no-print">Inventory ↗</a>'); ?>
                    <table class="cp-table sp-table">
                        <thead>
                            <tr>
                                <th scope="col">Part</th>
                                <th scope="col">Current stock</th>
                                <th scope="col">Low stock alert</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stockList as $item): ?>
                                <?php $qty = intval($item['quantity_on_hand']); $threshold = intval($item['low_stock_threshold']); ?>
                                <tr>
                                    <td data-label="Part"><span class="sp-ref sp-wrap"><?= cpEscape($item['part_name']) ?></span></td>
                                    <td data-label="Current stock" class="sp-mono"><?= $qty ?></td>
                                    <td data-label="Low stock alert" class="sp-mono"><?= $threshold ?></td>
                                    <td data-label="Status"><?= $qty <= 0 ? cpBadge('Out of stock') : ($qty <= $threshold ? cpBadge('Low stock') : cpBadge('In stock')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($stockList)): ?>
                                <tr class="sp-empty-row"><td colspan="4">No active parts.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </section>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php
/* ---- Receipt copy of the report: used by Print and Download PDF (hidden on screen) ---- */
$rcTypeLabels = [
    'all' => 'All reports',
    'sales' => 'Sales / Revenue Report',
    'inventory' => 'Inventory Report',
    'jobs' => 'Job Status Report',
];
$rcPeriod = $rangeStart === $rangeEnd
    ? date('M d, Y', strtotime($rangeStart))
    : date('M d', strtotime($rangeStart)) . ' – ' . date('M d, Y', strtotime($rangeEnd));

$rcPreparedBy = $_SESSION['first_name'] ?? 'Staff';
try {
    $stmtMe = $db->prepare("SELECT TRIM(CONCAT_WS(' ', first_name, last_name)) FROM user WHERE user_id = ?");
    $stmtMe->execute([intval($_SESSION['user_id'] ?? 0)]);
    $rcPreparedBy = $stmtMe->fetchColumn() ?: $rcPreparedBy;
} catch (Exception $e) {
    // keep the first name
}

$rcMethods = array_filter($methodTotals, function ($amount) { return $amount > 0; });
$rcMethodTotal = array_sum($rcMethods);
$rcShare = function ($part, $total) {
    return $total > 0 ? round($part / $total * 100) : 0;
};
$rcJobs = [
    ['Pending', $pendingJobs, 'amber'],
    ['In progress', $inProgressJobs, 'blue'],
    ['Ready for pickup', $readyJobs, 'green'],
    ['Completed', $completedJobs, 'gray'],
    ['Cancelled', $cancelledJobs, 'red'],
];
$rcSection = 0;
?>
<div id="reportReceipt" class="report-receipt">
    <article class="rc-receipt">
        <?php rcHeader('Shop report', '<h1 class="rc-title">' . rcEscape($reportTitle) . '</h1>'); ?>

        <hr class="rc-sep">
        <dl class="rc-details rc-section">
            <dt>Report type</dt><dd><?= rcEscape($rcTypeLabels[$reportType]) ?></dd>
            <dt>Period</dt><dd><?= rcEscape($rcPeriod) ?></dd>
            <dt>Generated</dt><dd class="rc-mono"><?= date('M d, Y · h:i A') ?></dd>
            <dt>Prepared by</dt><dd><?= rcEscape($rcPreparedBy) ?></dd>
        </dl>

        <?php if ($showSales): ?>
            <hr class="rc-sep">
            <section class="rc-section">
                <div class="rc-sec-head">
                    <h2><span class="rc-sec-num"><?= sprintf('%02d', ++$rcSection) ?></span>Sales and revenue</h2>
                    <span class="rc-sec-tag">Payments + POS</span>
                </div>

                <?php foreach (['Service revenue' => $serviceRevenue, 'Parts (web orders)' => $onlinePartsRevenue, 'Parts (POS walk-in)' => $posRevenue] as $label => $amount): ?>
                    <div class="rc-leader"><span><?= $label ?></span><span class="rc-leader-fill"></span><span class="rc-money"><?= rcMoney($amount) ?></span></div>
                    <div class="rc-bar-row">
                        <span class="rc-share"><?= $rcShare($amount, $grandTotal) ?>%</span>
                        <span class="rc-track"><span class="rc-fill" style="width: <?= $rcShare($amount, $grandTotal) ?>%"></span></span>
                    </div>
                <?php endforeach; ?>
                <div class="rc-row rc-total"><span>Total revenue</span><span class="rc-money"><?= rcMoney($grandTotal) ?></span></div>

                <div class="rc-inkbox"><span>Jobs completed</span><strong><?= intval($jobsCompleted) ?></strong></div>
                <div class="rc-row" style="margin-top: 10px"><span>Invoice payments / POS sales</span><span class="rc-mono"><?= $paymentCount ?> / <?= $posSalesCount ?></span></div>

                <p class="rc-label" style="margin-top: 20px">By payment method</p>
                <?php if (empty($rcMethods)): ?>
                    <p class="rc-empty">No payments recorded in this period.</p>
                <?php else: ?>
                    <?php foreach ($rcMethods as $label => $amount): ?>
                        <div class="rc-leader">
                            <span><?= rcEscape($label) ?></span><span class="rc-leader-fill"></span>
                            <span class="rc-share"><?= $rcShare($amount, $rcMethodTotal) ?>%</span>
                            <span class="rc-money"><?= rcMoney($amount) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <div class="rc-row rc-total"><span>Total collected</span><span class="rc-money"><?= rcMoney($rcMethodTotal) ?></span></div>
            </section>
        <?php endif; ?>

        <?php if ($showJobs): ?>
            <hr class="rc-sep">
            <section class="rc-section">
                <div class="rc-sec-head">
                    <h2><span class="rc-sec-num"><?= sprintf('%02d', ++$rcSection) ?></span>Job status</h2>
                    <span class="rc-sec-tag">Job orders</span>
                </div>
                <?php foreach ($rcJobs as $job): ?>
                    <div class="rc-leader">
                        <span><span class="rc-dot is-<?= $job[2] ?>"></span><?= $job[0] ?></span><span class="rc-leader-fill"></span>
                        <span class="rc-money"><?= intval($job[1]) ?></span>
                    </div>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>

        <?php if ($showInventory): ?>
            <hr class="rc-sep">
            <section class="rc-section">
                <div class="rc-sec-head">
                    <h2><span class="rc-sec-num"><?= sprintf('%02d', ++$rcSection) ?></span>Low stock</h2>
                    <span class="rc-sec-tag"><?= count($lowStock) ?> part<?= count($lowStock) === 1 ? '' : 's' ?></span>
                </div>
                <?php foreach ($lowStock as $item): ?>
                    <?php $qty = intval($item['quantity_on_hand']); ?>
                    <div class="rc-stock">
                        <div>
                            <?= rcEscape($item['part_name']) ?>
                            <span class="rc-item-sub"><?= $qty ?> on hand · alert at <?= intval($item['low_stock_threshold']) ?></span>
                        </div>
                        <?= $qty <= 0 ? rcBadge('Out of stock', 'red') : rcBadge('Low', 'amber') ?>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($lowStock)): ?>
                    <p class="rc-empty">No low stock items.</p>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <hr class="rc-double">
        <p class="rc-note">Revenue counts invoice payments and completed POS walk-in sales recorded in DASPMS within the period (voided POS sales are excluded). Job counts use the date each job order was created, or completed for Completed jobs.</p>

        <div class="rc-signs">
            <div class="rc-sign"><strong>Prepared by</strong><span>Signature over printed name</span></div>
            <div class="rc-sign"><strong>Reviewed by (Owner)</strong><span>Signature over printed name</span></div>
        </div>

        <footer class="rc-foot">
            <p class="rc-end">*** END OF REPORT ***</p>
            <p class="rc-foot-code">RPT-<?= date('Ymd-Hi') ?> · <?= rcEscape($reportTitle) ?></p>
        </footer>
    </article>
    <?= rcTear() ?>
</div>

<script>
// Download PDF: one receipt-shaped page, as tall as the receipt, so nothing is cut onto a second page.
document.getElementById('downloadPdfBtn').addEventListener('click', async function () {
    const btn = this;
    btn.disabled = true;
    btn.textContent = 'Preparing…';
    await document.fonts.ready;
    // Lay out a copy off-screen; the off-screen position goes on a wrapper so the PDF copy itself sits at 0,0
    const holder = document.createElement('div');
    holder.style.cssText = 'position:fixed;left:-10000px;top:0;width:440px;';
    const el = document.getElementById('reportReceipt').cloneNode(true);
    el.removeAttribute('id');
    el.style.cssText = 'display:block;width:440px';
    el.classList.add('rc-pdf');
    const root = document.documentElement;
    const oldOverflow = root.style.overflow;
    root.style.overflow = 'hidden'; // the scroll bar would shift the capture sideways
    holder.appendChild(el);
    document.body.appendChild(holder);
    const pxToMm = px => px * 25.4 / 96;
    const w = pxToMm(440) + 16, h = pxToMm(el.scrollHeight) + 18; // +2mm slack
    try {
        await html2pdf().set({
            margin: 8,
            filename: <?php echo json_encode($pdfFilename); ?>,
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2, useCORS: true, backgroundColor: '#E4E4E1', scrollX: 0, scrollY: 0 },
            jsPDF: { unit: 'mm', format: [w, h], orientation: 'portrait' },
            pagebreak: { mode: ['avoid-all'] }
        }).from(el).save();
    } catch (e) {
        cpNotice(btn.closest('form') || btn, 'Could not create the PDF. Please try again.');
    } finally {
        holder.remove();
        root.style.overflow = oldOverflow;
        btn.disabled = false;
        btn.textContent = 'Download PDF';
    }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
