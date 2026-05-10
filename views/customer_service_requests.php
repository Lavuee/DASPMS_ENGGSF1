<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Customer') {
    header("Location: login.php");
    exit;
}

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
        die("Customer profile not found.");
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

function displayText($value, $fallback = 'N/A')
{
    $value = trim(html_entity_decode((string)($value ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    if ($value === '') {
        return htmlspecialchars($fallback);
    }

    return htmlspecialchars($value);
}

function cleanSearchText($value)
{
    return strtolower(trim(html_entity_decode((string)($value ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
}

function serviceRequestBadgeClass($status)
{
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
<title>My Service Requests - Norily's Repair Shop</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/style.css">

<style>
    .main-content {
        overflow-x: hidden;
        background: #f4f5f7;
    }

    .customer-requests-page {
        width: 100%;
        max-width: 100%;
    }

    .requests-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 1.2rem;
        padding-bottom: 1.05rem;
        border-bottom: 1px solid rgba(17, 24, 39, 0.10);
    }

    .requests-header h2 {
        color: #111827;
        font-size: 2rem;
        font-weight: 900;
        line-height: 1.1;
        margin-bottom: 0.25rem;
    }

    .requests-header p {
        color: #5f6b7a;
        font-size: 0.95rem;
        font-weight: 500;
        margin-bottom: 0;
    }

    .request-new-btn {
        min-height: 42px;
        border-radius: 999px;
        padding: 0.6rem 1rem;
        background: #f5c518;
        border: 1px solid #f5c518;
        color: #111827;
        font-size: 0.88rem;
        font-weight: 900;
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        text-decoration: none;
        white-space: nowrap;
        transition: 0.2s ease;
    }

    .request-new-btn:hover {
        background: #111827;
        border-color: #111827;
        color: #fff;
    }

    .requests-alert {
        border-radius: 16px;
        font-size: 0.92rem;
        margin-bottom: 1rem;
    }

    .requests-toolbar {
        display: grid;
        grid-template-columns: minmax(260px, 1fr) 150px;
        gap: 0.8rem;
        align-items: end;
        margin-bottom: 1rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid rgba(17, 24, 39, 0.08);
    }

    .requests-filter-label {
        display: block;
        color: #6b7280;
        font-size: 0.76rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.35px;
        margin-bottom: 0.35rem;
    }

    .requests-search-bar {
        min-height: 44px;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        background: rgba(255, 255, 255, 0.50);
        border: 1px solid rgba(17, 24, 39, 0.08);
        border-radius: 16px;
        padding: 0.65rem 0.9rem;
        transition: 0.2s ease;
    }

    .requests-search-bar:focus-within {
        background: rgba(255, 255, 255, 0.85);
        border-color: rgba(245, 197, 24, 0.72);
        box-shadow: 0 0 0 4px rgba(245, 197, 24, 0.12);
    }

    .requests-search-bar i {
        color: #5f6b7a;
        font-size: 1.05rem;
        flex-shrink: 0;
    }

    .requests-search-bar input {
        width: 100%;
        border: none;
        outline: none;
        background: transparent;
        color: #111827;
        font-size: 0.92rem;
        font-weight: 500;
    }

    .requests-search-bar input::placeholder {
        color: #6b7280;
    }

    .requests-clear-btn {
        min-height: 44px;
        border-radius: 999px;
        border: 1px solid rgba(17, 24, 39, 0.10);
        background: rgba(255, 255, 255, 0.50);
        color: #111827;
        font-size: 0.88rem;
        font-weight: 900;
        transition: 0.2s ease;
    }

    .requests-clear-btn:hover {
        background: #f5c518;
        border-color: #f5c518;
        color: #111827;
    }

    .request-tabs {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.6rem;
        padding-bottom: 1rem;
        margin-bottom: 0;
        border-bottom: 1px solid rgba(17, 24, 39, 0.09);
    }

    .request-tab {
        min-height: 38px;
        border-radius: 999px;
        padding: 0.48rem 0.82rem;
        border: 1px solid rgba(17, 24, 39, 0.10);
        background: rgba(255, 255, 255, 0.38);
        color: #5f6b7a;
        font-size: 0.83rem;
        font-weight: 900;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        transition: 0.2s ease;
    }

    .request-tab:hover,
    .request-tab.active {
        background: #f5c518;
        border-color: #f5c518;
        color: #111827;
    }

    .requests-table-wrap {
        width: 100%;
        overflow-x: auto;
        margin-top: 1rem;
    }

    .requests-table {
        width: 100%;
        min-width: 980px;
        border-collapse: collapse;
    }

    .requests-table thead th {
        color: #6b7280;
        font-size: 0.76rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.42px;
        padding: 0.95rem 0.95rem;
        border-bottom: 1px solid rgba(17, 24, 39, 0.12);
        white-space: nowrap;
        background: transparent;
    }

    .requests-table tbody td {
        padding: 1rem 0.95rem;
        border-bottom: 1px solid rgba(17, 24, 39, 0.065);
        vertical-align: middle;
        background: transparent;
    }

    .requests-table tbody tr:hover td {
        background: rgba(245, 197, 24, 0.045);
    }

    .request-primary-cell {
        display: flex;
        align-items: center;
        gap: 0.85rem;
        min-width: 0;
    }

    .request-icon {
        width: 42px;
        height: 42px;
        border-radius: 15px;
        background: rgba(245, 197, 24, 0.18);
        color: #111827;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.08rem;
        flex-shrink: 0;
    }

    .request-title {
        color: #111827;
        font-size: 0.98rem;
        font-weight: 900;
        line-height: 1.25;
        margin-bottom: 0.25rem;
        max-width: 260px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .request-muted {
        color: #5f6b7a;
        font-size: 0.78rem;
        font-weight: 700;
        line-height: 1.4;
    }

    .request-note {
        color: #6b7280;
        font-size: 0.78rem;
        line-height: 1.45;
        max-width: 310px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .vehicle-main,
    .appointment-main {
        color: #111827;
        font-size: 0.9rem;
        font-weight: 900;
        line-height: 1.35;
    }

    .vehicle-sub,
    .appointment-sub {
        color: #5f6b7a;
        font-size: 0.78rem;
        font-weight: 700;
        line-height: 1.45;
        margin-top: 0.22rem;
    }

    .request-jo-note {
        color: #047857;
        font-size: 0.78rem;
        font-weight: 900;
        line-height: 1.45;
        margin-top: 0.25rem;
    }

    .request-reject-note {
        color: #b91c1c;
        font-size: 0.78rem;
        font-weight: 800;
        line-height: 1.45;
        margin-top: 0.25rem;
        max-width: 280px;
    }

    .request-status {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.42rem 0.75rem;
        border-radius: 999px;
        font-size: 0.76rem;
        font-weight: 900;
        white-space: nowrap;
    }

    .status-pending {
        background: #fff3cd;
        color: #9a5b00;
    }

    .status-converted {
        background: #d7fbe5;
        color: #047857;
    }

    .status-rejected {
        background: #fee2e2;
        color: #b91c1c;
    }

    .status-cancelled,
    .status-muted {
        background: #eef0f3;
        color: #4b5563;
    }

    .cancel-request-btn {
        border: none;
        background: transparent;
        color: #b91c1c;
        font-size: 0.8rem;
        font-weight: 900;
        padding: 0;
        text-decoration: underline;
        text-underline-offset: 3px;
        text-decoration-thickness: 1.4px;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        white-space: nowrap;
    }

    .cancel-request-btn:hover {
        color: #991b1b;
        opacity: 0.82;
    }

    .no-action-text {
        color: #6b7280;
        font-size: 0.78rem;
        font-weight: 900;
        white-space: nowrap;
    }

    .empty-state {
        border-top: 1px solid rgba(17, 24, 39, 0.10);
        border-bottom: 1px solid rgba(17, 24, 39, 0.10);
        padding: 3.2rem 1rem;
        text-align: center;
        color: #5f6b7a;
        background: transparent;
        margin-top: 1rem;
    }

    .empty-state i {
        display: block;
        font-size: 2.2rem;
        color: #d1d5db;
        margin-bottom: 0.75rem;
    }

    .search-empty-state {
        display: none;
    }

    @media (max-width: 767.98px) {
        .requests-header {
            flex-direction: column;
            align-items: stretch;
        }

        .request-new-btn {
            width: 100%;
            justify-content: center;
        }

        .requests-toolbar {
            grid-template-columns: 1fr;
        }

        .requests-clear-btn {
            width: 100%;
        }

        .requests-table {
            min-width: 860px;
        }
    }

    @media (max-width: 575.98px) {
        .requests-header h2 {
            font-size: 1.55rem;
        }
    }
</style>
</head>

<body>

<div class="app-wrapper">
<?php include '../includes/sidebar.php'; ?>

<main class="main-content">
    <div class="customer-requests-page">

        <div class="requests-header">
            <div>
                <h2>My Service Requests</h2>
                <p>Track your online service appointments and shop approval status.</p>
            </div>

            <a href="customer_services.php" class="request-new-btn">
                <i class="bi bi-calendar-plus"></i>
                Request New Service
            </a>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show requests-alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show requests-alert">
                <i class="bi bi-exclamation-circle-fill me-2"></i>
                <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="requests-toolbar">
            <div>
                <label class="requests-filter-label" for="requestsSearch">Search</label>
                <div class="requests-search-bar">
                    <i class="bi bi-search"></i>
                    <input
                        type="text"
                        id="requestsSearch"
                        placeholder="Search by service, request number, plate number, concern, or status..."
                    >
                </div>
            </div>

            <button type="button" id="clearRequestSearch" class="requests-clear-btn">
                Clear
            </button>
        </div>

        <div class="request-tabs">
            <?php foreach ($allowedStatuses as $status): ?>
                <a
                    href="customer_service_requests.php?status=<?php echo urlencode($status); ?>"
                    class="request-tab <?php echo $currentStatus === $status ? 'active' : ''; ?>"
                >
                    <?php echo $status === 'Converted' ? 'Job Order Created' : htmlspecialchars($status); ?>
                    <span>(<?php echo intval($counts[$status] ?? 0); ?>)</span>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ($dbError): ?>
            <div class="empty-state">
                <i class="bi bi-exclamation-circle"></i>
                <h6 class="fw-bold mb-1">Service requests unavailable</h6>
                <p class="mb-0"><?php echo htmlspecialchars($dbError); ?></p>
            </div>

        <?php elseif (empty($requests)): ?>
            <div class="empty-state">
                <i class="bi bi-calendar-check"></i>
                <h6 class="fw-bold mb-1">No service requests found</h6>
                <p class="mb-0">No records match the selected status filter.</p>
            </div>

        <?php else: ?>
            <div class="requests-table-wrap">
                <table class="requests-table">
                    <thead>
                        <tr>
                            <th>Service Request</th>
                            <th>Vehicle</th>
                            <th>Appointment</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>
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
                            ?>

                            <tr class="request-row" data-search="<?php echo htmlspecialchars($searchText); ?>">
                                <td>
                                    <div class="request-primary-cell">
                                        <div class="request-icon">
                                            <i class="bi bi-wrench-adjustable-circle"></i>
                                        </div>

                                        <div>
                                            <div class="request-title">
                                                <?php echo displayText($request['service_name'] ?? 'Service'); ?>
                                            </div>

                                            <div class="request-muted">
                                                <?php echo displayText($request['request_number'] ?? 'N/A'); ?>
                                                · Requested: <?php echo htmlspecialchars($requestDate); ?>
                                            </div>

                                            <div class="request-note">
                                                <?php echo displayText($request['concern_description'] ?? 'No concern details'); ?>
                                            </div>

                                            <?php if ($status === 'Converted' && !empty($request['job_order_number'])): ?>
                                                <div class="request-jo-note">
                                                    <?php echo displayText($request['job_order_number']); ?> created
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($status === 'Rejected' && !empty($request['rejection_reason'])): ?>
                                                <div class="request-reject-note">
                                                    Reason: <?php echo displayText($request['rejection_reason']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <div class="vehicle-main">
                                        <?php echo displayText($requestVehicle !== '' ? $requestVehicle : 'Vehicle'); ?>
                                    </div>

                                    <div class="vehicle-sub">
                                        Plate: <?php echo displayText($request['plate_number'] ?? 'N/A'); ?>
                                    </div>

                                    <?php if (!empty($request['assigned_mechanic_name'])): ?>
                                        <div class="vehicle-sub">
                                            Mechanic: <?php echo displayText($request['assigned_mechanic_name']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <div class="appointment-main">
                                        <?php echo htmlspecialchars($preferredDate); ?>
                                    </div>

                                    <div class="appointment-sub">
                                        <?php echo !empty($preferredTime) ? htmlspecialchars($preferredTime) : 'No preferred time'; ?>
                                    </div>
                                </td>

                                <td>
                                    <span class="<?php echo serviceRequestBadgeClass($status); ?>">
                                        <?php echo htmlspecialchars($statusLabel); ?>
                                    </span>
                                </td>

                                <td>
                                    <?php if ($status === 'Pending'): ?>
                                        <form action="../controllers/ServiceRequestController.php" method="POST" class="m-0" onsubmit="return confirm('Cancel this pending service request?');">
                                            <input type="hidden" name="action" value="cancel_customer_request">
                                            <input type="hidden" name="service_request_id" value="<?php echo intval($request['service_request_id']); ?>">
                                            <button type="submit" class="cancel-request-btn">
                                                <i class="bi bi-x-circle"></i>
                                                Cancel
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="no-action-text">No action</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="empty-state search-empty-state" id="requestSearchEmpty">
                <i class="bi bi-search"></i>
                <h6 class="fw-bold mb-1">No matching service requests</h6>
                <p class="mb-0">Try another service name, request number, plate number, concern, or status.</p>
            </div>
        <?php endif; ?>

    </div>
</main>
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

        if (searchText.includes(keyword)) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    if (requestSearchEmpty) {
        requestSearchEmpty.style.display = visibleCount === 0 && requestRows.length > 0 ? 'block' : 'none';
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
</script>

</body>
</html>