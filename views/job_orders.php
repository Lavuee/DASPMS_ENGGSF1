<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] === 'Customer') {
    header("Location: login.php");
    exit;
}

require_once '../config/Database.php';

$db = (new Database())->getConnection();

$userRole = $_SESSION['role'] ?? '';

$canCreateJobOrders = in_array($userRole, ['Owner', 'Cashier'], true);
$canEditJobOrders = in_array($userRole, ['Owner', 'Cashier'], true);
$canUpdateRepairProgress = in_array($userRole, ['Owner', 'Cashier', 'Head Mechanic'], true);
$canCompleteJobOrders = in_array($userRole, ['Owner', 'Cashier'], true);
$canCancelJobOrders = in_array($userRole, ['Owner', 'Cashier'], true);
$canAccessBilling = in_array($userRole, ['Owner', 'Cashier'], true);

$query = "
    SELECT 
        jo.*, 
        c.first_name, 
        c.last_name, 
        v.plate_number,
        v.make,
        v.model,
        CONCAT(am.first_name, ' ', am.last_name) AS assigned_mechanic_name,
        CONCAT(cb.first_name, ' ', cb.last_name) AS completed_by_name
    FROM job_order jo
    JOIN customer c ON jo.customer_id = c.customer_id
    JOIN vehicle v ON jo.vehicle_id = v.vehicle_id
    LEFT JOIN user am ON jo.assigned_mechanic_id = am.user_id
    LEFT JOIN user cb ON jo.completed_by = cb.user_id
    ORDER BY jo.date_created DESC
";

$stmt = $db->prepare($query);
$stmt->execute();
$jobOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$availablePartsStmt = $db->prepare("
    SELECT 
        part_id,
        part_name,
        brand,
        specification,
        unit,
        unit_price,
        quantity_on_hand,
        low_stock_threshold
    FROM part
    WHERE is_active = 1
    ORDER BY part_name ASC
");
$availablePartsStmt->execute();
$availableParts = $availablePartsStmt->fetchAll(PDO::FETCH_ASSOC);

$jobPartsStmt = $db->prepare("
    SELECT
        jop.*,
        p.part_name,
        p.unit
    FROM job_order_part jop
    JOIN part p ON jop.part_id = p.part_id
    ORDER BY jop.job_part_id DESC
");
$jobPartsStmt->execute();
$allJobParts = $jobPartsStmt->fetchAll(PDO::FETCH_ASSOC);

$jobPartsByOrder = [];
$jobPartsTotals = [];

foreach ($allJobParts as $partUsed) {
    $joId = intval($partUsed['job_order_id']);

    if (!isset($jobPartsByOrder[$joId])) {
        $jobPartsByOrder[$joId] = [];
    }

    if (!isset($jobPartsTotals[$joId])) {
        $jobPartsTotals[$joId] = 0;
    }

    $jobPartsByOrder[$joId][] = $partUsed;
    $jobPartsTotals[$joId] += floatval($partUsed['subtotal']);
}

$vehicles = [];
$mechanics = [];

if ($canCreateJobOrders) {
    $stmtV = $db->prepare("
        SELECT 
            v.*, 
            c.first_name, 
            c.last_name 
        FROM vehicle v 
        JOIN customer c ON v.customer_id = c.customer_id 
        WHERE v.status = 'Active'
        ORDER BY v.plate_number ASC
    ");
    $stmtV->execute();
    $vehicles = $stmtV->fetchAll(PDO::FETCH_ASSOC);

    $stmtM = $db->prepare("
        SELECT user_id, first_name, last_name
        FROM user
        WHERE role = 'Head Mechanic'
          AND is_active = 1
        ORDER BY first_name ASC, last_name ASC
    ");
    $stmtM->execute();
    $mechanics = $stmtM->fetchAll(PDO::FETCH_ASSOC);
}

$counts = [
    'All' => count($jobOrders),
    'Pending' => 0,
    'In Progress' => 0,
    'Ready for Pickup' => 0,
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
        case 'Ready for Pickup':
            return 'jo-status jo-status-ready';
        case 'Completed':
            return 'jo-status jo-status-completed';
        case 'Cancelled':
            return 'jo-status jo-status-cancelled';
        default:
            return 'jo-status jo-status-pending';
    }
}

function getRequestSourceBadgeClass($source) {
    return $source === 'Online' ? 'jo-source jo-source-online' : 'jo-source jo-source-walkin';
}

function getDisplayCost($jo, $partsUsedTotal = 0) {
    $finalCost = isset($jo['final_cost']) ? floatval($jo['final_cost']) : 0;
    $estimatedCost = isset($jo['estimated_cost']) ? floatval($jo['estimated_cost']) : 0;

    if ($finalCost > 0) {
        return $finalCost;
    }

    return $estimatedCost + floatval($partsUsedTotal);
}

function formatDateValue($value) {
    if (empty($value)) {
        return 'N/A';
    }

    return date('M d, Y', strtotime($value));
}

function formatDateTimeValue($value) {
    if (empty($value)) {
        return 'N/A';
    }

    return date('M d, Y h:i A', strtotime($value));
}

$jobOrderModals = '';
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
        margin-bottom: 0;
    }

    .jo-table thead th {
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

    .jo-table tbody td {
        padding: 1rem 0.75rem;
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
        min-width: 210px;
    }

    .jo-id {
        font-family: monospace;
        font-size: 0.9rem;
        font-weight: 800;
        color: var(--dashboard-text-main);
        max-width: 115px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .jo-date,
    .jo-customer,
    .jo-vehicle,
    .jo-total,
    .jo-mechanic {
        font-size: 0.92rem;
        white-space: nowrap;
    }

    .jo-customer,
    .jo-vehicle,
    .jo-total,
    .jo-mechanic {
        font-weight: 900;
    }

    .jo-date {
        font-weight: 500;
    }

    .jo-vehicle-sub,
    .jo-mechanic-sub {
        font-size: 0.78rem;
        color: var(--dashboard-text-muted);
        margin-top: 0.2rem;
        max-width: 160px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-weight: 500;
    }

    .jo-parts-sub {
        color: var(--dashboard-text-muted);
        font-size: 0.78rem;
        margin-top: 0.2rem;
        font-weight: 700;
    }

    .jo-source,
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

    .jo-source-online {
        background: #e7f0ff;
        color: #1d4ed8;
    }

    .jo-source-walkin {
        background: #fff5db;
        color: #b77900;
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
        justify-content: flex-end;
        gap: 0.45rem;
        flex-wrap: nowrap;
    }

    .jo-action-btn {
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

    .jo-start-btn {
        border-color: #dbeafe;
        background: #eff6ff;
        color: #1d4ed8;
    }

    .jo-ready-btn {
        border-color: #ddd6fe;
        background: #f5f3ff;
        color: #7c3aed;
    }

    .jo-complete-btn {
        border-color: #bbf7d0;
        background: #ecfdf5;
        color: #15803d;
    }

    .jo-cancel-btn {
        border-color: #fecdd3;
        background: #fff1f2;
        color: #be123c;
    }

    .jo-action-form {
        margin: 0;
        display: inline-flex;
    }

    .jo-no-action {
        color: var(--dashboard-text-muted);
        font-size: 0.8rem;
        font-weight: 800;
        white-space: nowrap;
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

    .jo-pagination-wrap {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 0.35rem;
        margin-top: 1rem;
        flex-wrap: wrap;
    }

    .jo-page-btn {
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

    .jo-page-btn:hover:not(:disabled) {
        border-color: var(--dashboard-primary);
        background: #fffaf0;
        color: var(--black);
    }

    .jo-page-btn.active {
        background: var(--dashboard-primary);
        border-color: var(--dashboard-primary);
        color: var(--black);
    }

    .jo-page-btn:disabled {
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
        white-space: pre-line;
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

    .parts-used-section {
        margin-top: 1.6rem;
        padding-top: 1.4rem;
        border-top: 1px solid #eef2f6;
    }

    .parts-used-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 1rem;
    }

    .parts-used-title {
        font-size: 0.95rem;
        font-weight: 900;
        color: var(--dashboard-text-main);
        margin-bottom: 0.2rem;
    }

    .parts-used-subtitle {
        color: var(--dashboard-text-muted);
        font-size: 0.8rem;
        margin-bottom: 0;
    }

    .parts-used-total {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #fff7d6;
        color: #9a5b00;
        border-radius: 999px;
        padding: 0.45rem 0.8rem;
        font-size: 0.8rem;
        font-weight: 900;
        white-space: nowrap;
    }

    .parts-used-empty {
        border: 1px dashed #d1d5db;
        border-radius: 14px;
        padding: 1rem;
        color: var(--dashboard-text-muted);
        font-size: 0.88rem;
        text-align: center;
        margin-bottom: 1rem;
        background: #fafafa;
    }

    .parts-used-table-wrap {
        width: 100%;
        overflow-x: auto;
        margin-bottom: 1rem;
    }

    .parts-used-table {
        width: 100%;
        min-width: 760px;
        border-collapse: collapse;
    }

    .parts-used-table th {
        color: var(--dashboard-text-muted);
        font-size: 0.72rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.35px;
        padding: 0.7rem 0.65rem;
        border-bottom: 1px solid #dfe3e8;
        background: #f8fafc;
        white-space: nowrap;
    }

    .parts-used-table td {
        padding: 0.75rem 0.65rem;
        border-bottom: 1px solid #edf0f4;
        color: var(--dashboard-text-main);
        font-size: 0.86rem;
        vertical-align: top;
    }

    .parts-used-table .money-cell {
        text-align: right;
        white-space: nowrap;
        font-weight: 900;
    }

    .parts-note {
        color: var(--dashboard-text-muted);
        font-size: 0.76rem;
        margin-top: 0.2rem;
        max-width: 260px;
        white-space: normal;
    }

    .add-part-used-box {
        border: 1px solid #e5e7eb;
        border-radius: 16px;
        background: rgba(255, 253, 246, 0.72);
        padding: 1rem;
        margin-top: 1rem;
    }

    .add-part-title {
        color: var(--dashboard-text-main);
        font-size: 0.9rem;
        font-weight: 900;
        margin-bottom: 0.85rem;
    }

    .add-part-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.7fr) 120px minmax(0, 1fr) auto;
        gap: 0.8rem;
        align-items: end;
    }

    .add-part-field label {
        display: block;
        color: var(--dashboard-text-muted);
        font-size: 0.72rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.35px;
        margin-bottom: 0.35rem;
    }

    .add-part-control {
        width: 100%;
        min-height: 44px;
        border: 1px solid #dfe3e8;
        border-radius: 14px;
        padding: 0.65rem 0.8rem;
        color: var(--dashboard-text-main);
        font-size: 0.88rem;
        background: #fff;
    }

    .add-part-control:focus {
        border-color: var(--dashboard-primary);
        box-shadow: 0 0 0 4px rgba(245, 197, 24, 0.12);
        outline: none;
    }

    .add-part-btn {
        min-height: 44px;
        border-radius: 999px;
        background: var(--dashboard-primary);
        border: 1px solid var(--dashboard-primary);
        color: var(--black);
        font-size: 0.82rem;
        font-weight: 900;
        padding: 0.55rem 1rem;
        white-space: nowrap;
    }

    .add-part-btn:hover {
        background: var(--black);
        border-color: var(--black);
        color: var(--white);
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

    .job-detail-edit-btn,
    .minimal-save-btn {
        border: 1px solid var(--dashboard-primary);
        background: var(--dashboard-primary);
        color: var(--black);
        font-weight: 900;
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

    .minimal-modal-footer {
        border-top: none;
        padding-top: 0.75rem;
    }

    @media (max-width: 991.98px) {
        .add-part-grid {
            grid-template-columns: 1fr;
        }

        .add-part-btn {
            width: 100%;
        }
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

        .jo-pagination-wrap {
            justify-content: center;
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

                <?php if ($canCreateJobOrders): ?>
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
                <button type="button" class="jo-tab active" data-filter="All">All (<?php echo $counts['All']; ?>)</button>
                <button type="button" class="jo-tab" data-filter="Pending">Pending (<?php echo $counts['Pending']; ?>)</button>
                <button type="button" class="jo-tab" data-filter="In Progress">In Progress (<?php echo $counts['In Progress']; ?>)</button>
                <button type="button" class="jo-tab" data-filter="Ready for Pickup">Ready for Pickup (<?php echo $counts['Ready for Pickup']; ?>)</button>
                <button type="button" class="jo-tab" data-filter="Completed">Completed (<?php echo $counts['Completed']; ?>)</button>
                <button type="button" class="jo-tab" data-filter="Cancelled">Cancelled (<?php echo $counts['Cancelled']; ?>)</button>
            </div>

            <div class="jo-search-wrap">
                <div class="jo-search-bar">
                    <i class="bi bi-search"></i>
                    <input
                        type="text"
                        id="searchInput"
                        placeholder="Search by job number, customer, plate number, source, mechanic, or description..."
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
                            <th>Source</th>
                            <th>Mechanic</th>
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
                                    $dateCreated = formatDateValue($jo['date_created'] ?? null);
                                    $dateCompleted = formatDateValue($jo['date_completed'] ?? null);
                                    $expectedCompletionDate = formatDateValue($jo['expected_completion_date'] ?? null);
                                    $requestSource = !empty($jo['request_source']) ? $jo['request_source'] : 'Walk-in';
                                    $sourceBadgeClass = getRequestSourceBadgeClass($requestSource);
                                    $assignedMechanicName = trim($jo['assigned_mechanic_name'] ?? '');
                                    $completedByName = trim($jo['completed_by_name'] ?? '');
                                    $displayMechanic = $assignedMechanicName !== '' ? $assignedMechanicName : 'Unassigned';

                                    $partsUsedForJob = $jobPartsByOrder[$jobOrderId] ?? [];
                                    $partsUsedTotal = $jobPartsTotals[$jobOrderId] ?? 0;
                                    $displayCost = getDisplayCost($jo, $partsUsedTotal);

                                    $searchText = strtolower(
                                        $jobNumber . ' ' .
                                        $customerName . ' ' .
                                        $plateNumber . ' ' .
                                        $vehicleLabel . ' ' .
                                        $description . ' ' .
                                        $requestSource . ' ' .
                                        $displayMechanic . ' ' .
                                        $status
                                    );
                                ?>

                                <tr class="job-row"
                                    data-status="<?php echo htmlspecialchars($status); ?>"
                                    data-search="<?php echo htmlspecialchars($searchText); ?>">
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
                                            <div class="jo-vehicle-sub"><?php echo htmlspecialchars($vehicleLabel); ?></div>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <span class="<?php echo $sourceBadgeClass; ?>">
                                            <?php echo htmlspecialchars($requestSource); ?>
                                        </span>
                                    </td>

                                    <td class="jo-mechanic">
                                        <?php echo htmlspecialchars($displayMechanic); ?>
                                        <?php if ($status === 'Completed' && $completedByName !== ''): ?>
                                            <div class="jo-mechanic-sub">Completed by <?php echo htmlspecialchars($completedByName); ?></div>
                                        <?php endif; ?>
                                    </td>

                                    <td class="jo-total">
                                        ₱<?php echo number_format($displayCost, 2); ?>
                                        <?php if ($partsUsedTotal > 0): ?>
                                            <div class="jo-parts-sub">Includes parts: ₱<?php echo number_format($partsUsedTotal, 2); ?></div>
                                        <?php endif; ?>
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
                                                data-bs-toggle="modal"
                                                data-bs-target="#viewJobOrderModal<?php echo $jobOrderId; ?>">
                                                <i class="bi bi-eye"></i> View
                                            </button>

                                            <?php if (($status === 'Pending' || $status === 'In Progress') && $canEditJobOrders): ?>
                                                <button
                                                    type="button"
                                                    class="jo-action-btn jo-edit-btn"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editJobOrderModal<?php echo $jobOrderId; ?>">
                                                    <i class="bi bi-pencil-square"></i> Edit
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($status === 'Pending' && $canUpdateRepairProgress): ?>
                                                <form action="../controllers/JobOrderController.php" method="POST" class="jo-action-form">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="job_order_id" value="<?php echo $jobOrderId; ?>">
                                                    <input type="hidden" name="status" value="In Progress">
                                                    <button type="submit" class="jo-action-btn jo-start-btn">
                                                        <i class="bi bi-play-fill"></i> Start
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if ($status === 'In Progress' && $canUpdateRepairProgress): ?>
                                                <form action="../controllers/JobOrderController.php" method="POST" class="jo-action-form">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="job_order_id" value="<?php echo $jobOrderId; ?>">
                                                    <input type="hidden" name="status" value="Ready for Pickup">
                                                    <button type="submit" class="jo-action-btn jo-ready-btn">
                                                        <i class="bi bi-bag-check"></i> Ready
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if (($status === 'Ready for Pickup' || $status === 'In Progress') && $canCompleteJobOrders): ?>
                                                <form action="../controllers/JobOrderController.php" method="POST" class="jo-action-form">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="job_order_id" value="<?php echo $jobOrderId; ?>">
                                                    <input type="hidden" name="status" value="Completed">
                                                    <button type="submit" class="jo-action-btn jo-complete-btn">
                                                        <i class="bi bi-check-lg"></i> Complete
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if (($status === 'Pending' || $status === 'In Progress' || $status === 'Ready for Pickup') && $canCancelJobOrders): ?>
                                                <form
                                                    action="../controllers/JobOrderController.php"
                                                    method="POST"
                                                    class="jo-action-form"
                                                    onsubmit="return confirm('Cancel this job order? This will mark the record as Cancelled.');">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="job_order_id" value="<?php echo $jobOrderId; ?>">
                                                    <input type="hidden" name="status" value="Cancelled">
                                                    <button type="submit" class="jo-action-btn jo-cancel-btn">
                                                        <i class="bi bi-x-lg"></i> Cancel
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if ($status === 'Completed' && $canAccessBilling): ?>
                                                <a href="billing.php" class="jo-action-btn jo-billing-btn">
                                                    <i class="bi bi-receipt"></i> Billing
                                                </a>
                                            <?php endif; ?>

                                            <?php if (($status === 'Completed' && !$canAccessBilling) || ($status === 'Cancelled')): ?>
                                                <span class="jo-no-action">No action</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>

                                <?php ob_start(); ?>
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
                                                    <div class="job-detail-avatar"><i class="bi bi-tools"></i></div>
                                                    <div>
                                                        <div class="job-detail-name"><?php echo htmlspecialchars($jobNumber); ?></div>
                                                        <span class="<?php echo $badgeClass; ?>"><?php echo htmlspecialchars($status); ?></span>
                                                        <div class="job-detail-sub">
                                                            <?php echo htmlspecialchars($customerName); ?>
                                                            <?php if ($plateNumber !== ''): ?> • <?php echo htmlspecialchars($plateNumber); ?><?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="job-detail-grid">
                                                    <div class="job-detail-field">
                                                        <div class="job-detail-label">
                                                            <span>Customer</span>
                                                            <span class="linked-note"><i class="bi bi-check-circle-fill"></i> Linked</span>
                                                        </div>
                                                        <div class="job-detail-value"><?php echo htmlspecialchars($customerName); ?></div>
                                                    </div>

                                                    <div class="job-detail-field">
                                                        <div class="job-detail-label">
                                                            <span>Vehicle</span>
                                                            <span class="linked-note"><i class="bi bi-check-circle-fill"></i> Linked</span>
                                                        </div>
                                                        <div class="job-detail-value">
                                                            <?php echo htmlspecialchars($plateNumber); ?>
                                                            <?php if ($vehicleLabel !== ''): ?> — <?php echo htmlspecialchars($vehicleLabel); ?><?php endif; ?>
                                                        </div>
                                                    </div>

                                                    <div class="job-detail-field">
                                                        <div class="job-detail-label"><span>Request Source</span></div>
                                                        <div class="job-detail-value">
                                                            <span class="<?php echo $sourceBadgeClass; ?>"><?php echo htmlspecialchars($requestSource); ?></span>
                                                        </div>
                                                    </div>

                                                    <div class="job-detail-field">
                                                        <div class="job-detail-label"><span>Assigned Mechanic</span></div>
                                                        <div class="job-detail-value"><?php echo htmlspecialchars($displayMechanic); ?></div>
                                                    </div>

                                                    <div class="job-detail-field full">
                                                        <div class="job-detail-label"><span>Repair Description</span></div>
                                                        <div class="job-detail-value long-text"><?php echo htmlspecialchars($description); ?></div>
                                                    </div>

                                                    <div class="job-detail-field">
                                                        <div class="job-detail-label"><span>Status</span></div>
                                                        <div class="job-detail-value"><?php echo htmlspecialchars($status); ?></div>
                                                    </div>

                                                    <div class="job-detail-field">
                                                        <div class="job-detail-label"><span>Estimated Cost</span></div>
                                                        <div class="job-detail-value">₱<?php echo number_format(floatval($jo['estimated_cost'] ?? 0), 2); ?></div>
                                                    </div>

                                                    <div class="job-detail-field">
                                                        <div class="job-detail-label"><span>Parts Used Total</span></div>
                                                        <div class="job-detail-value">₱<?php echo number_format($partsUsedTotal, 2); ?></div>
                                                    </div>

                                                    <div class="job-detail-field">
                                                        <div class="job-detail-label"><span>Job Total</span></div>
                                                        <div class="job-detail-value">₱<?php echo number_format($displayCost, 2); ?></div>
                                                    </div>

                                                    <div class="job-detail-field">
                                                        <div class="job-detail-label"><span>Requires Down Payment</span></div>
                                                        <div class="job-detail-value"><?php echo !empty($jo['requires_down_payment']) ? 'Yes' : 'No'; ?></div>
                                                    </div>

                                                    <div class="job-detail-field">
                                                        <div class="job-detail-label"><span>Down Payment Amount</span></div>
                                                        <div class="job-detail-value">₱<?php echo number_format(floatval($jo['down_payment_amount'] ?? 0), 2); ?></div>
                                                    </div>

                                                    <div class="job-detail-field">
                                                        <div class="job-detail-label"><span>Date Created</span></div>
                                                        <div class="job-detail-value"><?php echo htmlspecialchars($dateCreated); ?></div>
                                                    </div>

                                                    <div class="job-detail-field">
                                                        <div class="job-detail-label"><span>Target Completion Date</span></div>
                                                        <div class="job-detail-value"><?php echo htmlspecialchars($expectedCompletionDate); ?></div>
                                                    </div>

                                                    <div class="job-detail-field">
                                                        <div class="job-detail-label"><span>Date Completed</span></div>
                                                        <div class="job-detail-value"><?php echo htmlspecialchars($dateCompleted); ?></div>
                                                    </div>

                                                    <div class="job-detail-field">
                                                        <div class="job-detail-label"><span>Completed By</span></div>
                                                        <div class="job-detail-value"><?php echo htmlspecialchars($completedByName !== '' ? $completedByName : 'N/A'); ?></div>
                                                    </div>

                                                    <?php if ($status === 'Cancelled' && !empty($jo['cancellation_reason'])): ?>
                                                        <div class="job-detail-field full">
                                                            <div class="job-detail-label"><span>Cancellation Reason</span></div>
                                                            <div class="job-detail-value long-text"><?php echo htmlspecialchars($jo['cancellation_reason']); ?></div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="parts-used-section">
                                                    <div class="parts-used-header">
                                                        <div>
                                                            <div class="parts-used-title">Parts Used</div>
                                                            <p class="parts-used-subtitle">Optional record of parts/materials used during this repair.</p>
                                                        </div>
                                                        <span class="parts-used-total">Parts Total: ₱<?php echo number_format($partsUsedTotal, 2); ?></span>
                                                    </div>

                                                    <?php if (empty($partsUsedForJob)): ?>
                                                        <div class="parts-used-empty">No parts have been recorded for this job order yet.</div>
                                                    <?php else: ?>
                                                        <div class="parts-used-table-wrap">
                                                            <table class="parts-used-table">
                                                                <thead>
                                                                    <tr>
                                                                        <th>Part</th>
                                                                        <th>Qty</th>
                                                                        <th>Unit Price</th>
                                                                        <th>Subtotal</th>
                                                                        <th>Recorded By</th>
                                                                        <th>Date</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <?php foreach ($partsUsedForJob as $usedPart): ?>
                                                                        <tr>
                                                                            <td>
                                                                                <strong><?php echo htmlspecialchars($usedPart['part_name']); ?></strong>
                                                                                <?php if (!empty($usedPart['notes'])): ?>
                                                                                    <div class="parts-note"><?php echo htmlspecialchars($usedPart['notes']); ?></div>
                                                                                <?php endif; ?>
                                                                            </td>
                                                                            <td><?php echo intval($usedPart['quantity_used']); ?> <?php echo htmlspecialchars($usedPart['unit'] ?? ''); ?></td>
                                                                            <td class="money-cell">₱<?php echo number_format(floatval($usedPart['unit_price_at_use']), 2); ?></td>
                                                                            <td class="money-cell">₱<?php echo number_format(floatval($usedPart['subtotal']), 2); ?></td>
                                                                            <td><?php echo htmlspecialchars(trim($usedPart['used_by_name'] ?? '') ?: 'N/A'); ?></td>
                                                                            <td><?php echo htmlspecialchars(formatDateTimeValue($usedPart['used_at'] ?? null)); ?></td>
                                                                        </tr>
                                                                    <?php endforeach; ?>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    <?php endif; ?>

                                                    <?php if (in_array($status, ['Pending', 'In Progress'], true) && $canUpdateRepairProgress): ?>
                                                        <div class="add-part-used-box">
                                                            <div class="add-part-title"><i class="bi bi-plus-circle me-1"></i> Add Part Used</div>

                                                            <?php if (empty($availableParts)): ?>
                                                                <div class="parts-used-empty mb-0">No active parts are available in inventory.</div>
                                                            <?php else: ?>
                                                                <form action="../controllers/JobOrderController.php" method="POST">
                                                                    <input type="hidden" name="action" value="add_part_used">
                                                                    <input type="hidden" name="job_order_id" value="<?php echo $jobOrderId; ?>">

                                                                    <div class="add-part-grid">
                                                                        <div class="add-part-field">
                                                                            <label>Part</label>
                                                                            <select name="part_id" class="add-part-control" required>
                                                                                <option value="" disabled selected>Select part</option>
                                                                                <?php foreach ($availableParts as $partOption): ?>
                                                                                    <?php
                                                                                        $stock = intval($partOption['quantity_on_hand']);
                                                                                        $partLabel = $partOption['part_name'];
                                                                                        if (!empty($partOption['brand'])) $partLabel .= ' - ' . $partOption['brand'];
                                                                                        if (!empty($partOption['specification'])) $partLabel .= ' (' . $partOption['specification'] . ')';
                                                                                    ?>
                                                                                    <?php if ($stock > 0): ?>
                                                                                        <option value="<?php echo intval($partOption['part_id']); ?>">
                                                                                            <?php echo htmlspecialchars($partLabel); ?> | Stock: <?php echo $stock; ?> | ₱<?php echo number_format(floatval($partOption['unit_price']), 2); ?>
                                                                                        </option>
                                                                                    <?php endif; ?>
                                                                                <?php endforeach; ?>
                                                                            </select>
                                                                        </div>

                                                                        <div class="add-part-field">
                                                                            <label>Quantity</label>
                                                                            <input type="number" name="quantity_used" class="add-part-control" min="1" value="1" required>
                                                                        </div>

                                                                        <div class="add-part-field">
                                                                            <label>Notes</label>
                                                                            <input type="text" name="notes" class="add-part-control" maxlength="500" placeholder="Optional">
                                                                        </div>

                                                                        <button type="submit" class="add-part-btn" onclick="return confirm('Record this part as used and deduct it from inventory stock?');">
                                                                            Add Part
                                                                        </button>
                                                                    </div>
                                                                </form>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <div class="modal-footer job-detail-footer">
                                                <button type="button" class="job-detail-close-btn" data-bs-dismiss="modal">Close</button>
                                                <?php if (($status === 'Pending' || $status === 'In Progress') && $canEditJobOrders): ?>
                                                    <button type="button" class="job-detail-edit-btn" data-bs-target="#editJobOrderModal<?php echo $jobOrderId; ?>" data-bs-toggle="modal">
                                                        Edit Job Order
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <?php if (($status === 'Pending' || $status === 'In Progress') && $canEditJobOrders): ?>
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
                                                                <textarea name="description" class="minimal-control" required><?php echo htmlspecialchars($description); ?></textarea>
                                                                <div class="minimal-helper">Update the issue, diagnosis, or requested repair description.</div>
                                                            </div>

                                                            <div class="minimal-form-field">
                                                                <label class="minimal-label">Estimated Cost</label>
                                                                <input type="number" step="0.01" min="0" name="estimated_cost" class="minimal-control" value="<?php echo htmlspecialchars(floatval($jo['estimated_cost'] ?? 0)); ?>" required>
                                                                <div class="minimal-helper">Initial estimated amount for this repair job.</div>
                                                            </div>

                                                            <div class="minimal-form-field">
                                                                <label class="minimal-label">Down Payment Amount</label>
                                                                <input type="number" step="0.01" min="0" name="down_payment_amount" class="minimal-control" value="<?php echo htmlspecialchars(floatval($jo['down_payment_amount'] ?? 0)); ?>">
                                                                <div class="minimal-helper">Leave as 0 if no down payment is required.</div>
                                                            </div>

                                                            <div class="minimal-check-field">
                                                                <input class="form-check-input" type="checkbox" name="requires_down_payment" id="requiresDownPayment<?php echo $jobOrderId; ?>" <?php echo !empty($jo['requires_down_payment']) ? 'checked' : ''; ?>>
                                                                <label for="requiresDownPayment<?php echo $jobOrderId; ?>">Requires Down Payment</label>
                                                            </div>

                                                            <div class="minimal-form-field full">
                                                                <div class="minimal-helper">Only Pending or In Progress job orders can be edited. Parts used are recorded separately in the View Details modal.</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="modal-footer minimal-modal-footer">
                                                    <button type="button" class="minimal-cancel-btn" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="minimal-save-btn">Save Changes</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <?php $jobOrderModals .= ob_get_clean(); ?>
                            <?php endforeach; ?>

                            <tr id="noJobResults" style="display:none;">
                                <td colspan="9">
                                    <div class="jo-empty-state">
                                        <i class="bi bi-search"></i>
                                        <div class="fw-bold mb-1">No matching job orders found</div>
                                        <div>Try another keyword or change the selected tab.</div>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td colspan="9">
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

            <div class="jo-pagination-wrap" id="jobOrderPagination"></div>

            <?php echo $jobOrderModals; ?>

            <?php if ($canCreateJobOrders): ?>
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
                                            <div class="minimal-helper">Choose the vehicle that will be assigned to this job order.</div>
                                        </div>

                                        <div class="minimal-form-field">
                                            <label class="minimal-label">Request Source</label>
                                            <select name="request_source" class="minimal-control" required>
                                                <option value="Walk-in" selected>Walk-in</option>
                                                <option value="Online">Online</option>
                                            </select>
                                            <div class="minimal-helper">Use Online only if the repair request came from the customer portal.</div>
                                        </div>

                                        <div class="minimal-form-field">
                                            <label class="minimal-label">Assigned Mechanic</label>
                                            <select name="assigned_mechanic_id" class="minimal-control">
                                                <option value="">Unassigned</option>
                                                <?php foreach ($mechanics as $mechanic): ?>
                                                    <option value="<?php echo intval($mechanic['user_id']); ?>">
                                                        <?php echo htmlspecialchars($mechanic['first_name'] . ' ' . $mechanic['last_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="minimal-helper">Optional for walk-in job orders, but recommended for tracking.</div>
                                        </div>

                                        <div class="minimal-form-field full">
                                            <label class="minimal-label">Repair Description</label>
                                            <textarea name="description" class="minimal-control" placeholder="Describe the issue, symptoms, diagnosis, or requested repair..." required></textarea>
                                            <div class="minimal-helper">This will help mechanics and billing staff understand the repair scope.</div>
                                        </div>

                                        <div class="minimal-form-field">
                                            <label class="minimal-label">Estimated Cost (₱)</label>
                                            <input type="number" step="0.01" min="0" name="estimated_cost" class="minimal-control" placeholder="0.00" required>
                                            <div class="minimal-helper">Initial amount for the repair job before optional parts used are added.</div>
                                        </div>

                                        <div class="minimal-form-field">
                                            <label class="minimal-label">Target Completion Date</label>
                                            <input type="date" name="expected_completion_date" class="minimal-control">
                                            <div class="minimal-helper">Optional internal target date for shop planning.</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="modal-footer minimal-modal-footer">
                                    <button type="button" class="minimal-cancel-btn" data-bs-dismiss="modal">Cancel</button>
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
const ITEMS_PER_PAGE = 5;

const tabs = document.querySelectorAll('.jo-tab');
const rows = Array.from(document.querySelectorAll('.job-row'));
const searchInput = document.getElementById('searchInput');
const noResults = document.getElementById('noJobResults');
const pagination = document.getElementById('jobOrderPagination');

let activeFilter = 'All';
let currentPage = 1;

function getFilteredRows() {
    const term = searchInput ? searchInput.value.trim().toLowerCase() : '';

    return rows.filter(row => {
        const status = row.getAttribute('data-status');
        const searchText = row.getAttribute('data-search') || '';

        const matchesStatus = activeFilter === 'All' || status === activeFilter;
        const matchesSearch = searchText.includes(term);

        return matchesStatus && matchesSearch;
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
    prevButton.className = 'jo-page-btn';
    prevButton.innerHTML = '&laquo;';
    prevButton.disabled = currentPage === 1;
    prevButton.addEventListener('click', function () {
        if (currentPage > 1) {
            currentPage--;
            applyFilters();
        }
    });
    pagination.appendChild(prevButton);

    for (let page = 1; page <= totalPages; page++) {
        const pageButton = document.createElement('button');
        pageButton.type = 'button';
        pageButton.className = 'jo-page-btn' + (page === currentPage ? ' active' : '');
        pageButton.textContent = page;
        pageButton.addEventListener('click', function () {
            currentPage = page;
            applyFilters();
        });
        pagination.appendChild(pageButton);
    }

    const nextButton = document.createElement('button');
    nextButton.type = 'button';
    nextButton.className = 'jo-page-btn';
    nextButton.innerHTML = '&raquo;';
    nextButton.disabled = currentPage === totalPages;
    nextButton.addEventListener('click', function () {
        if (currentPage < totalPages) {
            currentPage++;
            applyFilters();
        }
    });
    pagination.appendChild(nextButton);
}

function applyFilters() {
    const filteredRows = getFilteredRows();
    const totalPages = Math.ceil(filteredRows.length / ITEMS_PER_PAGE) || 1;

    if (currentPage > totalPages) {
        currentPage = totalPages;
    }

    const start = (currentPage - 1) * ITEMS_PER_PAGE;
    const end = start + ITEMS_PER_PAGE;

    rows.forEach(row => {
        row.style.display = 'none';
    });

    filteredRows.slice(start, end).forEach(row => {
        row.style.display = '';
    });

    if (noResults) {
        noResults.style.display = (filteredRows.length === 0 && rows.length > 0) ? '' : 'none';
    }

    renderPagination(totalPages);
}

tabs.forEach(tab => {
    tab.addEventListener('click', function() {
        tabs.forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        activeFilter = this.getAttribute('data-filter');
        currentPage = 1;
        applyFilters();
    });
});

if (searchInput) {
    searchInput.addEventListener('input', function () {
        currentPage = 1;
        applyFilters();
    });
}

applyFilters();
</script>

</body>
</html>