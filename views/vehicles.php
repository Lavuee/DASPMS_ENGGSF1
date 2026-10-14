<?php
require_once __DIR__ . '/../includes/auth.php';

requirePermission('vehicles.manage');

require_once '../config/Database.php';
require_once '../includes/staff_ui.php';

$db = (new Database())->getConnection();

$isOwner = can('vehicles.archive');

function getVehicleStatusBadgeClass($status) {
    switch ($status) {
        case 'Active':
            return 'status-badge status-active';
        case 'Inactive':
            return 'status-badge status-inactive';
        case 'Archived':
            return 'status-badge status-archived';
        default:
            return 'status-badge status-inactive';
    }
}

$statusFilter = $_GET['status'] ?? 'Active';
$allowedFilters = ['All', 'Active', 'Inactive', 'Archived'];

if (!in_array($statusFilter, $allowedFilters)) {
    $statusFilter = 'Active';
}

/* =========================
   VEHICLE COUNTS
========================= */
$totalStmt = $db->prepare("SELECT COUNT(*) FROM vehicle");
$totalStmt->execute();
$totalVehicles = intval($totalStmt->fetchColumn());

$statusCountStmt = $db->prepare("
    SELECT status, COUNT(*) AS total
    FROM vehicle
    GROUP BY status
");
$statusCountStmt->execute();
$statusCountsRaw = $statusCountStmt->fetchAll(PDO::FETCH_ASSOC);

$statusCounts = [
    'Active' => 0,
    'Inactive' => 0,
    'Archived' => 0
];

foreach ($statusCountsRaw as $row) {
    $key = $row['status'] ?? 'Active';

    if (isset($statusCounts[$key])) {
        $statusCounts[$key] = intval($row['total']);
    }
}

/* =========================
   VEHICLE LIST
========================= */
// Every vehicle is loaded; the page filters by status itself.
$stmt = $db->prepare("
    SELECT 
        v.*,
        c.first_name,
        c.last_name
    FROM vehicle v
    INNER JOIN customer c ON v.customer_id = c.customer_id
    ORDER BY
        FIELD(v.status, 'Active', 'Inactive', 'Archived'),
        v.plate_number ASC
");
$stmt->execute();

$vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
$displayCount = count($vehicles);

/* =========================
   CUSTOMER DROPDOWN LIST
========================= */
$stmtCustomers = $db->prepare("
    SELECT customer_id, first_name, last_name, status
    FROM customer
    WHERE status != 'Archived'
    ORDER BY first_name ASC, last_name ASC
");
$stmtCustomers->execute();
$customersList = $stmtCustomers->fetchAll(PDO::FETCH_ASSOC);

// Repair history per vehicle (read-only), newest first
$repairsByVehicle = [];
foreach ($db->query("
    SELECT vehicle_id, job_order_number, description, status, date_created
    FROM job_order
    ORDER BY date_created DESC
")->fetchAll(PDO::FETCH_ASSOC) as $repair) {
    $repairsByVehicle[$repair['vehicle_id']][] = $repair;
}

// Owner-only status button for a vehicle, confirmed in a popup
function vehicleStatusForm($action, $vehicleId, $label, $message, $danger, $statusFilter) {
    return '<form method="POST" action="../controllers/VehicleController.php" data-cp-confirm="' . cpEscape($message) . '"'
        . ' data-cp-confirm-title="' . cpEscape($label) . ' vehicle?" data-cp-confirm-yes="' . cpEscape($label) . '" data-cp-confirm-no="Go back"'
        . ($danger ? '' : ' data-cp-confirm-tone="dark"') . '>'
        . csrfField()
        . '<input type="hidden" name="action" value="' . cpEscape($action) . '">'
        . '<input type="hidden" name="vehicle_id" value="' . intval($vehicleId) . '">'
        . '<input type="hidden" name="current_filter" value="' . cpEscape($statusFilter) . '">'
        . '<button type="submit" class="cp-btn cp-btn-line' . ($danger ? ' sp-btn-cancel' : '') . '">' . cpEscape($label) . '</button>'
        . '</form>';
}

// Owner dropdown (shared by the add and edit popups)
function vehicleOwnerOptions(array $customersList, $selectedId = 0) {
    $html = '';
    foreach ($customersList as $c) {
        $html .= '<option value="' . intval($c['customer_id']) . '"' . (intval($c['customer_id']) === intval($selectedId) ? ' selected' : '') . '>'
            . cpEscape($c['first_name'] . ' ' . $c['last_name'] . ($c['status'] !== 'Active' ? ' (' . $c['status'] . ')' : ''))
            . '</option>';
    }
    return $html;
}

$vehicleDialogs = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Vehicles - Norily's Repair Shop</title>
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
        $pageActions = '<button type="button" class="cp-btn cp-btn-dark" data-sp-open="#addVehicleDialog">+ Add vehicle</button>';
        spPageHead(
            spPortalLabel() . ' · Records',
            'Vehicles',
            'Every vehicle the shop has worked on, by plate number.'
        );
        cpFlash();
        ?>

        <div data-sp-filter="#vehicleRows">
            <div class="sp-toolbar">
                <div class="sp-search-field-inline">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                    <input type="search" id="vehicleSearchInput" class="cp-input" data-sp-search placeholder="Search plate, owner or model" aria-label="Search plate, owner or model" autocomplete="off">
                </div>
                <select class="cp-input" data-sp-type aria-label="Status">
                    <option value="all">All statuses (<?= $totalVehicles ?>)</option>
                    <option value="Active">Active (<?= $statusCounts['Active'] ?>)</option>
                    <option value="Inactive">Inactive (<?= $statusCounts['Inactive'] ?>)</option>
                    <option value="Archived">Archived (<?= $statusCounts['Archived'] ?>)</option>
                </select>
                <div class="sp-toolbar-actions"><?= $pageActions ?></div>
            </div>
            <p class="sp-showing" data-sp-showing aria-live="polite" style="margin-top: 16px">Showing <?= count($vehicles) ?> of <?= count($vehicles) ?></p>
        </div>

        <table class="cp-table sp-table" id="vehiclesTable">
            <thead>
                <tr>
                    <th scope="col">Vehicle</th>
                    <th scope="col">Owner</th>
                    <th scope="col">Details</th>
                    <th scope="col">Status</th>
                    <th scope="col">Added</th>
                    <th scope="col"><span class="visually-hidden">Actions</span></th>
                </tr>
            </thead>
            <tbody id="vehicleRows" data-cp-paginate="10" data-cp-noun="vehicle">
                <?php foreach ($vehicles as $v): ?>
                    <?php
                    $vehicleId = intval($v['vehicle_id']);
                    $ownerName = trim(($v['first_name'] ?? '') . ' ' . ($v['last_name'] ?? ''));
                    $status = $v['status'] ?? 'Active';
                    $makeModel = trim(($v['make'] ?? '') . ' ' . ($v['model'] ?? ''));
                    $plate = trim($v['plate_number'] ?? '');
                    $details = implode(' · ', array_filter([trim($v['year'] ?? ''), trim($v['color'] ?? '')]));
                    $repairs = $repairsByVehicle[$vehicleId] ?? [];
                    $searchText = strtolower($plate . ' ' . $makeModel . ' ' . ($v['year'] ?? '') . ' ' . ($v['color'] ?? '') . ' ' . $ownerName . ' ' . $status);
                    ?>
                    <tr data-type="<?= cpEscape($status) ?>" data-search="<?= cpEscape($searchText) ?>">
                        <td data-label="Vehicle">
                            <div>
                                <span class="sp-plate"><?= cpEscape($plate !== '' ? $plate : '—') ?></span>
                                <span class="cp-cell-sub"><?= cpEscape($makeModel) ?></span>
                            </div>
                        </td>
                        <td data-label="Owner"><?= cpEscape($ownerName) ?></td>
                        <td data-label="Details"><?= cpEscape($details !== '' ? $details : '—') ?></td>
                        <td data-label="Status"><?= cpBadge($status) ?></td>
                        <td data-label="Added" class="sp-nowrap"><?= !empty($v['created_at']) ? date('d M Y', strtotime($v['created_at'])) : '—' ?></td>
                        <td class="cp-num">
                            <div class="sp-row-actions">
                                <button type="button" class="cp-btn cp-btn-line cp-btn-sm" data-sp-open="#vehicleView<?= $vehicleId ?>">View</button>
                                <button type="button" class="cp-btn cp-btn-line cp-btn-sm" data-sp-open="#vehicleEdit<?= $vehicleId ?>">Edit</button>
                            </div>
                        </td>
                    </tr>

                    <?php ob_start(); ?>
                    <!-- Vehicle details -->
                    <div class="sp-dialog" id="vehicleView<?= $vehicleId ?>" role="dialog" aria-modal="true" aria-labelledby="vehicleViewTitle<?= $vehicleId ?>" hidden>
                        <div class="sp-dialog-box">
                            <div class="cp-modal-head">
                                <div>
                                    <p class="cp-label">Vehicle · <span class="sp-plate sp-plate-sm"><?= cpEscape($plate !== '' ? $plate : '—') ?></span></p>
                                    <h2 class="cp-modal-title" id="vehicleViewTitle<?= $vehicleId ?>"><?= cpEscape($makeModel !== '' ? $makeModel : 'Vehicle') ?></h2>
                                </div>
                                <button type="button" class="cp-modal-x" data-sp-close aria-label="Close">&times;</button>
                            </div>
                            <div class="sp-dialog-body">
                                <dl class="sp-facts">
                                    <div><dt>Owner</dt><dd><?= cpEscape($ownerName) ?></dd></div>
                                    <div><dt>Year</dt><dd><?= cpEscape($v['year'] ?: '—') ?></dd></div>
                                    <div><dt>Color</dt><dd><?= cpEscape($v['color'] ?: '—') ?></dd></div>
                                    <div><dt>Status</dt><dd><?= cpBadge($status) ?></dd></div>
                                    <div><dt>Added</dt><dd><?= !empty($v['created_at']) ? date('d M Y', strtotime($v['created_at'])) : '—' ?></dd></div>
                                    <div><dt>Repairs</dt><dd><?= count($repairs) ?></dd></div>
                                    <?php if (!empty($v['notes'])): ?>
                                        <div class="is-wide"><dt>Notes</dt><dd class="sp-fact-text"><?= nl2br(cpEscape($v['notes'])) ?></dd></div>
                                    <?php endif; ?>
                                    <?php if (!empty($v['deactivated_at']) && $status === 'Inactive'): ?>
                                        <div class="is-wide"><dt>Deactivated</dt><dd><?= date('d M Y, g:i A', strtotime($v['deactivated_at'])) ?></dd></div>
                                    <?php endif; ?>
                                    <?php if (!empty($v['archived_at']) && $status === 'Archived'): ?>
                                        <div class="is-wide"><dt>Archived</dt><dd><?= date('d M Y, g:i A', strtotime($v['archived_at'])) ?></dd></div>
                                    <?php endif; ?>
                                </dl>

                                <p class="sp-dialog-section">Repair history</p>
                                <?php if (empty($repairs)): ?>
                                    <p class="cp-hint" style="margin-top: 10px">No repairs recorded for this vehicle yet.</p>
                                <?php else: ?>
                                    <div class="sp-history">
                                        <?php foreach ($repairs as $repair): ?>
                                            <div class="sp-history-row">
                                                <div>
                                                    <strong><span class="sp-mono"><?= cpEscape($repair['job_order_number']) ?></span> · <?= cpEscape(mb_strimwidth(plainText($repair['description'] ?? ''), 0, 70, '…')) ?></strong>
                                                    <small><?= !empty($repair['date_created']) ? date('d M Y', strtotime($repair['date_created'])) : '' ?></small>
                                                </div>
                                                <?= cpBadge($repair['status']) ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="cp-modal-foot">
                                <button type="button" class="cp-btn cp-btn-line" data-sp-close>Close</button>
                                <?php if ($isOwner): ?>
                                    <?php if ($status === 'Active'): ?>
                                        <?= vehicleStatusForm('deactivate_vehicle', $vehicleId, 'Deactivate', 'The vehicle is set to Inactive. You can reactivate it later.', true, $statusFilter) ?>
                                    <?php else: ?>
                                        <?= vehicleStatusForm('reactivate_vehicle', $vehicleId, 'Reactivate', 'The vehicle becomes Active again.', false, $statusFilter) ?>
                                    <?php endif; ?>
                                    <?php if ($status !== 'Archived'): ?>
                                        <?= vehicleStatusForm('archive_vehicle', $vehicleId, 'Archive', 'Archiving keeps the repair history but takes the vehicle out of active use.', true, $statusFilter) ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <button type="button" class="cp-btn cp-btn-dark" data-sp-open="#vehicleEdit<?= $vehicleId ?>">Edit vehicle</button>
                            </div>
                        </div>
                    </div>

                    <!-- Edit vehicle -->
                    <div class="sp-dialog" id="vehicleEdit<?= $vehicleId ?>" role="dialog" aria-modal="true" aria-labelledby="vehicleEditTitle<?= $vehicleId ?>" hidden>
                        <div class="sp-dialog-box">
                            <form method="POST" action="../controllers/VehicleController.php">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="update_vehicle">
                                <input type="hidden" name="vehicle_id" value="<?= $vehicleId ?>">
                                <input type="hidden" name="current_filter" value="<?= cpEscape($statusFilter) ?>">

                                <div class="cp-modal-head">
                                    <div>
                                        <p class="cp-label sp-mono"><?= cpEscape($plate) ?></p>
                                        <h2 class="cp-modal-title" id="vehicleEditTitle<?= $vehicleId ?>">Edit vehicle</h2>
                                    </div>
                                    <button type="button" class="cp-modal-x" data-sp-close aria-label="Close">&times;</button>
                                </div>

                                <div class="sp-dialog-body">
                                    <div class="sp-fgrid">
                                        <div class="sp-field sp-span">
                                            <label for="editOwner<?= $vehicleId ?>">Owner <span class="sp-req">*</span></label>
                                            <select name="customer_id" id="editOwner<?= $vehicleId ?>" class="cp-input" required><?= vehicleOwnerOptions($customersList, $v['customer_id']) ?></select>
                                        </div>
                                        <div class="sp-field">
                                            <label for="editPlate<?= $vehicleId ?>">Plate number <span class="sp-req">*</span></label>
                                            <input type="text" name="plate_number" id="editPlate<?= $vehicleId ?>" class="cp-input sp-upper" value="<?= cpEscape($v['plate_number'] ?? '') ?>" required>
                                        </div>
                                        <div class="sp-field">
                                            <label for="editMake<?= $vehicleId ?>">Make or brand <span class="sp-req">*</span></label>
                                            <input type="text" name="make" id="editMake<?= $vehicleId ?>" class="cp-input" value="<?= cpEscape($v['make'] ?? '') ?>" required>
                                        </div>
                                        <div class="sp-field">
                                            <label for="editModel<?= $vehicleId ?>">Model <span class="sp-req">*</span></label>
                                            <input type="text" name="model" id="editModel<?= $vehicleId ?>" class="cp-input" value="<?= cpEscape($v['model'] ?? '') ?>" required>
                                        </div>
                                        <div class="sp-field">
                                            <label for="editYear<?= $vehicleId ?>">Year <span class="sp-req">*</span></label>
                                            <input type="text" name="year" id="editYear<?= $vehicleId ?>" class="cp-input" maxlength="4" inputmode="numeric" value="<?= cpEscape($v['year'] ?? '') ?>" required>
                                        </div>
                                        <div class="sp-field">
                                            <label for="editColor<?= $vehicleId ?>">Color <span class="sp-req">*</span></label>
                                            <input type="text" name="color" id="editColor<?= $vehicleId ?>" class="cp-input" value="<?= cpEscape($v['color'] ?? '') ?>" required>
                                        </div>
                                        <div class="sp-field sp-span">
                                            <label for="editNotes<?= $vehicleId ?>">Notes</label>
                                            <textarea name="notes" id="editNotes<?= $vehicleId ?>" class="cp-input" rows="3" placeholder="Optional"><?= cpEscape($v['notes'] ?? '') ?></textarea>
                                        </div>
                                    </div>
                                </div>

                                <div class="cp-modal-foot">
                                    <button type="button" class="cp-btn cp-btn-line" data-sp-close>Cancel</button>
                                    <button type="submit" class="cp-btn cp-btn-dark">Save changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php $vehicleDialogs .= ob_get_clean(); ?>
                <?php endforeach; ?>

                <tr class="sp-empty-row" data-sp-empty hidden>
                    <td colspan="6">No vehicles match your search or filter.</td>
                </tr>
                <?php if (empty($vehicles)): ?>
                    <tr class="sp-empty-row"><td colspan="6">No vehicles yet. Add one to link it to a customer.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </main>
</div>

<?= $vehicleDialogs ?>

<!-- Add vehicle -->
<div class="sp-dialog" id="addVehicleDialog" role="dialog" aria-modal="true" aria-labelledby="addVehicleTitle" hidden>
    <div class="sp-dialog-box">
        <form method="POST" action="../controllers/VehicleController.php">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add_vehicle">

            <div class="cp-modal-head">
                <div>
                    <p class="cp-label">Vehicles</p>
                    <h2 class="cp-modal-title" id="addVehicleTitle">Add a vehicle</h2>
                </div>
                <button type="button" class="cp-modal-x" data-sp-close aria-label="Close">&times;</button>
            </div>

            <div class="sp-dialog-body">
                <div class="sp-fgrid">
                    <div class="sp-field sp-span">
                        <label for="addOwner">Owner <span class="sp-req">*</span></label>
                        <select name="customer_id" id="addOwner" class="cp-input" required>
                            <option value="" disabled selected>Choose customer</option>
                            <?= vehicleOwnerOptions($customersList) ?>
                        </select>
                    </div>
                    <div class="sp-field">
                        <label for="addPlate">Plate number <span class="sp-req">*</span></label>
                        <input type="text" name="plate_number" id="addPlate" class="cp-input sp-upper" placeholder="ABC 1234" required>
                    </div>
                    <div class="sp-field">
                        <label for="addMake">Make or brand <span class="sp-req">*</span></label>
                        <input type="text" name="make" id="addMake" class="cp-input" required>
                    </div>
                    <div class="sp-field">
                        <label for="addModel">Model <span class="sp-req">*</span></label>
                        <input type="text" name="model" id="addModel" class="cp-input" required>
                    </div>
                    <div class="sp-field">
                        <label for="addYear">Year <span class="sp-req">*</span></label>
                        <input type="text" name="year" id="addYear" class="cp-input" maxlength="4" inputmode="numeric" required>
                    </div>
                    <div class="sp-field">
                        <label for="addColor">Color <span class="sp-req">*</span></label>
                        <input type="text" name="color" id="addColor" class="cp-input" required>
                    </div>
                    <div class="sp-field sp-span">
                        <label for="addNotes">Notes</label>
                        <textarea name="notes" id="addNotes" class="cp-input" rows="3" placeholder="Optional"></textarea>
                    </div>
                </div>
            </div>

            <div class="cp-modal-foot">
                <button type="button" class="cp-btn cp-btn-line" data-sp-close>Cancel</button>
                <button type="submit" class="cp-btn cp-btn-dark">Save vehicle</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
