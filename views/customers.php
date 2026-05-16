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
        SELECT c.*, u.username AS login_username
        FROM customer c
        LEFT JOIN user u ON c.user_id = u.user_id
        ORDER BY
            FIELD(c.status, 'Active', 'Inactive', 'Archived'),
            c.first_name ASC
    ");
    $stmt->execute();
} else {
    $stmt = $db->prepare("
        SELECT c.*, u.username AS login_username
        FROM customer c
        LEFT JOIN user u ON c.user_id = u.user_id
        WHERE c.status = ?
        ORDER BY c.first_name ASC
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

    .customer-table {
        width: 100%;
        min-width: 1180px;
        margin-bottom: 0;
        border-collapse: collapse;
        background: transparent;
    }

    .customer-table thead th {
        background: transparent;
        color: var(--dashboard-text-muted);
        font-size: 0.82rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.35px;
        padding: 0.9rem 0.85rem;
        border-bottom: 1px solid #dcdfe4;
        white-space: nowrap;
    }

    .customer-table tbody td {
        padding: 1rem 0.85rem;
        border-bottom: 1px solid #e8ebef;
        vertical-align: middle;
        color: var(--dashboard-text-main);
        background: transparent;
        font-size: 0.95rem;
        white-space: nowrap;
    }

    .customer-table tbody tr:hover td {
        background: rgba(245, 197, 24, 0.035);
    }

    .customer-table th:last-child,
    .customer-table td:last-child {
        min-width: 190px;
    }

    .customer-profile-cell {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        min-width: 230px;
    }

    .customer-avatar {
        width: 44px;
        height: 44px;
        border-radius: 14px;
        background: var(--dashboard-primary-soft);
        color: var(--black);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.86rem;
        font-weight: 900;
        flex-shrink: 0;
    }

    .customer-name {
        font-weight: 900;
        color: var(--dashboard-text-main);
        line-height: 1.25;
    }

    .customer-subtext {
        font-size: 0.78rem;
        color: var(--dashboard-text-muted);
        font-weight: 600;
        margin-top: 0.12rem;
        max-width: 260px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .customer-type-badge,
    .status-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.35rem;
        padding: 0.42rem 0.72rem;
        border-radius: 999px;
        font-size: 0.78rem;
        font-weight: 900;
        white-space: nowrap;
    }

    .type-walkin {
        background: #f3f4f6;
        color: #374151;
    }

    .type-registered {
        background: #e8f7ef;
        color: #15803d;
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

    .customer-action-group {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 0.45rem;
        flex-wrap: nowrap;
    }

    .customer-action-btn {
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
        white-space: nowrap;
    }

    .customer-action-btn:hover {
        transform: translateY(-1px);
    }

    .customer-action-btn.edit-btn:hover {
        background: var(--dashboard-primary);
        border-color: var(--dashboard-primary);
        color: var(--black);
    }

    .customer-action-btn.danger:hover {
        background: #fff1f2;
        border-color: #fecdd3;
        color: #be123c;
    }

    .customer-action-btn.success:hover {
        background: #ecfdf5;
        border-color: #bbf7d0;
        color: #15803d;
    }

    .customer-empty {
        padding: 3rem 1rem;
        text-align: center;
        color: var(--dashboard-text-muted);
        font-size: 0.95rem;
    }

    .customer-empty i {
        font-size: 2.25rem;
        color: var(--dashboard-primary);
        display: block;
        margin-bottom: 0.7rem;
    }

    .customer-empty .fw-bold {
        color: var(--dashboard-text-main);
        font-size: 0.95rem;
        font-weight: 900 !important;
    }

    .customer-pagination-wrap {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 0.35rem;
        margin-top: 1rem;
        flex-wrap: wrap;
    }

    .customer-page-btn {
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

    .customer-page-btn:hover:not(:disabled) {
        border-color: var(--dashboard-primary);
        background: #fffaf0;
        color: var(--black);
    }

    .customer-page-btn.active {
        background: var(--dashboard-primary);
        border-color: var(--dashboard-primary);
        color: var(--black);
    }

    .customer-page-btn:disabled {
        opacity: 0.45;
        cursor: not-allowed;
    }

    .customer-modal .modal-content {
        border: none;
        border-radius: 22px;
        overflow: hidden;
        box-shadow: 0 25px 60px rgba(17, 17, 17, 0.18);
    }

    .customer-modal .modal-header {
        background: #fff;
        border-bottom: 1px solid #e5e7eb;
        padding: 1.15rem 1.35rem;
    }

    .customer-modal .modal-title {
        font-weight: 900;
        color: var(--dashboard-text-main);
    }

    .customer-modal .modal-body {
        padding: 1.35rem;
    }

    .customer-modal .modal-footer {
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

    .modal-cancel-btn {
        border-radius: 999px;
        font-weight: 800;
        padding: 0.65rem 1.1rem;
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

        .customer-pagination-wrap {
            justify-content: center;
        }

        .customer-search-bar {
            height: 42px;
            min-height: 42px;
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

                <button type="button" class="customer-add-btn" onclick="window.location.href='create_transaction.php'">
                    <i class="bi bi-plus-lg"></i>
                    Add New Transaction
                </button>
            </div>

            <?php if(isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show customer-alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if(isset($_SESSION['error_message'])): ?>
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
                            id="customerSearchInput"
                            placeholder="Search by name, contact, email, customer type, status, or address..."
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
                <table class="table customer-table" id="customersTable">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Contact</th>
                            <th>Email</th>
                            <th>Customer Type</th>
                            <th>Status</th>
                            <th>Address</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($customers)): ?>
                            <tr>
                                <td colspan="7">
                                    <div class="customer-empty">
                                        <i class="bi bi-people"></i>
                                        <div class="fw-bold mb-1">No customer records found</div>
                                        <div>Add a walk-in customer or adjust your filter.</div>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($customers as $customer): ?>
                                <?php
                                    $customerName = getFullName($customer);
                                    $customerType = empty($customer['user_id']) ? 'Walk-in' : 'Registered';
                                    $customerTypeClass = empty($customer['user_id']) ? 'type-walkin' : 'type-registered';
                                    $statusClass = getStatusBadgeClass($customer['status'] ?? 'Active');
                                    $searchBlob = strtolower(
                                        $customerName . ' ' .
                                        ($customer['contact_number'] ?? '') . ' ' .
                                        ($customer['email'] ?? '') . ' ' .
                                        $customerType . ' ' .
                                        ($customer['status'] ?? '') . ' ' .
                                        ($customer['address'] ?? '')
                                    );
                                ?>
                                <tr class="customer-row" data-search="<?php echo htmlspecialchars($searchBlob); ?>">
                                    <td>
                                        <div class="customer-profile-cell">
                                            <div class="customer-avatar">
                                                <?php echo htmlspecialchars(getCustomerInitials($customer['first_name'], $customer['last_name'])); ?>
                                            </div>
                                            <div>
                                                <div class="customer-name">
                                                    <?php echo htmlspecialchars($customerName); ?>
                                                </div>
                                                <div class="customer-subtext">
                                                    ID: <?php echo intval($customer['customer_id']); ?>
                                                    <?php if (!empty($customer['login_username'])): ?>
                                                        · Login: <?php echo htmlspecialchars($customer['login_username']); ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <td>
                                        <i class="bi bi-telephone me-1 text-muted"></i>
                                        <?php echo htmlspecialchars($customer['contact_number'] ?? 'N/A'); ?>
                                    </td>

                                    <td>
                                        <?php if (!empty($customer['email'])): ?>
                                            <i class="bi bi-envelope me-1 text-muted"></i>
                                            <?php echo htmlspecialchars($customer['email']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">No email</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <span class="customer-type-badge <?php echo $customerTypeClass; ?>">
                                            <i class="bi <?php echo $customerType === 'Registered' ? 'bi-person-check' : 'bi-person'; ?>"></i>
                                            <?php echo $customerType; ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="<?php echo $statusClass; ?>">
                                            <?php echo htmlspecialchars($customer['status'] ?? 'Active'); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span title="<?php echo htmlspecialchars($customer['address'] ?? ''); ?>">
                                            <?php
                                                $address = $customer['address'] ?? 'No address';
                                                echo htmlspecialchars(strlen($address) > 28 ? substr($address, 0, 28) . '...' : $address);
                                            ?>
                                        </span>
                                    </td>

                                    <td class="text-end">
                                        <div class="customer-action-group">
                                            <button
                                                type="button"
                                                class="customer-action-btn edit-btn"
                                                title="Edit Customer"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editCustomerModal"
                                                data-customer-id="<?php echo intval($customer['customer_id']); ?>"
                                                data-first-name="<?php echo htmlspecialchars($customer['first_name'] ?? '', ENT_QUOTES); ?>"
                                                data-middle-name="<?php echo htmlspecialchars($customer['middle_name'] ?? '', ENT_QUOTES); ?>"
                                                data-last-name="<?php echo htmlspecialchars($customer['last_name'] ?? '', ENT_QUOTES); ?>"
                                                data-email="<?php echo htmlspecialchars($customer['email'] ?? '', ENT_QUOTES); ?>"
                                                data-contact="<?php echo htmlspecialchars($customer['contact_number'] ?? '', ENT_QUOTES); ?>"
                                                data-address="<?php echo htmlspecialchars($customer['address'] ?? '', ENT_QUOTES); ?>"
                                            >
                                                <i class="bi bi-pencil-square"></i>
                                            </button>

                                            <?php if ($isOwner): ?>
                                                <?php if (($customer['status'] ?? 'Active') === 'Active'): ?>
                                                    <form method="POST" class="m-0 action-confirm-form" data-confirm-message="Deactivate this customer profile?">
                                                        <input type="hidden" name="action" value="deactivate_customer">
                                                        <input type="hidden" name="customer_id" value="<?php echo intval($customer['customer_id']); ?>">
                                                        <input type="hidden" name="current_filter" value="<?php echo htmlspecialchars($statusFilter); ?>">
                                                        <button type="submit" class="customer-action-btn danger" title="Deactivate">
                                                            <i class="bi bi-person-dash"></i>
                                                        </button>
                                                    </form>
                                                <?php elseif (($customer['status'] ?? '') === 'Inactive'): ?>
                                                    <form method="POST" class="m-0 action-confirm-form" data-confirm-message="Reactivate this customer profile?">
                                                        <input type="hidden" name="action" value="reactivate_customer">
                                                        <input type="hidden" name="customer_id" value="<?php echo intval($customer['customer_id']); ?>">
                                                        <input type="hidden" name="current_filter" value="<?php echo htmlspecialchars($statusFilter); ?>">
                                                        <button type="submit" class="customer-action-btn success" title="Reactivate">
                                                            <i class="bi bi-person-check"></i>
                                                        </button>
                                                    </form>

                                                    <form method="POST" class="m-0 action-confirm-form" data-confirm-message="Archive this customer profile? This keeps historical records but removes it from active use.">
                                                        <input type="hidden" name="action" value="archive_customer">
                                                        <input type="hidden" name="customer_id" value="<?php echo intval($customer['customer_id']); ?>">
                                                        <input type="hidden" name="current_filter" value="<?php echo htmlspecialchars($statusFilter); ?>">
                                                        <button type="submit" class="customer-action-btn danger" title="Archive">
                                                            <i class="bi bi-archive"></i>
                                                        </button>
                                                    </form>
                                                <?php elseif (($customer['status'] ?? '') === 'Archived'): ?>
                                                    <form method="POST" class="m-0 action-confirm-form" data-confirm-message="Restore this archived customer profile?">
                                                        <input type="hidden" name="action" value="reactivate_customer">
                                                        <input type="hidden" name="customer_id" value="<?php echo intval($customer['customer_id']); ?>">
                                                        <input type="hidden" name="current_filter" value="<?php echo htmlspecialchars($statusFilter); ?>">
                                                        <button type="submit" class="customer-action-btn success" title="Restore">
                                                            <i class="bi bi-arrow-counterclockwise"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <tr id="noCustomerResults" style="display:none;">
                            <td colspan="7">
                                <div class="customer-empty">
                                    <i class="bi bi-search"></i>
                                    <div class="fw-bold mb-1">No matching customers found</div>
                                    <div>Try another keyword or change the selected filter.</div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="customer-pagination-wrap" id="customerPagination"></div>

        </div>
    </main>
</div>

<!-- ADD CUSTOMER MODAL -->
<div class="modal fade customer-modal" id="addCustomerModal" tabindex="-1" aria-labelledby="addCustomerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="add_customer">

            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="addCustomerModalLabel">Add Walk-in Customer</h5>
                    <p class="mb-0 text-muted small">
                        This creates a customer profile only. No username or login account is required.
                    </p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="alert alert-light border mb-3" style="border-radius: 14px;">
                    <i class="bi bi-info-circle me-1"></i>
                    Walk-in customers are stored for service history, vehicle linking, billing, and future transactions.
                </div>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label-custom">First Name <span class="text-danger">*</span></label>
                        <input type="text" name="first_name" class="form-control form-control-custom" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label-custom">Middle Name</label>
                        <input type="text" name="middle_name" class="form-control form-control-custom">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label-custom">Last Name <span class="text-danger">*</span></label>
                        <input type="text" name="last_name" class="form-control form-control-custom" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label-custom">Contact Number <span class="text-danger">*</span></label>
                        <input
                            type="text"
                            name="contact_number"
                            class="form-control form-control-custom"
                            maxlength="11"
                            pattern="[0-9]{11}"
                            placeholder="Example: 09123456789"
                            required
                        >
                    </div>

                    <div class="col-md-6">
                        <label class="form-label-custom">Email Address</label>
                        <input
                            type="email"
                            name="email"
                            class="form-control form-control-custom"
                            placeholder="Optional for walk-in customer"
                        >
                    </div>

                    <div class="col-12">
                        <label class="form-label-custom">Address</label>
                        <textarea
                            name="address"
                            class="form-control form-control-custom"
                            rows="3"
                            placeholder="Optional address"
                        ></textarea>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary modal-cancel-btn" data-bs-dismiss="modal">
                    Cancel
                </button>
                <button type="submit" class="btn modal-save-btn">
                    <i class="bi bi-save me-1"></i>
                    Save Customer
                </button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT CUSTOMER MODAL -->
<div class="modal fade customer-modal" id="editCustomerModal" tabindex="-1" aria-labelledby="editCustomerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="update_customer">
            <input type="hidden" name="customer_id" id="edit_customer_id">
            <input type="hidden" name="current_filter" value="<?php echo htmlspecialchars($statusFilter); ?>">

            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="editCustomerModalLabel">Edit Customer</h5>
                    <p class="mb-0 text-muted small">
                        Update customer contact details without changing login credentials.
                    </p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label-custom">First Name <span class="text-danger">*</span></label>
                        <input type="text" name="first_name" id="edit_first_name" class="form-control form-control-custom" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label-custom">Middle Name</label>
                        <input type="text" name="middle_name" id="edit_middle_name" class="form-control form-control-custom">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label-custom">Last Name <span class="text-danger">*</span></label>
                        <input type="text" name="last_name" id="edit_last_name" class="form-control form-control-custom" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label-custom">Contact Number <span class="text-danger">*</span></label>
                        <input
                            type="text"
                            name="contact_number"
                            id="edit_contact_number"
                            class="form-control form-control-custom"
                            maxlength="11"
                            pattern="[0-9]{11}"
                            required
                        >
                    </div>

                    <div class="col-md-6">
                        <label class="form-label-custom">Email Address</label>
                        <input
                            type="email"
                            name="email"
                            id="edit_email"
                            class="form-control form-control-custom"
                            placeholder="Optional for walk-in customer"
                        >
                    </div>

                    <div class="col-12">
                        <label class="form-label-custom">Address</label>
                        <textarea
                            name="address"
                            id="edit_address"
                            class="form-control form-control-custom"
                            rows="3"
                        ></textarea>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary modal-cancel-btn" data-bs-dismiss="modal">
                    Cancel
                </button>
                <button type="submit" class="btn modal-save-btn">
                    <i class="bi bi-save me-1"></i>
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const ITEMS_PER_PAGE = 5;

    const searchInput = document.getElementById('customerSearchInput');
    const customerRows = Array.from(document.querySelectorAll('.customer-row'));
    const noResults = document.getElementById('noCustomerResults');
    const pagination = document.getElementById('customerPagination');
    const editCustomerModal = document.getElementById('editCustomerModal');
    const confirmForms = document.querySelectorAll('.action-confirm-form');

    let currentPage = 1;

    function getFilteredRows() {
        const searchValue = searchInput ? searchInput.value.toLowerCase().trim() : '';

        return customerRows.filter(function (row) {
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
        prevButton.className = 'customer-page-btn';
        prevButton.innerHTML = '&laquo;';
        prevButton.disabled = currentPage === 1;
        prevButton.addEventListener('click', function () {
            if (currentPage > 1) {
                currentPage--;
                applyCustomerFilters();
            }
        });
        pagination.appendChild(prevButton);

        for (let page = 1; page <= totalPages; page++) {
            const pageButton = document.createElement('button');
            pageButton.type = 'button';
            pageButton.className = 'customer-page-btn' + (page === currentPage ? ' active' : '');
            pageButton.textContent = page;
            pageButton.addEventListener('click', function () {
                currentPage = page;
                applyCustomerFilters();
            });
            pagination.appendChild(pageButton);
        }

        const nextButton = document.createElement('button');
        nextButton.type = 'button';
        nextButton.className = 'customer-page-btn';
        nextButton.innerHTML = '&raquo;';
        nextButton.disabled = currentPage === totalPages;
        nextButton.addEventListener('click', function () {
            if (currentPage < totalPages) {
                currentPage++;
                applyCustomerFilters();
            }
        });
        pagination.appendChild(nextButton);
    }

    function applyCustomerFilters() {
        const filteredRows = getFilteredRows();
        const totalPages = Math.ceil(filteredRows.length / ITEMS_PER_PAGE) || 1;

        if (currentPage > totalPages) {
            currentPage = totalPages;
        }

        const start = (currentPage - 1) * ITEMS_PER_PAGE;
        const end = start + ITEMS_PER_PAGE;

        customerRows.forEach(function (row) {
            row.style.display = 'none';
        });

        filteredRows.slice(start, end).forEach(function (row) {
            row.style.display = '';
        });

        if (noResults) {
            noResults.style.display = filteredRows.length === 0 && customerRows.length > 0 ? '' : 'none';
        }

        renderPagination(totalPages);
    }

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            currentPage = 1;
            applyCustomerFilters();
        });
    }

    if (editCustomerModal) {
        editCustomerModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;

            if (!button) {
                return;
            }

            document.getElementById('edit_customer_id').value = button.getAttribute('data-customer-id') || '';
            document.getElementById('edit_first_name').value = button.getAttribute('data-first-name') || '';
            document.getElementById('edit_middle_name').value = button.getAttribute('data-middle-name') || '';
            document.getElementById('edit_last_name').value = button.getAttribute('data-last-name') || '';
            document.getElementById('edit_email').value = button.getAttribute('data-email') || '';
            document.getElementById('edit_contact_number').value = button.getAttribute('data-contact') || '';
            document.getElementById('edit_address').value = button.getAttribute('data-address') || '';
        });
    }

    confirmForms.forEach(function (form) {
        form.addEventListener('submit', function (event) {
            const message = form.getAttribute('data-confirm-message') || 'Are you sure you want to continue?';

            if (!confirm(message)) {
                event.preventDefault();
            }
        });
    });

    applyCustomerFilters();
});
</script>

</body>
</html>