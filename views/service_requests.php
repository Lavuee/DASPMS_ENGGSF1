<?php
session_start();

if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['role'], ['Owner', 'Cashier'], true)) {
    header("Location: login.php");
    exit;
}

require_once '../config/Database.php';
require_once '../models/ServiceRequest.php';

$db = (new Database())->getConnection();
$serviceRequestModel = new ServiceRequest($db);

$statusFilter = $_GET['status'] ?? 'Pending';
$allowedFilters = ['All', 'Pending', 'Converted', 'Rejected', 'Cancelled'];

if (!in_array($statusFilter, $allowedFilters, true)) {
    $statusFilter = 'Pending';
}

$stmtRequests = $serviceRequestModel->readAll($statusFilter);
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Service Requests - Norily's Repair Shop</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/style.css">

<style>
    .main-content {
        overflow-x: hidden;
    }

    .service-requests-page {
        width: 100%;
        max-width: 100%;
    }

    .sr-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 1.5rem;
    }

    .sr-header h2 {
        font-size: 2rem;
        font-weight: 900;
        color: var(--dashboard-text-main);
        margin-bottom: 0.25rem;
        line-height: 1.1;
    }

    .sr-count-text {
        color: var(--dashboard-text-muted);
        font-size: 0.95rem;
        font-weight: 500;
        margin-bottom: 0;
    }

    .sr-date-pill {
        min-height: 44px;
        background: #fff;
        border: 1px solid #e5e7eb;
        color: var(--dashboard-text-muted);
        border-radius: 999px;
        padding: 0.58rem 0.95rem;
        font-size: 0.9rem;
        font-weight: 800;
        white-space: nowrap;
        display: inline-flex;
        align-items: center;
        box-shadow: 0 4px 12px rgba(17, 17, 17, 0.035);
    }

    .request-alert {
        border-radius: 16px;
        font-size: 0.92rem;
        margin-bottom: 1.5rem;
    }

    .sr-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 2rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid #e5e7eb;
        margin-bottom: 1.4rem;
    }

    .sr-tab {
        border: none;
        background: transparent;
        color: var(--dashboard-text-muted);
        font-size: 0.92rem;
        font-weight: 600;
        padding: 0;
        cursor: pointer;
        transition: 0.2s ease;
        text-decoration: none;
    }

    .sr-tab:hover {
        color: var(--black);
    }

    .sr-tab.active {
        color: var(--dashboard-primary);
        font-weight: 800;
    }

    .sr-search-wrap {
        margin-bottom: 1.5rem;
    }

    .sr-search-bar {
        width: 100%;
        min-height: 44px;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        padding: 0.65rem 0.9rem;
        box-shadow: 0 4px 12px rgba(17, 17, 17, 0.035);
    }

    .sr-search-bar i {
        font-size: 1.05rem;
        color: var(--dashboard-text-muted);
        flex-shrink: 0;
    }

    .sr-search-bar input {
        width: 100%;
        border: none;
        outline: none;
        background: transparent;
        color: var(--dashboard-text-main);
        font-size: 0.92rem;
    }

    .sr-search-bar input::placeholder {
        color: #6b7280;
    }

    .sr-table-wrap {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .sr-table {
        width: 100%;
        min-width: 1180px;
        border-collapse: collapse;
        background: transparent;
        margin-bottom: 0;
    }

    .sr-table thead th {
        color: var(--dashboard-text-muted);
        font-size: 0.82rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.35px;
        padding: 0.9rem 0.75rem;
        border-bottom: 1px solid #dcdfe4;
        background: transparent;
        white-space: nowrap;
    }

    .sr-table tbody td {
        padding: 1rem 0.75rem;
        border-bottom: 1px solid #e8ebef;
        vertical-align: middle;
        color: var(--dashboard-text-main);
        background: transparent;
        font-size: 0.95rem;
    }

    .sr-table tbody tr:hover td {
        background: rgba(245, 197, 24, 0.035);
    }

    .sr-table th:last-child,
    .sr-table td:last-child {
        min-width: 230px;
    }

    .sr-request-number {
        font-family: monospace;
        font-size: 0.9rem;
        font-weight: 800;
        color: var(--dashboard-text-main);
        max-width: 135px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .sr-main {
        font-size: 0.92rem;
        font-weight: 900;
        color: var(--dashboard-text-main);
        line-height: 1.25;
        white-space: nowrap;
    }

    .sr-muted {
        color: var(--dashboard-text-muted);
        font-size: 0.78rem;
        margin-top: 0.2rem;
        line-height: 1.35;
        max-width: 210px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-weight: 500;
    }

    .request-status {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        padding: 0.42rem 0.72rem;
        font-size: 0.78rem;
        font-weight: 900;
        white-space: nowrap;
    }

    .status-pending {
        background: #fff5db;
        color: #b77900;
    }

    .status-converted {
        background: #e8f7ef;
        color: #15803d;
    }

    .status-rejected {
        background: #fee2e2;
        color: #b91c1c;
    }

    .status-cancelled,
    .status-muted {
        background: #f3f4f6;
        color: #6b7280;
    }

    .request-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 0.45rem;
        flex-wrap: nowrap;
    }

    .request-action-btn {
        min-height: 38px;
        border-radius: 999px;
        padding: 0.45rem 0.65rem;
        border: 1px solid #e5e7eb;
        background: #fff;
        color: var(--dashboard-text-muted);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.35rem;
        text-decoration: none;
        font-size: 0.78rem;
        font-weight: 900;
        transition: all 0.2s ease;
        white-space: nowrap;
    }

    .request-action-btn:hover {
        transform: translateY(-1px);
    }

    .btn-view:hover {
        border-color: var(--dashboard-primary);
        background: #fffaf0;
        color: var(--black);
    }

    .btn-review {
        border-color: #bbf7d0;
        background: #ecfdf5;
        color: #15803d;
    }

    .btn-reject {
        border-color: #fecdd3;
        background: #fff1f2;
        color: #be123c;
    }

    .reviewed-text {
        color: var(--dashboard-text-muted);
        font-size: 0.8rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .empty-state {
        padding: 3rem 1rem;
        text-align: center;
        color: var(--dashboard-text-muted);
        font-size: 0.95rem;
    }

    .empty-state i {
        display: block;
        color: var(--dashboard-primary);
        font-size: 2.25rem;
        margin-bottom: 0.75rem;
    }

    .empty-state .fw-bold {
        color: var(--dashboard-text-main);
        font-size: 0.95rem;
        font-weight: 900 !important;
    }

    .sr-pagination-wrap {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 0.35rem;
        margin-top: 1rem;
        flex-wrap: wrap;
    }

    .sr-page-btn {
        min-width: 38px;
        min-height: 38px;
        border: 1px solid #e5e7eb;
        background: #fff;
        color: var(--dashboard-text-muted);
        border-radius: 999px;
        font-size: 0.82rem;
        font-weight: 900;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.45rem 0.75rem;
        transition: 0.2s ease;
    }

    .sr-page-btn:hover:not(:disabled) {
        border-color: var(--dashboard-primary);
        background: #fffaf0;
        color: var(--black);
    }

    .sr-page-btn.active {
        background: var(--dashboard-primary);
        border-color: var(--dashboard-primary);
        color: var(--black);
    }

    .sr-page-btn:disabled {
        opacity: 0.45;
        cursor: not-allowed;
    }

    .request-modal .modal-content {
        border: none;
        border-radius: 22px;
        overflow: hidden;
        box-shadow: 0 25px 60px rgba(17, 17, 17, 0.18);
    }

    .request-modal .modal-header {
        background: #fff;
        border-bottom: 1px solid #e5e7eb;
        padding: 1.15rem 1.35rem;
    }

    .request-modal .modal-title {
        font-weight: 900;
        color: var(--dashboard-text-main);
    }

    .request-modal .modal-body {
        padding: 1.35rem;
    }

    .request-modal .modal-footer {
        border-top: 1px solid #e5e7eb;
        padding: 1rem 1.35rem;
    }

    .form-label-custom {
        font-size: 0.78rem;
        font-weight: 900;
        color: #4b5563;
        text-transform: uppercase;
        letter-spacing: 0.35px;
        margin-bottom: 0.35rem;
    }

    .form-control-custom {
        min-height: 44px;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        padding: 0.65rem 0.85rem;
        color: var(--dashboard-text-main);
        font-size: 0.92rem;
        box-shadow: none !important;
    }

    .form-control-custom:focus {
        border-color: var(--dashboard-primary);
        box-shadow: 0 0 0 4px rgba(245, 197, 24, 0.18) !important;
    }

    .modal-save-btn {
        background: var(--dashboard-primary);
        border: 1px solid var(--dashboard-primary);
        color: var(--black);
        font-weight: 900;
        border-radius: 999px;
        padding: 0.65rem 1.1rem;
    }

    .modal-save-btn:hover {
        background: var(--black);
        border-color: var(--black);
        color: var(--white);
    }

    .modal-danger-btn {
        background: #fee2e2;
        border: 1px solid #fee2e2;
        color: #b91c1c;
        font-weight: 900;
        border-radius: 999px;
        padding: 0.65rem 1.1rem;
    }

    .modal-danger-btn:hover {
        background: #b91c1c;
        border-color: #b91c1c;
        color: #fff;
    }

    .modal-cancel-btn {
        border-radius: 999px;
        font-weight: 800;
        padding: 0.65rem 1.1rem;
    }

    .detail-box {
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 16px;
        padding: 1rem;
        height: 100%;
    }

    .detail-label {
        font-size: 0.72rem;
        font-weight: 900;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.35px;
        margin-bottom: 0.25rem;
    }

    .detail-value {
        color: var(--dashboard-text-main);
        font-size: 0.95rem;
        font-weight: 800;
        margin-bottom: 0;
    }

    @media (max-width: 767.98px) {
        .sr-header {
            flex-direction: column;
            align-items: stretch;
        }

        .sr-date-pill {
            width: 100%;
            justify-content: center;
        }

        .sr-tabs {
            gap: 1.35rem;
        }

        .sr-header h2 {
            font-size: 1.75rem;
        }

        .sr-pagination-wrap {
            justify-content: center;
        }
    }
</style>
</head>

<body>

<div class="app-wrapper">
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="service-requests-page">

            <div class="sr-header">
                <div>
                    <h2>Service Requests</h2>
                    <p class="sr-count-text">
                        <?php echo $totalRequests; ?> total request<?php echo $totalRequests !== 1 ? 's' : ''; ?>
                    </p>
                </div>

                <div class="sr-date-pill">
                    <i class="bi bi-calendar-event me-2"></i>
                    <?php echo date('M d, Y'); ?>
                </div>
            </div>

            <?php if(isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success request-alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
                </div>
            <?php endif; ?>

            <?php if(isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger request-alert">
                    <i class="bi bi-exclamation-circle-fill me-2"></i>
                    <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>

            <div class="sr-tabs">
                <a href="service_requests.php?status=All" class="sr-tab <?php echo $statusFilter === 'All' ? 'active' : ''; ?>">
                    All (<?php echo $totalRequests; ?>)
                </a>

                <a href="service_requests.php?status=Pending" class="sr-tab <?php echo $statusFilter === 'Pending' ? 'active' : ''; ?>">
                    Pending (<?php echo $statusCounts['Pending']; ?>)
                </a>

                <a href="service_requests.php?status=Converted" class="sr-tab <?php echo $statusFilter === 'Converted' ? 'active' : ''; ?>">
                    Converted (<?php echo $statusCounts['Converted']; ?>)
                </a>

                <a href="service_requests.php?status=Rejected" class="sr-tab <?php echo $statusFilter === 'Rejected' ? 'active' : ''; ?>">
                    Rejected (<?php echo $statusCounts['Rejected']; ?>)
                </a>

                <a href="service_requests.php?status=Cancelled" class="sr-tab <?php echo $statusFilter === 'Cancelled' ? 'active' : ''; ?>">
                    Cancelled (<?php echo $statusCounts['Cancelled']; ?>)
                </a>
            </div>

            <div class="sr-search-wrap">
                <div class="sr-search-bar">
                    <i class="bi bi-search"></i>
                    <input
                        type="text"
                        id="serviceRequestSearch"
                        placeholder="Search by request number, customer, contact, service, vehicle, date, or status..."
                    >
                </div>
            </div>

            <div class="sr-table-wrap">
                <table class="sr-table" id="serviceRequestsTable">
                    <thead>
                        <tr>
                            <th>Request No.</th>
                            <th>Customer</th>
                            <th>Service / Vehicle</th>
                            <th>Preferred Date</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (empty($serviceRequests)): ?>
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">
                                        <i class="bi bi-calendar-check"></i>
                                        <div class="fw-bold mb-1">No service requests found</div>
                                        <div>No records match the selected filter.</div>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($serviceRequests as $request): ?>
                                <?php
                                    $customerName = trim(($request['first_name'] ?? '') . ' ' . ($request['last_name'] ?? ''));
                                    $serviceName = $request['service_name'] ?? 'N/A';
                                    $vehicleText = trim(($request['plate_number'] ?? 'N/A') . ' · ' . ($request['make'] ?? '') . ' ' . ($request['model'] ?? ''));
                                    $preferredDate = formatDateValue($request['preferred_appointment_date'] ?? null);
                                    $preferredTime = formatTimeValue($request['preferred_appointment_time'] ?? null);
                                    $requestStatus = $request['status'] ?? 'N/A';
                                    $searchBlob = strtolower(
                                        ($request['request_number'] ?? '') . ' ' .
                                        $customerName . ' ' .
                                        ($request['contact_number'] ?? '') . ' ' .
                                        ($request['email'] ?? '') . ' ' .
                                        $serviceName . ' ' .
                                        $vehicleText . ' ' .
                                        $preferredDate . ' ' .
                                        $preferredTime . ' ' .
                                        $requestStatus . ' ' .
                                        ($request['job_order_number'] ?? '')
                                    );
                                ?>

                                <tr class="service-request-row" data-search="<?php echo htmlspecialchars($searchBlob); ?>">
                                    <td>
                                        <div class="sr-request-number" title="<?php echo htmlspecialchars($request['request_number'] ?? 'N/A'); ?>">
                                            <?php echo htmlspecialchars($request['request_number'] ?? 'N/A'); ?>
                                        </div>
                                        <div class="sr-muted">
                                            <?php echo formatDateValue($request['created_at'] ?? null); ?>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="sr-main">
                                            <?php echo htmlspecialchars($customerName); ?>
                                        </div>
                                        <div class="sr-muted">
                                            <?php echo htmlspecialchars($request['contact_number'] ?? 'No contact'); ?>
                                            <?php if (!empty($request['email'])): ?>
                                                · <?php echo htmlspecialchars($request['email']); ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="sr-main">
                                            <?php echo htmlspecialchars($serviceName); ?>
                                        </div>
                                        <div class="sr-muted">
                                            <?php echo htmlspecialchars($vehicleText); ?>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="sr-main">
                                            <?php echo htmlspecialchars($preferredDate); ?>
                                        </div>
                                        <div class="sr-muted">
                                            <?php echo htmlspecialchars($preferredTime); ?>
                                        </div>
                                    </td>

                                    <td>
                                        <span class="<?php echo getServiceRequestStatusBadge($requestStatus); ?>">
                                            <?php echo htmlspecialchars($requestStatus); ?>
                                        </span>

                                        <?php if (!empty($request['job_order_number'])): ?>
                                            <div class="sr-muted">
                                                <?php echo htmlspecialchars($request['job_order_number']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <td class="text-end">
                                        <div class="request-actions">
                                            <button
                                                type="button"
                                                class="request-action-btn btn-view"
                                                data-bs-toggle="modal"
                                                data-bs-target="#viewRequestModal"
                                                data-request-number="<?php echo cleanModalText($request['request_number'] ?? ''); ?>"
                                                data-customer-name="<?php echo cleanModalText(($request['first_name'] ?? '') . ' ' . ($request['last_name'] ?? '')); ?>"
                                                data-contact="<?php echo cleanModalText($request['contact_number'] ?? ''); ?>"
                                                data-email="<?php echo cleanModalText($request['email'] ?? ''); ?>"
                                                data-service="<?php echo cleanModalText($request['service_name'] ?? ''); ?>"
                                                data-vehicle="<?php echo cleanModalText(($request['plate_number'] ?? '') . ' - ' . ($request['make'] ?? '') . ' ' . ($request['model'] ?? '')); ?>"
                                                data-concern="<?php echo cleanModalText($request['concern_description'] ?? ''); ?>"
                                                data-preferred="<?php echo cleanModalText(formatDateValue($request['preferred_appointment_date'] ?? null) . ' ' . formatTimeValue($request['preferred_appointment_time'] ?? null)); ?>"
                                                data-status="<?php echo cleanModalText($request['status'] ?? ''); ?>"
                                                data-estimated-cost="<?php echo cleanModalText(!empty($request['estimated_cost']) ? '₱' . number_format(floatval($request['estimated_cost']), 2) : 'Not yet set'); ?>"
                                                data-mechanic="<?php echo cleanModalText($request['assigned_mechanic_name'] ?? 'Not assigned'); ?>"
                                                data-job-order="<?php echo cleanModalText($request['job_order_number'] ?? 'Not converted'); ?>"
                                                data-rejection-reason="<?php echo cleanModalText($request['rejection_reason'] ?? ''); ?>"
                                            >
                                                <i class="bi bi-eye"></i>
                                                View
                                            </button>

                                            <?php if (($request['status'] ?? '') === 'Pending'): ?>
                                                <button
                                                    type="button"
                                                    class="request-action-btn btn-review"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#approveRequestModal"
                                                    data-service-request-id="<?php echo intval($request['service_request_id']); ?>"
                                                    data-request-number="<?php echo cleanModalText($request['request_number'] ?? ''); ?>"
                                                    data-service="<?php echo cleanModalText($request['service_name'] ?? ''); ?>"
                                                    data-base-price="<?php echo cleanModalText($request['base_price'] ?? '0'); ?>"
                                                    data-customer-name="<?php echo cleanModalText(($request['first_name'] ?? '') . ' ' . ($request['last_name'] ?? '')); ?>"
                                                    data-vehicle="<?php echo cleanModalText(($request['plate_number'] ?? '') . ' - ' . ($request['make'] ?? '') . ' ' . ($request['model'] ?? '')); ?>"
                                                >
                                                    <i class="bi bi-check-circle"></i>
                                                    Approve
                                                </button>

                                                <button
                                                    type="button"
                                                    class="request-action-btn btn-reject"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#rejectRequestModal"
                                                    data-service-request-id="<?php echo intval($request['service_request_id']); ?>"
                                                    data-request-number="<?php echo cleanModalText($request['request_number'] ?? ''); ?>"
                                                >
                                                    <i class="bi bi-x-circle"></i>
                                                    Reject
                                                </button>
                                            <?php else: ?>
                                                <span class="reviewed-text">Reviewed</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <tr id="noServiceRequestResults" style="display:none;">
                                <td colspan="6">
                                    <div class="empty-state">
                                        <i class="bi bi-search"></i>
                                        <div class="fw-bold mb-1">No matching service requests found</div>
                                        <div>Try another keyword or change the selected tab.</div>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="sr-pagination-wrap" id="serviceRequestPagination"></div>

        </div>
    </main>
</div>

<!-- VIEW REQUEST MODAL -->
<div class="modal fade request-modal" id="viewRequestModal" tabindex="-1" aria-labelledby="viewRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="viewRequestModalLabel">Service Request Details</h5>
                    <p class="mb-0 text-muted small">Complete information submitted by the customer.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="detail-box">
                            <div class="detail-label">Request Number</div>
                            <p class="detail-value" id="view_request_number"></p>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="detail-box">
                            <div class="detail-label">Status</div>
                            <p class="detail-value" id="view_status"></p>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="detail-box">
                            <div class="detail-label">Preferred Appointment</div>
                            <p class="detail-value" id="view_preferred"></p>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="detail-box">
                            <div class="detail-label">Customer</div>
                            <p class="detail-value" id="view_customer_name"></p>
                            <div class="sr-muted" id="view_customer_contact"></div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="detail-box">
                            <div class="detail-label">Vehicle</div>
                            <p class="detail-value" id="view_vehicle"></p>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="detail-box">
                            <div class="detail-label">Service</div>
                            <p class="detail-value" id="view_service"></p>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="detail-box">
                            <div class="detail-label">Estimated Cost / Mechanic</div>
                            <p class="detail-value" id="view_estimated_cost"></p>
                            <div class="sr-muted" id="view_mechanic"></div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="detail-box">
                            <div class="detail-label">Customer Concern</div>
                            <p class="detail-value" id="view_concern" style="white-space: pre-line;"></p>
                        </div>
                    </div>

                    <div class="col-12" id="view_rejection_box" style="display: none;">
                        <div class="detail-box">
                            <div class="detail-label">Rejection Reason</div>
                            <p class="detail-value text-danger" id="view_rejection_reason"></p>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="detail-box">
                            <div class="detail-label">Converted Job Order</div>
                            <p class="detail-value" id="view_job_order"></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary modal-cancel-btn" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- APPROVE REQUEST MODAL -->
<div class="modal fade request-modal" id="approveRequestModal" tabindex="-1" aria-labelledby="approveRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form action="../controllers/ServiceRequestController.php" method="POST" class="modal-content">
            <input type="hidden" name="action" value="approve_convert">
            <input type="hidden" name="service_request_id" id="approve_service_request_id">

            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="approveRequestModalLabel">Approve Service Request</h5>
                    <p class="mb-0 text-muted small">Approving will automatically create an official job order.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <?php if (empty($mechanics)): ?>
                    <div class="alert alert-warning" style="border-radius: 14px;">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        No active Head Mechanic account found. Please add or activate a mechanic first.
                    </div>
                <?php endif; ?>

                <div class="alert alert-light border mb-3" style="border-radius: 14px;">
                    <i class="bi bi-info-circle me-1"></i>
                    Request <strong id="approve_request_number"></strong> will be converted into a job order with source marked as <strong>Online</strong>.
                    The customer's preferred appointment date remains the appointment basis.
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="detail-box">
                            <div class="detail-label">Customer</div>
                            <p class="detail-value" id="approve_customer_name"></p>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="detail-box">
                            <div class="detail-label">Vehicle</div>
                            <p class="detail-value" id="approve_vehicle"></p>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="detail-box">
                            <div class="detail-label">Requested Service</div>
                            <p class="detail-value" id="approve_service"></p>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label-custom">Estimated Cost <span class="text-danger">*</span></label>
                        <input
                            type="number"
                            name="estimated_cost"
                            id="approve_estimated_cost"
                            class="form-control form-control-custom"
                            min="1"
                            step="0.01"
                            required
                        >
                    </div>

                    <div class="col-md-4">
                        <label class="form-label-custom">Assigned Mechanic <span class="text-danger">*</span></label>
                        <select name="assigned_mechanic_id" class="form-select form-control-custom" required>
                            <option value="">Select mechanic</option>
                            <?php foreach ($mechanics as $mechanic): ?>
                                <option value="<?php echo intval($mechanic['user_id']); ?>">
                                    <?php echo htmlspecialchars($mechanic['first_name'] . ' ' . $mechanic['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label-custom">Target Completion Date <span class="text-muted">(Optional)</span></label>
                        <input
                            type="date"
                            name="expected_completion_date"
                            class="form-control form-control-custom"
                            min="<?php echo date('Y-m-d'); ?>"
                        >
                        <div class="sr-muted mt-1">
                            Optional internal target only. Not required by the service request.
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary modal-cancel-btn" data-bs-dismiss="modal">Cancel</button>

                <?php if (!empty($mechanics)): ?>
                    <button type="submit" class="btn modal-save-btn">
                        <i class="bi bi-check-circle me-1"></i>
                        Approve & Create Job Order
                    </button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- REJECT REQUEST MODAL -->
<div class="modal fade request-modal" id="rejectRequestModal" tabindex="-1" aria-labelledby="rejectRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="../controllers/ServiceRequestController.php" method="POST" class="modal-content">
            <input type="hidden" name="action" value="reject_request">
            <input type="hidden" name="service_request_id" id="reject_service_request_id">

            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="rejectRequestModalLabel">Reject Service Request</h5>
                    <p class="mb-0 text-muted small">Provide a reason so the customer understands why it was rejected.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="alert alert-light border mb-3" style="border-radius: 14px;">
                    <i class="bi bi-info-circle me-1"></i>
                    Rejecting request <strong id="reject_request_number"></strong>.
                </div>

                <label class="form-label-custom">Rejection Reason <span class="text-danger">*</span></label>
                <textarea
                    name="rejection_reason"
                    class="form-control form-control-custom"
                    rows="4"
                    placeholder="Example: Requested date is unavailable. Please choose another date."
                    required
                ></textarea>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary modal-cancel-btn" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn modal-danger-btn">
                    <i class="bi bi-x-circle me-1"></i>
                    Reject Request
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const ITEMS_PER_PAGE = 5;

    const searchInput = document.getElementById('serviceRequestSearch');
    const requestRows = Array.from(document.querySelectorAll('.service-request-row'));
    const noResults = document.getElementById('noServiceRequestResults');
    const pagination = document.getElementById('serviceRequestPagination');

    const viewRequestModal = document.getElementById('viewRequestModal');
    const approveRequestModal = document.getElementById('approveRequestModal');
    const rejectRequestModal = document.getElementById('rejectRequestModal');

    let currentPage = 1;

    function getFilteredRows() {
        const searchValue = searchInput ? searchInput.value.toLowerCase().trim() : '';

        return requestRows.filter(function (row) {
            const searchableText = row.getAttribute('data-search') || '';
            return searchableText.includes(searchValue);
        });
    }

    function renderPagination(totalPages) {
        if (!pagination) {
            return;
        }

        pagination.innerHTML = '';

        if (totalPages <= 1) {
            pagination.style.display = 'none';
            return;
        }

        pagination.style.display = 'flex';

        const prevButton = document.createElement('button');
        prevButton.type = 'button';
        prevButton.className = 'sr-page-btn';
        prevButton.innerHTML = '&laquo;';
        prevButton.disabled = currentPage === 1;
        prevButton.addEventListener('click', function () {
            if (currentPage > 1) {
                currentPage--;
                applyRequestPagination();
            }
        });
        pagination.appendChild(prevButton);

        for (let page = 1; page <= totalPages; page++) {
            const pageButton = document.createElement('button');
            pageButton.type = 'button';
            pageButton.className = 'sr-page-btn' + (page === currentPage ? ' active' : '');
            pageButton.textContent = page;
            pageButton.addEventListener('click', function () {
                currentPage = page;
                applyRequestPagination();
            });
            pagination.appendChild(pageButton);
        }

        const nextButton = document.createElement('button');
        nextButton.type = 'button';
        nextButton.className = 'sr-page-btn';
        nextButton.innerHTML = '&raquo;';
        nextButton.disabled = currentPage === totalPages;
        nextButton.addEventListener('click', function () {
            if (currentPage < totalPages) {
                currentPage++;
                applyRequestPagination();
            }
        });
        pagination.appendChild(nextButton);
    }

    function applyRequestPagination() {
        const filteredRows = getFilteredRows();
        const totalPages = Math.ceil(filteredRows.length / ITEMS_PER_PAGE) || 1;

        if (currentPage > totalPages) {
            currentPage = totalPages;
        }

        const start = (currentPage - 1) * ITEMS_PER_PAGE;
        const end = start + ITEMS_PER_PAGE;

        requestRows.forEach(function (row) {
            row.style.display = 'none';
        });

        filteredRows.slice(start, end).forEach(function (row) {
            row.style.display = '';
        });

        if (noResults) {
            noResults.style.display = filteredRows.length === 0 && requestRows.length > 0 ? '' : 'none';
        }

        renderPagination(totalPages);
    }

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            currentPage = 1;
            applyRequestPagination();
        });
    }

    if (viewRequestModal) {
        viewRequestModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;

            document.getElementById('view_request_number').textContent = button.getAttribute('data-request-number') || 'N/A';
            document.getElementById('view_status').textContent = button.getAttribute('data-status') || 'N/A';
            document.getElementById('view_preferred').textContent = button.getAttribute('data-preferred') || 'N/A';
            document.getElementById('view_customer_name').textContent = button.getAttribute('data-customer-name') || 'N/A';

            const contact = button.getAttribute('data-contact') || '';
            const email = button.getAttribute('data-email') || '';
            document.getElementById('view_customer_contact').textContent = [contact, email].filter(Boolean).join(' · ') || 'No contact details';

            document.getElementById('view_vehicle').textContent = button.getAttribute('data-vehicle') || 'N/A';
            document.getElementById('view_service').textContent = button.getAttribute('data-service') || 'N/A';
            document.getElementById('view_estimated_cost').textContent = button.getAttribute('data-estimated-cost') || 'Not yet set';
            document.getElementById('view_mechanic').textContent = button.getAttribute('data-mechanic') || 'Not assigned';
            document.getElementById('view_concern').textContent = button.getAttribute('data-concern') || 'No concern details';
            document.getElementById('view_job_order').textContent = button.getAttribute('data-job-order') || 'Not converted';

            const rejectionReason = button.getAttribute('data-rejection-reason') || '';
            const rejectionBox = document.getElementById('view_rejection_box');

            if (rejectionReason.trim() !== '') {
                rejectionBox.style.display = '';
                document.getElementById('view_rejection_reason').textContent = rejectionReason;
            } else {
                rejectionBox.style.display = 'none';
                document.getElementById('view_rejection_reason').textContent = '';
            }
        });
    }

    if (approveRequestModal) {
        approveRequestModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;

            document.getElementById('approve_service_request_id').value = button.getAttribute('data-service-request-id') || '';
            document.getElementById('approve_request_number').textContent = button.getAttribute('data-request-number') || '';
            document.getElementById('approve_customer_name').textContent = button.getAttribute('data-customer-name') || 'N/A';
            document.getElementById('approve_vehicle').textContent = button.getAttribute('data-vehicle') || 'N/A';
            document.getElementById('approve_service').textContent = button.getAttribute('data-service') || 'N/A';

            const basePrice = parseFloat(button.getAttribute('data-base-price') || '0');

            if (basePrice > 0) {
                document.getElementById('approve_estimated_cost').value = basePrice.toFixed(2);
            } else {
                document.getElementById('approve_estimated_cost').value = '';
            }
        });
    }

    if (rejectRequestModal) {
        rejectRequestModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;

            document.getElementById('reject_service_request_id').value = button.getAttribute('data-service-request-id') || '';
            document.getElementById('reject_request_number').textContent = button.getAttribute('data-request-number') || '';
        });
    }

    applyRequestPagination();
});
</script>

</body>
</html>