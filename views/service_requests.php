<?php
require_once __DIR__ . '/../includes/auth.php';

requirePermission('service_requests.review');

require_once '../config/Database.php';
require_once '../models/ServiceRequest.php';
require_once '../includes/business_rules.php';
require_once '../includes/staff_ui.php';

$db = (new Database())->getConnection();
$serviceRequestModel = new ServiceRequest($db);

$statusFilter = $_GET['status'] ?? 'Pending';
$allowedFilters = ['All', 'Pending', 'Converted', 'Rejected', 'Cancelled'];

if (!in_array($statusFilter, $allowedFilters, true)) {
    $statusFilter = 'Pending';
}

// All requests are loaded; the status chips filter them on the page.
$stmtRequests = $serviceRequestModel->readAll('All');
$serviceRequests = $stmtRequests->fetchAll(PDO::FETCH_ASSOC);

$stmtMechanics = $serviceRequestModel->readActiveMechanics();
$mechanics = $stmtMechanics->fetchAll(PDO::FETCH_ASSOC);

$stmtCounts = $db->prepare("
    SELECT status, COUNT(*) AS total
    FROM service_request
    GROUP BY status
");
$stmtCounts->execute();
$countRows = $stmtCounts->fetchAll(PDO::FETCH_ASSOC);

$statusCounts = [
    'Pending' => 0,
    'Converted' => 0,
    'Rejected' => 0,
    'Cancelled' => 0
];

$totalRequests = 0;

foreach ($countRows as $row) {
    $status = $row['status'] ?? '';
    $total = intval($row['total'] ?? 0);

    if (isset($statusCounts[$status])) {
        $statusCounts[$status] = $total;
    }

    $totalRequests += $total;
}

function getServiceRequestStatusBadge($status) {
    switch ($status) {
        case 'Pending':
            return 'request-status status-pending';
        case 'Converted':
            return 'request-status status-converted';
        case 'Rejected':
            return 'request-status status-rejected';
        case 'Cancelled':
            return 'request-status status-cancelled';
        default:
            return 'request-status status-muted';
    }
}

function formatDateValue($dateValue) {
    if (empty($dateValue)) {
        return 'N/A';
    }

    return date('M d, Y', strtotime($dateValue));
}

function formatDateTimeValue($dateValue) {
    if (empty($dateValue)) {
        return 'N/A';
    }

    return date('M d, Y h:i A', strtotime($dateValue));
}

function formatTimeValue($timeValue) {
    if (empty($timeValue)) {
        return 'No preferred time';
    }

    return date('h:i A', strtotime($timeValue));
}

function cleanModalText($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES);
}

// Chip group for a request status
function requestChipKey($status) {
    if ($status === 'Pending') return 'pending';
    if ($status === 'Converted') return 'converted';
    return 'closed'; // Rejected or Cancelled
}

$statusLabels = ['Converted' => 'Converted to job'];
$requestDialogs = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Service Requests - Norily's Repair Shop</title>
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
            'Service Requests',
            'Requests customers sent from the website. Approving one creates a job order.'
        );
        cpFlash();
        ?>

        <div data-sp-filter="#requestRows">
            <div class="sp-toolbar">
                <div class="sp-search-field-inline">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                    <input type="search" id="serviceRequestSearch" class="cp-input" data-sp-search placeholder="Search request no., customer or service" aria-label="Search request no., customer or service" autocomplete="off">
                </div>
            </div>

            <div class="sp-chips" role="group" aria-label="Filter by status">
                <button type="button" class="sp-chip" data-sp-chip="all" aria-pressed="true">All <span class="sp-chip-count"><?= $totalRequests ?></span></button>
                <button type="button" class="sp-chip is-pend" data-sp-chip="pending" aria-pressed="false"><span class="sp-chip-dot"></span>Pending <span class="sp-chip-count"><?= $statusCounts['Pending'] ?></span></button>
                <button type="button" class="sp-chip is-prog" data-sp-chip="converted" aria-pressed="false"><span class="sp-chip-dot"></span>Converted <span class="sp-chip-count"><?= $statusCounts['Converted'] ?></span></button>
                <button type="button" class="sp-chip is-bad" data-sp-chip="closed" aria-pressed="false"><span class="sp-chip-dot"></span>Rejected or cancelled <span class="sp-chip-count"><?= $statusCounts['Rejected'] + $statusCounts['Cancelled'] ?></span></button>
            </div>

            <p class="sp-showing" data-sp-showing aria-live="polite">Showing <?= count($serviceRequests) ?> of <?= count($serviceRequests) ?></p>
        </div>

        <table class="cp-table sp-table" id="serviceRequestsTable">
            <thead>
                <tr>
                    <th scope="col">Request no.</th>
                    <th scope="col">Customer</th>
                    <th scope="col">Service / vehicle</th>
                    <th scope="col">Preferred date</th>
                    <th scope="col">Status</th>
                    <th scope="col"><span class="visually-hidden">Actions</span></th>
                </tr>
            </thead>
            <tbody id="requestRows" data-cp-paginate="10" data-cp-noun="request">
                <?php foreach ($serviceRequests as $request): ?>
                    <?php
                    $requestId = intval($request['service_request_id']);
                    $requestNo = $request['request_number'] ?? 'N/A';
                    $customerName = trim(($request['first_name'] ?? '') . ' ' . ($request['last_name'] ?? ''));
                    $serviceName = $request['service_name'] ?? 'N/A';
                    $vehicleName = trim(($request['make'] ?? '') . ' ' . ($request['model'] ?? ''));
                    $plate = $request['plate_number'] ?? '';
                    $requestStatus = $request['status'] ?? 'N/A';
                    $hasDate = !empty($request['preferred_appointment_date']);
                    $hasTime = !empty($request['preferred_appointment_time']);
                    $isPending = $requestStatus === 'Pending';
                    $badge = cpBadge($requestStatus, $statusLabels[$requestStatus] ?? $requestStatus);
                    $actionData = 'data-request-id="' . $requestId . '"'
                        . ' data-request-number="' . cpEscape($requestNo) . '"'
                        . ' data-service="' . cpEscape($serviceName) . '"'
                        . ' data-base-price="' . cpEscape($request['base_price'] ?? '0') . '"'
                        . ' data-customer-name="' . cpEscape($customerName) . '"'
                        . ' data-vehicle="' . cpEscape(trim($vehicleName . ($plate !== '' ? ' · ' . $plate : ''))) . '"';
                    $searchBlob = strtolower($requestNo . ' ' . $customerName . ' ' . ($request['contact_number'] ?? '') . ' ' . ($request['email'] ?? '') . ' ' . $serviceName . ' ' . $plate . ' ' . $vehicleName . ' ' . $requestStatus . ' ' . ($request['job_order_number'] ?? ''));
                    ?>
                    <tr data-status="<?= requestChipKey($requestStatus) ?>" data-search="<?= cpEscape($searchBlob) ?>">
                        <td data-label="Request no.">
                            <div>
                                <span class="sp-ref"><?= cpEscape($requestNo) ?></span>
                                <span class="cp-cell-sub">Sent <?= cpEscape(formatDateValue($request['created_at'] ?? null)) ?></span>
                            </div>
                        </td>
                        <td data-label="Customer"><?= cpEscape($customerName) ?></td>
                        <td data-label="Service / vehicle">
                            <div>
                                <span class="sp-cell-line"><?= cpEscape($serviceName) ?></span>
                                <span class="cp-cell-sub"><?= cpEscape(trim($vehicleName . ($plate !== '' ? ' · ' . $plate : ''))) ?></span>
                            </div>
                        </td>
                        <td data-label="Preferred date">
                            <div>
                                <span class="sp-cell-line"><?= $hasDate ? date('d M', strtotime($request['preferred_appointment_date'])) : '—' ?></span>
                                <span class="cp-cell-sub"><?= $hasTime ? date('g:i A', strtotime($request['preferred_appointment_time'])) : '—' ?></span>
                            </div>
                        </td>
                        <td data-label="Status">
                            <div>
                                <?= $badge ?>
                                <?php if (!empty($request['job_order_number'])): ?>
                                    <span class="cp-cell-sub sp-mono"><?= cpEscape($request['job_order_number']) ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="cp-num">
                            <div class="sp-row-actions">
                                <button type="button" class="cp-btn cp-btn-line cp-btn-sm" data-sp-open="#requestView<?= $requestId ?>">View</button>
                                <?php if ($isPending): ?>
                                    <button type="button" class="cp-btn cp-btn-dark cp-btn-sm" data-sp-open="#approveRequestDialog" <?= $actionData ?>>Approve</button>
                                    <button type="button" class="cp-btn cp-btn-line cp-btn-sm sp-btn-cancel" data-sp-open="#rejectRequestDialog" <?= $actionData ?>>Reject</button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>

                    <?php ob_start(); ?>
                    <div class="sp-dialog" id="requestView<?= $requestId ?>" role="dialog" aria-modal="true" aria-labelledby="requestViewTitle<?= $requestId ?>" hidden>
                        <div class="sp-dialog-box">
                            <div class="cp-modal-head">
                                <div>
                                    <p class="cp-label">Service request · <span class="sp-mono"><?= cpEscape($requestNo) ?></span> <?= $badge ?></p>
                                    <h2 class="cp-modal-title" id="requestViewTitle<?= $requestId ?>"><?= cpEscape($serviceName) ?></h2>
                                </div>
                                <button type="button" class="cp-modal-x" data-sp-close aria-label="Close">&times;</button>
                            </div>
                            <div class="sp-dialog-body">
                                <dl class="sp-facts">
                                    <div><dt>Customer</dt><dd><?= cpEscape($customerName) ?></dd></div>
                                    <div><dt>Contact</dt><dd><?= cpEscape($request['contact_number'] ?: '—') ?></dd></div>
                                    <div><dt>Email</dt><dd class="sp-fact-small"><?= cpEscape($request['email'] ?: '—') ?></dd></div>
                                    <div><dt>Vehicle</dt><dd><?= cpEscape($vehicleName !== '' ? $vehicleName : '—') ?><?php if ($plate !== ''): ?> <span class="sp-plate sp-plate-sm"><?= cpEscape($plate) ?></span><?php endif; ?></dd></div>
                                    <div><dt>Preferred date</dt><dd><?= $hasDate ? date('d M Y', strtotime($request['preferred_appointment_date'])) : '—' ?></dd></div>
                                    <div><dt>Preferred time</dt><dd><?= $hasTime ? date('g:i A', strtotime($request['preferred_appointment_time'])) : '—' ?></dd></div>
                                    <div class="is-wide"><dt>Concern</dt><dd class="sp-fact-text"><?= nl2br(cpEscape($request['concern_description'] ?: 'No concern details')) ?></dd></div>
                                    <?php if (!$isPending): ?>
                                        <div><dt>Estimated cost</dt><dd class="sp-mono"><?= !empty($request['estimated_cost']) ? cpMoney($request['estimated_cost']) : 'Not set' ?></dd></div>
                                        <div><dt>Mechanic</dt><dd><?= cpEscape($request['assigned_mechanic_name'] ?? '') ?: 'Not assigned' ?></dd></div>
                                        <div><dt>Job order</dt><dd class="sp-mono"><?= cpEscape($request['job_order_number'] ?? '') ?: 'Not converted' ?></dd></div>
                                    <?php endif; ?>
                                    <?php if (!empty($request['rejection_reason'])): ?>
                                        <div class="is-wide"><dt>Rejection reason</dt><dd class="sp-fact-text sp-text-bad"><?= nl2br(cpEscape($request['rejection_reason'])) ?></dd></div>
                                    <?php endif; ?>
                                </dl>
                            </div>
                            <div class="cp-modal-foot">
                                <button type="button" class="cp-btn cp-btn-line" data-sp-close>Close</button>
                                <?php if ($isPending): ?>
                                    <button type="button" class="cp-btn cp-btn-line" data-sp-open="#rejectRequestDialog" <?= $actionData ?>>Reject</button>
                                    <button type="button" class="cp-btn cp-btn-dark" data-sp-open="#approveRequestDialog" <?= $actionData ?>>Approve</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php $requestDialogs .= ob_get_clean(); ?>
                <?php endforeach; ?>

                <tr class="sp-empty-row" data-sp-empty hidden>
                    <td colspan="6">No service requests match your search or filter.</td>
                </tr>
                <?php if (empty($serviceRequests)): ?>
                    <tr class="sp-empty-row"><td colspan="6">No service requests yet. Requests sent from the website show up here.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </main>
</div>

<?= $requestDialogs ?>

<!-- Approve: creates the job order -->
<div class="sp-dialog" id="approveRequestDialog" role="dialog" aria-modal="true" aria-labelledby="approveRequestTitle" hidden>
    <div class="sp-dialog-box">
        <form action="../controllers/ServiceRequestController.php" method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="approve_convert">
            <input type="hidden" name="service_request_id" id="approve_service_request_id">

            <div class="cp-modal-head">
                <div>
                    <p class="cp-label" id="approve_ref"></p>
                    <h2 class="cp-modal-title" id="approveRequestTitle">Approve request</h2>
                </div>
                <button type="button" class="cp-modal-x" data-sp-close aria-label="Close">&times;</button>
            </div>

            <div class="sp-dialog-body">
                <?php if (empty($mechanics)): ?>
                    <p class="sp-callout is-bad">No active Head Mechanic account. Add or reactivate one under Owner › Staff Accounts first.</p>
                <?php endif; ?>
                <p class="sp-callout" id="approve_note"></p>

                <div class="sp-fgrid">
                    <div class="sp-field">
                        <label for="approve_estimated_cost">Estimated cost <span class="sp-req">*</span></label>
                        <input type="number" name="estimated_cost" id="approve_estimated_cost" class="cp-input" min="1" step="0.01" required>
                        <p class="cp-hint">Minimum service fee is <?= cpMoney(MIN_SERVICE_FEE) ?>.</p>
                    </div>
                    <div class="sp-field">
                        <label for="approve_mechanic">Assigned mechanic <span class="sp-req">*</span></label>
                        <select name="assigned_mechanic_id" id="approve_mechanic" class="cp-input" required>
                            <option value="">Select mechanic</option>
                            <?php foreach ($mechanics as $mechanic): ?>
                                <option value="<?= intval($mechanic['user_id']) ?>"><?= cpEscape($mechanic['first_name'] . ' ' . $mechanic['last_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sp-field">
                        <label for="approve_target">Target completion date</label>
                        <input type="date" name="expected_completion_date" id="approve_target" class="cp-input" min="<?= date('Y-m-d') ?>">
                        <p class="cp-hint">Optional</p>
                    </div>
                </div>
            </div>

            <div class="cp-modal-foot">
                <button type="button" class="cp-btn cp-btn-line" data-sp-close>Cancel</button>
                <?php if (!empty($mechanics)): ?>
                    <button type="submit" class="cp-btn cp-btn-dark">Approve &amp; create job order</button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Reject: the customer sees the reason -->
<div class="sp-dialog" id="rejectRequestDialog" role="dialog" aria-modal="true" aria-labelledby="rejectRequestTitle" hidden>
    <div class="sp-dialog-box">
        <form action="../controllers/ServiceRequestController.php" method="POST" id="rejectRequestForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="reject_request">
            <input type="hidden" name="service_request_id" id="reject_service_request_id">

            <div class="cp-modal-head">
                <div>
                    <p class="cp-label sp-mono" id="reject_request_number"></p>
                    <h2 class="cp-modal-title" id="rejectRequestTitle">Reject request</h2>
                </div>
                <button type="button" class="cp-modal-x" data-sp-close aria-label="Close">&times;</button>
            </div>

            <div class="sp-dialog-body">
                <div class="sp-field">
                    <label for="reject_reason">Reason <span class="sp-req">*</span></label>
                    <textarea name="rejection_reason" id="reject_reason" class="cp-input" rows="4" placeholder="The customer sees this reason. Example: the requested date is full, please pick another." required></textarea>
                </div>
            </div>

            <div class="cp-modal-foot">
                <button type="button" class="cp-btn cp-btn-line" data-sp-close>Cancel</button>
                <button type="submit" class="cp-btn cp-btn-dark">Reject request</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    document.getElementById('approveRequestDialog').addEventListener('sp:open', function (event) {
        const btn = event.detail.trigger;
        if (!btn || !btn.dataset.requestId) return;
        document.getElementById('approve_service_request_id').value = btn.dataset.requestId;
        document.getElementById('approve_ref').textContent = btn.dataset.requestNumber + ' · ' + btn.dataset.service;
        document.getElementById('approve_note').textContent = 'Approving creates a job order for ' + btn.dataset.customerName
            + (btn.dataset.vehicle ? "'s " + btn.dataset.vehicle : '') + ', marked as an Online request.';
        const base = parseFloat(btn.dataset.basePrice || '0');
        document.getElementById('approve_estimated_cost').value = base > 0 ? base.toFixed(2) : '';
    });

    document.getElementById('rejectRequestDialog').addEventListener('sp:open', function (event) {
        const btn = event.detail.trigger;
        if (!btn || !btn.dataset.requestId) return;
        document.getElementById('reject_service_request_id').value = btn.dataset.requestId;
        document.getElementById('reject_request_number').textContent = btn.dataset.requestNumber;
        document.getElementById('reject_reason').value = '';
    });

    document.getElementById('rejectRequestForm').addEventListener('submit', function (e) {
        const reason = document.getElementById('reject_reason');
        reason.value = reason.value.trim();
        if (reason.value === '') { e.preventDefault(); reason.focus(); }
    });
})();
</script>

</body>
</html>
