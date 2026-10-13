<?php
require_once __DIR__ . '/../includes/auth.php';

requirePermission('inventory.manage');

require_once '../config/Database.php';
require_once '../includes/staff_ui.php';

$db = (new Database())->getConnection();

// SRS: archiving, changing prices and cost (procurement) prices are for the Owner
$canArchiveParts = can('inventory.archive');
$canManagePricing = can('pricing.manage');

// Active suppliers for the dropdown menus
$supplierStmt = $db->query("SELECT supplier_id, supplier_name FROM supplier WHERE is_active = 1 ORDER BY supplier_name ASC");
$suppliersList = $supplierStmt->fetchAll(PDO::FETCH_ASSOC);

$viewStatus = $_GET['status'] ?? 'active';
$viewStatus = $viewStatus === 'archived' ? 'archived' : 'active';
$isActiveView = $viewStatus === 'active';
$isActiveValue = $isActiveView ? 1 : 0;

$countActiveStmt = $db->prepare("SELECT COUNT(*) FROM part WHERE is_active = 1");
$countActiveStmt->execute();
$activeCount = intval($countActiveStmt->fetchColumn());

$countArchivedStmt = $db->prepare("SELECT COUNT(*) FROM part WHERE is_active = 0");
$countArchivedStmt->execute();
$archivedCount = intval($countArchivedStmt->fetchColumn());

// Parts with their linked supplier name and email
$stmt = $db->prepare("
    SELECT p.*, s.supplier_name, s.email AS supplier_email 
    FROM part p
    LEFT JOIN supplier s ON p.supplier_id = s.supplier_id
    WHERE p.is_active = :is_active
    ORDER BY p.category ASC, p.part_name ASC
");
// All parts in this view are loaded; the page pages and filters them itself.
$stmt->bindValue(':is_active', $isActiveValue, PDO::PARAM_INT);
$stmt->execute();

$parts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Extra gallery photos for the parts on this page, keyed by part_id
require_once '../models/PartImage.php';
$extraImagesByPart = (new PartImage($db))->listForItems(array_column($parts, 'part_id'));

function partImagePath($image) {
    if (!$image) {
        return '../assets/images/parts/default.png';
    }

    return '../assets/images/parts/' . htmlspecialchars($image, ENT_QUOTES, 'UTF-8');
}

function cleanValue($value, $fallback = 'N/A') {
    $value = trim(html_entity_decode((string)($value ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    if ($value === '') {
        return htmlspecialchars($fallback, ENT_QUOTES, 'UTF-8');
    }

    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function inventoryStatusUrl($status) {
    $status = $status === 'archived' ? 'archived' : 'active';

    return 'inventory.php?status=' . $status;
}

// Suggestions for the free-text category and unit fields
$categoryOptions = $db->query("SELECT DISTINCT category FROM part WHERE category IS NOT NULL AND category <> '' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
$unitOptions = array_values(array_unique(array_merge(['piece', 'pair', 'set', 'meter', 'box'], $db->query("SELECT DISTINCT unit FROM part WHERE unit IS NOT NULL AND unit <> ''")->fetchAll(PDO::FETCH_COLUMN))));

// Stock state used for the chips and badges (same rule as everywhere else)
function partStockKey($qty, $threshold) {
    if ($qty <= 0) return 'out';
    if ($qty <= $threshold) return 'low';
    return 'in';
}

$stockCounts = ['in' => 0, 'low' => 0, 'out' => 0];
foreach ($parts as $p) {
    $stockCounts[partStockKey(intval($p['quantity_on_hand']), intval($p['low_stock_threshold']))]++;
}

// Field value for a form (raw text, escaped once)
function partField($p, $key, $default = '') {
    $value = $p !== null ? ($p[$key] ?? $default) : $default;
    return cpEscape(html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

// Fields shared by the Add part and Edit part popups
function partFormFields($p, $id, array $suppliersList) {
    $isEdit = $p !== null;
    ob_start();
    ?>
    <div class="sp-fgrid">
        <div class="sp-field">
            <label for="<?= $id ?>Name">Part name <span class="sp-req">*</span></label>
            <input type="text" name="part_name" id="<?= $id ?>Name" class="cp-input" value="<?= partField($p, 'part_name') ?>" required>
        </div>
        <div class="sp-field">
            <label for="<?= $id ?>Category">Category <span class="sp-req">*</span></label>
            <input type="text" name="category" id="<?= $id ?>Category" class="cp-input" list="partCategoryList" value="<?= partField($p, 'category') ?>" placeholder="e.g. Electrical Parts & Supplies" required>
        </div>
        <div class="sp-field">
            <label for="<?= $id ?>Brand">Brand</label>
            <input type="text" name="brand" id="<?= $id ?>Brand" class="cp-input" value="<?= partField($p, 'brand') ?>" placeholder="e.g. Bosch, NGK, Motolite">
        </div>
        <div class="sp-field">
            <label for="<?= $id ?>Unit">Unit</label>
            <input type="text" name="unit" id="<?= $id ?>Unit" class="cp-input" list="partUnitList" value="<?= partField($p, 'unit', 'piece') ?>">
        </div>
    </div>
    <div class="sp-fgrid is-3" style="margin-top: 22px">
        <div class="sp-field">
            <label for="<?= $id ?>Qty">Quantity <span class="sp-req">*</span></label>
            <input type="number" name="quantity_on_hand" id="<?= $id ?>Qty" class="cp-input" min="0" value="<?= partField($p, 'quantity_on_hand') ?>" required>
        </div>
        <?php $priceLocked = $isEdit && !can('pricing.manage'); ?>
        <div class="sp-field">
            <label for="<?= $id ?>Price">Unit price <span class="sp-req">*</span></label>
            <input type="number" name="unit_price" id="<?= $id ?>Price" class="cp-input" step="0.01" min="0" value="<?= partField($p, 'unit_price') ?>" required<?= $priceLocked ? ' readonly aria-describedby="' . $id . 'PriceNote"' : '' ?>>
            <?php if ($priceLocked): ?>
                <p class="cp-hint" id="<?= $id ?>PriceNote">Only the Owner can change prices.</p>
            <?php endif; ?>
        </div>
        <?php if (can('pricing.manage')): ?>
            <div class="sp-field">
                <label for="<?= $id ?>Cost">Cost price</label>
                <input type="number" name="cost_price" id="<?= $id ?>Cost" class="cp-input" step="0.01" min="0" value="<?= partField($p, 'cost_price') ?>">
            </div>
        <?php endif; ?>
        <div class="sp-field">
            <label for="<?= $id ?>Alert">Low stock alert at</label>
            <input type="number" name="low_stock_threshold" id="<?= $id ?>Alert" class="cp-input" min="0" value="<?= partField($p, 'low_stock_threshold', '5') ?>">
            <p class="cp-hint">The supplier gets an email at this level.</p>
        </div>
        <div class="sp-field" style="grid-column: span 2">
            <label for="<?= $id ?>Supplier">Primary supplier</label>
            <select name="supplier_id" id="<?= $id ?>Supplier" class="cp-input">
                <option value="">Select supplier</option>
                <?php foreach ($suppliersList as $sup): ?>
                    <option value="<?= intval($sup['supplier_id']) ?>"<?= $isEdit && intval($p['supplier_id'] ?? 0) === intval($sup['supplier_id']) ? ' selected' : '' ?>><?= cpEscape($sup['supplier_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="cp-hint">Restock alerts go to this supplier.</p>
        </div>
    </div>

    <?php if ($isEdit): ?>
        <div class="sp-fgrid" style="margin-top: 22px">
            <div class="sp-field">
                <label for="<?= $id ?>Ref">Restock document no.</label>
                <input type="text" name="stock_in_reference" id="<?= $id ?>Ref" class="cp-input" maxlength="100" placeholder="Optional, e.g. supplier receipt no.">
            </div>
            <div class="sp-field">
                <label for="<?= $id ?>RefNote">Restock note</label>
                <input type="text" name="stock_in_notes" id="<?= $id ?>RefNote" class="cp-input" maxlength="255" placeholder="Optional">
            </div>
            <p class="cp-hint sp-span">Raising the quantity records a stock-in entry with these details.</p>
        </div>
    <?php endif; ?>

    <div class="sp-fgrid" style="margin-top: 22px">
        <div class="sp-field sp-span">
            <label for="<?= $id ?>Desc">Short description <span class="sp-req">*</span></label>
            <input type="text" name="description" id="<?= $id ?>Desc" class="cp-input" value="<?= partField($p, 'description') ?>" required>
        </div>
        <div class="sp-field">
            <label for="<?= $id ?>Spec">Specification</label>
            <input type="text" name="specification" id="<?= $id ?>Spec" class="cp-input" value="<?= partField($p, 'specification') ?>" placeholder="e.g. 12V, 40A">
        </div>
        <div class="sp-field">
            <label for="<?= $id ?>Fits">Compatibility</label>
            <input type="text" name="compatibility" id="<?= $id ?>Fits" class="cp-input" value="<?= partField($p, 'compatibility') ?>" placeholder="e.g. Toyota Vios 2014–2019, Universal">
        </div>
        <div class="sp-field sp-span">
            <label for="<?= $id ?>Full">Full description</label>
            <textarea name="full_description" id="<?= $id ?>Full" class="cp-input" rows="4"><?= partField($p, 'full_description') ?></textarea>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

$partDialogs = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Parts Inventory - Norily's Repair Shop</title>
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
        $pageActions = $isActiveView
                ? '<a href="' . inventoryStatusUrl('archived') . '" class="cp-btn cp-btn-line">Archived (' . $archivedCount . ')</a><button type="button" class="cp-btn cp-btn-dark" data-sp-open="#addPartDialog">+ Add part</button>'
                : '<a href="' . inventoryStatusUrl('active') . '" class="cp-btn cp-btn-line">← Active parts (' . $activeCount . ')</a>';
        spPageHead(
            spPortalLabel() . ' · Stock',
            $isActiveView ? 'Parts Inventory' : 'Archived Parts',
            $isActiveView
                ? 'When a part drops to its alert level, its supplier gets a restock email.'
                : 'Parts taken out of the active list. Restore one to sell it again.'
        );
        cpFlash();
        ?>

        <div data-sp-filter="#partRows">
            <div class="sp-toolbar">
                <div class="sp-search-field-inline">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                    <input type="search" class="cp-input" data-sp-search placeholder="Search part, brand or supplier" aria-label="Search part, brand or supplier" autocomplete="off">
                </div>
                <div class="sp-toolbar-actions"><?= $pageActions ?></div>
            </div>

            <?php if ($isActiveView): ?>
                <div class="sp-chips" role="group" aria-label="Filter by stock">
                    <button type="button" class="sp-chip" data-sp-chip="all" aria-pressed="true">All <span class="sp-chip-count"><?= count($parts) ?></span></button>
                    <button type="button" class="sp-chip is-ok" data-sp-chip="in" aria-pressed="false"><span class="sp-chip-dot"></span>In stock <span class="sp-chip-count"><?= $stockCounts['in'] ?></span></button>
                    <button type="button" class="sp-chip is-pend" data-sp-chip="low" aria-pressed="false"><span class="sp-chip-dot"></span>Low stock <span class="sp-chip-count"><?= $stockCounts['low'] ?></span></button>
                    <button type="button" class="sp-chip is-bad" data-sp-chip="out" aria-pressed="false"><span class="sp-chip-dot"></span>Out of stock <span class="sp-chip-count"><?= $stockCounts['out'] ?></span></button>
                </div>
            <?php endif; ?>

            <p class="sp-showing" data-sp-showing aria-live="polite"<?= $isActiveView ? '' : ' style="margin-top: 16px"' ?>>Showing <?= count($parts) ?> of <?= count($parts) ?></p>
        </div>

        <table class="cp-table sp-table" id="partsTable">
            <thead>
                <tr>
                    <th scope="col">Image</th>
                    <th scope="col">Part</th>
                    <th scope="col">Category</th>
                    <th scope="col">Description</th>
                    <th scope="col">Specs</th>
                    <th scope="col">Stock</th>
                    <th scope="col">Price</th>
                    <th scope="col">Supplier</th>
                    <th scope="col"><span class="visually-hidden">Actions</span></th>
                </tr>
            </thead>
            <tbody id="partRows" data-cp-paginate="10" data-cp-noun="part">
                <?php foreach ($parts as $p): ?>
                    <?php
                    $partId = intval($p['part_id']);
                    $qty = intval($p['quantity_on_hand']);
                    $threshold = intval($p['low_stock_threshold']);
                    $stockKey = partStockKey($qty, $threshold);
                    $name = html_entity_decode((string) ($p['part_name'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $brand = html_entity_decode((string) ($p['brand'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $unit = trim($p['unit'] ?? '') ?: 'piece';
                    $spec = html_entity_decode((string) ($p['specification'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $fits = html_entity_decode((string) ($p['compatibility'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $searchText = strtolower($name . ' ' . $brand . ' ' . ($p['category'] ?? '') . ' ' . ($p['description'] ?? '') . ' ' . $spec . ' ' . $fits . ' ' . ($p['supplier_name'] ?? ''));
                    ?>
                    <tr data-status="<?= $stockKey ?>" data-search="<?= cpEscape($searchText) ?>">
                        <td data-label="Image">
                            <span class="sp-thumb"><?= cpThumb(!empty($p['image']) ? partImagePath($p['image']) : '', $name) ?></span>
                        </td>
                        <td data-label="Part">
                            <div>
                                <span class="sp-ref sp-wrap"><?= cpEscape($name) ?></span>
                                <span class="cp-cell-sub"><?= cpHasValue($brand) ? cpEscape($brand) : '—' ?> · per <?= cpEscape($unit) ?></span>
                            </div>
                        </td>
                        <td data-label="Category"><?= cleanValue($p['category'] ?? '', '—') ?></td>
                        <td data-label="Description" class="sp-desc"><?= cleanValue($p['description'] ?? '', '—') ?></td>
                        <td data-label="Specs">
                            <div>
                                <span class="sp-cell-line"><?= cpHasValue($spec) ? cpEscape($spec) : '—' ?></span>
                                <span class="cp-cell-sub">Fits <?= cpHasValue($fits) ? cpEscape($fits) : '—' ?></span>
                            </div>
                        </td>
                        <td data-label="Stock">
                            <div>
                                <strong class="sp-stock-qty"><?= $qty ?></strong>
                                <span class="cp-cell-sub">alert at <?= $threshold ?></span>
                                <?php if ($isActiveView): ?>
                                    <div class="sp-badge-gap"><?= $stockKey === 'out' ? cpBadge('Out of stock') : ($stockKey === 'low' ? cpBadge('Low stock') : cpBadge('In stock')) ?></div>
                                <?php else: ?>
                                    <div class="sp-badge-gap"><?= cpBadge('Archived') ?></div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td data-label="Price" class="sp-nowrap">
                            <div>
                                <span class="sp-mono sp-price"><?= cpMoney($p['unit_price']) ?></span>
                                <?php if ($canManagePricing && !empty($p['cost_price'])): ?>
                                    <span class="cp-cell-sub">cost <?= cpMoney($p['cost_price']) ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td data-label="Supplier"><?= cleanValue($p['supplier_name'] ?? '', 'No supplier') ?></td>
                        <td class="cp-num">
                            <div class="sp-row-actions">
                                <?php if ($isActiveView): ?>
                                    <button type="button" class="cp-btn cp-btn-line cp-btn-sm" data-sp-open="#partEdit<?= $partId ?>">Edit</button>
                                <?php elseif (!$canArchiveParts): ?>
                                    <span class="cp-cell-sub">Owner only</span>
                                <?php else: ?>
                                    <form action="../controllers/PartController.php" method="POST"
                                          data-cp-confirm="The part goes back to the active inventory." data-cp-confirm-title="Restore this part?"
                                          data-cp-confirm-yes="Restore" data-cp-confirm-no="Go back" data-cp-confirm-tone="dark">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="restore">
                                        <input type="hidden" name="part_id" value="<?= $partId ?>">
                                        <input type="hidden" name="redirect_status" value="archived">
                                        <button type="submit" class="cp-btn cp-btn-line cp-btn-sm">Restore</button>
                                    </form>
                                    <form action="../controllers/PartController.php" method="POST"
                                          data-cp-confirm="This can't be undone. It only works if the part was never used in a sale, order or job." data-cp-confirm-title="Delete permanently?"
                                          data-cp-confirm-yes="Delete" data-cp-confirm-no="Keep it">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="permanent_delete">
                                        <input type="hidden" name="part_id" value="<?= $partId ?>">
                                        <input type="hidden" name="redirect_status" value="archived">
                                        <button type="submit" class="cp-btn cp-btn-line cp-btn-sm sp-btn-cancel">Delete</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>

                    <?php if ($isActiveView): ?>
                        <?php ob_start(); ?>
                        <?php $extraImages = $extraImagesByPart[$p['part_id']] ?? []; ?>
                        <div class="sp-dialog" id="partEdit<?= $partId ?>" role="dialog" aria-modal="true" aria-labelledby="partEditTitle<?= $partId ?>" hidden>
                            <div class="sp-dialog-box is-wide">
                                <form action="../controllers/PartController.php" method="POST" enctype="multipart/form-data" id="partEditForm<?= $partId ?>">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="edit">
                                    <input type="hidden" name="part_id" value="<?= $partId ?>">
                                    <input type="hidden" name="existing_image" value="<?= cpEscape($p['image'] ?? '') ?>">
                                    <input type="hidden" name="redirect_status" value="active">

                                    <div class="cp-modal-head">
                                        <div>
                                            <p class="cp-label"><?= cpEscape($name) ?></p>
                                            <h2 class="cp-modal-title" id="partEditTitle<?= $partId ?>">Edit part</h2>
                                        </div>
                                        <button type="button" class="cp-modal-x" data-sp-close aria-label="Close">&times;</button>
                                    </div>

                                    <div class="sp-dialog-body">
                                        <?= partFormFields($p, 'edit' . $partId, $suppliersList) ?>

                                        <p class="sp-dialog-section">Photos</p>
                                        <div class="sp-fgrid" style="margin-top: 12px">
                                            <div class="sp-field">
                                                <label for="editImage<?= $partId ?>">Main image</label>
                                                <div class="sp-photo-current">
                                                    <span class="sp-thumb"><?= cpThumb(!empty($p['image']) ? partImagePath($p['image']) : '', $name) ?></span>
                                                    <input type="file" name="image" id="editImage<?= $partId ?>" class="cp-input sp-file" accept=".jpg,.jpeg,.png,.webp">
                                                </div>
                                                <p class="cp-hint">Shown first everywhere. Leave empty to keep the current image.</p>
                                            </div>
                                            <div class="sp-field">
                                                <label for="editExtra<?= $partId ?>">More photos</label>
                                                <input type="file" name="extra_images[]" id="editExtra<?= $partId ?>" class="cp-input sp-file" accept=".jpg,.jpeg,.png,.webp" multiple>
                                                <p class="cp-hint">Other angles for the part page. Up to <?= PartImage::MAX_PER_ITEM ?> photos (<?= max(0, PartImage::MAX_PER_ITEM - count($extraImages)) ?> left).</p>
                                            </div>
                                        </div>
                                        <?php if (!empty($extraImages)): ?>
                                            <div class="sp-photo-grid">
                                                <?php foreach ($extraImages as $extra): ?>
                                                    <div class="sp-photo-tile">
                                                        <img src="<?= partImagePath($extra['image']) ?>" alt="Extra photo">
                                                        <label>Order <input type="number" name="image_order[<?= (int) $extra['image_id'] ?>]" value="<?= (int) $extra['sort_order'] ?>" min="0" max="99" class="cp-input"></label>
                                                        <label class="sp-check"><input type="checkbox" name="remove_images[]" value="<?= (int) $extra['image_id'] ?>"> <span>Remove</span></label>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="cp-modal-foot sp-foot-split">
                                        <div class="sp-foot-left">
                                            <?php if ($canArchiveParts): ?>
                                                <button type="submit" form="partArchive<?= $partId ?>" class="cp-btn cp-btn-line sp-btn-cancel">Archive</button>
                                            <?php endif; ?>
                                            <?php if (!empty($p['supplier_email'])): ?>
                                                <button type="submit" form="partRestock<?= $partId ?>" class="cp-btn cp-btn-line">Email supplier</button>
                                            <?php endif; ?>
                                        </div>
                                        <button type="button" class="cp-btn cp-btn-line" data-sp-close>Cancel</button>
                                        <button type="submit" class="cp-btn cp-btn-dark">Save changes</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Archive / restock email forms live outside the edit form (forms can't be nested) -->
                        <?php if ($canArchiveParts): ?>
                        <form action="../controllers/PartController.php" method="POST" id="partArchive<?= $partId ?>" hidden
                              data-cp-confirm="The part leaves the active inventory but its records are kept. You can restore it later."
                              data-cp-confirm-title="Archive this part?" data-cp-confirm-yes="Archive" data-cp-confirm-no="Go back">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="archive">
                            <input type="hidden" name="part_id" value="<?= $partId ?>">
                            <input type="hidden" name="redirect_status" value="active">
                        </form>
                        <?php endif; ?>
                        <?php if (!empty($p['supplier_email'])): ?>
                            <form action="../controllers/PartController.php" method="POST" id="partRestock<?= $partId ?>" hidden
                                  data-cp-confirm="<?= cpEscape('A restock request email is sent to ' . ($p['supplier_name'] ?? 'the supplier') . ' (' . $p['supplier_email'] . ').') ?>"
                                  data-cp-confirm-title="Email the supplier?" data-cp-confirm-yes="Send email" data-cp-confirm-no="Go back" data-cp-confirm-tone="dark">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="send_restock_alert">
                                <input type="hidden" name="part_id" value="<?= $partId ?>">
                                <input type="hidden" name="redirect_status" value="active">
                            </form>
                        <?php endif; ?>
                        <?php $partDialogs .= ob_get_clean(); ?>
                    <?php endif; ?>
                <?php endforeach; ?>

                <tr class="sp-empty-row" data-sp-empty hidden>
                    <td colspan="9">No parts match your search or filter.</td>
                </tr>
                <?php if (empty($parts)): ?>
                    <tr class="sp-empty-row"><td colspan="9"><?= $isActiveView ? 'No parts yet. Add one to start tracking stock.' : 'No archived parts.' ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </main>
</div>

<datalist id="partCategoryList">
    <?php foreach ($categoryOptions as $option): ?><option value="<?= cpEscape($option) ?>"></option><?php endforeach; ?>
</datalist>
<datalist id="partUnitList">
    <?php foreach ($unitOptions as $option): ?><option value="<?= cpEscape($option) ?>"></option><?php endforeach; ?>
</datalist>

<?= $partDialogs ?>

<?php if ($isActiveView): ?>
    <!-- Add part -->
    <div class="sp-dialog" id="addPartDialog" role="dialog" aria-modal="true" aria-labelledby="addPartTitle" hidden>
        <div class="sp-dialog-box is-wide">
            <form action="../controllers/PartController.php" method="POST" enctype="multipart/form-data">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="redirect_status" value="active">

                <div class="cp-modal-head">
                    <div>
                        <p class="cp-label">Inventory</p>
                        <h2 class="cp-modal-title" id="addPartTitle">Add part</h2>
                    </div>
                    <button type="button" class="cp-modal-x" data-sp-close aria-label="Close">&times;</button>
                </div>

                <div class="sp-dialog-body">
                    <?= partFormFields(null, 'add', $suppliersList) ?>

                    <p class="sp-dialog-section">Photos</p>
                    <div class="sp-fgrid" style="margin-top: 12px">
                        <div class="sp-field">
                            <label for="addImage">Main image</label>
                            <input type="file" name="image" id="addImage" class="cp-input sp-file" accept=".jpg,.jpeg,.png,.webp">
                        </div>
                        <div class="sp-field">
                            <label for="addExtra">More photos</label>
                            <input type="file" name="extra_images[]" id="addExtra" class="cp-input sp-file" accept=".jpg,.jpeg,.png,.webp" multiple>
                            <p class="cp-hint">Optional. Up to <?= PartImage::MAX_PER_ITEM ?> photos.</p>
                        </div>
                    </div>
                </div>

                <div class="cp-modal-foot">
                    <button type="button" class="cp-btn cp-btn-line" data-sp-close>Cancel</button>
                    <button type="submit" class="cp-btn cp-btn-dark">Add part</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
