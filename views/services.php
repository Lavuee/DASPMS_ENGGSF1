<?php
require_once __DIR__ . '/../includes/auth.php';

requirePermission('services.manage');

require_once '../config/Database.php';
require_once '../includes/business_rules.php';
require_once '../includes/staff_ui.php';

$db = (new Database())->getConnection();

$stmt = $db->prepare("
    SELECT *
    FROM service
    WHERE is_active = 1
    ORDER BY category ASC, service_name ASC
");
$stmt->execute();

$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Extra gallery photos for each service, keyed by service_id
require_once '../models/ServiceImage.php';
$extraImagesByService = (new ServiceImage($db))->listForItems(array_column($services, 'service_id'));

function serviceImagePath($image) {
    if (!$image) {
        return '../assets/images/services/default.png';
    }

    return '../assets/images/services/' . htmlspecialchars($image);
}

$categoryOptions = array_values(array_unique(array_filter(array_column($services, 'category'))));
sort($categoryOptions);

function serviceWarrantyText($days) {
    $days = intval($days);
    return $days > 0 ? $days . '-day warranty' : 'No warranty';
}

// Fields shared by the Add service and Edit service popups
function serviceFormFields($s, $id) {
    $v = function ($key, $default = '') use ($s) {
        return cpEscape($s !== null ? ($s[$key] ?? $default) : $default);
    };
    ob_start();
    ?>
    <div class="sp-fgrid">
        <div class="sp-field">
            <label for="<?= $id ?>Name">Service name <span class="sp-req">*</span></label>
            <input type="text" name="service_name" id="<?= $id ?>Name" class="cp-input" value="<?= $v('service_name') ?>" required>
        </div>
        <div class="sp-field">
            <label for="<?= $id ?>Category">Category <span class="sp-req">*</span></label>
            <input type="text" name="category" id="<?= $id ?>Category" class="cp-input" list="serviceCategoryList" value="<?= $v('category') ?>" placeholder="e.g. Rewinding Services" required>
        </div>
        <div class="sp-field">
            <label for="<?= $id ?>Price">Base price <span class="sp-req">*</span></label>
            <?php $priceLocked = $s !== null && !can('pricing.manage'); // SRS UC09: only the Owner changes prices ?>
            <input type="number" name="base_price" id="<?= $id ?>Price" class="cp-input" step="0.01" min="0" value="<?= $v('base_price') ?>" required<?= $priceLocked ? ' readonly' : '' ?>>
            <p class="cp-hint"><?= $priceLocked ? 'Only the Owner can change prices.' : 'Minimum service fee is ' . cpMoney(MIN_SERVICE_FEE) . '. Only the Owner can go lower.' ?></p>
        </div>
        <div class="sp-field">
            <label for="<?= $id ?>Warranty">Warranty days</label>
            <input type="number" name="warranty_days" id="<?= $id ?>Warranty" class="cp-input" min="0" value="<?= $v('warranty_days', '30') ?>">
            <p class="cp-hint">0 means no warranty.</p>
        </div>
        <label class="sp-check sp-span" for="<?= $id ?>Down">
            <input type="checkbox" name="requires_down_payment" id="<?= $id ?>Down" <?= $s !== null && intval($s['requires_down_payment']) === 1 ? 'checked' : '' ?>>
            <span>Requires a <?= intval(DOWN_PAYMENT_RATE * 100) ?>% down payment</span>
        </label>
        <div class="sp-field sp-span">
            <label for="<?= $id ?>Desc">Short description <span class="sp-req">*</span></label>
            <input type="text" name="description" id="<?= $id ?>Desc" class="cp-input" value="<?= $v('description') ?>" required>
        </div>
        <div class="sp-field sp-span">
            <label for="<?= $id ?>Full">Full description</label>
            <textarea name="full_description" id="<?= $id ?>Full" class="cp-input" rows="4"><?= $v('full_description') ?></textarea>
        </div>
        <div class="sp-field sp-span">
            <label for="<?= $id ?>Features">Features</label>
            <textarea name="features" id="<?= $id ?>Features" class="cp-input" rows="4" placeholder="One per line"><?= $v('features') ?></textarea>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

$serviceDialogs = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Services - Norily's Repair Shop</title>
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
        $pageActions = '<button type="button" class="cp-btn cp-btn-dark" data-sp-open="#addServiceDialog">+ Add service</button>';
        spPageHead(
            spPortalLabel() . ' · Stock',
            'Services',
            'What customers see on the website and in the transaction form.'
        );
        cpFlash();
        ?>

        <div data-sp-filter="#serviceRows">
            <div class="sp-toolbar">
                <div class="sp-search-field-inline">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                    <input type="search" class="cp-input" data-sp-search placeholder="Search service or category" aria-label="Search service or category" autocomplete="off">
                </div>
                <select class="cp-input" data-sp-type aria-label="Category">
                    <option value="all">All categories</option>
                    <?php foreach ($categoryOptions as $category): ?>
                        <option value="<?= cpEscape($category) ?>"><?= cpEscape($category) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="sp-toolbar-actions"><?= $pageActions ?></div>
            </div>
            <p class="sp-showing" data-sp-showing aria-live="polite" style="margin-top: 16px">Showing <?= count($services) ?> of <?= count($services) ?></p>
        </div>

        <table class="cp-table sp-table" id="servicesTable">
            <thead>
                <tr>
                    <th scope="col">Image</th>
                    <th scope="col">Service</th>
                    <th scope="col">Category</th>
                    <th scope="col">Description</th>
                    <th scope="col">Base price</th>
                    <th scope="col"><span class="visually-hidden">Actions</span></th>
                </tr>
            </thead>
            <tbody id="serviceRows" data-cp-paginate="10" data-cp-noun="service">
                <?php foreach ($services as $s): ?>
                    <?php
                    $serviceId = intval($s['service_id']);
                    $extraImages = $extraImagesByService[$s['service_id']] ?? [];
                    ?>
                    <tr data-type="<?= cpEscape($s['category']) ?>" data-search="<?= cpEscape(strtolower($s['service_name'] . ' ' . $s['category'] . ' ' . $s['description'])) ?>">
                        <td data-label="Image"><span class="sp-thumb"><?= cpThumb(!empty($s['image']) ? serviceImagePath($s['image']) : '', $s['service_name']) ?></span></td>
                        <td data-label="Service">
                            <div>
                                <span class="sp-ref sp-wrap"><?= cpEscape($s['service_name']) ?></span>
                                <span class="cp-cell-sub"><?= serviceWarrantyText($s['warranty_days']) ?></span>
                            </div>
                        </td>
                        <td data-label="Category"><?= cpEscape($s['category']) ?></td>
                        <td data-label="Description" class="sp-desc"><?= cpEscape($s['description']) ?></td>
                        <td data-label="Base price" class="sp-nowrap">
                            <div>
                                <span class="sp-mono sp-price"><?= cpMoney($s['base_price']) ?></span>
                                <?php if ((int) $s['requires_down_payment'] === 1): ?>
                                    <div class="sp-badge-gap"><span class="st pend"><?= intval(DOWN_PAYMENT_RATE * 100) ?>% down</span></div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="cp-num">
                            <button type="button" class="cp-btn cp-btn-line cp-btn-sm" data-sp-open="#serviceEdit<?= $serviceId ?>">Edit</button>
                        </td>
                    </tr>

                    <?php ob_start(); ?>
                    <div class="sp-dialog" id="serviceEdit<?= $serviceId ?>" role="dialog" aria-modal="true" aria-labelledby="serviceEditTitle<?= $serviceId ?>" hidden>
                        <div class="sp-dialog-box is-wide">
                            <form action="../controllers/ServiceController.php" method="POST" enctype="multipart/form-data">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="edit">
                                <input type="hidden" name="service_id" value="<?= $serviceId ?>">
                                <input type="hidden" name="existing_image" value="<?= cpEscape($s['image'] ?? '') ?>">

                                <div class="cp-modal-head">
                                    <div>
                                        <p class="cp-label"><?= cpEscape($s['service_name']) ?></p>
                                        <h2 class="cp-modal-title" id="serviceEditTitle<?= $serviceId ?>">Edit service</h2>
                                    </div>
                                    <button type="button" class="cp-modal-x" data-sp-close aria-label="Close">&times;</button>
                                </div>

                                <div class="sp-dialog-body">
                                    <?= serviceFormFields($s, 'edit' . $serviceId) ?>

                                    <p class="sp-dialog-section">Photos</p>
                                    <div class="sp-fgrid" style="margin-top: 12px">
                                        <div class="sp-field">
                                            <label for="editSvcImage<?= $serviceId ?>">Main image</label>
                                            <div class="sp-photo-current">
                                                <span class="sp-thumb"><?= cpThumb(!empty($s['image']) ? serviceImagePath($s['image']) : '', $s['service_name']) ?></span>
                                                <input type="file" name="image" id="editSvcImage<?= $serviceId ?>" class="cp-input sp-file" accept=".jpg,.jpeg,.png,.webp">
                                            </div>
                                            <p class="cp-hint">Leave empty to keep the current image.</p>
                                        </div>
                                        <div class="sp-field">
                                            <label for="editSvcExtra<?= $serviceId ?>">More photos</label>
                                            <input type="file" name="extra_images[]" id="editSvcExtra<?= $serviceId ?>" class="cp-input sp-file" accept=".jpg,.jpeg,.png,.webp" multiple>
                                            <p class="cp-hint">Up to <?= ServiceImage::MAX_PER_ITEM ?> photos (<?= max(0, ServiceImage::MAX_PER_ITEM - count($extraImages)) ?> left).</p>
                                        </div>
                                    </div>
                                    <?php if (!empty($extraImages)): ?>
                                        <div class="sp-photo-grid">
                                            <?php foreach ($extraImages as $extra): ?>
                                                <div class="sp-photo-tile">
                                                    <img src="<?= serviceImagePath($extra['image']) ?>" alt="Extra photo">
                                                    <label>Order <input type="number" name="image_order[<?= (int) $extra['image_id'] ?>]" value="<?= (int) $extra['sort_order'] ?>" min="0" max="99" class="cp-input"></label>
                                                    <label class="sp-check"><input type="checkbox" name="remove_images[]" value="<?= (int) $extra['image_id'] ?>"> <span>Remove</span></label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="cp-modal-foot sp-foot-split">
                                    <div class="sp-foot-left">
                                        <button type="submit" form="serviceDeactivate<?= $serviceId ?>" class="cp-btn cp-btn-line sp-btn-cancel">Deactivate</button>
                                    </div>
                                    <button type="button" class="cp-btn cp-btn-line" data-sp-close>Cancel</button>
                                    <button type="submit" class="cp-btn cp-btn-dark">Save changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <form action="../controllers/ServiceController.php" method="POST" id="serviceDeactivate<?= $serviceId ?>" hidden
                          data-cp-confirm="The service is hidden from the website and the transaction form. Past jobs keep it."
                          data-cp-confirm-title="Deactivate this service?" data-cp-confirm-yes="Deactivate" data-cp-confirm-no="Go back">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="service_id" value="<?= $serviceId ?>">
                    </form>
                    <?php $serviceDialogs .= ob_get_clean(); ?>
                <?php endforeach; ?>

                <tr class="sp-empty-row" data-sp-empty hidden>
                    <td colspan="6">No services match your search or filter.</td>
                </tr>
                <?php if (empty($services)): ?>
                    <tr class="sp-empty-row"><td colspan="6">No services yet. Add one so customers can request it.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </main>
</div>

<datalist id="serviceCategoryList">
    <?php foreach ($categoryOptions as $option): ?><option value="<?= cpEscape($option) ?>"></option><?php endforeach; ?>
</datalist>

<?= $serviceDialogs ?>

<!-- Add service -->
<div class="sp-dialog" id="addServiceDialog" role="dialog" aria-modal="true" aria-labelledby="addServiceTitle" hidden>
    <div class="sp-dialog-box is-wide">
        <form action="../controllers/ServiceController.php" method="POST" enctype="multipart/form-data">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add">

            <div class="cp-modal-head">
                <div>
                    <p class="cp-label">Services</p>
                    <h2 class="cp-modal-title" id="addServiceTitle">Add service</h2>
                </div>
                <button type="button" class="cp-modal-x" data-sp-close aria-label="Close">&times;</button>
            </div>

            <div class="sp-dialog-body">
                <?= serviceFormFields(null, 'add') ?>

                <p class="sp-dialog-section">Photos</p>
                <div class="sp-fgrid" style="margin-top: 12px">
                    <div class="sp-field">
                        <label for="addSvcImage">Main image</label>
                        <input type="file" name="image" id="addSvcImage" class="cp-input sp-file" accept=".jpg,.jpeg,.png,.webp">
                    </div>
                    <div class="sp-field">
                        <label for="addSvcExtra">More photos</label>
                        <input type="file" name="extra_images[]" id="addSvcExtra" class="cp-input sp-file" accept=".jpg,.jpeg,.png,.webp" multiple>
                        <p class="cp-hint">Optional. Up to <?= ServiceImage::MAX_PER_ITEM ?> photos.</p>
                    </div>
                </div>
            </div>

            <div class="cp-modal-foot">
                <button type="button" class="cp-btn cp-btn-line" data-sp-close>Cancel</button>
                <button type="submit" class="cp-btn cp-btn-dark">Add service</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
