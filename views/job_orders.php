<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] === 'Customer') {
    header("Location: login.php");
    exit;
}

require_once '../config/Database.php';

$db = (new Database())->getConnection();

$query = "
    SELECT 
        jo.*, 
        c.first_name, 
        c.last_name, 
        v.plate_number,
        v.make,
        v.model
    FROM job_order jo
    JOIN customer c ON jo.customer_id = c.customer_id
    JOIN vehicle v ON jo.vehicle_id = v.vehicle_id
    ORDER BY jo.date_created DESC
";

$stmt = $db->prepare($query);
$stmt->execute();
$jobOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$vehicles = [];

if ($_SESSION['role'] !== 'Head Mechanic') {
    $stmtV = $db->prepare("
        SELECT 
            v.*, 
            c.first_name, 
            c.last_name 
        FROM vehicle v 
        JOIN customer c ON v.customer_id = c.customer_id 
        ORDER BY v.plate_number ASC
    ");
    $stmtV->execute();
    $vehicles = $stmtV->fetchAll(PDO::FETCH_ASSOC);
}

$counts = [
    'All' => count($jobOrders),
    'Pending' => 0,
    'In Progress' => 0,
    'Completed' => 0,
    'Cancelled' => 0
];

foreach ($jobOrders as $jo) {
    $status = $jo['status'] ?? 'Pending';

    if (isset($counts[$status])) {
        $counts[$status]++;
    }
}

function getJobStatusBadgeClass($status) {
    switch ($status) {
        case 'Pending':
            return 'jo-status jo-status-pending';
        case 'In Progress':
            return 'jo-status jo-status-progress';
        case 'Completed':
            return 'jo-status jo-status-completed';
        case 'Cancelled':
            return 'jo-status jo-status-cancelled';
        case 'Ready for Pickup':
            return 'jo-status jo-status-ready';
        default:
            return 'jo-status jo-status-pending';
    }
}

function getDisplayCost($jo) {
    $finalCost = isset($jo['final_cost']) ? floatval($jo['final_cost']) : 0;
    $estimatedCost = isset($jo['estimated_cost']) ? floatval($jo['estimated_cost']) : 0;

    return $finalCost > 0 ? $finalCost : $estimatedCost;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Job Orders - Norily's Repair Shop</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
    .main-content {
        overflow-x: hidden;
    }

    .job-orders-page {
        width: 100%;
        max-width: 100%;
    }

    .jo-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 1.5rem;
    }

    .jo-header h2 {
        font-size: 2rem;
        font-weight: 900;
        color: var(--dashboard-text-main);
        margin-bottom: 0.25rem;
        line-height: 1.1;
    }

    .jo-count-text {
        color: var(--dashboard-text-muted);
        font-size: 0.95rem;
        font-weight: 500;
        margin-bottom: 0;
    }

    .jo-create-btn {
        min-height: 44px;
        background: var(--dashboard-primary);
        border: 1px solid var(--dashboard-primary);
        color: var(--black);
        font-size: 0.9rem;
        font-weight: 800;
        padding: 0.55rem 1rem;
        border-radius: 999px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.45rem;
        transition: 0.2s ease;
        white-space: nowrap;
    }

    .jo-create-btn i {
        font-size: 0.95rem;
    }

    .jo-create-btn:hover {
        background: var(--black);
        border-color: var(--black);
        color: var(--white);
    }

    .jo-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 2rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid #e5e7eb;
        margin-bottom: 1.4rem;
    }

    .jo-tab {
        border: none;
        background: transparent;
        color: var(--dashboard-text-muted);
        font-size: 0.92rem;
        font-weight: 600;
        padding: 0;
        cursor: pointer;
        transition: 0.2s ease;
    }

    .jo-tab:hover {
        color: var(--black);
    }

    .jo-tab.active {
        color: var(--dashboard-primary);
        font-weight: 800;
    }

    .jo-search-wrap {
        margin-bottom: 1.5rem;
    }

    .jo-search-bar {
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

    .jo-search-bar i {
        font-size: 1.05rem;
        color: var(--dashboard-text-muted);
        flex-shrink: 0;
    }

    .jo-search-bar input {
        width: 100%;
        border: none;
        outline: none;
        background: transparent;
        color: var(--dashboard-text-main);
        font-size: 0.92rem;
    }

    .jo-search-bar input::placeholder {
        color: #6b7280;
    }

    .jo-table-wrap {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .jo-table {
        width: 100%;
        min-width: 1180px;
        border-collapse: collapse;
        background: transparent;
    }

    .jo-table thead th {
        color: var(--dashboard-text-muted);
        font-size: 0.82rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.35px;
        padding: 0.9rem 0.85rem;
        border-bottom: 1px solid #dcdfe4;
        background: transparent;
        white-space: nowrap;
    }

    .jo-table tbody td {
        padding: 1rem 0.85rem;
        border-bottom: 1px solid #e8ebef;
        vertical-align: middle;
        color: var(--dashboard-text-main);
        background: transparent;
        font-size: 0.95rem;
    }

    .jo-table tbody tr:hover td {
        background: rgba(245, 197, 24, 0.035);
    }

    .jo-table th:last-child,
    .jo-table td:last-child {
        min-width: 200px;
    }

    .jo-id {
        font-family: monospace;
        font-size: 0.95rem;
        font-weight: 800;
        color: var(--dashboard-text-main);
        max-width: 210px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .jo-date,
    .jo-customer,
    .jo-vehicle,
    .jo-total {
        font-size: 0.95rem;
        white-space: nowrap;
    }

    .jo-customer,
    .jo-vehicle,
    .jo-total {
        font-weight: 900;
    }

    .jo-date {
        font-weight: 500;
    }

    .jo-vehicle-sub {
        font-size: 0.78rem;
        color: var(--dashboard-text-muted);
        margin-top: 0.2rem;
        max-width: 160px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-weight: 500;
    }

    .jo-items {
        font-size: 0.95rem;
        font-weight: 800;
        text-align: center;
    }

    .jo-status {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        padding: 0.42rem 0.72rem;
        font-size: 0.78rem;
        font-weight: 900;
        white-space: nowrap;
    }

    .jo-status-pending {
        background: #fff5db;
        color: #b77900;
    }

    .jo-status-progress {
        background: #e9f2ff;
        color: #1d4ed8;
    }

    .jo-status-completed {
        background: #e8f7ef;
        color: #15803d;
    }

    .jo-status-cancelled {
        background: #f3f4f6;
        color: #6b7280;
    }

    .jo-status-ready {
        background: #f4ecff;
        color: #7c3aed;
    }

    .jo-actions {
        display: flex;
        align-items: center;
        gap: 0.45rem;
        flex-wrap: nowrap;
    }

    .jo-action-btn {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        padding: 0;
        border: 1px solid #e5e7eb;
        background: #fff;
        color: var(--dashboard-text-muted);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        font-size: 0.95rem;
        transition: all 0.2s ease;
    }

    .jo-action-btn:hover {
        transform: translateY(-1px);
    }

    .jo-view-btn:hover,
    .jo-edit-btn:hover,
    .jo-billing-btn:hover {
        border-color: var(--dashboard-primary);
        background: #fffaf0;
        color: var(--black);
    }

    .jo-start-btn:hover {
        border-color: var(--dashboard-primary);
        background: var(--dashboard-primary);
        color: var(--black);
    }

    .jo-complete-btn:hover {
        border-color: #bbf7d0;
        background: #ecfdf5;
        color: #15803d;
    }

    .jo-cancel-btn:hover {
        border-color: #fecdd3;
        background: #fff1f2;
        color: #be123c;
    }

    .jo-action-form {
        margin: 0;
        display: inline-flex;
    }

    .jo-empty-state {
        text-align: center;
        padding: 3rem 1rem;
        color: var(--dashboard-text-muted);
        font-size: 0.95rem;
    }

    .jo-empty-state i {
        font-size: 2.25rem;
        color: var(--dashboard-primary);
        display: block;
        margin-bottom: 0.7rem;
    }

    .jo-empty-state .fw-bold {
        color: var(--dashboard-text-main);
        font-size: 0.95rem;
        font-weight: 900 !important;
    }

    .modal-content {
        border-radius: 20px;
        border: 1px solid var(--border-light);
        overflow: hidden;
    }

    .modal-header {
        border-bottom: 1px solid var(--border-light);
        background: #fffdf6;
    }

    .modal-footer {
        border-top: 1px solid var(--border-light);
    }

    .modal-title {
        font-size: 1rem;
        font-weight: 900;
    }

    .modal-header small {
        font-size: 0.78rem;
    }

    .alert {
        border-radius: 16px;
        font-size: 0.92rem;
    }

    .job-detail-body {
        padding: 1.5rem;
    }

    .job-detail-profile {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.5rem;
        padding-bottom: 1.25rem;
        border-bottom: 1px solid #eef2f6;
    }

    .job-detail-avatar {
        width: 64px;
        height: 64px;
        border-radius: 18px;
        background: var(--dashboard-primary-soft);
        color: var(--black);
        font-size: 1.25rem;
        font-weight: 900;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .job-detail-name {
        font-size: 1.15rem;
        font-weight: 900;
        color: var(--dashboard-text-main);
        margin-bottom: 0.4rem;
        line-height: 1.15;
        text-transform: uppercase;
    }

    .job-detail-sub {
        color: var(--dashboard-text-muted);
        font-size: 0.84rem;
        margin-top: 0.35rem;
    }

    .job-detail-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        column-gap: 1.35rem;
        row-gap: 1.2rem;
    }

    .job-detail-field {
        min-width: 0;
    }

    .job-detail-field.full {
        grid-column: 1 / -1;
    }

    .job-detail-label {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        color: var(--dashboard-text-muted);
        font-size: 0.76rem;
        font-weight: 900;
        margin-bottom: 0.35rem;
    }

    .job-detail-value {
        border: none;
        border-bottom: 1px solid #d7dde5;
        padding: 0.55rem 0 0.65rem 0;
        color: var(--dashboard-text-main);
        font-size: 0.95rem;
        font-weight: 800;
        min-height: 38px;
        word-break: break-word;
    }

    .job-detail-value.long-text {
        min-height: 74px;
        line-height: 1.5;
    }

    .linked-note {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        color: #047857;
        font-size: 0.72rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .job-detail-footer {
        border-top: none;
        padding-top: 0.6rem;
    }

    .job-detail-close-btn,
    .job-detail-edit-btn,
    .minimal-cancel-btn,
    .minimal-save-btn {
        border-radius: 999px;
        padding: 0.55rem 1rem;
        font-size: 0.9rem;
        font-weight: 800;
        transition: 0.2s ease;
    }

    .job-detail-close-btn,
    .minimal-cancel-btn {
        border: 1px solid #e5e7eb;
        background: transparent;
        color: var(--dashboard-text-main);
    }

    .job-detail-close-btn:hover,
    .minimal-cancel-btn:hover {
        background: #f8fafc;
        color: var(--black);
    }

    .job-detail-edit-btn,
    .minimal-save-btn {
        border: 1px solid var(--dashboard-primary);
        background: var(--dashboard-primary);
        color: var(--black);
        font-weight: 900;
    }

    .job-detail-edit-btn:hover,
    .minimal-save-btn:hover {
        background: var(--black);
        border-color: var(--black);
        color: var(--white);
    }

    .minimal-job-form {
        padding: 0.25rem 0;
    }

    .minimal-section-title {
        font-size: 0.95rem;
        font-weight: 900;
        color: var(--dashboard-primary);
        margin-bottom: 1.35rem;
        padding-bottom: 0.85rem;
        border-bottom: 1px solid #dfe3e8;
    }

    .minimal-form-grid {
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        column-gap: 1.2rem;
        row-gap: 1.4rem;
    }

    .minimal-form-field {
        min-width: 0;
        grid-column: span 3;
    }

    .minimal-form-field.full {
        grid-column: 1 / -1;
    }

    .minimal-label {
        display: block;
        font-size: 0.76rem;
        font-weight: 800;
        color: var(--dashboard-text-muted);
        margin-bottom: 0.35rem;
    }

    .minimal-control {
        width: 100%;
        border: none;
        border-bottom: 1px solid #cfd6df;
        border-radius: 0;
        padding: 0.45rem 0 0.55rem 0;
        font-size: 0.95rem;
        color: var(--dashboard-text-main);
        background: transparent;
        box-shadow: none;
    }

    .minimal-control:focus {
        border-color: var(--dashboard-primary);
        outline: none;
        box-shadow: none;
        background: transparent;
    }

    select.minimal-control {
        cursor: pointer;
    }

    .empty-vehicle-note {
        grid-column: 1 / -1;
        border: 1px solid #fde68a;
        background: #fffbeb;
        color: #92400e;
        border-radius: 10px;
        padding: 0.85rem 1rem;
        font-size: 0.88rem;
        font-weight: 700;
    }

    textarea.minimal-control {
        min-height: 105px;
        resize: vertical;
        line-height: 1.5;
    }

    .minimal-helper {
        color: var(--dashboard-text-muted);
        font-size: 0.76rem;
        margin-top: 0.35rem;
    }

    .minimal-check-field {
        grid-column: 1 / -1;
        display: flex;
        align-items: center;
        gap: 0.65rem;
        padding-top: 0.1rem;
    }

    .minimal-check-field .form-check-input {
        width: 18px;
        height: 18px;
        margin: 0;
        border: 1px solid #d1d5db;
        cursor: pointer;
    }

    .minimal-check-field .form-check-input:checked {
        background-color: var(--dashboard-primary);
        border-color: var(--dashboard-primary);
    }

    .minimal-check-field label {
        margin: 0;
        color: var(--dashboard-text-main);
        font-size: 0.92rem;
        font-weight: 800;
        cursor: pointer;
    }

    .minimal-modal-footer {
        border-top: none;
        padding-top: 0.75rem;
    }

    @media (max-width: 767.98px) {
        .jo-header {
            flex-direction: column;
            align-items: stretch;
        }

        .jo-create-btn {
            width: 100%;
            justify-content: center;
        }

        .jo-tabs {
            gap: 1.35rem;
        }

        .jo-header h2 {
            font-size: 1.75rem;
        }

        .job-detail-grid {
            grid-template-columns: 1fr;
        }

        .job-detail-footer,
        .minimal-modal-footer {
            flex-direction: column-reverse;
            align-items: stretch;
        }

        .job-detail-close-btn,
        .job-detail-edit-btn,
        .minimal-cancel-btn,
        .minimal-save-btn {
            width: 100%;
        }

        .minimal-form-grid {
            grid-template-columns: 1fr;
        }

        .minimal-form-field,
        .minimal-form-field.full,
        .minimal-check-field {
            grid-column: 1 / -1;
        }
    }
    </style>
</head>
<body>

<div class="app-wrapper">
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="job-orders-page">

            <div class="jo-header">
                <div>
                    <h2>Job Orders</h2>
                    <p class="jo-count-text"><?php echo $counts['All']; ?> total orders</p>
                </div>

                <?php if ($_SESSION['role'] !== 'Head Mechanic'): ?>
                    <button type="button" class="jo-create-btn" data-bs-toggle="modal" data-bs-target="#createJobOrderModal">
                        <i class="bi bi-plus-lg"></i>
                        New Job Order
                    </button>
                <?php endif; ?>
            </div>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show mb-4">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <?php
                        echo htmlspecialchars($_SESSION['success_message']);
                        unset($_SESSION['success_message']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show mb-4">
                    <i class="bi bi-exclamation-circle-fill me-2"></i>
                    <?php
                        echo htmlspecialchars($_SESSION['error_message']);
                        unset($_SESSION['error_message']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="jo-tabs" id="joTabs">
                <button type="button" class="jo-tab active" data-filter="All">
                    All (<?php echo $counts['All']; ?>)
                </button>

                <button type="button" class="jo-tab" data-filter="Pending">
                    Pending (<?php echo $counts['Pending']; ?>)
                </button>

                <button type="button" class="jo-tab" data-filter="In Progress">
                    In Progress (<?php echo $counts['In Progress']; ?>)
                </button>

                <button type="button" class="jo-tab" data-filter="Completed">
                    Completed (<?php echo $counts['Completed']; ?>)
                </button>

                <button type="button" class="jo-tab" data-filter="Cancelled">
                    Cancelled (<?php echo $counts['Cancelled']; ?>)
                </button>
            </div>
                        <div class="jo-search-wrap">
                <div class="jo-search-bar">
                    <i class="bi bi-search"></i>
                    <input
                        type="text"
                        id="searchInput"
                        placeholder="Search by job number, customer, plate number, or description..."
                    >
                </div>
            </div>

            <div class="jo-table-wrap">
                <table class="jo-table" id="jobOrdersTable">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Vehicle</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (count($jobOrders) > 0): ?>
                            <?php foreach ($jobOrders as $jo): ?>
                                <?php
                                    $jobOrderId = intval($jo['job_order_id']);
                                    $jobNumber = $jo['job_order_number'] ?? ('JO-' . $jobOrderId);
                                    $status = $jo['status'] ?? 'Pending';
                                    $badgeClass = getJobStatusBadgeClass($status);
                                    $customerName = trim(($jo['first_name'] ?? '') . ' ' . ($jo['last_name'] ?? ''));
                                    $plateNumber = $jo['plate_number'] ?? 'No plate';
                                    $vehicleLabel = trim(($jo['make'] ?? '') . ' ' . ($jo['model'] ?? ''));
                                    $description = $jo['description'] ?? 'No description provided';
                                    $dateCreated = !empty($jo['date_created']) ? date('M d, Y', strtotime($jo['date_created'])) : 'N/A';
                                    $dateCompleted = !empty($jo['date_completed']) ? date('M d, Y', strtotime($jo['date_completed'])) : 'N/A';
                                    $displayCost = getDisplayCost($jo);

                                    $itemsCount = !empty($description) ? 1 : 0;

                                    $searchText = strtolower(
                                        $jobNumber . ' ' .
                                        $customerName . ' ' .
                                        $plateNumber . ' ' .
                                        $vehicleLabel . ' ' .
                                        $description . ' ' .
                                        $status
                                    );
                                ?>

                                <tr
                                    class="job-row"
                                    data-status="<?php echo htmlspecialchars($status); ?>"
                                    data-search="<?php echo htmlspecialchars($searchText); ?>"
                                >
                                    <td>
                                        <div class="jo-id" title="<?php echo htmlspecialchars($jobNumber); ?>">
                                            <?php echo htmlspecialchars($jobNumber); ?>
                                        </div>
                                    </td>

                                    <td class="jo-date">
                                        <?php echo htmlspecialchars($dateCreated); ?>
                                    </td>

                                    <td class="jo-customer">
                                        <?php echo htmlspecialchars($customerName); ?>
                                    </td>

                                    <td class="jo-vehicle">
                                        <?php echo htmlspecialchars($plateNumber); ?>

                                        <?php if ($vehicleLabel !== ''): ?>
                                            <div class="jo-vehicle-sub">
                                                <?php echo htmlspecialchars($vehicleLabel); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <td class="jo-items">
                                        <?php echo $itemsCount; ?>
                                    </td>

                                    <td class="jo-total">
                                        ₱<?php echo number_format($displayCost, 2); ?>
                                    </td>

                                    <td>
                                        <span class="<?php echo $badgeClass; ?>">
                                            <?php echo htmlspecialchars($status); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <div class="jo-actions">
                                            <button
                                                type="button"
                                                class="jo-action-btn jo-view-btn"
                                                title="View Details"
                                                aria-label="View Details"
                                                data-bs-toggle="modal"
                                                data-bs-target="#viewJobOrderModal<?php echo $jobOrderId; ?>"
                                            >
                                                <i class="bi bi-eye"></i>
                                            </button>

                                            <?php if ($status === 'Pending' || $status === 'In Progress'): ?>
                                                <button
                                                    type="button"
                                                    class="jo-action-btn jo-edit-btn"
                                                    title="Edit Job Order"
                                                    aria-label="Edit Job Order"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editJobOrderModal<?php echo $jobOrderId; ?>"
                                                >
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($status === 'Pending'): ?>
                                                <form action="../controllers/JobOrderController.php" method="POST" class="jo-action-form">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="job_order_id" value="<?php echo $jobOrderId; ?>">
                                                    <input type="hidden" name="status" value="In Progress">

                                                    <button
                                                        type="submit"
                                                        class="jo-action-btn jo-start-btn"
                                                        title="Start Work"
                                                        aria-label="Start Work"
                                                    >
                                                        <i class="bi bi-play-fill"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if ($status === 'In Progress'): ?>
                                                <form action="../controllers/JobOrderController.php" method="POST" class="jo-action-form">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="job_order_id" value="<?php echo $jobOrderId; ?>">
                                                    <input type="hidden" name="status" value="Completed">

                                                    <button
                                                        type="submit"
                                                        class="jo-action-btn jo-complete-btn"
                                                        title="Complete Job"
                                                        aria-label="Complete Job"
                                                    >
                                                        <i class="bi bi-check-lg"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if ($status === 'Pending' || $status === 'In Progress'): ?>
                                                <form
                                                    action="../controllers/JobOrderController.php"
                                                    method="POST"
                                                    class="jo-action-form"
                                                    onsubmit="return confirm('Cancel this job order? This will mark the record as Cancelled.');"
                                                >
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="job_order_id" value="<?php echo $jobOrderId; ?>">
                                                    <input type="hidden" name="status" value="Cancelled">

                                                    <button
                                                        type="submit"
                                                        class="jo-action-btn jo-cancel-btn"
                                                        title="Cancel Job Order"
                                                        aria-label="Cancel Job Order"
                                                    >
                                                        <i class="bi bi-x-lg"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if ($status === 'Completed'): ?>
                                                <a
                                                    href="billing.php"
                                                    class="jo-action-btn jo-billing-btn"
                                                    title="Open Billing / Invoice"
                                                    aria-label="Open Billing / Invoice"
                                                >
                                                    <i class="bi bi-receipt"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>

                                <div class="modal fade" id="viewJobOrderModal<?php echo $jobOrderId; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-dialog-centered modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <div>
                                                    <h5 class="modal-title">Job Order Details</h5>
                                                    <small class="text-muted"><?php echo htmlspecialchars($jobNumber); ?></small>
                                                </div>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>

                                            <div class="modal-body job-detail-body">
                                                <div class="job-detail-profile">
                                                    <div class="job-detail-avatar">
                                                        <i class="bi bi-tools"></i>
                                                    </div>

                                                    <div>
                                                        <div class="job-detail-name">
                                                            <?php echo htmlspecialchars($jobNumber); ?>
                                                        </div>

                                                        <span class="<?php echo $badgeClass; ?>">
                                                            <?php echo htmlspecialchars($status); ?>
                                                        </span>

                                                        <div class="job-detail-sub">
                                                            <?php echo htmlspecialchars($customerName); ?>
                                                            <?php if ($plateNumber !== ''): ?>
                                                                • <?php echo htmlspecialchars($plateNumber); ?>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="job-detail-grid">
                                                    <div class="job-detail-field">
                                                        <div class="job-detail-label">
                                                            <span>Customer</span>
                                                            <span class="linked-note">
                                                                <i class="bi bi-check-circle-fill"></i>
                                                                Linked
                                                            </span>
                                                        </div>
                                                        <div class="job-detail-value">
                                                            <?php echo htmlspecialchars($customerName); ?>
                                                        </div>
                                                    </div>

                                                    <div class="job-detail-field">
                                                        <div class="job-detail-label">
                                                            <span>Vehicle</span>
                                                            <span class="linked-note">
                                                                <i class="bi bi-check-circle-fill"></i>
                                                                Linked
                                                            </span>
                                                        </div>
                                                        <div class="job-detail-value">
                                                            <?php echo htmlspecialchars($plateNumber); ?>
                                                            <?php if ($vehicleLabel !== ''): ?>
                                                                — <?php echo htmlspecialchars($vehicleLabel); ?>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>

                                                    <div class="job-detail-field full">
                                                        <div class="job-detail-label">
                                                            <span>Description</span>
                                                        </div>
                                                        <div class="job-detail-value long-text">
                                                            <?php echo htmlspecialchars($description); ?>
                                                        </div>
                                                    </div>

                                                    <div class="job-detail-field">
                                                        <div class="job-detail-label">
                                                            <span>Status</span>
                                                        </div>
                                                        <div class="job-detail-value">
                                                            <?php echo htmlspecialchars($status); ?>
                                                        </div>
                                                    </div>

                                                    <div class="job-detail-field">
                                                        <div class="job-detail-label">
                                                            <span>Estimated Cost</span>
                                                        </div>
                                                        <div class="job-detail-value">
                                                            ₱<?php echo number_format(floatval($jo['estimated_cost'] ?? 0), 2); ?>
                                                        </div>
                                                    </div>

                                                    <div class="job-detail-field">
                                                        <div class="job-detail-label">
                                                            <span>Final Cost</span>
                                                        </div>
                                                        <div class="job-detail-value">
                                                            ₱<?php echo number_format(floatval($jo['final_cost'] ?? 0), 2); ?>
                                                        </div>
                                                    </div>

                                                    <div class="job-detail-field">
                                                        <div class="job-detail-label">
                                                            <span>Requires Down Payment</span>
                                                        </div>
                                                        <div class="job-detail-value">
                                                            <?php echo !empty($jo['requires_down_payment']) ? 'Yes' : 'No'; ?>
                                                        </div>
                                                    </div>

                                                    <div class="job-detail-field">
                                                        <div class="job-detail-label">
                                                            <span>Down Payment Amount</span>
                                                        </div>
                                                        <div class="job-detail-value">
                                                            ₱<?php echo number_format(floatval($jo['down_payment_amount'] ?? 0), 2); ?>
                                                        </div>
                                                    </div>

                                                    <div class="job-detail-field">
                                                        <div class="job-detail-label">
                                                            <span>Date Created</span>
                                                        </div>
                                                        <div class="job-detail-value">
                                                            <?php echo htmlspecialchars($dateCreated); ?>
                                                        </div>
                                                    </div>

                                                    <div class="job-detail-field">
                                                        <div class="job-detail-label">
                                                            <span>Date Completed</span>
                                                        </div>
                                                        <div class="job-detail-value">
                                                            <?php echo htmlspecialchars($dateCompleted); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="modal-footer job-detail-footer">
                                                <button type="button" class="job-detail-close-btn" data-bs-dismiss="modal">
                                                    Close
                                                </button>

                                                <?php if ($status === 'Pending' || $status === 'In Progress'): ?>
                                                    <button
                                                        type="button"
                                                        class="job-detail-edit-btn"
                                                        data-bs-target="#editJobOrderModal<?php echo $jobOrderId; ?>"
                                                        data-bs-toggle="modal"
                                                    >
                                                        Edit Job Order
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <?php if ($status === 'Pending' || $status === 'In Progress'): ?>
                                    <div class="modal fade" id="editJobOrderModal<?php echo $jobOrderId; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-dialog-centered modal-lg">
                                            <div class="modal-content">
                                                <form action="../controllers/JobOrderController.php" method="POST">
                                                    <input type="hidden" name="action" value="update_details">
                                                    <input type="hidden" name="job_order_id" value="<?php echo $jobOrderId; ?>">

                                                    <div class="modal-header">
                                                        <div>
                                                            <h5 class="modal-title">Edit Job Order</h5>
                                                            <small class="text-muted"><?php echo htmlspecialchars($jobNumber); ?></small>
                                                        </div>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>

                                                    <div class="modal-body">
                                                        <div class="minimal-job-form">
                                                            <div class="minimal-section-title">Basic Job Order Details</div>

                                                            <div class="minimal-form-grid">
                                                                <div class="minimal-form-field full">
                                                                    <label class="minimal-label">Repair Description</label>
                                                                    <textarea
                                                                        name="description"
                                                                        class="minimal-control"
                                                                        required
                                                                    ><?php echo htmlspecialchars($description); ?></textarea>
                                                                    <div class="minimal-helper">
                                                                        Update the issue, diagnosis, or requested repair description.
                                                                    </div>
                                                                </div>

                                                                <div class="minimal-form-field">
                                                                    <label class="minimal-label">Estimated Cost</label>
                                                                    <input
                                                                        type="number"
                                                                        step="0.01"
                                                                        min="0"
                                                                        name="estimated_cost"
                                                                        class="minimal-control"
                                                                        value="<?php echo htmlspecialchars(floatval($jo['estimated_cost'] ?? 0)); ?>"
                                                                        required
                                                                    >
                                                                    <div class="minimal-helper">
                                                                        Initial estimated amount for this repair job.
                                                                    </div>
                                                                </div>

                                                                <div class="minimal-form-field">
                                                                    <label class="minimal-label">Down Payment Amount</label>
                                                                    <input
                                                                        type="number"
                                                                        step="0.01"
                                                                        min="0"
                                                                        name="down_payment_amount"
                                                                        class="minimal-control"
                                                                        value="<?php echo htmlspecialchars(floatval($jo['down_payment_amount'] ?? 0)); ?>"
                                                                    >
                                                                    <div class="minimal-helper">
                                                                        Leave as 0 if no down payment is required.
                                                                    </div>
                                                                </div>

                                                                <div class="minimal-check-field">
                                                                    <input
                                                                        class="form-check-input"
                                                                        type="checkbox"
                                                                        name="requires_down_payment"
                                                                        id="requiresDownPayment<?php echo $jobOrderId; ?>"
                                                                        <?php echo !empty($jo['requires_down_payment']) ? 'checked' : ''; ?>
                                                                    >
                                                                    <label for="requiresDownPayment<?php echo $jobOrderId; ?>">
                                                                        Requires Down Payment
                                                                    </label>
                                                                </div>

                                                                <div class="minimal-form-field full">
                                                                    <div class="minimal-helper">
                                                                        Only Pending or In Progress job orders can be edited.
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="modal-footer minimal-modal-footer">
                                                        <button type="button" class="minimal-cancel-btn" data-bs-dismiss="modal">
                                                            Cancel
                                                        </button>

                                                        <button type="submit" class="minimal-save-btn">
                                                            Save Changes
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                            <?php endforeach; ?>

                            <tr id="noJobResults" style="display:none;">
                                <td colspan="8">
                                    <div class="jo-empty-state">
                                        <i class="bi bi-search"></i>
                                        <div class="fw-bold mb-1">No matching job orders found</div>
                                        <div>Try another keyword or change the selected tab.</div>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td colspan="8">
                                    <div class="jo-empty-state">
                                        <i class="bi bi-clipboard-x"></i>
                                        <div class="fw-bold mb-1">No job orders found</div>
                                        <div>Create a new job order to start tracking records.</div>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($_SESSION['role'] !== 'Head Mechanic'): ?>
                <div class="modal fade" id="createJobOrderModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered modal-lg">
                        <div class="modal-content">
                            <form action="../controllers/JobOrderController.php" method="POST" class="minimal-job-form">
                                <input type="hidden" name="action" value="create">

                                <div class="modal-header">
                                    <div>
                                        <h5 class="modal-title">Create New Job Order</h5>
                                        <small class="text-muted">
                                            Register a new vehicle repair task while keeping customer and vehicle records connected.
                                        </small>
                                    </div>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>

                                <div class="modal-body">
                                    <div class="minimal-section-title">Basic Job Details</div>

                                    <div class="minimal-form-grid">
                                        <?php if (count($vehicles) === 0): ?>
                                            <div class="empty-vehicle-note">
                                                <i class="bi bi-exclamation-triangle me-2"></i>
                                                No registered vehicles found. Please add a customer vehicle first before creating a job order.
                                            </div>
                                        <?php endif; ?>

                                        <div class="minimal-form-field full">
                                            <label class="minimal-label">Vehicle & Customer</label>
                                            <select name="vehicle_id" class="minimal-control" required <?php echo count($vehicles) === 0 ? 'disabled' : ''; ?>>
                                                <option value="" disabled selected>Select vehicle by plate number</option>
                                                <?php foreach ($vehicles as $v): ?>
                                                    <option value="<?php echo intval($v['vehicle_id']); ?>">
                                                        <?php
                                                            echo htmlspecialchars(
                                                                $v['plate_number'] . ' - ' .
                                                                $v['make'] . ' ' .
                                                                $v['model'] . ' (' .
                                                                $v['first_name'] . ' ' .
                                                                $v['last_name'] . ')'
                                                            );
                                                        ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="minimal-helper">
                                                Choose the vehicle that will be assigned to this job order.
                                            </div>
                                        </div>

                                        <div class="minimal-form-field full">
                                            <label class="minimal-label">Repair Description</label>
                                            <textarea
                                                name="description"
                                                class="minimal-control"
                                                placeholder="Describe the issue, symptoms, diagnosis, or requested repair..."
                                                required
                                            ></textarea>
                                            <div class="minimal-helper">
                                                This will help mechanics and billing staff understand the repair scope.
                                            </div>
                                        </div>

                                        <div class="minimal-form-field">
                                            <label class="minimal-label">Estimated Cost (₱)</label>
                                            <input
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                name="estimated_cost"
                                                class="minimal-control"
                                                placeholder="0.00"
                                                required
                                            >
                                            <div class="minimal-helper">
                                                Initial amount for the repair job.
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="modal-footer minimal-modal-footer">
                                    <button type="button" class="minimal-cancel-btn" data-bs-dismiss="modal">
                                        Cancel
                                    </button>
                                    <button type="submit" class="minimal-save-btn" <?php echo count($vehicles) === 0 ? 'disabled' : ''; ?>>
                                        Save Job Order
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>


        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    const tabs = document.querySelectorAll('.jo-tab');
    const rows = document.querySelectorAll('.job-row');
    const searchInput = document.getElementById('searchInput');
    const noResults = document.getElementById('noJobResults');

    let activeFilter = 'All';

    function applyFilters() {
        const term = searchInput.value.trim().toLowerCase();
        let visibleCount = 0;

        rows.forEach(row => {
            const status = row.getAttribute('data-status');
            const searchText = row.getAttribute('data-search') || '';

            const matchesStatus = activeFilter === 'All' || status === activeFilter;
            const matchesSearch = searchText.includes(term);

            if (matchesStatus && matchesSearch) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        if (noResults) {
            noResults.style.display = (visibleCount === 0 && rows.length > 0) ? '' : 'none';
        }
    }

    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            tabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');

            activeFilter = this.getAttribute('data-filter');
            applyFilters();
        });
    });

    if (searchInput) {
        searchInput.addEventListener('input', applyFilters);
    }
</script>

</body>
</html>