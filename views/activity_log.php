<?php
require_once __DIR__ . '/../includes/auth.php';

requirePermission('activity_log.view');

require_once '../config/Database.php';
require_once '../includes/staff_ui.php';

$db = (new Database())->getConnection();

// Date range (defaults to the last 30 days)
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-29 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$isValidDate = function ($d) {
    $t = DateTime::createFromFormat('Y-m-d', $d);
    return $t && $t->format('Y-m-d') === $d;
};
$rangeError = '';
if (!$isValidDate($startDate) || !$isValidDate($endDate) || $startDate > $endDate) {
    $rangeError = 'Invalid date range. Showing the last 30 days instead.';
    $startDate = date('Y-m-d', strtotime('-29 days'));
    $endDate = date('Y-m-d');
}

$stmt = $db->prepare("
    SELECT al.*, u.first_name, u.last_name, u.role
    FROM activity_log al
    LEFT JOIN user u ON u.user_id = al.user_id
    WHERE DATE(al.created_at) BETWEEN ? AND ?
    ORDER BY al.created_at DESC, al.log_id DESC
");
$stmt->execute([$startDate, $endDate]);
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

$entityTypes = array_values(array_unique(array_column($entries, 'entity_type')));
sort($entityTypes);

// Colour of the action label: red for cancellations/voids/expiry, amber for archiving, blue for payments
function activityTone($action) {
    $a = strtolower($action);
    if (strpos($a, 'cancel') !== false || strpos($a, 'void') !== false || strpos($a, 'expired') !== false || strpos($a, 'delete') !== false) return 'bad';
    if (strpos($a, 'archiv') !== false || strpos($a, 'deactivat') !== false) return 'pend';
    if (strpos($a, 'payment') !== false) return 'prog';
    return 'ok';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Activity Log - Norily's Repair Shop</title>
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
            spPortalLabel() . ' · Owner',
            'Activity Log',
            'Who cancelled, voided, collected or archived what, and when. Entries can’t be edited or deleted.'
        );
        ?>

        <?php if ($rangeError !== ''): ?>
            <div class="cp-flash cp-flash-bad" role="alert">
                <span><?= cpEscape($rangeError) ?></span>
                <button type="button" class="cp-flash-close" aria-label="Dismiss" onclick="this.parentElement.remove()">&times;</button>
            </div>
        <?php endif; ?>

        <form method="GET" class="sp-toolbar sp-report-bar">
            <input type="date" name="start_date" class="cp-input" value="<?= cpEscape($startDate) ?>" aria-label="From" required>
            <input type="date" name="end_date" class="cp-input" value="<?= cpEscape($endDate) ?>" aria-label="To" required>
            <button type="submit" class="cp-btn cp-btn-dark">Apply</button>
        </form>

        <div data-sp-filter="#activityRows">
            <div class="sp-toolbar" style="border-bottom: 0; padding-top: 16px">
                <div class="sp-search-field-inline">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                    <input type="search" class="cp-input" data-sp-search placeholder="Search staff, record or reason" aria-label="Search staff, record or reason" autocomplete="off">
                </div>
                <select class="cp-input" data-sp-type aria-label="Record type">
                    <option value="all">All records</option>
                    <?php foreach ($entityTypes as $type): ?>
                        <option value="<?= cpEscape($type) ?>"><?= cpEscape($type) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <p class="sp-showing" data-sp-showing aria-live="polite">Showing <?= count($entries) ?> of <?= count($entries) ?></p>
        </div>

        <table class="cp-table sp-table">
            <thead>
                <tr>
                    <th scope="col">When</th>
                    <th scope="col">Who</th>
                    <th scope="col">Action</th>
                    <th scope="col">Record</th>
                    <th scope="col">Details</th>
                </tr>
            </thead>
            <tbody id="activityRows" data-cp-paginate="15" data-cp-noun="entry">
                <?php foreach ($entries as $e): ?>
                    <?php
                    $who = $e['user_id'] ? trim($e['first_name'] . ' ' . $e['last_name']) : 'System';
                    $whoSub = $e['user_id'] ? ($e['role'] ?? '') : 'Automatic';
                    ?>
                    <tr data-type="<?= cpEscape($e['entity_type']) ?>"
                        data-search="<?= cpEscape(strtolower($who . ' ' . $e['action'] . ' ' . $e['entity_type'] . ' ' . $e['entity_ref'] . ' ' . $e['details'])) ?>">
                        <td data-label="When" class="sp-nowrap">
                            <div>
                                <span class="sp-cell-line"><?= date('d M Y', strtotime($e['created_at'])) ?></span>
                                <span class="cp-cell-sub"><?= date('g:i A', strtotime($e['created_at'])) ?></span>
                            </div>
                        </td>
                        <td data-label="Who">
                            <div>
                                <span class="sp-ref"><?= cpEscape($who ?: 'Former user') ?></span>
                                <span class="cp-cell-sub"><?= cpEscape($whoSub) ?></span>
                            </div>
                        </td>
                        <td data-label="Action"><span class="st <?= activityTone($e['action']) ?>"><?= cpEscape($e['action']) ?></span></td>
                        <td data-label="Record">
                            <div>
                                <span class="sp-cell-line sp-mono"><?= cpEscape($e['entity_ref'] ?: '#' . $e['entity_id']) ?></span>
                                <span class="cp-cell-sub"><?= cpEscape($e['entity_type']) ?></span>
                            </div>
                        </td>
                        <td data-label="Details" class="sp-desc"><?= cpEscape($e['details'] ?: '—') ?></td>
                    </tr>
                <?php endforeach; ?>

                <tr class="sp-empty-row" data-sp-empty hidden>
                    <td colspan="5">No entries match your search or filter.</td>
                </tr>
                <?php if (empty($entries)): ?>
                    <tr class="sp-empty-row"><td colspan="5">Nothing recorded in this date range.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
