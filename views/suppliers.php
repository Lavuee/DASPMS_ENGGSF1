<?php
require_once __DIR__ . '/../includes/auth.php';

requirePermission('suppliers.manage');

require_once '../config/Database.php';
require_once '../includes/staff_ui.php';
$db = (new Database())->getConnection();

// Fetch active suppliers
$stmt = $db->query("SELECT * FROM supplier WHERE is_active = 1 ORDER BY supplier_name ASC");
$suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fields shared by the New supplier and Edit supplier popups
function supplierFormFields($s, $id) {
    $v = function ($key) use ($s) {
        return cpEscape($s[$key] ?? '');
    };
    ob_start();
    ?>
    <div class="sp-fgrid">
        <div class="sp-field sp-span">
            <label for="<?= $id ?>Name">Company name <span class="sp-req">*</span></label>
            <input type="text" name="supplier_name" id="<?= $id ?>Name" class="cp-input" value="<?= $v('supplier_name') ?>" required>
        </div>
        <div class="sp-field sp-span">
            <label for="<?= $id ?>Email">Email address for restock alerts <span class="sp-req">*</span></label>
            <input type="email" name="email" id="<?= $id ?>Email" class="cp-input" value="<?= $v('email') ?>" required>
            <p class="cp-hint">Low stock emails are sent here automatically.</p>
        </div>
        <div class="sp-field">
            <label for="<?= $id ?>Contact">Contact person</label>
            <input type="text" name="contact_person" id="<?= $id ?>Contact" class="cp-input" value="<?= $v('contact_person') ?>">
        </div>
        <div class="sp-field">
            <label for="<?= $id ?>Phone">Phone</label>
            <input type="text" name="phone" id="<?= $id ?>Phone" class="cp-input" value="<?= $v('phone') ?>" inputmode="tel">
        </div>
        <div class="sp-field sp-span">
            <label for="<?= $id ?>Address">Address</label>
            <input type="text" name="address" id="<?= $id ?>Address" class="cp-input" value="<?= $v('address') ?>">
        </div>
    </div>
    <?php
    return ob_get_clean();
}

$supplierDialogs = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Suppliers - Norily's Repair Shop</title>
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
        $pageActions = '<button type="button" class="cp-btn cp-btn-dark" data-sp-open="#addSupplierDialog">+ Add supplier</button>';
        spPageHead(
            spPortalLabel() . ' · Stock',
            'Suppliers',
            "Restock alert emails go to the supplier's email address."
        );
        cpFlash();
        ?>

        <div data-sp-filter="#supplierRows">
            <div class="sp-toolbar">
                <div class="sp-search-field-inline">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                    <input type="search" class="cp-input" data-sp-search placeholder="Search company or contact person" aria-label="Search company or contact person" autocomplete="off">
                </div>
                <div class="sp-toolbar-actions"><?= $pageActions ?></div>
            </div>
            <p class="sp-showing" data-sp-showing aria-live="polite" style="margin-top: 16px">Showing <?= count($suppliers) ?> of <?= count($suppliers) ?></p>
        </div>

        <table class="cp-table sp-table">
            <thead>
                <tr>
                    <th scope="col">Company</th>
                    <th scope="col">Contact person</th>
                    <th scope="col">Email for restock alerts</th>
                    <th scope="col">Phone</th>
                    <th scope="col"><span class="visually-hidden">Actions</span></th>
                </tr>
            </thead>
            <tbody id="supplierRows" data-cp-paginate="10" data-cp-noun="supplier">
                <?php foreach ($suppliers as $s): ?>
                    <?php $supplierId = intval($s['supplier_id']); ?>
                    <tr data-search="<?= cpEscape(strtolower($s['supplier_name'] . ' ' . $s['contact_person'] . ' ' . $s['email'] . ' ' . $s['phone'] . ' ' . $s['address'])) ?>">
                        <td data-label="Company">
                            <div>
                                <span class="sp-ref sp-wrap"><?= cpEscape($s['supplier_name']) ?></span>
                                <?php if (!empty($s['address'])): ?><span class="cp-cell-sub"><?= cpEscape($s['address']) ?></span><?php endif; ?>
                            </div>
                        </td>
                        <td data-label="Contact person"><?= cpEscape($s['contact_person'] ?: '—') ?></td>
                        <td data-label="Email"><a href="mailto:<?= cpEscape($s['email']) ?>" class="sp-mono sp-mail"><?= cpEscape($s['email']) ?></a></td>
                        <td data-label="Phone" class="sp-nowrap"><?= cpEscape($s['phone'] ?: '—') ?></td>
                        <td class="cp-num">
                            <button type="button" class="cp-btn cp-btn-line cp-btn-sm" data-sp-open="#supplierEdit<?= $supplierId ?>">Edit</button>
                        </td>
                    </tr>

                    <?php ob_start(); ?>
                    <div class="sp-dialog" id="supplierEdit<?= $supplierId ?>" role="dialog" aria-modal="true" aria-labelledby="supplierEditTitle<?= $supplierId ?>" hidden>
                        <div class="sp-dialog-box">
                            <form action="../controllers/SupplierController.php" method="POST">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="edit">
                                <input type="hidden" name="supplier_id" value="<?= $supplierId ?>">

                                <div class="cp-modal-head">
                                    <div>
                                        <p class="cp-label"><?= cpEscape($s['supplier_name']) ?></p>
                                        <h2 class="cp-modal-title" id="supplierEditTitle<?= $supplierId ?>">Edit supplier</h2>
                                    </div>
                                    <button type="button" class="cp-modal-x" data-sp-close aria-label="Close">&times;</button>
                                </div>

                                <div class="sp-dialog-body"><?= supplierFormFields($s, 'edit' . $supplierId) ?></div>

                                <div class="cp-modal-foot sp-foot-split">
                                    <div class="sp-foot-left">
                                        <button type="submit" form="supplierArchive<?= $supplierId ?>" class="cp-btn cp-btn-line sp-btn-cancel">Archive</button>
                                    </div>
                                    <button type="button" class="cp-btn cp-btn-line" data-sp-close>Cancel</button>
                                    <button type="submit" class="cp-btn cp-btn-dark">Save changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <form action="../controllers/SupplierController.php" method="POST" id="supplierArchive<?= $supplierId ?>" hidden
                          data-cp-confirm="The supplier is removed from the list and from the supplier dropdowns. Past records keep their name."
                          data-cp-confirm-title="Archive this supplier?" data-cp-confirm-yes="Archive" data-cp-confirm-no="Go back">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="archive">
                        <input type="hidden" name="supplier_id" value="<?= $supplierId ?>">
                    </form>
                    <?php $supplierDialogs .= ob_get_clean(); ?>
                <?php endforeach; ?>

                <tr class="sp-empty-row" data-sp-empty hidden>
                    <td colspan="5">No suppliers match your search.</td>
                </tr>
                <?php if (empty($suppliers)): ?>
                    <tr class="sp-empty-row"><td colspan="5">No suppliers yet. Add one so low-stock emails have somewhere to go.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </main>
</div>

<?= $supplierDialogs ?>

<!-- New supplier -->
<div class="sp-dialog" id="addSupplierDialog" role="dialog" aria-modal="true" aria-labelledby="addSupplierTitle" hidden>
    <div class="sp-dialog-box">
        <form action="../controllers/SupplierController.php" method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add">

            <div class="cp-modal-head">
                <div>
                    <p class="cp-label">Suppliers</p>
                    <h2 class="cp-modal-title" id="addSupplierTitle">New supplier</h2>
                </div>
                <button type="button" class="cp-modal-x" data-sp-close aria-label="Close">&times;</button>
            </div>

            <div class="sp-dialog-body"><?= supplierFormFields(null, 'add') ?></div>

            <div class="cp-modal-foot">
                <button type="button" class="cp-btn cp-btn-line" data-sp-close>Cancel</button>
                <button type="submit" class="cp-btn cp-btn-dark">Add supplier</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
