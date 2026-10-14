<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/activity_log.php';

requirePermission('customers.manage');

require_once '../config/Database.php';
require_once '../includes/staff_ui.php';

$db = (new Database())->getConnection();

$isOwner = can('customers.archive');

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

            logActivity($db, 'Deactivated customer', 'Customer', $customer_id, activityRef($db, 'Customer', $customer_id));
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

            logActivity($db, 'Reactivated customer', 'Customer', $customer_id, activityRef($db, 'Customer', $customer_id));
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

            logActivity($db, 'Archived customer', 'Customer', $customer_id, activityRef($db, 'Customer', $customer_id));
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
// Every customer is loaded; the page filters by type and status itself.
$stmt = $db->prepare("
    SELECT c.*, u.username AS login_username
    FROM customer c
    LEFT JOIN user u ON c.user_id = u.user_id
    ORDER BY
        FIELD(c.status, 'Active', 'Inactive', 'Archived'),
        c.first_name ASC
");
$stmt->execute();

$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
$displayCount = count($customers);

// Customers with an active vehicle can start a job order straight from the list (read-only lookup)
$activeVehicleCounts = $db->query("
    SELECT customer_id, COUNT(*) FROM vehicle WHERE status = 'Active' GROUP BY customer_id
")->fetchAll(PDO::FETCH_KEY_PAIR);
$canCreateJobs = can('job_orders.manage');

$typeCounts = ['registered' => 0, 'walkin' => 0];
foreach ($customers as $customer) {
    $typeCounts[empty($customer['user_id']) ? 'walkin' : 'registered']++;
}

// Owner-only status button (deactivate / reactivate / archive / restore), confirmed in a popup
function customerStatusForm($action, $customerId, $label, $message, $danger, $statusFilter) {
    return '<form method="POST" data-cp-confirm="' . cpEscape($message) . '" data-cp-confirm-title="' . cpEscape($label) . ' customer?"'
        . ' data-cp-confirm-yes="' . cpEscape($label) . '" data-cp-confirm-no="Go back"' . ($danger ? '' : ' data-cp-confirm-tone="dark"') . '>'
        . csrfField()
        . '<input type="hidden" name="action" value="' . cpEscape($action) . '">'
        . '<input type="hidden" name="customer_id" value="' . intval($customerId) . '">'
        . '<input type="hidden" name="current_filter" value="' . cpEscape($statusFilter) . '">'
        . '<button type="submit" class="cp-btn cp-btn-line cp-btn-sm' . ($danger ? ' sp-btn-cancel' : '') . '">' . cpEscape($label) . '</button>'
        . '</form>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Customers - Norily's Repair Shop</title>
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
        $pageActions = '<button type="button" class="cp-btn cp-btn-dark" data-sp-open="#addCustomerDialog">+ Add walk-in customer</button>';
        spPageHead(
            spPortalLabel() . ' · Records',
            'Customers',
            'Registered customers sign up on the website. Walk-in customers are added here or through the transaction form.'
        );
        cpFlash();
        ?>

        <div data-sp-filter="#customerRows">
            <div class="sp-toolbar">
                <div class="sp-search-field-inline">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                    <input type="search" id="customerSearchInput" class="cp-input" data-sp-search placeholder="Search name, contact or email" aria-label="Search name, contact or email" autocomplete="off">
                </div>
                <select class="cp-input" data-sp-type aria-label="Status">
                    <option value="all">All statuses</option>
                    <option value="Active">Active (<?= $statusCounts['Active'] ?>)</option>
                    <option value="Inactive">Inactive (<?= $statusCounts['Inactive'] ?>)</option>
                    <option value="Archived">Archived (<?= $statusCounts['Archived'] ?>)</option>
                </select>
                <div class="sp-toolbar-actions"><?= $pageActions ?></div>
            </div>

            <div class="sp-chips" role="group" aria-label="Filter by type">
                <button type="button" class="sp-chip" data-sp-chip="all" aria-pressed="true">All <span class="sp-chip-count"><?= $totalCustomers ?></span></button>
                <button type="button" class="sp-chip" data-sp-chip="registered" aria-pressed="false">Registered <span class="sp-chip-count"><?= $typeCounts['registered'] ?></span></button>
                <button type="button" class="sp-chip" data-sp-chip="walkin" aria-pressed="false">Walk-in <span class="sp-chip-count"><?= $typeCounts['walkin'] ?></span></button>
            </div>

            <p class="sp-showing" data-sp-showing aria-live="polite">Showing <?= count($customers) ?> of <?= count($customers) ?></p>
        </div>

        <table class="cp-table sp-table" id="customersTable">
            <thead>
                <tr>
                    <th scope="col">Customer</th>
                    <th scope="col">Contact</th>
                    <th scope="col">Email</th>
                    <th scope="col">Type</th>
                    <th scope="col">Status</th>
                    <th scope="col">Address</th>
                    <th scope="col"><span class="visually-hidden">Actions</span></th>
                </tr>
            </thead>
            <tbody id="customerRows" data-cp-paginate="10" data-cp-noun="customer">
                <?php foreach ($customers as $customer): ?>
                    <?php
                    $customerId = intval($customer['customer_id']);
                    $customerName = getFullName($customer);
                    $isWalkin = empty($customer['user_id']);
                    $status = $customer['status'] ?? 'Active';
                    $address = trim($customer['address'] ?? '');
                    $hasAddress = $address !== '' && strtolower($address) !== 'no address provided';
                    $searchBlob = strtolower($customerName . ' ' . ($customer['contact_number'] ?? '') . ' ' . ($customer['email'] ?? '') . ' ' . ($isWalkin ? 'walk-in' : 'registered') . ' ' . $status . ' ' . $address . ' ' . ($customer['login_username'] ?? ''));
                    ?>
                    <tr data-status="<?= $isWalkin ? 'walkin' : 'registered' ?>" data-type="<?= cpEscape($status) ?>" data-search="<?= cpEscape($searchBlob) ?>">
                        <td data-label="Customer">
                            <div>
                                <span class="sp-ref"><?= cpEscape($customerName) ?></span>
                                <?php if (!empty($customer['login_username'])): ?>
                                    <span class="cp-cell-sub">Login: <?= cpEscape($customer['login_username']) ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td data-label="Contact" class="sp-nowrap"><?= cpEscape($customer['contact_number'] ?: '—') ?></td>
                        <td data-label="Email"><?= cpEscape($customer['email'] ?: '—') ?></td>
                        <td data-label="Type"><?= $isWalkin ? 'Walk-in' : 'Registered' ?></td>
                        <td data-label="Status"><?= cpBadge($status) ?></td>
                        <td data-label="Address"><?= $hasAddress ? cpEscape($address) : '<span class="cp-faint">—</span>' ?></td>
                        <td class="cp-num">
                            <div class="sp-row-actions">
                                <button type="button" class="cp-btn cp-btn-line cp-btn-sm" data-sp-open="#editCustomerDialog"
                                        data-customer-id="<?= $customerId ?>"
                                        data-name="<?= cpEscape($customerName) ?>"
                                        data-first-name="<?= cpEscape($customer['first_name'] ?? '') ?>"
                                        data-middle-name="<?= cpEscape($customer['middle_name'] ?? '') ?>"
                                        data-last-name="<?= cpEscape($customer['last_name'] ?? '') ?>"
                                        data-email="<?= cpEscape($customer['email'] ?? '') ?>"
                                        data-contact="<?= cpEscape($customer['contact_number'] ?? '') ?>"
                                        data-address="<?= cpEscape($hasAddress ? $address : '') ?>">Edit</button>
                                <?php if ($canCreateJobs && $status === 'Active' && !empty($activeVehicleCounts[$customerId])): ?>
                                    <a href="job_orders.php?new=1&amp;customer=<?= $customerId ?>" class="cp-btn cp-btn-line cp-btn-sm">New job</a>
                                <?php endif; ?>
                                <?php if ($isOwner): ?>
                                    <?php if ($status === 'Active'): ?>
                                        <?= customerStatusForm('deactivate_customer', $customerId, 'Deactivate', 'The customer is set to Inactive. Their history is kept and you can reactivate them later.', true, $statusFilter) ?>
                                    <?php elseif ($status === 'Inactive'): ?>
                                        <?= customerStatusForm('reactivate_customer', $customerId, 'Reactivate', 'The customer becomes Active again.', false, $statusFilter) ?>
                                        <?= customerStatusForm('archive_customer', $customerId, 'Archive', 'Archiving keeps all past records but takes the customer out of active use.', true, $statusFilter) ?>
                                    <?php elseif ($status === 'Archived'): ?>
                                        <?= customerStatusForm('reactivate_customer', $customerId, 'Restore', 'The archived customer becomes Active again.', false, $statusFilter) ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <tr class="sp-empty-row" data-sp-empty hidden>
                    <td colspan="7">No customers match your search or filter.</td>
                </tr>
                <?php if (empty($customers)): ?>
                    <tr class="sp-empty-row"><td colspan="7">No customers yet. Add a walk-in customer to get started.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </main>
</div>

<!-- Add walk-in customer -->
<div class="sp-dialog" id="addCustomerDialog" role="dialog" aria-modal="true" aria-labelledby="addCustomerTitle" hidden>
    <div class="sp-dialog-box">
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add_customer">

            <div class="cp-modal-head">
                <div>
                    <p class="cp-label">Customers</p>
                    <h2 class="cp-modal-title" id="addCustomerTitle">Add walk-in customer</h2>
                </div>
                <button type="button" class="cp-modal-x" data-sp-close aria-label="Close">&times;</button>
            </div>

            <div class="sp-dialog-body">
                <div class="sp-fgrid is-3">
                    <div class="sp-field">
                        <label for="addFirst">First name <span class="sp-req">*</span></label>
                        <input type="text" name="first_name" id="addFirst" class="cp-input" required>
                    </div>
                    <div class="sp-field">
                        <label for="addMiddle">Middle name</label>
                        <input type="text" name="middle_name" id="addMiddle" class="cp-input">
                    </div>
                    <div class="sp-field">
                        <label for="addLast">Last name <span class="sp-req">*</span></label>
                        <input type="text" name="last_name" id="addLast" class="cp-input" required>
                    </div>
                </div>
                <div class="sp-fgrid" style="margin-top: 22px">
                    <div class="sp-field">
                        <label for="addContact">Contact number <span class="sp-req">*</span></label>
                        <input type="text" name="contact_number" id="addContact" class="cp-input" maxlength="11" pattern="[0-9]{11}" placeholder="09XXXXXXXXX" inputmode="tel" required>
                    </div>
                    <div class="sp-field">
                        <label for="addEmail">Email address</label>
                        <input type="email" name="email" id="addEmail" class="cp-input" placeholder="Optional">
                    </div>
                    <div class="sp-field sp-span">
                        <label for="addAddress">Address</label>
                        <input type="text" name="address" id="addAddress" class="cp-input">
                    </div>
                </div>
                <p class="cp-hint" style="margin-top: 16px">This saves a customer profile only. Walk-in customers don't get a login.</p>
            </div>

            <div class="cp-modal-foot">
                <button type="button" class="cp-btn cp-btn-line" data-sp-close>Cancel</button>
                <button type="submit" class="cp-btn cp-btn-dark">Save customer</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit customer -->
<div class="sp-dialog" id="editCustomerDialog" role="dialog" aria-modal="true" aria-labelledby="editCustomerTitle" hidden>
    <div class="sp-dialog-box">
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update_customer">
            <input type="hidden" name="customer_id" id="edit_customer_id">
            <input type="hidden" name="current_filter" value="<?= cpEscape($statusFilter) ?>">

            <div class="cp-modal-head">
                <div>
                    <p class="cp-label" id="edit_customer_name"></p>
                    <h2 class="cp-modal-title" id="editCustomerTitle">Edit customer</h2>
                </div>
                <button type="button" class="cp-modal-x" data-sp-close aria-label="Close">&times;</button>
            </div>

            <div class="sp-dialog-body">
                <div class="sp-fgrid is-3">
                    <div class="sp-field">
                        <label for="edit_first_name">First name <span class="sp-req">*</span></label>
                        <input type="text" name="first_name" id="edit_first_name" class="cp-input" required>
                    </div>
                    <div class="sp-field">
                        <label for="edit_middle_name">Middle name</label>
                        <input type="text" name="middle_name" id="edit_middle_name" class="cp-input">
                    </div>
                    <div class="sp-field">
                        <label for="edit_last_name">Last name <span class="sp-req">*</span></label>
                        <input type="text" name="last_name" id="edit_last_name" class="cp-input" required>
                    </div>
                </div>
                <div class="sp-fgrid" style="margin-top: 22px">
                    <div class="sp-field">
                        <label for="edit_contact_number">Contact number <span class="sp-req">*</span></label>
                        <input type="text" name="contact_number" id="edit_contact_number" class="cp-input" maxlength="11" pattern="[0-9]{11}" inputmode="tel" required>
                    </div>
                    <div class="sp-field">
                        <label for="edit_email">Email address</label>
                        <input type="email" name="email" id="edit_email" class="cp-input" placeholder="Optional">
                    </div>
                    <div class="sp-field sp-span">
                        <label for="edit_address">Address</label>
                        <input type="text" name="address" id="edit_address" class="cp-input">
                    </div>
                </div>
                <p class="cp-hint" style="margin-top: 16px">Login details aren't changed here.</p>
            </div>

            <div class="cp-modal-foot">
                <button type="button" class="cp-btn cp-btn-line" data-sp-close>Cancel</button>
                <button type="submit" class="cp-btn cp-btn-dark">Save changes</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('editCustomerDialog').addEventListener('sp:open', function (event) {
    const btn = event.detail.trigger;
    if (!btn) return;
    document.getElementById('edit_customer_id').value = btn.dataset.customerId || '';
    document.getElementById('edit_customer_name').textContent = btn.dataset.name || '';
    document.getElementById('edit_first_name').value = btn.dataset.firstName || '';
    document.getElementById('edit_middle_name').value = btn.dataset.middleName || '';
    document.getElementById('edit_last_name').value = btn.dataset.lastName || '';
    document.getElementById('edit_email').value = btn.dataset.email || '';
    document.getElementById('edit_contact_number').value = btn.dataset.contact || '';
    document.getElementById('edit_address').value = btn.dataset.address || '';
});
</script>

</body>
</html>
