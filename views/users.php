<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Owner') {
    header("Location: login.php");
    exit;
}

require_once '../config/Database.php';

$db = (new Database())->getConnection();
$stmt = $db->prepare("
    SELECT *
    FROM user
    WHERE role IN ('Owner', 'Cashier', 'Head Mechanic')
    ORDER BY role ASC, last_name ASC
");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

function getStaffBadgeClass($role) {
    if ($role === 'Owner') {
        return 'staff-role-badge role-owner';
    }

    if ($role === 'Head Mechanic') {
        return 'staff-role-badge role-mechanic';
    }

    return 'staff-role-badge role-cashier';
}

function getStaffStatusBadgeClass($isActive) {
    return intval($isActive) === 1 ? 'staff-status-badge status-active' : 'staff-status-badge status-inactive';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Staff Management - Norily's Repair Shop</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/style.css">

<style>
    .main-content {
        overflow-x: hidden;
    }

    .staff-page {
        width: 100%;
        max-width: 100%;
    }

    .staff-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 1.5rem;
    }

    .staff-header h2 {
        font-size: 2rem;
        font-weight: 900;
        color: var(--dashboard-text-main);
        margin-bottom: 0.25rem;
        line-height: 1.1;
    }

    .staff-subtitle {
        color: var(--dashboard-text-muted);
        font-size: 0.95rem;
        font-weight: 500;
        margin-bottom: 0;
    }

    .staff-register-btn {
        min-height: 44px;
        background: var(--dashboard-primary);
        border: 1px solid var(--dashboard-primary);
        color: var(--black);
        font-size: 0.9rem;
        font-weight: 800;
        padding: 0.55rem 1rem;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.45rem;
        transition: 0.2s ease;
        white-space: nowrap;
    }

    .staff-register-btn:hover {
        background: var(--black);
        border-color: var(--black);
        color: var(--white);
    }

    .staff-alert {
        border-radius: 16px;
        font-size: 0.92rem;
        margin-bottom: 1.5rem;
    }

    .staff-table-wrap {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .staff-table {
        width: 100%;
        min-width: 980px;
        border-collapse: collapse;
        background: transparent;
        margin-bottom: 0;
    }

    .staff-table thead th {
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

    .staff-table tbody td {
        padding: 1rem 0.85rem;
        border-bottom: 1px solid #e8ebef;
        vertical-align: middle;
        color: var(--dashboard-text-main);
        background: transparent;
        font-size: 0.95rem;
    }

    .staff-table tbody tr:hover td {
        background: rgba(245, 197, 24, 0.035);
    }

    .staff-profile {
        display: flex;
        align-items: center;
        gap: 0.8rem;
        min-width: 0;
    }

    .staff-avatar {
        width: 44px;
        height: 44px;
        border-radius: 14px;
        background: rgba(245, 197, 24, 0.16);
        color: var(--black);
        font-size: 1rem;
        font-weight: 900;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .staff-name {
        color: var(--dashboard-text-main);
        font-size: 0.95rem;
        font-weight: 900;
        margin-bottom: 0.15rem;
        max-width: 240px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .staff-sub {
        color: var(--dashboard-text-muted);
        font-size: 0.82rem;
        font-weight: 500;
    }

    .staff-role-badge,
    .staff-status-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        padding: 0.42rem 0.72rem;
        font-size: 0.78rem;
        font-weight: 900;
        white-space: nowrap;
    }

    .role-owner {
        background: #fee2e2;
        color: #b91c1c;
    }

    .role-cashier {
        background: #fff4cc;
        color: #7a5200;
    }

    .role-mechanic {
        background: rgba(16, 185, 129, 0.12);
        color: #047857;
    }

    .status-active {
        background: rgba(16, 185, 129, 0.12);
        color: #047857;
    }

    .status-inactive {
        background: #f1f5f9;
        color: #475569;
    }

    .staff-action-group {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 0.45rem;
        flex-wrap: nowrap;
    }

    .staff-action-form {
        margin: 0;
        display: inline-flex;
    }

    .icon-action-btn {
        width: 36px;
        height: 36px;
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

    .staff-locked {
        color: var(--dashboard-text-muted);
        font-size: 0.82rem;
        font-style: italic;
        white-space: nowrap;
    }

    .staff-empty-state {
        text-align: center;
        color: var(--dashboard-text-muted);
        padding: 3rem 1rem;
    }

    .staff-empty-state i {
        display: block;
        color: var(--dashboard-primary);
        font-size: 2.25rem;
        margin-bottom: 0.75rem;
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
        border-top: none;
        padding-top: 0.75rem;
    }

    .modal-title {
        font-size: 1rem;
        font-weight: 900;
    }

    .modal-header small {
        font-size: 0.78rem;
    }

    .minimal-staff-form {
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

    .minimal-helper {
        color: var(--dashboard-text-muted);
        font-size: 0.76rem;
        margin-top: 0.35rem;
    }

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

    .minimal-cancel-btn:hover {
        background: #f8fafc;
        color: var(--black);
    }

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

    .minimal-save-btn:hover {
        background: var(--black);
        border-color: var(--black);
        color: var(--white);
    }

    @media (max-width: 767.98px) {
        .staff-header {
            flex-direction: column;
            align-items: stretch;
        }

        .staff-register-btn {
            width: 100%;
        }

        .staff-header h2 {
            font-size: 1.75rem;
        }

        .minimal-form-grid {
            grid-template-columns: 1fr;
        }

        .minimal-form-field,
        .minimal-form-field.full {
            grid-column: 1 / -1;
        }

        .modal-footer {
            flex-direction: column-reverse;
            align-items: stretch;
        }

        .minimal-cancel-btn,
        .minimal-save-btn {
            width: 100%;
        }
    }
</style>
</head>

<body>
<div class="app-wrapper">
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="staff-page">

            <div class="staff-header">
                <div>
                    <h2>Staff Accounts</h2>
                    <p class="staff-subtitle">Manage system access for employees</p>
                </div>

                <button
                    class="btn staff-register-btn"
                    data-bs-toggle="modal"
                    data-bs-target="#addUserModal"
                >
                    <i class="bi bi-person-plus"></i>
                    Register Staff
                </button>
            </div>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show staff-alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show staff-alert">
                    <i class="bi bi-exclamation-circle-fill me-2"></i>
                    <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="staff-table-wrap">
                <table class="staff-table">
                    <thead>
                        <tr>
                            <th>Staff</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (count($users) > 0): ?>
                            <?php foreach ($users as $u): ?>
                                <?php
                                    $userId = intval($u['user_id']);
                                    $initial = strtoupper(substr($u['first_name'], 0, 1));
                                    $fullName = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
                                    $badge = getStaffBadgeClass($u['role']);
                                    $statusBadge = getStaffStatusBadgeClass($u['is_active'] ?? 1);
                                    $isActive = intval($u['is_active'] ?? 1) === 1;
                                    $isOwnerAccount = ($u['role'] === 'Owner');
                                ?>

                                <tr>
                                    <td>
                                        <div class="staff-profile">
                                            <div class="staff-avatar">
                                                <?php echo htmlspecialchars($initial ?: 'S'); ?>
                                            </div>

                                            <div>
                                                <div class="staff-name" title="<?php echo htmlspecialchars($fullName); ?>">
                                                    <?php echo htmlspecialchars($fullName); ?>
                                                </div>
                                                <div class="staff-sub">
                                                    Staff ID: <?php echo $userId; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <td>
                                        @<?php echo htmlspecialchars($u['username']); ?>
                                    </td>

                                    <td>
                                        <span class="<?php echo $badge; ?>">
                                            <?php echo htmlspecialchars($u['role']); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="<?php echo $statusBadge; ?>">
                                            <?php echo $isActive ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>

                                    <td class="text-end">
                                        <?php if ($isOwnerAccount): ?>
                                            <span class="staff-locked">Protected</span>
                                        <?php else: ?>
                                            <div class="staff-action-group">
                                                <button
                                                    type="button"
                                                    class="icon-action-btn edit-btn"
                                                    title="Edit Staff"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editUserModal<?php echo $userId; ?>"
                                                >
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>

                                                <?php if ($isActive): ?>
                                                    <form
                                                        action="../controllers/UserController.php"
                                                        method="POST"
                                                        class="staff-action-form"
                                                        onsubmit="return confirm('Deactivate this staff account? This user will no longer be able to log in.');"
                                                    >
                                                        <input type="hidden" name="action" value="deactivate">
                                                        <input type="hidden" name="user_id" value="<?php echo $userId; ?>">

                                                        <button
                                                            type="submit"
                                                            class="icon-action-btn deactivate-btn"
                                                            title="Deactivate Staff"
                                                        >
                                                            <i class="bi bi-person-dash"></i>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <form
                                                        action="../controllers/UserController.php"
                                                        method="POST"
                                                        class="staff-action-form"
                                                        onsubmit="return confirm('Reactivate this staff account?');"
                                                    >
                                                        <input type="hidden" name="action" value="reactivate">
                                                        <input type="hidden" name="user_id" value="<?php echo $userId; ?>">

                                                        <button
                                                            type="submit"
                                                            class="icon-action-btn reactivate-btn"
                                                            title="Reactivate Staff"
                                                        >
                                                            <i class="bi bi-person-check"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>

                                <?php if (!$isOwnerAccount): ?>
                                    <div class="modal fade" id="editUserModal<?php echo $userId; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content">
                                                <form action="../controllers/UserController.php" method="POST">
                                                    <input type="hidden" name="action" value="update">
                                                    <input type="hidden" name="user_id" value="<?php echo $userId; ?>">

                                                    <div class="modal-header">
                                                        <div>
                                                            <h5 class="modal-title">Edit Staff Account</h5>
                                                            <small class="text-muted">Update staff details and assigned role.</small>
                                                        </div>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>

                                                    <div class="modal-body">
                                                        <div class="minimal-staff-form">
                                                            <div class="minimal-section-title">Staff Details</div>

                                                            <div class="minimal-form-grid">
                                                                <div class="minimal-form-field">
                                                                    <label class="minimal-label">First Name</label>
                                                                    <input
                                                                        type="text"
                                                                        name="first_name"
                                                                        class="minimal-control"
                                                                        value="<?php echo htmlspecialchars($u['first_name']); ?>"
                                                                        required
                                                                    >
                                                                </div>

                                                                <div class="minimal-form-field">
                                                                    <label class="minimal-label">Last Name</label>
                                                                    <input
                                                                        type="text"
                                                                        name="last_name"
                                                                        class="minimal-control"
                                                                        value="<?php echo htmlspecialchars($u['last_name']); ?>"
                                                                        required
                                                                    >
                                                                </div>

                                                                <div class="minimal-form-field full">
                                                                    <label class="minimal-label">Middle Name</label>
                                                                    <input
                                                                        type="text"
                                                                        name="middle_name"
                                                                        class="minimal-control"
                                                                        value="<?php echo htmlspecialchars($u['middle_name'] ?? ''); ?>"
                                                                        placeholder="Optional"
                                                                    >
                                                                </div>

                                                                <div class="minimal-form-field full">
                                                                    <label class="minimal-label">Assign Role</label>
                                                                    <select name="role" class="minimal-control" required>
                                                                        <option value="Cashier" <?php echo $u['role'] === 'Cashier' ? 'selected' : ''; ?>>
                                                                            Cashier
                                                                        </option>
                                                                        <option value="Head Mechanic" <?php echo $u['role'] === 'Head Mechanic' ? 'selected' : ''; ?>>
                                                                            Head Mechanic
                                                                        </option>
                                                                    </select>
                                                                </div>

                                                                <div class="minimal-form-field full">
                                                                    <div class="minimal-section-title mb-0">Login Credentials</div>
                                                                </div>

                                                                <div class="minimal-form-field full">
                                                                    <label class="minimal-label">Login Username</label>
                                                                    <input
                                                                        type="text"
                                                                        name="username"
                                                                        class="minimal-control"
                                                                        value="<?php echo htmlspecialchars($u['username']); ?>"
                                                                        required
                                                                    >
                                                                    <div class="minimal-helper">
                                                                        Username must be unique.
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="modal-footer">
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
                        <?php else: ?>
                            <tr>
                                <td colspan="5">
                                    <div class="staff-empty-state">
                                        <i class="bi bi-people"></i>
                                        <div class="fw-bold mb-1">No staff accounts found</div>
                                        <div>Register a staff account to manage system access.</div>
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

<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="../controllers/UserController.php" method="POST">
                <input type="hidden" name="action" value="add">

                <div class="modal-header">
                    <div>
                        <h5 class="modal-title">Register New Staff</h5>
                        <small class="text-muted">Create login access for an employee.</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="minimal-staff-form">
                        <div class="minimal-section-title">Staff Details</div>

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
                                <label class="minimal-label">Last Name</label>
                                <input
                                    type="text"
                                    name="last_name"
                                    class="minimal-control"
                                    required
                                >
                            </div>

                            <div class="minimal-form-field full">
                                <label class="minimal-label">Middle Name</label>
                                <input
                                    type="text"
                                    name="middle_name"
                                    class="minimal-control"
                                    placeholder="Optional"
                                >
                            </div>

                            <div class="minimal-form-field full">
                                <label class="minimal-label">Assign Role</label>
                                <select name="role" class="minimal-control" required>
                                    <option value="Cashier">Cashier</option>
                                    <option value="Head Mechanic">Head Mechanic</option>
                                </select>
                                <div class="minimal-helper">
                                    Owner accounts are not created from this form.
                                </div>
                            </div>

                            <div class="minimal-form-field full">
                                <div class="minimal-section-title mb-0">Login Credentials</div>
                            </div>

                            <div class="minimal-form-field">
                                <label class="minimal-label">Login Username</label>
                                <input
                                    type="text"
                                    name="username"
                                    class="minimal-control"
                                    required
                                >
                            </div>

                            <div class="minimal-form-field">
                                <label class="minimal-label">Temporary Password</label>
                                <input
                                    type="password"
                                    name="password"
                                    class="minimal-control"
                                    required
                                >
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="minimal-cancel-btn" data-bs-dismiss="modal">
                        Cancel
                    </button>

                    <button type="submit" class="minimal-save-btn">
                        Create Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>