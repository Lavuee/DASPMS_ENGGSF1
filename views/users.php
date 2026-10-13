<?php
require_once __DIR__ . '/../includes/auth.php';

requirePermission('users.manage');

require_once '../config/Database.php';
require_once '../includes/staff_ui.php';

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

$activeCount = count(array_filter($users, function ($u) { return intval($u['is_active'] ?? 1) === 1; }));
$staffRoles = ['Cashier', 'Head Mechanic']; // Owner accounts are not created or edited here

// Name, role and username fields shared by the Register and Edit popups
function staffFormFields($u, $id, array $staffRoles) {
    $v = function ($key) use ($u) {
        return cpEscape($u[$key] ?? '');
    };
    ob_start();
    ?>
    <div class="sp-fgrid is-3">
        <div class="sp-field">
            <label for="<?= $id ?>First">First name <span class="sp-req">*</span></label>
            <input type="text" name="first_name" id="<?= $id ?>First" class="cp-input" value="<?= $v('first_name') ?>" required>
        </div>
        <div class="sp-field">
            <label for="<?= $id ?>Middle">Middle name</label>
            <input type="text" name="middle_name" id="<?= $id ?>Middle" class="cp-input" value="<?= $v('middle_name') ?>">
        </div>
        <div class="sp-field">
            <label for="<?= $id ?>Last">Last name <span class="sp-req">*</span></label>
            <input type="text" name="last_name" id="<?= $id ?>Last" class="cp-input" value="<?= $v('last_name') ?>" required>
        </div>
    </div>
    <div class="sp-fgrid" style="margin-top: 22px">
        <div class="sp-field">
            <label for="<?= $id ?>Role">Role <span class="sp-req">*</span></label>
            <select name="role" id="<?= $id ?>Role" class="cp-input" required>
                <?php foreach ($staffRoles as $role): ?>
                    <option value="<?= $role ?>"<?= ($u['role'] ?? '') === $role ? ' selected' : '' ?>><?= $role ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="sp-field">
            <label for="<?= $id ?>Username">Login username <span class="sp-req">*</span></label>
            <input type="text" name="username" id="<?= $id ?>Username" class="cp-input" value="<?= $v('username') ?>" autocomplete="off" required>
            <p class="cp-hint">Must be unique.</p>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

$staffDialogs = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Staff Accounts - Norily's Repair Shop</title>
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
        $pageActions = '<button type="button" class="cp-btn cp-btn-dark" data-sp-open="#addStaffDialog">+ Register staff</button>';
        spPageHead(
            spPortalLabel() . ' · Owner',
            'Staff Accounts',
            'Staff log in with their username. Give each new staff member a temporary password.'
        );
        cpFlash();
        ?>

        <div data-sp-filter="#staffRows">
            <div class="sp-toolbar">
                <div class="sp-search-field-inline">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                    <input type="search" class="cp-input" data-sp-search placeholder="Search name or username" aria-label="Search name or username" autocomplete="off">
                </div>
                <div class="sp-toolbar-actions"><?= $pageActions ?></div>
            </div>

            <div class="sp-chips" role="group" aria-label="Filter by status">
                <button type="button" class="sp-chip" data-sp-chip="all" aria-pressed="true">All <span class="sp-chip-count"><?= count($users) ?></span></button>
                <button type="button" class="sp-chip is-ok" data-sp-chip="active" aria-pressed="false"><span class="sp-chip-dot"></span>Active <span class="sp-chip-count"><?= $activeCount ?></span></button>
                <button type="button" class="sp-chip is-done" data-sp-chip="inactive" aria-pressed="false"><span class="sp-chip-dot"></span>Inactive <span class="sp-chip-count"><?= count($users) - $activeCount ?></span></button>
            </div>

            <p class="sp-showing" data-sp-showing aria-live="polite">Showing <?= count($users) ?> of <?= count($users) ?></p>
        </div>

        <table class="cp-table sp-table">
            <thead>
                <tr>
                    <th scope="col">Staff</th>
                    <th scope="col">Username</th>
                    <th scope="col">Role</th>
                    <th scope="col">Status</th>
                    <th scope="col"><span class="visually-hidden">Actions</span></th>
                </tr>
            </thead>
            <tbody id="staffRows">
                <?php foreach ($users as $u): ?>
                    <?php
                    $userId = intval($u['user_id']);
                    $fullName = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
                    $isActive = intval($u['is_active'] ?? 1) === 1;
                    $isOwnerAccount = $u['role'] === 'Owner';
                    ?>
                    <tr data-status="<?= $isActive ? 'active' : 'inactive' ?>" data-search="<?= cpEscape(strtolower($fullName . ' ' . $u['username'] . ' ' . $u['role'])) ?>">
                        <td data-label="Staff"><span class="sp-ref"><?= cpEscape($fullName) ?></span></td>
                        <td data-label="Username" class="sp-mono"><?= cpEscape($u['username']) ?></td>
                        <td data-label="Role"><?= cpEscape($u['role']) ?></td>
                        <td data-label="Status"><?= cpBadge($isActive ? 'Active' : 'Inactive') ?></td>
                        <td class="cp-num">
                            <?php if ($isOwnerAccount): ?>
                                <span class="cp-hint">Main account</span>
                            <?php else: ?>
                                <div class="sp-row-actions">
                                    <button type="button" class="cp-btn cp-btn-line cp-btn-sm" data-sp-open="#staffEdit<?= $userId ?>">Edit</button>
                                    <form action="../controllers/UserController.php" method="POST"
                                          data-cp-confirm="<?= $isActive ? 'This person can no longer log in until the account is reactivated.' : 'This person can log in again.' ?>"
                                          data-cp-confirm-title="<?= $isActive ? 'Deactivate' : 'Reactivate' ?> this account?"
                                          data-cp-confirm-yes="<?= $isActive ? 'Deactivate' : 'Reactivate' ?>" data-cp-confirm-no="Go back"<?= $isActive ? '' : ' data-cp-confirm-tone="dark"' ?>>
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="<?= $isActive ? 'deactivate' : 'reactivate' ?>">
                                        <input type="hidden" name="user_id" value="<?= $userId ?>">
                                        <button type="submit" class="cp-btn cp-btn-line cp-btn-sm<?= $isActive ? ' sp-btn-cancel' : '' ?>"><?= $isActive ? 'Deactivate' : 'Reactivate' ?></button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <?php if (!$isOwnerAccount): ?>
                        <?php ob_start(); ?>
                        <div class="sp-dialog" id="staffEdit<?= $userId ?>" role="dialog" aria-modal="true" aria-labelledby="staffEditTitle<?= $userId ?>" hidden>
                            <div class="sp-dialog-box">
                                <form action="../controllers/UserController.php" method="POST">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="user_id" value="<?= $userId ?>">

                                    <div class="cp-modal-head">
                                        <div>
                                            <p class="cp-label"><?= cpEscape($fullName) ?></p>
                                            <h2 class="cp-modal-title" id="staffEditTitle<?= $userId ?>">Edit staff account</h2>
                                        </div>
                                        <button type="button" class="cp-modal-x" data-sp-close aria-label="Close">&times;</button>
                                    </div>

                                    <div class="sp-dialog-body"><?= staffFormFields($u, 'edit' . $userId, $staffRoles) ?></div>

                                    <div class="cp-modal-foot">
                                        <button type="button" class="cp-btn cp-btn-line" data-sp-close>Cancel</button>
                                        <button type="submit" class="cp-btn cp-btn-dark">Save changes</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <?php $staffDialogs .= ob_get_clean(); ?>
                    <?php endif; ?>
                <?php endforeach; ?>

                <tr class="sp-empty-row" data-sp-empty hidden>
                    <td colspan="5">No staff match your search or filter.</td>
                </tr>
                <?php if (empty($users)): ?>
                    <tr class="sp-empty-row"><td colspan="5">No staff accounts yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </main>
</div>

<?= $staffDialogs ?>

<!-- Register new staff -->
<div class="sp-dialog" id="addStaffDialog" role="dialog" aria-modal="true" aria-labelledby="addStaffTitle" hidden>
    <div class="sp-dialog-box">
        <form action="../controllers/UserController.php" method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add">

            <div class="cp-modal-head">
                <div>
                    <p class="cp-label">Staff accounts</p>
                    <h2 class="cp-modal-title" id="addStaffTitle">Register new staff</h2>
                </div>
                <button type="button" class="cp-modal-x" data-sp-close aria-label="Close">&times;</button>
            </div>

            <div class="sp-dialog-body">
                <?= staffFormFields(['role' => 'Cashier'], 'add', $staffRoles) ?>
                <div class="sp-field" style="margin-top: 22px">
                    <label for="addPassword">Temporary password <span class="sp-req">*</span></label>
                    <input type="password" name="password" id="addPassword" class="cp-input"
                           minlength="8" maxlength="72" pattern="(?=.*[A-Za-z])(?=.*\d).{8,72}"
                           title="At least 8 characters with at least one letter and one number" autocomplete="new-password" required>
                    <p class="cp-hint">8–72 characters with a letter and a number. Owner accounts aren't created here.</p>
                </div>
            </div>

            <div class="cp-modal-foot">
                <button type="button" class="cp-btn cp-btn-line" data-sp-close>Cancel</button>
                <button type="submit" class="cp-btn cp-btn-dark">Create account</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
