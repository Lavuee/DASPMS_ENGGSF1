<?php
require_once __DIR__ . '/../includes/auth.php';

requirePermission('dashboard.mechanic');

require_once '../config/Database.php';
require_once '../includes/staff_ui.php';

$db = (new Database())->getConnection();

$mechanicName = $_SESSION['first_name'] ?? 'Mechanic';
$canMoveJobs = can('job_orders.progress');

/* Jobs for the board: everything still open, plus jobs completed today */
$jobs = $db->query("
    SELECT
        jo.job_order_id,
        jo.job_order_number,
        jo.status,
        jo.date_created,
        jo.date_completed,
        jo.expected_completion_date,
        jo.description,
        c.first_name,
        c.last_name,
        v.plate_number,
        v.make,
        v.model,
        v.year,
        CONCAT(am.first_name, ' ', am.last_name) AS mechanic_name,
        (SELECT COUNT(*) FROM job_order_part jop WHERE jop.job_order_id = jo.job_order_id) AS parts_used
    FROM job_order jo
    JOIN customer c ON jo.customer_id = c.customer_id
    JOIN vehicle v ON jo.vehicle_id = v.vehicle_id
    LEFT JOIN user am ON am.user_id = jo.assigned_mechanic_id
    WHERE jo.status IN ('Pending', 'In Progress', 'Ready for Pickup')
       OR (jo.status = 'Completed' AND DATE(jo.date_completed) = CURDATE())
    ORDER BY jo.date_created ASC
")->fetchAll(PDO::FETCH_ASSOC);

$columns = [
    'Pending' => ['label' => 'Pending', 'note' => 'Not started yet', 'tone' => 'pend', 'next' => ['In Progress', 'Start repair']],
    'In Progress' => ['label' => 'In progress', 'note' => 'Being repaired now', 'tone' => 'prog', 'next' => ['Ready for Pickup', 'Mark ready']],
    'Ready for Pickup' => ['label' => 'Ready for pickup', 'note' => 'Waiting for the customer', 'tone' => 'ready', 'next' => null],
    'Completed' => ['label' => 'Completed today', 'note' => 'Released to the customer', 'tone' => 'done', 'next' => null],
];
$jobsByStatus = array_fill_keys(array_keys($columns), []);
foreach ($jobs as $job) {
    $jobsByStatus[$job['status']][] = $job;
}

/* Parts running low, with how many open jobs already used each one */
$lowParts = $db->query("
    SELECT p.part_name, p.quantity_on_hand, p.low_stock_threshold,
           (SELECT COUNT(DISTINCT jop.job_order_id)
              FROM job_order_part jop
              JOIN job_order jo ON jo.job_order_id = jop.job_order_id
             WHERE jop.part_id = p.part_id AND jo.status IN ('Pending', 'In Progress')) AS active_jobs
    FROM part p
    WHERE p.is_active = 1 AND p.quantity_on_hand <= p.low_stock_threshold
    ORDER BY p.quantity_on_hand ASC, p.part_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Date line at the bottom of a job card
function boardDateNote(array $job) {
    if ($job['status'] === 'Completed' && !empty($job['date_completed'])) {
        return date('g:i A', strtotime($job['date_completed']));
    }
    if ($job['status'] === 'In Progress' && !empty($job['expected_completion_date'])) {
        return 'target ' . date('d M', strtotime($job['expected_completion_date']));
    }
    return 'since ' . date('d M', strtotime($job['date_created']));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Job Board - Norily's Repair Shop</title>
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
            'Job Board',
            'Hi ' . $mechanicName . '. Each column is a job status. Tap the button on a job to move it to the next column. The front desk sees the change right away.',
            '<a href="inventory_view.php" class="cp-btn cp-btn-line">Parts availability</a>'
        );
        cpFlash();
        ?>

        <!-- 01 Jobs by status -->
        <section class="sp-sec" aria-label="Jobs by status">
            <?php spSectionHead('01', 'Jobs by status', '<a href="job_orders.php" class="sp-arrow-link">All jobs ↗</a>'); ?>
            <div class="sp-board">
                <?php foreach ($columns as $status => $col): ?>
                    <div class="sp-board-col is-<?= $col['tone'] ?>">
                        <div class="sp-board-head">
                            <div>
                                <h3><?= $col['label'] ?></h3>
                                <p><?= $col['note'] ?></p>
                            </div>
                            <b><?= count($jobsByStatus[$status]) ?></b>
                        </div>

                        <?php foreach ($jobsByStatus[$status] as $job): ?>
                            <?php $vehicle = trim(($job['make'] ?? '') . ' ' . ($job['model'] ?? '') . (!empty($job['year']) ? ' ' . $job['year'] : '')); ?>
                            <article class="sp-job-card">
                                <div class="sp-job-top">
                                    <span class="sp-plate sp-plate-sm"><?= cpEscape($job['plate_number'] ?: '—') ?></span>
                                    <span class="sp-mono sp-job-no"><?= cpEscape($job['job_order_number']) ?></span>
                                </div>
                                <h4><?= cpEscape($vehicle !== '' ? $vehicle : 'Vehicle') ?></h4>
                                <p class="sp-job-desc"><?= cpEscape(mb_strimwidth(plainText($job['description'] ?? ''), 0, 110, '…')) ?></p>
                                <div class="sp-job-meta">
                                    <span><?= cpEscape(trim($job['first_name'] . ' ' . $job['last_name'])) ?></span>
                                    <span><?= boardDateNote($job) ?></span>
                                </div>
                                <?php if (intval($job['parts_used']) > 0 || trim((string) $job['mechanic_name']) !== ''): ?>
                                    <div class="sp-job-meta">
                                        <span><?= trim((string) $job['mechanic_name']) !== '' ? cpEscape($job['mechanic_name']) : 'Unassigned' ?></span>
                                        <span><?= intval($job['parts_used']) ?> part<?= intval($job['parts_used']) === 1 ? '' : 's' ?> used</span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($canMoveJobs && $col['next']): ?>
                                    <form action="../controllers/JobOrderController.php" method="POST">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="job_order_id" value="<?= intval($job['job_order_id']) ?>">
                                        <input type="hidden" name="status" value="<?= cpEscape($col['next'][0]) ?>">
                                        <input type="hidden" name="return_to" value="dashboard_mechanic.php">
                                        <button type="submit" class="cp-btn cp-btn-dark sp-job-btn"><?= $col['next'][1] ?></button>
                                    </form>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>

                        <?php if (empty($jobsByStatus[$status])): ?>
                            <p class="sp-board-empty">No jobs here.</p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- 02 Parts running low -->
        <section class="sp-sec" aria-label="Parts running low">
            <?php spSectionHead('02', 'Parts running low', '<a href="inventory_view.php" class="sp-arrow-link">Parts availability ↗</a>'); ?>
            <?php foreach ($lowParts as $part): ?>
                <?php $qty = intval($part['quantity_on_hand']); $used = intval($part['active_jobs']); ?>
                <div class="sp-att-item">
                    <span>
                        <strong><?= cpEscape($part['part_name']) ?></strong>
                        <small>
                            <?= $qty ?> left
                            <?php if ($qty <= 0): ?> · tell the front desk before promising a date<?php endif; ?>
                            <?php if ($used > 0): ?> · used in <?= $used ?> active job<?= $used === 1 ? '' : 's' ?><?php endif; ?>
                        </small>
                    </span>
                    <?= $qty <= 0 ? cpBadge('Out of stock') : cpBadge('Low stock') ?>
                </div>
            <?php endforeach; ?>
            <?php if (empty($lowParts)): ?>
                <div class="cp-empty"><strong>Stock looks good</strong>No parts are at or below their alert level.</div>
            <?php endif; ?>
        </section>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
