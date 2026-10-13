<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requirePermission('shop.purchase');

require_once '../config/Database.php';

$db = (new Database())->getConnection();

$partCategories = [];
$dbError = '';

try {
    $query = "
        SELECT
            part_id,
            category,
            brand,
            part_name,
            description,
            specification,
            compatibility,
            unit,
            image,
            unit_price,
            quantity_on_hand,
            low_stock_threshold
        FROM part
        WHERE is_active = 1
          AND quantity_on_hand > 0
        ORDER BY category ASC, part_name ASC
    ";

    $stmt = $db->prepare($query);
    $stmt->execute();
    $parts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($parts as $part) {
        $categoryKey = trim(html_entity_decode((string)($part['category'] ?? 'Uncategorized'), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $categoryKey = $categoryKey !== '' ? $categoryKey : 'Uncategorized';

        $partCategories[$categoryKey][] = $part;
    }
} catch (Exception $e) {
    $dbError = 'Unable to load parts at the moment.';
}

function partImagePath($image)
{
    $fallback = 'default.png';

    if (!$image) {
        return '../assets/images/parts/' . $fallback;
    }

    return '../assets/images/parts/' . htmlspecialchars($image);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Browse Parts - Norily's Customer Portal</title>
<link rel="icon" type="image/png" href="../assets/images/logo.png">
<?php require_once __DIR__ . '/../includes/customer_ui.php'; cpHeadAssets(); ?>
</head>

<body class="cp-body">
<?php include '../includes/sidebar.php'; ?>

<main class="cp-main" id="cpMain">

    <?php
        $partCount = 0;
        foreach ($partCategories as $categoryParts) {
            $partCount += count($categoryParts);
        }

        cpPageHead(
            'Browse parts',
            'Select available parts and add them to your cart for reservation. Pay at pickup or online with GCash.',
            '<a href="customer_cart.php" class="cp-btn cp-btn-line">View cart (' . intval($cartCount ?? 0) . ')</a>'
        );
    ?>

    <?php cpFlash(); ?>

    <?php if ($dbError): ?>
        <div class="cp-empty">
            <strong>Parts unavailable</strong>
            <?php echo htmlspecialchars($dbError); ?>
        </div>

    <?php elseif (empty($partCategories)): ?>
        <div class="cp-empty">
            <strong>No parts available</strong>
            No parts are currently available for reservation.
        </div>

    <?php else: ?>
      <div data-cp-catalog>
        <div class="cp-toolbar">
            <ul class="cp-cats" aria-label="Filter by category">
                <li><button type="button" class="cp-cat is-active" data-cat="" aria-pressed="true">All <span class="cp-pill-count ms-1"><?php echo $partCount; ?></span></button></li>
                <?php foreach ($partCategories as $category => $parts): ?>
                    <li>
                        <button type="button" class="cp-cat" data-cat="<?php echo htmlspecialchars(md5($category)); ?>" aria-pressed="false">
                            <?php echo htmlspecialchars(partCategoryLabel($category)); ?>
                            <span class="cp-pill-count ms-1"><?php echo count($parts); ?></span>
                        </button>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="d-flex gap-2 align-items-center">
                <label class="visually-hidden" for="partsSearch">Search parts</label>
                <div class="cp-search flex-grow-1">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    <input type="search" id="partsSearch" class="cp-input" data-cp-search placeholder="Search name, brand, spec, fit..." autocomplete="off">
                </div>
                <button type="button" id="clearSearch" class="cp-link" data-cp-clear>Clear</button>
            </div>
        </div>

        <p class="cp-result-line" id="partsCount" aria-live="polite" data-cp-count data-noun="part">Showing <?php echo $partCount; ?> part<?php echo $partCount !== 1 ? 's' : ''; ?></p>

        <div class="cp-empty" id="noResults" hidden data-cp-noresults>
            <strong>No matching parts found</strong>
            Try another keyword or pick a different category.
        </div>

        <div class="cp-grid" id="partsGrid">
            <?php foreach ($partCategories as $category => $parts): ?>
                <?php foreach ($parts as $part): ?>
                    <?php
                        $searchText = strtolower(
                            html_entity_decode(($part['part_name'] ?? '') . ' ' .
                            ($part['brand'] ?? '') . ' ' .
                            ($part['category'] ?? '') . ' ' .
                            ($part['description'] ?? '') . ' ' .
                            ($part['specification'] ?? '') . ' ' .
                            ($part['compatibility'] ?? '') . ' ' .
                            ($part['unit'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')
                        );
                        $partImage = !empty($part['image']) ? '../assets/images/parts/' . $part['image'] : '';
                    ?>
                    <a
                        href="customer_part_details.php?id=<?php echo intval($part['part_id']); ?>"
                        class="cp-item part-card"
                        data-search="<?php echo htmlspecialchars($searchText); ?>"
                        data-cat="<?php echo htmlspecialchars(md5($category)); ?>"
                    >
                        <div class="cp-item-media">
                            <?php echo cpThumb($partImage, $part['part_name'] ?? 'Part'); ?>
                            <?php echo cpStockBadge($part['quantity_on_hand'], $part['low_stock_threshold'] ?? 3); ?>
                        </div>
                        <span class="cp-item-cat"><?php echo htmlspecialchars(partCategoryLabel($part['category'] ?? '')); ?><?php if (cpHasValue($part['brand'] ?? '')): ?> · <?php echo displayText($part['brand']); ?><?php endif; ?></span>
                        <h2 class="cp-item-name"><?php echo displayText($part['part_name'] ?? ''); ?></h2>
                        <div class="cp-item-foot">
                            <span class="cp-item-price">₱<?php echo number_format(floatval($part['unit_price']), 2); ?> <span class="cp-faint small">/ <?php echo displayText($part['unit'] ?? '', 'piece'); ?></span></span>
                            <span class="cp-item-go">View ↗</span>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>