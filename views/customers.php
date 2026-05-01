<?php
session_start();

if (!isset($_SESSION['logged_in'])) {
    header("Location: login.php");
    exit;
}

require_once '../config/Database.php';

$db = (new Database())->getConnection();

$isOwner = (isset($_SESSION['role']) && $_SESSION['role'] === 'Owner');

function cleanInput($value) {
    return trim($value ?? '');
}

function redirectToCustomers($statusFilter = null) {
    $url = "customers.php";

    if ($statusFilter !== null && $statusFilter !== '') {
        $url .= "?status=" . urlencode($statusFilter);
    }

    header("Location: " . $url);
    exit;
}

function validateCustomerFields($first_name, $last_name, $email, $contact_number) {
    if ($first_name === '' || $last_name === '' || $contact_number === '') {
        throw new Exception("First name, last name, and contact number are required.");
    }

    if (!preg_match('/^\d{11}$/', $contact_number)) {
        throw new Exception("Contact number must be exactly 11 digits.");
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Please enter a valid email address.");
    }
}

function getCustomerInitials($firstName, $lastName) {
    $first = !empty($firstName) ? strtoupper(substr($firstName, 0, 1)) : '';
    $last = !empty($lastName) ? strtoupper(substr($lastName, 0, 1)) : '';

    return $first . $last;
}

function getFullName($customer) {
    return trim(
        ($customer['first_name'] ?? '') . ' ' .
        (!empty($customer['middle_name']) ? $customer['middle_name'] . ' ' : '') .
        ($customer['last_name'] ?? '')
    );
}

function getStatusBadgeClass($status) {
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
   POST ACTIONS
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'])) {
    $action = $_POST['action'];

    try {
        if ($action === 'add_customer') {
            $first_name = cleanInput($_POST['first_name'] ?? '');
            $middle_name = cleanInput($_POST['middle_name'] ?? '');
            $last_name = cleanInput($_POST['last_name'] ?? '');
            $email = cleanInput($_POST['email'] ?? '');
            $contact_number = cleanInput($_POST['contact_number'] ?? '');
            $address = cleanInput($_POST['address'] ?? '');

            validateCustomerFields($first_name, $last_name, $email, $contact_number);

            $stmtCheck = $db->prepare("
                SELECT customer_id
                FROM customer
                WHERE contact_number = ?
                   OR (email IS NOT NULL AND email != '' AND email = ?)
                LIMIT 1
            ");
            $stmtCheck->execute([$contact_number, $email]);

            if ($stmtCheck->fetch()) {
                throw new Exception("A customer with this contact number or email already exists.");
            }

            $stmtAdd = $db->prepare("
                INSERT INTO customer (
                    first_name,
                    middle_name,
                    last_name,
                    email,
                    contact_number,
                    address,
                    credit_balance,
                    credit_due_date,
                    created_at,
                    user_id,
                    status
                )
                VALUES (?, ?, ?, ?, ?, ?, 0, NULL, NOW(), NULL, 'Active')
            ");

            $stmtAdd->execute([
                $first_name,
                $middle_name !== '' ? $middle_name : null,
                $last_name,
                $email !== '' ? $email : null,
                $contact_number,
                $address !== '' ? $address : 'no address provided'
            ]);

            $_SESSION['success_message'] = "Customer added successfully.";
            redirectToCustomers('Active');
        }

        if ($action === 'update_customer') {
            $customer_id = intval($_POST['customer_id'] ?? 0);
            $first_name = cleanInput($_POST['first_name'] ?? '');
            $middle_name = cleanInput($_POST['middle_name'] ?? '');
            $last_name = cleanInput($_POST['last_name'] ?? '');
            $email = cleanInput($_POST['email'] ?? '');
            $contact_number = cleanInput($_POST['contact_number'] ?? '');
            $address = cleanInput($_POST['address'] ?? '');
            $current_filter = cleanInput($_POST['current_filter'] ?? $statusFilter);

            if ($customer_id <= 0) {
                throw new Exception("Invalid customer record.");
            }

            validateCustomerFields($first_name, $last_name, $email, $contact_number);

            $stmtCheck = $db->prepare("
                SELECT customer_id
                FROM customer
                WHERE customer_id != ?
                  AND (
                        contact_number = ?
                        OR (email IS NOT NULL AND email != '' AND email = ?)
                  )
                LIMIT 1
            ");
            $stmtCheck->execute([$customer_id, $contact_number, $email]);

            if ($stmtCheck->fetch()) {
                throw new Exception("Another customer already uses this contact number or email.");
            }

            $stmtUpdate = $db->prepare("
                UPDATE customer
                SET
                    first_name = ?,
                    middle_name = ?,
                    last_name = ?,
                    email = ?,
                    contact_number = ?,
                    address = ?
                WHERE customer_id = ?
            ");

            $stmtUpdate->execute([
                $first_name,
                $middle_name !== '' ? $middle_name : null,
                $last_name,
                $email !== '' ? $email : null,
                $contact_number,
                $address !== '' ? $address : 'no address provided',
                $customer_id
            ]);

            $_SESSION['success_message'] = "Customer record updated successfully.";
            redirectToCustomers($current_filter);
        }

        if ($action === 'deactivate_customer') {
            if (!$isOwner) {
                throw new Exception("Only the Owner can deactivate customer records.");
            }

            $customer_id = intval($_POST['customer_id'] ?? 0);
            $current_filter = cleanInput($_POST['current_filter'] ?? $statusFilter);

            if ($customer_id <= 0) {
                throw new Exception("Invalid customer record.");
            }

            $stmtDeactivate = $db->prepare("
                UPDATE customer
                SET
                    status = 'Inactive',
                    deactivated_at = NOW(),
                    archived_at = NULL
                WHERE customer_id = ?
            ");
            $stmtDeactivate->execute([$customer_id]);

            $_SESSION['success_message'] = "Customer profile deactivated successfully.";
            redirectToCustomers($current_filter);
        }

        if ($action === 'reactivate_customer') {
            if (!$isOwner) {
                throw new Exception("Only the Owner can reactivate customer records.");
            }

            $customer_id = intval($_POST['customer_id'] ?? 0);
            $current_filter = cleanInput($_POST['current_filter'] ?? $statusFilter);

            if ($customer_id <= 0) {
                throw new Exception("Invalid customer record.");
            }

            $stmtReactivate = $db->prepare("
                UPDATE customer
                SET
                    status = 'Active',
                    deactivated_at = NULL,
                    archived_at = NULL
                WHERE customer_id = ?
            ");
            $stmtReactivate->execute([$customer_id]);

            $_SESSION['success_message'] = "Customer profile reactivated successfully.";
            redirectToCustomers($current_filter);
        }

        if ($action === 'archive_customer') {
            if (!$isOwner) {
                throw new Exception("Only the Owner can archive customer records.");
            }

            $customer_id = intval($_POST['customer_id'] ?? 0);
            $current_filter = cleanInput($_POST['current_filter'] ?? $statusFilter);

            if ($customer_id <= 0) {
                throw new Exception("Invalid customer record.");
            }

            $stmtArchive = $db->prepare("
                UPDATE customer
                SET
                    status = 'Archived',
                    archived_at = NOW()
                WHERE customer_id = ?
            ");
            $stmtArchive->execute([$customer_id]);

            $_SESSION['success_message'] = "Customer profile archived successfully.";
            redirectToCustomers($current_filter);
        }

    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
        redirectToCustomers($statusFilter);
    }
}

/* =========================
   COUNTS
========================= */
$totalStmt = $db->prepare("SELECT COUNT(*) FROM customer");
$totalStmt->execute();
$totalCustomers = intval($totalStmt->fetchColumn());

$statusCountStmt = $db->prepare("
    SELECT status, COUNT(*) AS total
    FROM customer
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
   CUSTOMER LIST
========================= */
if ($statusFilter === 'All') {
    $stmt = $db->prepare("
        SELECT *
        FROM customer
        ORDER BY
            FIELD(status, 'Active', 'Inactive', 'Archived'),
            first_name ASC
    ");
    $stmt->execute();
} else {
    $stmt = $db->prepare("
        SELECT *
        FROM customer
        WHERE status = ?
        ORDER BY first_name ASC
    ");
    $stmt->execute([$statusFilter]);
}

$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
$displayCount = count($customers);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Customers - Norily's Repair Shop</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/style.css">

<style>
    .main-content {
        overflow-x: hidden;
    }

    .customers-page {
        width: 100%;
        max-width: 100%;
    }

    .customers-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 1.5rem;
    }

    .customers-header h2 {
        font-size: 2rem;
        font-weight: 900;
        color: var(--dashboard-text-main);
        margin-bottom: 0.25rem;
        line-height: 1.1;
    }

    .customers-count-text {
        color: var(--dashboard-text-muted);
        font-size: 0.95rem;
        font-weight: 500;
        margin-bottom: 0;
    }

    .customer-add-btn {
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

    .customer-add-btn i {
        font-size: 0.95rem;
    }

    .customer-add-btn:hover {
        background: var(--black);
        border-color: var(--black);
        color: var(--white);
    }

    .customer-alert {
        border-radius: 16px;
        font-size: 0.92rem;
        margin-bottom: 1.5rem;
    }

    .customer-filter-area {
        display: grid;
        grid-template-columns: minmax(260px, 1fr) auto;
        gap: 0.85rem;
        align-items: end;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid #e5e7eb;
    }

    .customer-filter-label {
        display: block;
        color: var(--dashboard-text-muted);
        font-size: 0.76rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.35px;
        margin-bottom: 0.35rem;
    }

    .customer-search-bar {
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

    .customer-search-bar i {
        font-size: 1.05rem;
        color: var(--dashboard-text-muted);
        flex-shrink: 0;
    }

    .customer-search-bar input {
        width: 100%;
        border: none;
        outline: none;
        background: transparent;
        color: var(--dashboard-text-main);
        font-size: 0.92rem;
    }

    .customer-search-bar input::placeholder {
        color: #6b7280;
    }

    .customer-status-tabs {
        display: flex;
        align-items: center;
        gap: 0.55rem;
        flex-wrap: wrap;
    }

    .customer-status-tab {
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

    .customer-status-tab:hover {
        color: var(--black);
        border-color: var(--dashboard-primary);
    }

    .customer-status-tab.active {
        background: var(--dashboard-primary);
        border-color: var(--dashboard-primary);
        color: var(--black);
    }

    .customer-table-wrap {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .customers-table {
        width: 100%;
        min-width: 1180px;
        border-collapse: collapse;
        background: transparent;
        margin-bottom: 0;
    }

    .customers-table thead th {
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

    .customers-table tbody td {
        padding: 1rem 0.85rem;
        border-bottom: 1px solid #e8ebef;
        vertical-align: middle;
        color: var(--dashboard-text-main);
        background: transparent;
        font-size: 0.95rem;
    }

    .customers-table tbody tr:hover td {
        background: rgba(245, 197, 24, 0.035);
    }

    .customers-table th:last-child,
    .customers-table td:last-child {
        min-width: 190px;
    }

    .customer-profile {
        display: flex;
        align-items: center;
        gap: 0.8rem;
        min-width: 0;
    }

    .customer-avatar {
        width: 44px;
        height: 44px;
        border-radius: 14px;
        background: var(--dashboard-primary-soft);
        color: var(--black);
        font-size: 0.92rem;
        font-weight: 900;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .customer-name {
        color: var(--dashboard-text-main);
        font-size: 0.95rem;
        font-weight: 900;
        margin-bottom: 0.15rem;
        max-width: 175px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .customer-sub {
        color: var(--dashboard-text-muted);
        font-size: 0.78rem;
        font-weight: 500;
    }

    .contact-stack {
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
        min-width: 0;
    }

    .contact-line {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: var(--dashboard-text-main);
        font-size: 0.88rem;
        min-width: 0;
    }

    .contact-line i {
        color: var(--dashboard-text-muted);
        flex-shrink: 0;
    }

    .contact-line span {
        display: inline-block;
        max-width: 185px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .address-cell {
        max-width: 190px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        color: var(--dashboard-text-main);
        font-size: 0.95rem;
    }

    .credit-text {
        font-size: 0.95rem;
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

    .form-control {
        border-radius: 12px;
        border: 1px solid #e5e7eb;
        padding: 0.78rem 0.95rem;
        font-size: 0.95rem;
    }

    .form-control:focus {
        border-color: var(--dashboard-primary);
        box-shadow: 0 0 0 4px rgba(245, 197, 24, 0.18);
    }

    .customer-detail-body {
        padding: 1.5rem;
    }

    .customer-detail-profile {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.5rem;
        padding-bottom: 1.25rem;
        border-bottom: 1px solid #eef2f6;
    }

    .customer-detail-avatar {
        width: 64px;
        height: 64px;
        border-radius: 18px;
        background: var(--dashboard-primary-soft);
        color: var(--black);
        font-size: 1.15rem;
        font-weight: 900;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .customer-detail-name {
        font-size: 1.15rem;
        font-weight: 900;
        color: var(--dashboard-text-main);
        margin-bottom: 0.4rem;
        line-height: 1.15;
    }

    .customer-detail-sub {
        color: var(--dashboard-text-muted);
        font-size: 0.84rem;
        margin-top: 0.35rem;
    }

    .profile-detail-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        column-gap: 1.35rem;
        row-gap: 1.2rem;
    }

    .profile-detail-field {
        min-width: 0;
    }

    .profile-detail-field.full {
        grid-column: 1 / -1;
    }

    .profile-detail-label {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        color: var(--dashboard-text-muted);
        font-size: 0.76rem;
        font-weight: 900;
        margin-bottom: 0.35rem;
    }

    .profile-detail-value {
        border: none;
        border-bottom: 1px solid #d7dde5;
        padding: 0.55rem 0 0.65rem 0;
        color: var(--dashboard-text-main);
        font-size: 0.95rem;
        font-weight: 800;
        min-height: 38px;
        word-break: break-word;
    }

    .profile-detail-value.long-text {
        min-height: 74px;
        line-height: 1.5;
    }

    .verified-note {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        color: #047857;
        font-size: 0.72rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .customer-detail-footer {
        border-top: none;
        padding-top: 0.6rem;
    }

    .customer-detail-close-btn,
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

    .customer-detail-close-btn:hover,
    .minimal-cancel-btn:hover {
        background: #f8fafc;
        color: var(--black);
    }

    .customer-detail-edit-btn,
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

    .customer-detail-edit-btn:hover,
    .minimal-save-btn:hover {
        background: var(--black);
        border-color: var(--black);
        color: var(--white);
    }

    .minimal-customer-form {
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
        .customer-filter-area {
            grid-template-columns: 1fr;
        }

        .customer-status-tabs {
            width: 100%;
        }

        .customer-status-tab {
            flex: 1;
            text-align: center;
        }
    }

    @media (max-width: 767.98px) {
        .customers-header {
            flex-direction: column;
            align-items: stretch;
        }

        .customer-add-btn {
            width: 100%;
            justify-content: center;
        }

        .customers-header h2 {
            font-size: 1.75rem;
        }

        .customer-search-bar {
            height: 42px;
            min-height: 42px;
        }

        .profile-detail-grid {
            grid-template-columns: 1fr;
        }

        .customer-detail-profile {
            align-items: flex-start;
        }

        .customer-detail-footer,
        .minimal-modal-footer {
            flex-direction: column-reverse;
            align-items: stretch;
        }

        .customer-detail-close-btn,
        .customer-detail-edit-btn,
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
    <div class="customers-page">

        <div class="customers-header">
            <div>
                <h2>Customers</h2>
                <p class="customers-count-text">
                    Showing <?php echo $displayCount; ?>
                    <?php echo strtolower($statusFilter); ?>
                    customer<?php echo $displayCount !== 1 ? 's' : ''; ?>
                </p>
            </div>

            <button
                class="btn customer-add-btn"
                data-bs-toggle="modal"
                data-bs-target="#addCustomerModal"
            >
                <i class="bi bi-plus-lg"></i>
                Add Customer
            </button>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show customer-alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show customer-alert">
                <i class="bi bi-exclamation-circle-fill me-2"></i>
                <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="customer-filter-area">
            <div>
                <label class="customer-filter-label">Search</label>
                <div class="customer-search-bar">
                    <i class="bi bi-search"></i>
                    <input
                        type="text"
                        id="customerSearch"
                        placeholder="Search by name, email, phone, address, or status..."
                    >
                </div>
            </div>

            <div class="customer-status-tabs">
                <a href="customers.php?status=Active" class="customer-status-tab <?php echo $statusFilter === 'Active' ? 'active' : ''; ?>">
                    Active
                </a>

                <a href="customers.php?status=Inactive" class="customer-status-tab <?php echo $statusFilter === 'Inactive' ? 'active' : ''; ?>">
                    Inactive
                </a>

                <a href="customers.php?status=Archived" class="customer-status-tab <?php echo $statusFilter === 'Archived' ? 'active' : ''; ?>">
                    Archived
                </a>

                <a href="customers.php?status=All" class="customer-status-tab <?php echo $statusFilter === 'All' ? 'active' : ''; ?>">
                    All
                </a>
            </div>
        </div>

        <div class="customer-table-wrap">
            <table class="customers-table" id="customersTable">
                <thead>
                    <tr>
                        <th>Profile</th>
                        <th>Contact</th>
                        <th>Address</th>
                        <th>Credit</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (count($customers) > 0): ?>
                        <?php foreach ($customers as $c): ?>
                            <?php
                                $customerId = intval($c['customer_id']);
                                $fullName = getFullName($c);
                                $initials = getCustomerInitials($c['first_name'] ?? '', $c['last_name'] ?? '');
                                $status = $c['status'] ?? 'Active';
                                $badgeClass = getStatusBadgeClass($status);
                                $address = !empty($c['address']) ? $c['address'] : 'no address provided';
                                $email = !empty($c['email']) ? $c['email'] : 'No email provided';
                                $createdAt = !empty($c['created_at']) ? date('M d, Y', strtotime($c['created_at'])) : 'N/A';
                                $creditBalance = isset($c['credit_balance']) ? floatval($c['credit_balance']) : 0;

                                $searchText = strtolower(
                                    $fullName . ' ' .
                                    ($c['email'] ?? '') . ' ' .
                                    ($c['contact_number'] ?? '') . ' ' .
                                    ($c['address'] ?? '') . ' ' .
                                    ($c['status'] ?? '')
                                );
                            ?>

                            <tr class="customer-row" data-search="<?php echo htmlspecialchars($searchText); ?>">
                                <td>
                                    <div class="customer-profile">
                                        <div class="customer-avatar">
                                            <?php echo htmlspecialchars($initials ?: 'C'); ?>
                                        </div>

                                        <div>
                                            <div class="customer-name" title="<?php echo htmlspecialchars($fullName); ?>">
                                                <?php echo htmlspecialchars($fullName); ?>
                                            </div>
                                            <div class="customer-sub">
                                                Customer ID: <?php echo $customerId; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <div class="contact-stack">
                                        <div class="contact-line">
                                            <i class="bi bi-telephone"></i>
                                            <span title="<?php echo htmlspecialchars($c['contact_number']); ?>">
                                                <?php echo htmlspecialchars($c['contact_number']); ?>
                                            </span>
                                        </div>

                                        <div class="contact-line">
                                            <i class="bi bi-envelope"></i>
                                            <span title="<?php echo htmlspecialchars($email); ?>">
                                                <?php echo htmlspecialchars($email); ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <div class="address-cell" title="<?php echo htmlspecialchars($address); ?>">
                                        <i class="bi bi-geo-alt text-muted me-1"></i>
                                        <?php echo htmlspecialchars($address); ?>
                                    </div>
                                </td>

                                <td>
                                    <?php if ($creditBalance > 0): ?>
                                        <span class="credit-text fw-bold text-warning">
                                            ₱<?php echo number_format($creditBalance, 2); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="credit-text text-muted">₱0.00</span>
                                    <?php endif; ?>
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
                                            title="View Customer"
                                            data-bs-toggle="modal"
                                            data-bs-target="#viewCustomerModal<?php echo $customerId; ?>"
                                        >
                                            <i class="bi bi-eye"></i>
                                        </button>

                                        <button
                                            type="button"
                                            class="icon-action-btn edit-btn"
                                            title="Edit Customer"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editCustomerModal<?php echo $customerId; ?>"
                                        >
                                            <i class="bi bi-pencil-square"></i>
                                        </button>

                                        <?php if ($isOwner): ?>

                                            <?php if ($status === 'Active'): ?>
                                                <form
                                                    method="POST"
                                                    action="customers.php?status=<?php echo urlencode($statusFilter); ?>"
                                                    class="action-form"
                                                    onsubmit="return confirm('Deactivate this customer profile?');"
                                                >
                                                    <input type="hidden" name="action" value="deactivate_customer">
                                                    <input type="hidden" name="customer_id" value="<?php echo $customerId; ?>">
                                                    <input type="hidden" name="current_filter" value="<?php echo htmlspecialchars($statusFilter); ?>">

                                                    <button
                                                        type="submit"
                                                        class="icon-action-btn deactivate-btn"
                                                        title="Deactivate Customer"
                                                    >
                                                        <i class="bi bi-person-dash"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form
                                                    method="POST"
                                                    action="customers.php?status=<?php echo urlencode($statusFilter); ?>"
                                                    class="action-form"
                                                    onsubmit="return confirm('Reactivate this customer profile?');"
                                                >
                                                    <input type="hidden" name="action" value="reactivate_customer">
                                                    <input type="hidden" name="customer_id" value="<?php echo $customerId; ?>">
                                                    <input type="hidden" name="current_filter" value="<?php echo htmlspecialchars($statusFilter); ?>">

                                                    <button
                                                        type="submit"
                                                        class="icon-action-btn reactivate-btn"
                                                        title="Reactivate Customer"
                                                    >
                                                        <i class="bi bi-person-check"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if ($status !== 'Archived'): ?>
                                                <form
                                                    method="POST"
                                                    action="customers.php?status=<?php echo urlencode($statusFilter); ?>"
                                                    class="action-form"
                                                    onsubmit="return confirm('Archive this customer profile? Historical records will be retained.');"
                                                >
                                                    <input type="hidden" name="action" value="archive_customer">
                                                    <input type="hidden" name="customer_id" value="<?php echo $customerId; ?>">
                                                    <input type="hidden" name="current_filter" value="<?php echo htmlspecialchars($statusFilter); ?>">

                                                    <button
                                                        type="submit"
                                                        class="icon-action-btn archive-btn"
                                                        title="Archive Customer"
                                                    >
                                                        <i class="bi bi-archive"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                        <?php endif; ?>

                                    </div>
                                </td>
                            </tr>

                            <div class="modal fade" id="viewCustomerModal<?php echo $customerId; ?>" tabindex="-1">
                                <div class="modal-dialog modal-dialog-centered modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <div>
                                                <h5 class="modal-title">Customer Details</h5>
                                                <small class="text-muted">Viewing customer record #<?php echo $customerId; ?></small>
                                            </div>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>

                                        <div class="modal-body customer-detail-body">
                                            <div class="customer-detail-profile">
                                                <div class="customer-detail-avatar">
                                                    <?php echo htmlspecialchars($initials ?: 'C'); ?>
                                                </div>

                                                <div>
                                                    <div class="customer-detail-name">
                                                        <?php echo htmlspecialchars($fullName); ?>
                                                    </div>

                                                    <span class="<?php echo $badgeClass; ?>">
                                                        <i class="bi bi-circle-fill" style="font-size: 0.45rem;"></i>
                                                        <?php echo htmlspecialchars($status); ?>
                                                    </span>

                                                    <div class="customer-detail-sub">
                                                        Customer ID: <?php echo $customerId; ?>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="profile-detail-grid">
                                                <div class="profile-detail-field">
                                                    <div class="profile-detail-label">
                                                        <span>Full Name</span>
                                                    </div>
                                                    <div class="profile-detail-value">
                                                        <?php echo htmlspecialchars($fullName); ?>
                                                    </div>
                                                </div>

                                                <div class="profile-detail-field">
                                                    <div class="profile-detail-label">
                                                        <span>Email Address</span>
                                                        <?php if (!empty($c['email'])): ?>
                                                            <span class="verified-note">
                                                                <i class="bi bi-check-circle-fill"></i>
                                                                Saved
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="profile-detail-value">
                                                        <?php echo htmlspecialchars($email); ?>
                                                    </div>
                                                </div>

                                                <div class="profile-detail-field">
                                                    <div class="profile-detail-label">
                                                        <span>Contact Number</span>
                                                        <span class="verified-note">
                                                            <i class="bi bi-check-circle-fill"></i>
                                                            Saved
                                                        </span>
                                                    </div>
                                                    <div class="profile-detail-value">
                                                        <?php echo htmlspecialchars($c['contact_number']); ?>
                                                    </div>
                                                </div>

                                                <div class="profile-detail-field">
                                                    <div class="profile-detail-label">
                                                        <span>Status</span>
                                                    </div>
                                                    <div class="profile-detail-value">
                                                        <?php echo htmlspecialchars($status); ?>
                                                    </div>
                                                </div>

                                                <div class="profile-detail-field full">
                                                    <div class="profile-detail-label">
                                                        <span>Address</span>
                                                    </div>
                                                    <div class="profile-detail-value long-text">
                                                        <?php echo htmlspecialchars($address); ?>
                                                    </div>
                                                </div>

                                                <div class="profile-detail-field">
                                                    <div class="profile-detail-label">
                                                        <span>Credit Balance</span>
                                                    </div>
                                                    <div class="profile-detail-value">
                                                        ₱<?php echo number_format($creditBalance, 2); ?>
                                                    </div>
                                                </div>

                                                <div class="profile-detail-field">
                                                    <div class="profile-detail-label">
                                                        <span>Credit Due Date</span>
                                                    </div>
                                                    <div class="profile-detail-value">
                                                        <?php echo !empty($c['credit_due_date']) ? htmlspecialchars($c['credit_due_date']) : 'N/A'; ?>
                                                    </div>
                                                </div>

                                                <div class="profile-detail-field">
                                                    <div class="profile-detail-label">
                                                        <span>Created At</span>
                                                    </div>
                                                    <div class="profile-detail-value">
                                                        <?php echo htmlspecialchars($createdAt); ?>
                                                    </div>
                                                </div>

                                                <?php if (!empty($c['deactivated_at'])): ?>
                                                    <div class="profile-detail-field">
                                                        <div class="profile-detail-label">
                                                            <span>Deactivated At</span>
                                                        </div>
                                                        <div class="profile-detail-value">
                                                            <?php echo htmlspecialchars($c['deactivated_at']); ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if (!empty($c['archived_at'])): ?>
                                                    <div class="profile-detail-field">
                                                        <div class="profile-detail-label">
                                                            <span>Archived At</span>
                                                        </div>
                                                        <div class="profile-detail-value">
                                                            <?php echo htmlspecialchars($c['archived_at']); ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="modal-footer customer-detail-footer">
                                            <button type="button" class="customer-detail-close-btn" data-bs-dismiss="modal">
                                                Close
                                            </button>

                                            <button type="button" class="customer-detail-edit-btn" data-bs-toggle="modal" data-bs-target="#editCustomerModal<?php echo $customerId; ?>">
                                                Edit Customer
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="modal fade" id="editCustomerModal<?php echo $customerId; ?>" tabindex="-1">
                                <div class="modal-dialog modal-dialog-centered modal-lg">
                                    <div class="modal-content">
                                        <form method="POST" action="customers.php?status=<?php echo urlencode($statusFilter); ?>">
                                            <input type="hidden" name="action" value="update_customer">
                                            <input type="hidden" name="customer_id" value="<?php echo $customerId; ?>">
                                            <input type="hidden" name="current_filter" value="<?php echo htmlspecialchars($statusFilter); ?>">

                                            <div class="modal-header">
                                                <div>
                                                    <h5 class="modal-title">Edit Customer</h5>
                                                    <small class="text-muted">Update customer contact and profile details.</small>
                                                </div>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>

                                            <div class="modal-body">
                                                <div class="minimal-customer-form">
                                                    <div class="minimal-section-title">Basic Customer Details</div>

                                                    <div class="minimal-form-grid">
                                                        <div class="minimal-form-field">
                                                            <label class="minimal-label">First Name</label>
                                                            <input
                                                                type="text"
                                                                name="first_name"
                                                                class="minimal-control"
                                                                value="<?php echo htmlspecialchars($c['first_name']); ?>"
                                                                required
                                                            >
                                                        </div>

                                                        <div class="minimal-form-field">
                                                            <label class="minimal-label">Middle Name</label>
                                                            <input
                                                                type="text"
                                                                name="middle_name"
                                                                class="minimal-control"
                                                                value="<?php echo htmlspecialchars($c['middle_name'] ?? ''); ?>"
                                                                placeholder="Optional"
                                                            >
                                                        </div>

                                                        <div class="minimal-form-field">
                                                            <label class="minimal-label">Last Name</label>
                                                            <input
                                                                type="text"
                                                                name="last_name"
                                                                class="minimal-control"
                                                                value="<?php echo htmlspecialchars($c['last_name']); ?>"
                                                                required
                                                            >
                                                        </div>

                                                        <div class="minimal-form-field half">
                                                            <label class="minimal-label">Email</label>
                                                            <input
                                                                type="email"
                                                                name="email"
                                                                class="minimal-control"
                                                                value="<?php echo htmlspecialchars($c['email'] ?? ''); ?>"
                                                                placeholder="Optional"
                                                            >
                                                            <div class="minimal-helper">
                                                                Optional, but useful for customer records.
                                                            </div>
                                                        </div>

                                                        <div class="minimal-form-field half">
                                                            <label class="minimal-label">Contact Number</label>
                                                            <input
                                                                type="text"
                                                                name="contact_number"
                                                                class="minimal-control"
                                                                value="<?php echo htmlspecialchars($c['contact_number']); ?>"
                                                                maxlength="11"
                                                                required
                                                            >
                                                            <div class="minimal-helper">
                                                                Required. Must be exactly 11 digits.
                                                            </div>
                                                        </div>

                                                        <div class="minimal-form-field full">
                                                            <label class="minimal-label">Address</label>
                                                            <textarea
                                                                name="address"
                                                                class="minimal-control"
                                                                placeholder="Optional"
                                                            ><?php echo htmlspecialchars($c['address'] ?? ''); ?></textarea>
                                                            <div class="minimal-helper">
                                                                If left blank, the system will save it as “no address provided.”
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

                        <tr id="noCustomerResults" style="display:none;">
                            <td colspan="7">
                                <div class="empty-state">
                                    <i class="bi bi-search"></i>
                                    <div class="fw-bold mb-1">No matching customers found</div>
                                    <div>Try another keyword or status filter.</div>
                                </div>
                            </td>
                        </tr>

                    <?php else: ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <i class="bi bi-people"></i>
                                    <div class="fw-bold mb-1">No customers found</div>
                                    <div>Add a customer record or switch to another status filter.</div>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
</main>
</div>

<div class="modal fade" id="addCustomerModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="POST" action="customers.php">
                <input type="hidden" name="action" value="add_customer">

                <div class="modal-header">
                    <div>
                        <h5 class="modal-title">Add Customer</h5>
                        <small class="text-muted">Create a new customer profile.</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="minimal-customer-form">
                        <div class="minimal-section-title">Basic Customer Details</div>

                        <div class="minimal-form-grid">
                            <div class="minimal-form-field">
                                <label class="minimal-label">First Name</label>
                                <input
                                    type="text"
                                    name="first_name"
                                    class="minimal-control"
                                    required
                                >
                            </div>

                            <div class="minimal-form-field">
                                <label class="minimal-label">Middle Name</label>
                                <input
                                    type="text"
                                    name="middle_name"
                                    class="minimal-control"
                                    placeholder="Optional"
                                >
                            </div>

                            <div class="minimal-form-field">
                                <label class="minimal-label">Last Name</label>
                                <input
                                    type="text"
                                    name="last_name"
                                    class="minimal-control"
                                    required
                                >
                            </div>

                            <div class="minimal-form-field half">
                                <label class="minimal-label">Email</label>
                                <input
                                    type="email"
                                    name="email"
                                    class="minimal-control"
                                    placeholder="Optional"
                                >
                                <div class="minimal-helper">
                                    Optional, but useful for customer records.
                                </div>
                            </div>

                            <div class="minimal-form-field half">
                                <label class="minimal-label">Contact Number</label>
                                <input
                                    type="text"
                                    name="contact_number"
                                    class="minimal-control"
                                    maxlength="11"
                                    placeholder="11-digit number"
                                    required
                                >
                                <div class="minimal-helper">
                                    Required. Must be exactly 11 digits.
                                </div>
                            </div>

                            <div class="minimal-form-field full">
                                <label class="minimal-label">Address</label>
                                <textarea
                                    name="address"
                                    class="minimal-control"
                                    placeholder="Optional"
                                ></textarea>
                                <div class="minimal-helper">
                                    If left blank, the system will save it as “no address provided.”
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
                        Save Customer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const customerSearch = document.getElementById('customerSearch');
const customersTable = document.getElementById('customersTable');
const noCustomerResults = document.getElementById('noCustomerResults');

if (customerSearch && customersTable) {
    customerSearch.addEventListener('input', function () {
        const searchValue = this.value.trim().toLowerCase();
        const rows = customersTable.querySelectorAll('tbody tr.customer-row');
        let visibleCount = 0;

        rows.forEach(row => {
            const text = row.dataset.search || '';

            if (text.includes(searchValue)) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        if (noCustomerResults) {
            noCustomerResults.style.display = visibleCount === 0 && rows.length > 0 ? '' : 'none';
        }
    });
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>