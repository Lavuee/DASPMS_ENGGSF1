<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requirePermission('service_requests.submit');

require_once '../config/Database.php';
require_once '../models/ServiceRequest.php';

$db = (new Database())->getConnection();
$serviceRequestModel = new ServiceRequest($db);

$dbError = '';
$customer_id = null;
$requests = [];

$allowedStatuses = ['All', 'Pending', 'Converted', 'Rejected', 'Cancelled'];
$currentStatus = $_GET['status'] ?? 'All';

if (!in_array($currentStatus, $allowedStatuses, true)) {
    $currentStatus = 'All';
}

try {
    $customer_id = $serviceRequestModel->getCustomerIdByUserId($_SESSION['user_id']);

    if (!$customer_id) {
        require_once __DIR__ . '/../includes/error_page.php';
        renderErrorPage(404, 'Customer portal', 'Profile not found',
            'Your account is not linked to a customer profile yet. Please contact the shop so they can fix it.',
            [['label' => 'Contact the shop', 'href' => 'public_pages/contact.php', 'primary' => true], ['label' => '← Back to home', 'href' => '']]);
    }

    $stmtRequests = $serviceRequestModel->readByCustomer($customer_id);
    $allRequests = $stmtRequests->fetchAll(PDO::FETCH_ASSOC);

    $requests = array_values(array_filter($allRequests, function ($request) use ($currentStatus) {
        if ($currentStatus === 'All') {
            return true;
        }

        return ($request['status'] ?? '') === $currentStatus;
    }));

    $counts = [
        'All' => count($allRequests),
        'Pending' => 0,
        'Converted' => 0,
        'Rejected' => 0,
        'Cancelled' => 0
    ];

    foreach ($allRequests as $request) {
        $status = $request['status'] ?? '';

        if (isset($counts[$status])) {
            $counts[$status]++;
        }
    }
} catch (Exception $e) {
    $dbError = 'Unable to load your service requests at the moment.';
    $counts = [
        'All' => 0,
        'Pending' => 0,
        'Converted' => 0,
        'Rejected' => 0,
        'Cancelled' => 0
    ];
}

function cleanSearchText($value)
{
    return strtolower(trim(html_entity_decode((string)($value ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
}

function serviceRequestBadgeClass($status)
{
    switch ($status) {
        case 'Pending':
            return 'st pend';
        case 'Converted':
            return 'st prog';
        case 'Rejected':
            return 'st bad';
        case 'Cancelled':
            return 'st bad';
        default:
            return 'st done';
    }
}

function serviceRequestLabel($status)
{
    switch ($status) {
        case 'Pending':
            return 'Pending';
        case 'Converted':
            return 'Job Order Created';
        case 'Rejected':
            return 'Rejected';
        case 'Cancelled':
            return 'Cancelled';
        default:
            return 'N/A';
    }
}

function formatDateValue($date, $fallback = 'N/A')
{
    if (empty($date)) {
        return $fallback;
    }

    return date('M d, Y', strtotime($date));
}

function formatTimeValue($time)
{
    if (empty($time)) {
        return '';
    }

    return date('h:i A', strtotime($time));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Service Requests - Norily's Customer Portal</title>
<link rel="icon" type="image/png" href="../assets/images/logo.png">
<?php require_once __DIR__ . '/../includes/customer_ui.php'; cpHeadAssets(); ?>
</head>

<body class="cp-body">
<?php include '../includes/sidebar.php'; ?>

<main class="cp-main" id="cpMain">

    <?php
        cpPageHead(
            'My service requests',
            'Track your online service appointments and shop approval status.',
            '<a href="customer_services.php" class="cp-btn cp-btn-dark">Request new service</a>'
        );
    ?>

    <?php cpFlash(); ?>

    <div class="cp-toolbar">
        <ul class="cp-pills" aria-label="Filter by status">
            <?php foreach ($allowedStatuses as $status): ?>
                <li>
                    <a
                        href="customer_service_requests.php?status=<?php echo urlencode($status); ?>"
                        class="cp-pill<?php echo $currentStatus === $status ? ' is-active' : ''; ?>"
                        <?php echo $currentStatus === $status ? 'aria-current="page"' : ''; ?>
                    >
                        <?php echo $status === 'Converted' ? 'Job Order Created' : htmlspecialchars($status); ?>
                        <span class="cp-pill-count"><?php echo intval($counts[$status] ?? 0); ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <div class="d-flex gap-2 align-items-center">
            <label class="visually-hidden" for="requestsSearch">Search requests</label>
            <div class="cp-search flex-grow-1">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input type="search" id="requestsSearch" class="cp-input" placeholder="Search service, number, plate, status..." autocomplete="off">
            </div>
            <button type="button" id="clearRequestSearch" class="cp-link">Clear</button>
        </div>
    </div>

    <?php if ($dbError): ?>
        <div class="cp-empty">
            <strong>Service requests unavailable</strong>
            <?php echo htmlspecialchars($dbError); ?>
        </div>

    <?php elseif (empty($requests)): ?>
        <div class="cp-empty">
            <strong>No service requests found</strong>
            No records match the selected status filter.
            <div><a href="customer_services.php" class="cp-btn cp-btn-dark">Request a service</a></div>
        </div>

    <?php else: ?>
        <div class="cp-table-wrap">
            <table class="cp-table">
                <thead>
                    <tr>
                        <th>Service request</th>
                        <th>Vehicle</th>
                        <th>Appointment</th>
                        <th>Status</th>
                        <th class="cp-num">Action</th>
                    </tr>
                </thead>

                <tbody data-cp-paginate="10" data-cp-noun="request">
                    <?php foreach ($requests as $request): ?>
                        <?php
                            $requestDate = formatDateValue($request['created_at'] ?? '');
                            $preferredDate = formatDateValue($request['preferred_appointment_date'] ?? '');
                            $preferredTime = formatTimeValue($request['preferred_appointment_time'] ?? '');
                            $requestVehicle = trim(($request['make'] ?? '') . ' ' . ($request['model'] ?? ''));

                            $status = $request['status'] ?? '';
                            $statusLabel = serviceRequestLabel($status);

                            $searchText = cleanSearchText(
                                ($request['service_name'] ?? '') . ' ' .
                                ($request['request_number'] ?? '') . ' ' .
                                ($request['concern_description'] ?? '') . ' ' .
                                ($request['make'] ?? '') . ' ' .
                                ($request['model'] ?? '') . ' ' .
                                ($request['plate_number'] ?? '') . ' ' .
                                ($request['assigned_mechanic_name'] ?? '') . ' ' .
                                ($request['job_order_number'] ?? '') . ' ' .
                                ($request['rejection_reason'] ?? '') . ' ' .
                                $status . ' ' .
                                $statusLabel . ' ' .
                                $requestDate . ' ' .
                                $preferredDate . ' ' .
                                $preferredTime
                            );

                            // Everything the VIEW modal shows (filled in with textContent, so no HTML here)
                            $detail = [
                                'service' => plainText($request['service_name'] ?? '', 'Service'),
                                'number' => plainText($request['request_number'] ?? '', 'N/A'),
                                'status' => $statusLabel,
                                'badge' => serviceRequestBadgeClass($status),
                                'requested' => $requestDate,
                                'appointment' => $preferredDate . ($preferredTime !== '' ? ' · ' . $preferredTime : ' · No preferred time'),
                                'vehicle' => plainText($requestVehicle, 'Vehicle'),
                                'plate' => plainText($request['plate_number'] ?? '', 'N/A'),
                                'mechanic' => plainText($request['assigned_mechanic_name'] ?? '', ''),
                                'jo' => $status === 'Converted' ? plainText($request['job_order_number'] ?? '', '') : '',
                                'reason' => $status === 'Rejected' ? plainText($request['rejection_reason'] ?? '', '') : '',
                                'concern' => plainText($request['concern_description'] ?? '', 'No concern details'),
                            ];
                        ?>
                        <tr class="request-row" data-search="<?php echo htmlspecialchars($searchText); ?>">
                            <td data-label="Request">
                                <div>
                                    <span class="cp-cell-title"><?php echo displayText($request['service_name'] ?? 'Service'); ?></span>
                                    <span class="cp-cell-sub"><span class="cp-mono"><?php echo displayText($request['request_number'] ?? 'N/A'); ?></span> · Requested <span class="cp-mono"><?php echo htmlspecialchars($requestDate); ?></span></span>
                                    <span class="cp-cell-sub cp-clamp"><?php echo displayText($request['concern_description'] ?? 'No concern details'); ?></span>

                                    <?php if ($status === 'Converted' && !empty($request['job_order_number'])): ?>
                                        <span class="cp-cell-sub"><span class="cp-mono"><?php echo displayText($request['job_order_number']); ?></span> created</span>
                                    <?php endif; ?>

                                    <?php if ($status === 'Rejected' && !empty($request['rejection_reason'])): ?>
                                        <span class="cp-cell-sub text-danger">Reason: <?php echo displayText($request['rejection_reason']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <td data-label="Vehicle">
                                <div>
                                    <span class="cp-cell-title"><?php echo displayText($requestVehicle !== '' ? $requestVehicle : 'Vehicle'); ?></span>
                                    <span class="cp-cell-sub">Plate <span class="cp-mono"><?php echo displayText($request['plate_number'] ?? 'N/A'); ?></span></span>
                                    <?php if (!empty($request['assigned_mechanic_name'])): ?>
                                        <span class="cp-cell-sub">Mechanic: <?php echo displayText($request['assigned_mechanic_name']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <td data-label="Appointment">
                                <div>
                                    <span class="cp-cell-title cp-mono fw-normal"><?php echo htmlspecialchars($preferredDate); ?></span>
                                    <span class="cp-cell-sub cp-mono"><?php echo !empty($preferredTime) ? htmlspecialchars($preferredTime) : 'No preferred time'; ?></span>
                                </div>
                            </td>

                            <td data-label="Status">
                                <div><span class="<?php echo serviceRequestBadgeClass($status); ?>"><?php echo htmlspecialchars($statusLabel); ?></span></div>
                            </td>

                            <td data-label="Action" class="cp-num">
                                <div class="cp-row-end">
                                    <button type="button" class="cp-link" data-bs-toggle="modal" data-bs-target="#requestDetailModal" data-detail="<?php echo htmlspecialchars(json_encode($detail), ENT_QUOTES, 'UTF-8'); ?>">View</button>

                                    <?php if ($status === 'Pending'): ?>
                                        <form action="../controllers/ServiceRequestController.php" method="POST" class="m-0"
                                              data-cp-confirm="Cancel this pending service request?"
                                              data-cp-confirm-title="Cancel request?"
                                              data-cp-confirm-yes="Yes, cancel it"
                                              data-cp-confirm-no="Keep request">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="cancel_customer_request">
                                            <input type="hidden" name="service_request_id" value="<?php echo intval($request['service_request_id']); ?>">
                                            <button type="submit" class="cp-link cp-link-danger">Cancel</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="cp-empty mt-3" id="requestSearchEmpty" hidden>
            <strong>No matching service requests</strong>
            Try another service name, request number, plate number, concern, or status.
        </div>
    <?php endif; ?>

</main>

<!-- VIEW REQUEST MODAL -->
<div class="modal fade cp-modal" id="requestDetailModal" tabindex="-1" aria-labelledby="requestDetailTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="cp-modal-head">
                <div>
                    <p class="cp-label">Service request <span class="cp-mono" data-field="number"></span></p>
                    <h2 class="cp-modal-title" id="requestDetailTitle" data-field="service"></h2>
                </div>
                <button type="button" class="cp-modal-x" data-bs-dismiss="modal" aria-label="Close">&times;</button>
            </div>
            <div class="cp-modal-body cp-modal-scroll">
                <p class="mb-3"><span data-field="badge"></span></p>
                <dl class="cp-dl">
                    <dt>Requested</dt><dd class="cp-mono" data-field="requested"></dd>
                    <dt>Appointment</dt><dd class="cp-mono" data-field="appointment"></dd>
                    <dt>Vehicle</dt><dd data-field="vehicle"></dd>
                    <dt>Plate</dt><dd class="cp-mono" data-field="plate"></dd>
                    <dt data-row="mechanic">Mechanic</dt><dd data-row="mechanic" data-field="mechanic"></dd>
                    <dt data-row="jo">Job order</dt><dd data-row="jo" class="cp-mono" data-field="jo"></dd>
                    <dt data-row="reason">Rejection reason</dt><dd data-row="reason" data-field="reason"></dd>
                    <dt>Concern</dt><dd class="cp-preline" data-field="concern"></dd>
                </dl>
            </div>
            <div class="cp-modal-foot">
                <button type="button" class="cp-btn cp-btn-line" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
const requestSearch = document.getElementById('requestsSearch');
const clearRequestSearch = document.getElementById('clearRequestSearch');
const requestRows = document.querySelectorAll('.request-row');
const requestSearchEmpty = document.getElementById('requestSearchEmpty');

function filterRequestRows() {
    const keyword = requestSearch ? requestSearch.value.trim().toLowerCase() : '';
    let visibleCount = 0;

    requestRows.forEach(row => {
        const searchText = row.dataset.search || '';

        // Matching rows stay in the list; the pager decides which page shows them.
        if (searchText.includes(keyword)) {
            row.dataset.cpFiltered = '';
            visibleCount++;
        } else {
            row.dataset.cpFiltered = '1';
        }
    });

    if (window.cpRepaginate && requestRows.length) {
        window.cpRepaginate(requestRows[0].parentElement);
    } else {
        requestRows.forEach(row => { row.hidden = row.dataset.cpFiltered === '1'; });
    }

    if (requestSearchEmpty) {
        requestSearchEmpty.hidden = !(visibleCount === 0 && requestRows.length > 0);
    }
}

if (requestSearch) {
    requestSearch.addEventListener('input', filterRequestRows);
}

if (clearRequestSearch) {
    clearRequestSearch.addEventListener('click', function () {
        if (requestSearch) {
            requestSearch.value = '';
            filterRequestRows();
            requestSearch.focus();
        }
    });
}

// VIEW modal: fill the fields from the clicked row's data-detail
const requestDetailModal = document.getElementById('requestDetailModal');

if (requestDetailModal) {
    requestDetailModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        if (!button) return;

        let detail = {};
        try { detail = JSON.parse(button.getAttribute('data-detail') || '{}'); } catch (e) {}

        requestDetailModal.querySelectorAll('[data-field]').forEach(el => {
            const key = el.dataset.field;
            if (key === 'badge') {
                el.innerHTML = '';
                const badge = document.createElement('span');
                badge.className = detail.badge || 'st done';
                badge.textContent = detail.status || '';
                el.appendChild(badge);
            } else {
                el.textContent = detail[key] || '';
            }
        });

        ['mechanic', 'jo', 'reason'].forEach(key => {
            requestDetailModal.querySelectorAll('[data-row="' + key + '"]').forEach(el => {
                el.hidden = !detail[key];
            });
        });
    });
}
</script>

</body>
</html>