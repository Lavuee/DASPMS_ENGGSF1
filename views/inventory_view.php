<?php
require_once __DIR__ . '/../includes/auth.php';

requirePermission('inventory.view');

require_once '../config/Database.php';
require_once '../includes/staff_ui.php';

$db = (new Database())->getConnection();

$stmt = $db->prepare("
    SELECT *
    FROM part
    ORDER BY part_name ASC
");
$stmt->execute();

// Archived parts can't be used on jobs, so only active parts are listed
$parts = array_values(array_filter($stmt->fetchAll(PDO::FETCH_ASSOC), function ($p) {
    return intval($p['is_active'] ?? 1) === 1;
}));

function availabilityKey($qty, $threshold) {
    if ($qty <= 0) return 'out';
    if ($qty <= $threshold) return 'low';
    return 'in';
}

$stockCounts = ['in' => 0, 'low' => 0, 'out' => 0];
foreach ($parts as $p) {
    $stockCounts[availabilityKey(intval($p['quantity_on_hand']), intval($p['low_stock_threshold']))]++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Parts Availability - Norily's Repair Shop</title>
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
            spPortalLabel() . ' · Stock',
            'Parts Availability',
            'Check stock before you promise a date. Tell the front desk when something runs out.'
        );
        ?>

        <div data-sp-filter="#availabilityRows">
            <div class="sp-toolbar">
                <div class="sp-search-field-inline">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                    <input type="search" class="cp-input" data-sp-search placeholder="Search part or category" aria-label="Search part or category" autocomplete="off">
                </div>
            </div>

            <div class="sp-chips" role="group" aria-label="Filter by stock">
                <button type="button" class="sp-chip" data-sp-chip="all" aria-pressed="true">All <span class="sp-chip-count"><?= count($parts) ?></span></button>
                <button type="button" class="sp-chip is-ok" data-sp-chip="in" aria-pressed="false"><span class="sp-chip-dot"></span>In stock <span class="sp-chip-count"><?= $stockCounts['in'] ?></span></button>
                <button type="button" class="sp-chip is-pend" data-sp-chip="low" aria-pressed="false"><span class="sp-chip-dot"></span>Low stock <span class="sp-chip-count"><?= $stockCounts['low'] ?></span></button>
                <button type="button" class="sp-chip is-bad" data-sp-chip="out" aria-pressed="false"><span class="sp-chip-dot"></span>Out of stock <span class="sp-chip-count"><?= $stockCounts['out'] ?></span></button>
            </div>

            <p class="sp-showing" data-sp-showing aria-live="polite">Showing <?= count($parts) ?> of <?= count($parts) ?></p>
        </div>

        <table class="cp-table sp-table">
            <thead>
                <tr>
                    <th scope="col">Part</th>
                    <th scope="col">Category</th>
                    <th scope="col">Description</th>
                    <th scope="col">Current stock</th>
                    <th scope="col">Status</th>
                </tr>
            </thead>
            <tbody id="availabilityRows" data-cp-paginate="10" data-cp-noun="part">
                <?php foreach ($parts as $p): ?>
                    <?php
                    $qty = intval($p['quantity_on_hand']);
                    $key = availabilityKey($qty, intval($p['low_stock_threshold']));
                    $name = html_entity_decode((string) $p['part_name'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $brand = html_entity_decode((string) ($p['brand'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    ?>
                    <tr data-status="<?= $key ?>" data-search="<?= cpEscape(strtolower($name . ' ' . $brand . ' ' . ($p['category'] ?? '') . ' ' . ($p['description'] ?? '') . ' ' . ($p['compatibility'] ?? ''))) ?>">
                        <td data-label="Part">
                            <div>
                                <span class="sp-ref sp-wrap"><?= cpEscape($name) ?></span>
                                <?php if (cpHasValue($brand)): ?><span class="cp-cell-sub"><?= cpEscape($brand) ?></span><?php endif; ?>
                            </div>
                        </td>
                        <td data-label="Category"><?= cpEscape(html_entity_decode((string) ($p['category'] ?: '—'), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?></td>
                        <td data-label="Description" class="sp-desc"><?= cpEscape(html_entity_decode((string) ($p['description'] ?: '—'), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?></td>
                        <td data-label="Current stock">
                            <div>
                                <strong class="sp-stock-qty"><?= $qty ?></strong>
                                <span class="cp-cell-sub"><?= cpEscape(trim($p['unit'] ?? '') ?: 'piece') ?></span>
                            </div>
                        </td>
                        <td data-label="Status"><?= $key === 'out' ? cpBadge('Out of stock') : ($key === 'low' ? cpBadge('Low stock') : cpBadge('In stock')) ?></td>
                    </tr>
                <?php endforeach; ?>

                <tr class="sp-empty-row" data-sp-empty hidden>
                    <td colspan="5">No parts match your search or filter.</td>
                </tr>
                <?php if (empty($parts)): ?>
                    <tr class="sp-empty-row"><td colspan="5">No parts in the inventory yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
