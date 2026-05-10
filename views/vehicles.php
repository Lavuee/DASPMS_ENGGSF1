<?php
session_start();

if (!isset($_SESSION['logged_in'])) {
    header("Location: login.php");
    exit;
}

require_once '../config/Database.php';

$db = (new Database())->getConnection();

$isOwner = isset($_SESSION['role']) && $_SESSION['role'] === 'Owner';

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
if ($statusFilter === 'All') {
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
} else {
    $stmt = $db->prepare("
        SELECT 
            v.*,
            c.first_name,
            c.last_name
        FROM vehicle v
        INNER JOIN customer c ON v.customer_id = c.customer_id
        WHERE v.status = ?
        ORDER BY v.plate_number ASC
    ");
    $stmt->execute([$statusFilter]);
}

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Vehicles - Norily's Repair Shop</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/style.css">

<style>
    .main-content {
        overflow-x: hidden;
    }

    .vehicles-page {
        width: 100%;
        max-width: 100%;
    }

    .vehicles-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 1.5rem;
    }

    .vehicles-header h2 {
        font-size: 2rem;
        font-weight: 900;
        color: var(--dashboard-text-main);
        margin-bottom: 0.25rem;
        line-height: 1.1;
    }

    .vehicles-count-text {
        color: var(--dashboard-text-muted);
        font-size: 0.95rem;
        font-weight: 500;
        margin-bottom: 0;
    }

    .vehicle-add-btn {
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

    .vehicle-add-btn i {
        font-size: 0.95rem;
    }

    .vehicle-add-btn:hover {
        background: var(--black);
        border-color: var(--black);
        color: var(--white);
    }

    .vehicle-alert {
        border-radius: 16px;
        font-size: 0.92rem;
        margin-bottom: 1.5rem;
    }

    .vehicle-filter-area {
        display: grid;
        grid-template-columns: minmax(260px, 1fr) auto;
        gap: 0.85rem;
        align-items: end;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid #e5e7eb;
    }

    .vehicle-filter-label {
        display: block;
        color: var(--dashboard-text-muted);
        font-size: 0.76rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.35px;
        margin-bottom: 0.35rem;
    }

    .vehicle-search-bar {
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

    .vehicle-search-bar i {
        font-size: 1.05rem;
        color: var(--dashboard-text-muted);
        flex-shrink: 0;
    }

    .vehicle-search-bar input {
        width: 100%;
        border: none;
        outline: none;
        background: transparent;
        color: var(--dashboard-text-main);
        font-size: 0.92rem;
    }

    .vehicle-search-bar input::placeholder {
        color: #6b7280;
    }

    .vehicle-status-tabs {
        display: flex;
        align-items: center;
        gap: 0.55rem;
        flex-wrap: wrap;
    }

    .vehicle-status-tab {
        text-decoration: none;
        border: 1px solid #e5e7eb;
        background: #fff;
        color: var(--dashboard-text-muted);
        border-radius: 999px;
        padding: 0.65rem 0.95rem;
        font-size: 0.84rem;
        font-weight: 800;
        transition: all 0.2s ease;
        white-space: nowrap;
    }

    .vehicle-status-tab:hover {
        color: var(--black);
        border-color: var(--dashboard-primary);
    }

    .vehicle-status-tab.active {
        background: var(--dashboard-primary);
        border-color: var(--dashboard-primary);
        color: var(--black);
    }

    .vehicle-table-wrap {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .vehicles-table {
        width: 100%;
        min-width: 1080px;
        border-collapse: collapse;
        background: transparent;
        margin-bottom: 0;
    }

    .vehicles-table thead th {
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

    .vehicles-table tbody td {
        padding: 1rem 0.85rem;
        border-bottom: 1px solid #e8ebef;
        vertical-align: middle;
        color: var(--dashboard-text-main);
        background: transparent;
        font-size: 0.95rem;
    }

    .vehicles-table tbody tr:hover td {
        background: rgba(245, 197, 24, 0.035);
    }

    .vehicles-table th:last-child,
    .vehicles-table td:last-child {
        min-width: 190px;
    }

    .vehicle-profile {
        display: flex;
        align-items: center;
        gap: 0.8rem;
        min-width: 0;
    }

    .vehicle-avatar {
        width: 44px;
        height: 44px;
        border-radius: 14px;
        background: var(--dashboard-primary-soft);
        color: var(--black);
        font-size: 1.05rem;
        font-weight: 900;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .vehicle-plate {
        color: var(--dashboard-text-main);
        font-size: 0.95rem;
        font-weight: 900;
        margin-bottom: 0.15rem;
        max-width: 180px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        text-transform: uppercase;
    }

    .vehicle-sub {
        color: var(--dashboard-text-muted);
        font-size: 0.78rem;
        font-weight: 500;
        max-width: 180px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .owner-line,
    .vehicle-line {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: var(--dashboard-text-main);
        font-size: 0.9rem;
        min-width: 0;
    }

    .owner-line i,
    .vehicle-line i {
        color: var(--dashboard-text-muted);
        flex-shrink: 0;
    }

    .owner-line span,
    .vehicle-line span {
        display: inline-block;
        max-width: 190px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        border-radius: 999px;
        padding: 0.42rem 0.72rem;
        font-size: 0.78rem;
        font-weight: 900;
        white-space: nowrap;
    }

    .status-active {
        background: rgba(16, 185, 129, 0.12);
        color: #047857;
    }

    .status-inactive {
        background: rgba(245, 158, 11, 0.14);
        color: #b45309;
    }

    .status-archived {
        background: #f1f5f9;
        color: #475569;
    }

    .action-group {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 0.45rem;
        flex-wrap: nowrap;
    }

    .action-form {
        margin: 0;
        display: inline-flex;
    }

    .icon-action-btn {
        width: 38px;
        height: 38px;
        border-radius: 10px;
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

    .icon-action-btn:hover {
        transform: translateY(-1px);
    }

    .icon-action-btn.view-btn:hover {
        background: #eef2ff;
        border-color: #c7d2fe;
        color: #4338ca;
    }

    .icon-action-btn.edit-btn:hover {
        background: var(--dashboard-primary);
        border-color: var(--dashboard-primary);
        color: var(--black);
    }

    .icon-action-btn.deactivate-btn:hover {
        background: #fff7ed;
        border-color: #fdba74;
        color: #c2410c;
    }

    .icon-action-btn.reactivate-btn:hover {
        background: #ecfdf5;
        border-color: #a7f3d0;
        color: #047857;
    }

    .icon-action-btn.archive-btn:hover {
        background: #fff1f2;
        border-color: #fecdd3;
        color: #be123c;
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

    .vehicle-pagination-wrap {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 0.35rem;
        margin-top: 1rem;
        flex-wrap: wrap;
    }

    .vehicle-page-btn {
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

    .vehicle-page-btn:hover:not(:disabled) {
        border-color: var(--dashboard-primary);
        background: #fffaf0;
        color: var(--black);
    }

    .vehicle-page-btn.active {
        background: var(--dashboard-primary);
        border-color: var(--dashboard-primary);
        color: var(--black);
    }

    .vehicle-page-btn:disabled {
        opacity: 0.45;
        cursor: not-allowed;
    }

    .modal-content {
        border-radius: 20px;
        border: 1px solid var(--border-light);
        overflow: hidden;
    }

    .modal-header {
        border-bottom: 1px solid var(--border-light);
        background: #fffdf5;
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

    .form-label {
        font-size: 0.84rem;
        font-weight: 800;
        color: var(--dashboard-text-main);
        margin-bottom: 0.45rem;
    }

    .form-control,
    .form-select {
        border-radius: 12px;
        border: 1px solid #e5e7eb;
        padding: 0.78rem 0.95rem;
        font-size: 0.95rem;
    }

    .form-control:focus,
    .form-select:focus {
        border-color: var(--dashboard-primary);
        box-shadow: 0 0 0 4px rgba(245, 197, 24, 0.18);
    }

    .vehicle-detail-body {
        padding: 1.5rem;
    }

    .vehicle-detail-profile {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.5rem;
        padding-bottom: 1.25rem;
        border-bottom: 1px solid #eef2f6;
    }

    .vehicle-detail-avatar {
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

    .vehicle-detail-name {
        font-size: 1.15rem;
        font-weight: 900;
        color: var(--dashboard-text-main);
        margin-bottom: 0.4rem;
        line-height: 1.15;
        text-transform: uppercase;
    }

    .vehicle-detail-sub {
        color: var(--dashboard-text-muted);
        font-size: 0.84rem;
        margin-top: 0.35rem;
    }

    .vehicle-detail-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        column-gap: 1.35rem;
        row-gap: 1.2rem;
    }

    .vehicle-detail-field {
        min-width: 0;
    }

    .vehicle-detail-field.full {
        grid-column: 1 / -1;
    }

    .vehicle-detail-label {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        color: var(--dashboard-text-muted);
        font-size: 0.76rem;
        font-weight: 900;
        margin-bottom: 0.35rem;
    }

    .vehicle-detail-value {
        border: none;
        border-bottom: 1px solid #d7dde5;
        padding: 0.55rem 0 0.65rem 0;
        color: var(--dashboard-text-main);
        font-size: 0.95rem;
        font-weight: 800;
        min-height: 38px;
        word-break: break-word;
    }

    .vehicle-detail-value.long-text {
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

    .vehicle-detail-footer {
        border-top: none;
        padding-top: 0.6rem;
    }

    .vehicle-detail-close-btn,
    .minimal-cancel-btn {
        border: 1px solid #e5e7eb;
        background: transparent;
        color: var(--dashboard-text-main);
        border-radius: 999px;
        padding: 0.55rem 1rem;
        font-size: 0.9rem;
        font-weight: 800;
        transition: 0.2s ease;
    }

    .vehicle-detail-close-btn:hover,
    .minimal-cancel-btn:hover {
        background: #f8fafc;
        color: var(--black);
    }

    .vehicle-detail-edit-btn,
    .minimal-save-btn {
        border: 1px solid var(--dashboard-primary);
        background: var(--dashboard-primary);
        color: var(--black);
        border-radius: 999px;
        padding: 0.55rem 1rem;
        font-size: 0.9rem;
        font-weight: 900;
        transition: 0.2s ease;
    }

    .vehicle-detail-edit-btn:hover,
    .minimal-save-btn:hover {
        background: var(--black);
        border-color: var(--black);
        color: var(--white);
    }

    .minimal-vehicle-form {
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
        grid-column: span 2;
    }

    .minimal-form-field.half {
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

    textarea.minimal-control {
        min-height: 95px;
        resize: vertical;
        line-height: 1.5;
    }

    .minimal-helper {
        color: var(--dashboard-text-muted);
        font-size: 0.76rem;
        margin-top: 0.35rem;
    }

    .minimal-modal-footer {
        border-top: none;
        padding-top: 0.75rem;
    }

    @media (max-width: 991.98px) {
        .vehicle-filter-area {
            grid-template-columns: 1fr;
        }

        .vehicle-status-tabs {
            width: 100%;
        }

        .vehicle-status-tab {
            flex: 1;
            text-align: center;
        }
    }

    @media (max-width: 767.98px) {
        .vehicles-header {
            flex-direction: column;
            align-items: stretch;
        }

        .vehicle-add-btn {
            width: 100%;
            justify-content: center;
        }

        .vehicles-header h2 {
            font-size: 1.75rem;
        }

        .vehicle-pagination-wrap {
            justify-content: center;
        }

        .vehicle-search-bar {
            height: 42px;
            min-height: 42px;
        }

        .vehicle-detail-grid {
            grid-template-columns: 1fr;
        }

        .vehicle-detail-profile {
            align-items: flex-start;
        }

        .vehicle-detail-footer,
        .minimal-modal-footer {
            flex-direction: column-reverse;
            align-items: stretch;
        }

        .vehicle-detail-close-btn,
        .vehicle-detail-edit-btn,
        .minimal-cancel-btn,
        .minimal-save-btn {
            width: 100%;
        }

        .minimal-form-grid {
            grid-template-columns: 1fr;
        }

        .minimal-form-field,
        .minimal-form-field.half,
        .minimal-form-field.full {
            grid-column: 1 / -1;
        }
    }
</style>
</head>

<body>

<div class="app-wrapper">
<?php include '../includes/sidebar.php'; ?>

<main class="main-content">
    <div class="vehicles-page">

        <div class="vehicles-header">
            <div>
                <h2>Vehicles</h2>
                <p class="vehicles-count-text">
                    Showing <?php echo $displayCount; ?>
                    <?php echo strtolower($statusFilter); ?>
                    vehicle<?php echo $displayCount !== 1 ? 's' : ''; ?>
                </p>
            </div>

            <button
                class="btn vehicle-add-btn"
                data-bs-toggle="modal"
                data-bs-target="#addVehicleModal"
            >
                <i class="bi bi-plus-lg"></i>
                Add Vehicle
            </button>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show vehicle-alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show vehicle-alert">
                <i class="bi bi-exclamation-circle-fill me-2"></i>
                <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="vehicle-filter-area">
            <div>
                <label class="vehicle-filter-label">Search</label>
                <div class="vehicle-search-bar">
                    <i class="bi bi-search"></i>
                    <input
                        type="text"
                        id="vehicleSearch"
                        placeholder="Search by plate, owner, make, model, year, color, or status..."
                    >
                </div>
            </div>

            <div class="vehicle-status-tabs">
                <a href="vehicles.php?status=Active" class="vehicle-status-tab <?php echo $statusFilter === 'Active' ? 'active' : ''; ?>">
                    Active
                </a>

                <a href="vehicles.php?status=Inactive" class="vehicle-status-tab <?php echo $statusFilter === 'Inactive' ? 'active' : ''; ?>">
                    Inactive
                </a>

                <a href="vehicles.php?status=Archived" class="vehicle-status-tab <?php echo $statusFilter === 'Archived' ? 'active' : ''; ?>">
                    Archived
                </a>

                <a href="vehicles.php?status=All" class="vehicle-status-tab <?php echo $statusFilter === 'All' ? 'active' : ''; ?>">
                    All
                </a>
            </div>
        </div>

        <div class="vehicle-table-wrap">
            <table class="vehicles-table" id="vehiclesTable">
                <thead>
                    <tr>
                        <th>Vehicle</th>
                        <th>Owner</th>
                        <th>Details</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (count($vehicles) > 0): ?>
                        <?php foreach ($vehicles as $v): ?>
                            <?php
                                $vehicleId = intval($v['vehicle_id']);
                                $ownerName = trim(($v['first_name'] ?? '') . ' ' . ($v['last_name'] ?? ''));
                                $status = $v['status'] ?? 'Active';
                                $badgeClass = getVehicleStatusBadgeClass($status);
                                $notes = !empty($v['notes']) ? $v['notes'] : 'No notes provided';
                                $createdAt = !empty($v['created_at']) ? date('M d, Y', strtotime($v['created_at'])) : 'N/A';
                                $deactivatedAt = !empty($v['deactivated_at']) ? date('M d, Y h:i A', strtotime($v['deactivated_at'])) : 'N/A';
                                $archivedAt = !empty($v['archived_at']) ? date('M d, Y h:i A', strtotime($v['archived_at'])) : 'N/A';

                                $makeModel = trim(($v['make'] ?? '') . ' ' . ($v['model'] ?? ''));

                                $searchText = strtolower(
                                    ($v['plate_number'] ?? '') . ' ' .
                                    ($v['make'] ?? '') . ' ' .
                                    ($v['model'] ?? '') . ' ' .
                                    ($v['year'] ?? '') . ' ' .
                                    ($v['color'] ?? '') . ' ' .
                                    $ownerName . ' ' .
                                    ($v['status'] ?? '')
                                );
                            ?>

                            <tr class="vehicle-row" data-search="<?php echo htmlspecialchars($searchText); ?>">
                                <td>
                                    <div class="vehicle-profile">
                                        <div class="vehicle-avatar">
                                            <i class="bi bi-car-front-fill"></i>
                                        </div>

                                        <div>
                                            <div class="vehicle-plate" title="<?php echo htmlspecialchars($v['plate_number']); ?>">
                                                <?php echo htmlspecialchars($v['plate_number']); ?>
                                            </div>
                                            <div class="vehicle-sub">
                                                Vehicle ID: <?php echo $vehicleId; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <div class="owner-line">
                                        <i class="bi bi-person"></i>
                                        <span title="<?php echo htmlspecialchars($ownerName); ?>">
                                            <?php echo htmlspecialchars($ownerName); ?>
                                        </span>
                                    </div>
                                </td>

                                <td>
                                    <div class="vehicle-line mb-1">
                                        <i class="bi bi-wrench-adjustable"></i>
                                        <span title="<?php echo htmlspecialchars($makeModel); ?>">
                                            <?php echo htmlspecialchars($makeModel); ?>
                                        </span>
                                    </div>
                                    <div class="vehicle-line">
                                        <i class="bi bi-palette"></i>
                                        <span>
                                            <?php echo htmlspecialchars(($v['year'] ?? '') . ' • ' . ($v['color'] ?? '')); ?>
                                        </span>
                                    </div>
                                </td>

                                <td>
                                    <span class="<?php echo $badgeClass; ?>">
                                        <i class="bi bi-circle-fill" style="font-size: 0.45rem;"></i>
                                        <?php echo htmlspecialchars($status); ?>
                                    </span>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($createdAt); ?>
                                </td>

                                <td class="text-end">
                                    <div class="action-group">

                                        <button
                                            type="button"
                                            class="icon-action-btn view-btn"
                                            title="View Vehicle"
                                            data-bs-toggle="modal"
                                            data-bs-target="#viewVehicleModal<?php echo $vehicleId; ?>"
                                        >
                                            <i class="bi bi-eye"></i>
                                        </button>

                                        <button
                                            type="button"
                                            class="icon-action-btn edit-btn"
                                            title="Edit Vehicle"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editVehicleModal<?php echo $vehicleId; ?>"
                                        >
                                            <i class="bi bi-pencil-square"></i>
                                        </button>

                                        <?php if ($isOwner): ?>

                                            <?php if ($status === 'Active'): ?>
                                                <form
                                                    method="POST"
                                                    action="../controllers/VehicleController.php"
                                                    class="action-form"
                                                    onsubmit="return confirm('Deactivate this vehicle record?');"
                                                >
                                                    <input type="hidden" name="action" value="deactivate_vehicle">
                                                    <input type="hidden" name="vehicle_id" value="<?php echo $vehicleId; ?>">
                                                    <input type="hidden" name="current_filter" value="<?php echo htmlspecialchars($statusFilter); ?>">

                                                    <button
                                                        type="submit"
                                                        class="icon-action-btn deactivate-btn"
                                                        title="Deactivate Vehicle"
                                                    >
                                                        <i class="bi bi-slash-circle"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form
                                                    method="POST"
                                                    action="../controllers/VehicleController.php"
                                                    class="action-form"
                                                    onsubmit="return confirm('Reactivate this vehicle record?');"
                                                >
                                                    <input type="hidden" name="action" value="reactivate_vehicle">
                                                    <input type="hidden" name="vehicle_id" value="<?php echo $vehicleId; ?>">
                                                    <input type="hidden" name="current_filter" value="<?php echo htmlspecialchars($statusFilter); ?>">

                                                    <button
                                                        type="submit"
                                                        class="icon-action-btn reactivate-btn"
                                                        title="Reactivate Vehicle"
                                                    >
                                                        <i class="bi bi-check-circle"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if ($status !== 'Archived'): ?>
                                                <form
                                                    method="POST"
                                                    action="../controllers/VehicleController.php"
                                                    class="action-form"
                                                    onsubmit="return confirm('Archive this vehicle record? Historical records will be retained.');"
                                                >
                                                    <input type="hidden" name="action" value="archive_vehicle">
                                                    <input type="hidden" name="vehicle_id" value="<?php echo $vehicleId; ?>">
                                                    <input type="hidden" name="current_filter" value="<?php echo htmlspecialchars($statusFilter); ?>">

                                                    <button
                                                        type="submit"
                                                        class="icon-action-btn archive-btn"
                                                        title="Archive Vehicle"
                                                    >
                                                        <i class="bi bi-archive"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                        <?php endif; ?>

                                    </div>
                                </td>
                            </tr>

                            <div class="modal fade" id="viewVehicleModal<?php echo $vehicleId; ?>" tabindex="-1">
                                <div class="modal-dialog modal-dialog-centered modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <div>
                                                <h5 class="modal-title">Vehicle Details</h5>
                                                <small class="text-muted">Viewing vehicle record #<?php echo $vehicleId; ?></small>
                                            </div>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>

                                        <div class="modal-body vehicle-detail-body">
                                            <div class="vehicle-detail-profile">
                                                <div class="vehicle-detail-avatar">
                                                    <i class="bi bi-car-front-fill"></i>
                                                </div>

                                                <div>
                                                    <div class="vehicle-detail-name">
                                                        <?php echo htmlspecialchars($v['plate_number']); ?>
                                                    </div>

                                                    <span class="<?php echo $badgeClass; ?>">
                                                        <i class="bi bi-circle-fill" style="font-size: 0.45rem;"></i>
                                                        <?php echo htmlspecialchars($status); ?>
                                                    </span>

                                                    <div class="vehicle-detail-sub">
                                                        Vehicle ID: <?php echo $vehicleId; ?>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="vehicle-detail-grid">
                                                <div class="vehicle-detail-field">
                                                    <div class="vehicle-detail-label">
                                                        <span>Owner</span>
                                                        <span class="linked-note">
                                                            <i class="bi bi-check-circle-fill"></i>
                                                            Linked
                                                        </span>
                                                    </div>
                                                    <div class="vehicle-detail-value">
                                                        <?php echo htmlspecialchars($ownerName); ?>
                                                    </div>
                                                </div>

                                                <div class="vehicle-detail-field">
                                                    <div class="vehicle-detail-label">
                                                        <span>Plate Number</span>
                                                    </div>
                                                    <div class="vehicle-detail-value">
                                                        <?php echo htmlspecialchars($v['plate_number']); ?>
                                                    </div>
                                                </div>

                                                <div class="vehicle-detail-field">
                                                    <div class="vehicle-detail-label">
                                                        <span>Make / Model</span>
                                                    </div>
                                                    <div class="vehicle-detail-value">
                                                        <?php echo htmlspecialchars($makeModel); ?>
                                                    </div>
                                                </div>

                                                <div class="vehicle-detail-field">
                                                    <div class="vehicle-detail-label">
                                                        <span>Year / Color</span>
                                                    </div>
                                                    <div class="vehicle-detail-value">
                                                        <?php echo htmlspecialchars(($v['year'] ?? '') . ' • ' . ($v['color'] ?? '')); ?>
                                                    </div>
                                                </div>

                                                <div class="vehicle-detail-field full">
                                                    <div class="vehicle-detail-label">
                                                        <span>Notes</span>
                                                    </div>
                                                    <div class="vehicle-detail-value long-text">
                                                        <?php echo htmlspecialchars($notes); ?>
                                                    </div>
                                                </div>

                                                <div class="vehicle-detail-field">
                                                    <div class="vehicle-detail-label">
                                                        <span>Status</span>
                                                    </div>
                                                    <div class="vehicle-detail-value">
                                                        <?php echo htmlspecialchars($status); ?>
                                                    </div>
                                                </div>

                                                <div class="vehicle-detail-field">
                                                    <div class="vehicle-detail-label">
                                                        <span>Created At</span>
                                                    </div>
                                                    <div class="vehicle-detail-value">
                                                        <?php echo htmlspecialchars($createdAt); ?>
                                                    </div>
                                                </div>

                                                <?php if (!empty($v['deactivated_at'])): ?>
                                                    <div class="vehicle-detail-field">
                                                        <div class="vehicle-detail-label">
                                                            <span>Deactivated At</span>
                                                        </div>
                                                        <div class="vehicle-detail-value">
                                                            <?php echo htmlspecialchars($deactivatedAt); ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if (!empty($v['archived_at'])): ?>
                                                    <div class="vehicle-detail-field">
                                                        <div class="vehicle-detail-label">
                                                            <span>Archived At</span>
                                                        </div>
                                                        <div class="vehicle-detail-value">
                                                            <?php echo htmlspecialchars($archivedAt); ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="modal-footer vehicle-detail-footer">
                                            <button type="button" class="vehicle-detail-close-btn" data-bs-dismiss="modal">
                                                Close
                                            </button>

                                            <button
                                                type="button"
                                                class="vehicle-detail-edit-btn"
                                                data-bs-target="#editVehicleModal<?php echo $vehicleId; ?>"
                                                data-bs-toggle="modal"
                                            >
                                                Edit Vehicle
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="modal fade" id="editVehicleModal<?php echo $vehicleId; ?>" tabindex="-1">
                                <div class="modal-dialog modal-dialog-centered modal-lg">
                                    <div class="modal-content">
                                        <form method="POST" action="../controllers/VehicleController.php">
                                            <input type="hidden" name="action" value="update_vehicle">
                                            <input type="hidden" name="vehicle_id" value="<?php echo $vehicleId; ?>">
                                            <input type="hidden" name="current_filter" value="<?php echo htmlspecialchars($statusFilter); ?>">

                                            <div class="modal-header">
                                                <div>
                                                    <h5 class="modal-title">Edit Vehicle</h5>
                                                    <small class="text-muted">Update vehicle and ownership details.</small>
                                                </div>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>

                                            <div class="modal-body">
                                                <div class="minimal-vehicle-form">
                                                    <div class="minimal-section-title">Basic Vehicle Details</div>

                                                    <div class="minimal-form-grid">
                                                        <div class="minimal-form-field full">
                                                            <label class="minimal-label">Owner</label>
                                                            <select name="customer_id" class="minimal-control" required>
                                                                <?php foreach ($customersList as $c): ?>
                                                                    <option
                                                                        value="<?php echo intval($c['customer_id']); ?>"
                                                                        <?php echo intval($c['customer_id']) === intval($v['customer_id']) ? 'selected' : ''; ?>
                                                                    >
                                                                        <?php echo htmlspecialchars($c['first_name'] . ' ' . $c['last_name']); ?>
                                                                        <?php echo ($c['status'] !== 'Active') ? ' (' . htmlspecialchars($c['status']) . ')' : ''; ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                            <div class="minimal-helper">
                                                                Select the customer who owns this vehicle.
                                                            </div>
                                                        </div>

                                                        <div class="minimal-form-field half">
                                                            <label class="minimal-label">Plate Number</label>
                                                            <input
                                                                type="text"
                                                                name="plate_number"
                                                                class="minimal-control text-uppercase"
                                                                value="<?php echo htmlspecialchars($v['plate_number'] ?? ''); ?>"
                                                                required
                                                            >
                                                            <div class="minimal-helper">
                                                                Plate number will be saved in uppercase by the system.
                                                            </div>
                                                        </div>

                                                        <div class="minimal-form-field half">
                                                            <label class="minimal-label">Make</label>
                                                            <input
                                                                type="text"
                                                                name="make"
                                                                class="minimal-control"
                                                                value="<?php echo htmlspecialchars($v['make'] ?? ''); ?>"
                                                                required
                                                            >
                                                        </div>

                                                        <div class="minimal-form-field">
                                                            <label class="minimal-label">Model</label>
                                                            <input
                                                                type="text"
                                                                name="model"
                                                                class="minimal-control"
                                                                value="<?php echo htmlspecialchars($v['model'] ?? ''); ?>"
                                                                required
                                                            >
                                                        </div>

                                                        <div class="minimal-form-field">
                                                            <label class="minimal-label">Year</label>
                                                            <input
                                                                type="text"
                                                                name="year"
                                                                class="minimal-control"
                                                                maxlength="4"
                                                                value="<?php echo htmlspecialchars($v['year'] ?? ''); ?>"
                                                                required
                                                            >
                                                        </div>

                                                        <div class="minimal-form-field">
                                                            <label class="minimal-label">Color</label>
                                                            <input
                                                                type="text"
                                                                name="color"
                                                                class="minimal-control"
                                                                value="<?php echo htmlspecialchars($v['color'] ?? ''); ?>"
                                                                required
                                                            >
                                                        </div>

                                                        <div class="minimal-form-field full">
                                                            <label class="minimal-label">Notes</label>
                                                            <textarea
                                                                name="notes"
                                                                class="minimal-control"
                                                                placeholder="Optional"
                                                            ><?php echo htmlspecialchars($v['notes'] ?? ''); ?></textarea>
                                                            <div class="minimal-helper">
                                                                Optional remarks about the vehicle condition, history, or special instructions.
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

                        <?php endforeach; ?>

                        <tr id="noVehicleResults" style="display:none;">
                            <td colspan="6">
                                <div class="empty-state">
                                    <i class="bi bi-search"></i>
                                    <div class="fw-bold mb-1">No matching vehicles found</div>
                                    <div>Try another keyword or status filter.</div>
                                </div>
                            </td>
                        </tr>

                    <?php else: ?>
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <i class="bi bi-car-front"></i>
                                    <div class="fw-bold mb-1">No vehicles found</div>
                                    <div>Add a vehicle record or switch to another status filter.</div>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="vehicle-pagination-wrap" id="vehiclePagination"></div>

    </div>
</main>
</div>

<div class="modal fade" id="addVehicleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="POST" action="../controllers/VehicleController.php">
                <input type="hidden" name="action" value="add_vehicle">

                <div class="modal-header">
                    <div>
                        <h5 class="modal-title">Add Vehicle</h5>
                        <small class="text-muted">Register a vehicle and link it to a customer record.</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="minimal-vehicle-form">
                        <div class="minimal-section-title">Basic Vehicle Details</div>

                        <div class="minimal-form-grid">
                            <div class="minimal-form-field full">
                                <label class="minimal-label">Owner</label>
                                <select name="customer_id" class="minimal-control" required>
                                    <option value="" disabled selected>Choose Customer</option>
                                    <?php foreach ($customersList as $c): ?>
                                        <option value="<?php echo intval($c['customer_id']); ?>">
                                            <?php echo htmlspecialchars($c['first_name'] . ' ' . $c['last_name']); ?>
                                            <?php echo ($c['status'] !== 'Active') ? ' (' . htmlspecialchars($c['status']) . ')' : ''; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="minimal-helper">
                                    Select the customer who owns this vehicle.
                                </div>
                            </div>

                            <div class="minimal-form-field half">
                                <label class="minimal-label">Plate Number</label>
                                <input
                                    type="text"
                                    name="plate_number"
                                    class="minimal-control text-uppercase"
                                    required
                                >
                                <div class="minimal-helper">
                                    Plate number will be saved in uppercase by the system.
                                </div>
                            </div>

                            <div class="minimal-form-field half">
                                <label class="minimal-label">Make</label>
                                <input
                                    type="text"
                                    name="make"
                                    class="minimal-control"
                                    placeholder="e.g. Toyota"
                                    required
                                >
                            </div>

                            <div class="minimal-form-field">
                                <label class="minimal-label">Model</label>
                                <input
                                    type="text"
                                    name="model"
                                    class="minimal-control"
                                    placeholder="e.g. Vios"
                                    required
                                >
                            </div>

                            <div class="minimal-form-field">
                                <label class="minimal-label">Year</label>
                                <input
                                    type="text"
                                    name="year"
                                    class="minimal-control"
                                    maxlength="4"
                                    placeholder="e.g. 2020"
                                    required
                                >
                            </div>

                            <div class="minimal-form-field">
                                <label class="minimal-label">Color</label>
                                <input
                                    type="text"
                                    name="color"
                                    class="minimal-control"
                                    placeholder="e.g. White"
                                    required
                                >
                            </div>

                            <div class="minimal-form-field full">
                                <label class="minimal-label">Notes</label>
                                <textarea
                                    name="notes"
                                    class="minimal-control"
                                    placeholder="Optional"
                                ></textarea>
                                <div class="minimal-helper">
                                    Optional remarks about the vehicle condition, history, or special instructions.
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
                        Save Vehicle
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const ITEMS_PER_PAGE = 5;

    const vehicleSearch = document.getElementById('vehicleSearch');
    const vehiclesTable = document.getElementById('vehiclesTable');
    const noVehicleResults = document.getElementById('noVehicleResults');
    const vehiclePagination = document.getElementById('vehiclePagination');
    const vehicleRows = vehiclesTable ? Array.from(vehiclesTable.querySelectorAll('tbody tr.vehicle-row')) : [];

    let currentPage = 1;

    function getFilteredRows() {
        const searchValue = vehicleSearch ? vehicleSearch.value.trim().toLowerCase() : '';

        return vehicleRows.filter(function (row) {
            const text = row.dataset.search || '';
            return text.includes(searchValue);
        });
    }

    function renderPagination(totalPages) {
        if (!vehiclePagination) {
            return;
        }

        vehiclePagination.innerHTML = '';

        if (totalPages <= 1) {
            vehiclePagination.style.display = 'none';
            return;
        }

        vehiclePagination.style.display = 'flex';

        const prevButton = document.createElement('button');
        prevButton.type = 'button';
        prevButton.className = 'vehicle-page-btn';
        prevButton.innerHTML = '&laquo;';
        prevButton.disabled = currentPage === 1;
        prevButton.addEventListener('click', function () {
            if (currentPage > 1) {
                currentPage--;
                applyVehiclePagination();
            }
        });
        vehiclePagination.appendChild(prevButton);

        for (let page = 1; page <= totalPages; page++) {
            const pageButton = document.createElement('button');
            pageButton.type = 'button';
            pageButton.className = 'vehicle-page-btn' + (page === currentPage ? ' active' : '');
            pageButton.textContent = page;
            pageButton.addEventListener('click', function () {
                currentPage = page;
                applyVehiclePagination();
            });
            vehiclePagination.appendChild(pageButton);
        }

        const nextButton = document.createElement('button');
        nextButton.type = 'button';
        nextButton.className = 'vehicle-page-btn';
        nextButton.innerHTML = '&raquo;';
        nextButton.disabled = currentPage === totalPages;
        nextButton.addEventListener('click', function () {
            if (currentPage < totalPages) {
                currentPage++;
                applyVehiclePagination();
            }
        });
        vehiclePagination.appendChild(nextButton);
    }

    function applyVehiclePagination() {
        const filteredRows = getFilteredRows();
        const totalPages = Math.ceil(filteredRows.length / ITEMS_PER_PAGE) || 1;

        if (currentPage > totalPages) {
            currentPage = totalPages;
        }

        const start = (currentPage - 1) * ITEMS_PER_PAGE;
        const end = start + ITEMS_PER_PAGE;

        vehicleRows.forEach(function (row) {
            row.style.display = 'none';
        });

        filteredRows.slice(start, end).forEach(function (row) {
            row.style.display = '';
        });

        if (noVehicleResults) {
            noVehicleResults.style.display = filteredRows.length === 0 && vehicleRows.length > 0 ? '' : 'none';
        }

        renderPagination(totalPages);
    }

    if (vehicleSearch && vehiclesTable) {
        vehicleSearch.addEventListener('input', function () {
            currentPage = 1;
            applyVehiclePagination();
        });
    }

    applyVehiclePagination();
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>