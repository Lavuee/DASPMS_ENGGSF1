<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requirePermission('shop.purchase');

require_once '../config/Database.php';

$db = (new Database())->getConnection();

$serviceCategories = [];
$dbError = '';

try {
    $query = "
        SELECT
            service_id,
            service_name,
            category,
            base_price,
            requires_down_payment,
            warranty_days,
            description,
            full_description,
            features,
            image
        FROM service
        WHERE is_active = 1
        ORDER BY category ASC, service_name ASC
    ";

    $stmt = $db->prepare($query);
    $stmt->execute();
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($services as $service) {
        $categoryKey = trim(html_entity_decode((string)($service['category'] ?? 'Uncategorized'), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $categoryKey = $categoryKey !== '' ? $categoryKey : 'Uncategorized';

        $serviceCategories[$categoryKey][] = $service;
    }
} catch (Exception $e) {
    $dbError = 'Unable to load services at the moment.';
}

function serviceImagePath($image)
{
    $fallback = 'default.png';

    if (!$image) {
        return '../assets/images/services/' . $fallback;
    }

    return '../assets/images/services/' . htmlspecialchars($image);
}

function shortText($value, $fallback = 'Service details will be confirmed by the shop after assessment.', $limit = 115)
{
    $value = trim(html_entity_decode((string)($value ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    if ($value === '') {
        $value = $fallback;
    }

    if (mb_strlen($value) > $limit) {
        $value = mb_substr($value, 0, $limit) . '...';
    }

    return htmlspecialchars($value);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Services Offered - Norily's Customer Portal</title>
<link rel="icon" type="image/png" href="../assets/images/logo.png">
<?php require_once __DIR__ . '/../includes/customer_ui.php'; cpHeadAssets(); ?>
</head>

<body class="cp-body">
<?php include '../includes/sidebar.php'; ?>

<main class="cp-main" id="cpMain">

    <?php
        $serviceCount = 0;
        foreach ($serviceCategories as $categoryServices) {
            $serviceCount += count($categoryServices);
        }

        cpPageHead(
            'Services offered',
            'Browse available repair services before submitting a service request. Final cost is confirmed after the shop assesses your vehicle.',
            '<a href="customer_service_requests.php" class="cp-btn cp-btn-line">My requests</a>'
        );
    ?>

    <?php cpFlash(); ?>

    <?php if ($dbError): ?>
        <div class="cp-empty">
            <strong>Services unavailable</strong>
            <?php echo htmlspecialchars($dbError); ?>
        </div>

    <?php elseif (empty($serviceCategories)): ?>
        <div class="cp-empty">
            <strong>No services available</strong>
            No services are currently available for online request.
        </div>

    <?php else: ?>
      <div data-cp-catalog>
        <div class="cp-toolbar">
            <ul class="cp-cats" aria-label="Filter by category">
                <li><button type="button" class="cp-cat is-active" data-cat="" aria-pressed="true">All <span class="cp-pill-count ms-1"><?php echo $serviceCount; ?></span></button></li>
                <?php foreach ($serviceCategories as $category => $services): ?>
                    <li>
                        <button type="button" class="cp-cat" data-cat="<?php echo htmlspecialchars(md5($category)); ?>" aria-pressed="false">
                            <?php echo displayText($category); ?>
                            <span class="cp-pill-count ms-1"><?php echo count($services); ?></span>
                        </button>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="d-flex gap-2 align-items-center">
                <label class="visually-hidden" for="servicesSearch">Search services</label>
                <div class="cp-search flex-grow-1">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    <input type="search" id="servicesSearch" class="cp-input" placeholder="Search name, category, features..." autocomplete="off" data-cp-search>
                </div>
                <button type="button" id="clearSearch" class="cp-link" data-cp-clear>Clear</button>
            </div>
        </div>

        <p class="cp-result-line" aria-live="polite" data-cp-count data-noun="service">Showing <?php echo $serviceCount; ?> service<?php echo $serviceCount !== 1 ? 's' : ''; ?></p>

        <div class="cp-empty" id="noResults" hidden data-cp-noresults>
            <strong>No matching services found</strong>
            Try another keyword or pick a different category.
        </div>

        <div class="cp-grid cp-grid-4">
            <?php foreach ($serviceCategories as $category => $services): ?>
                <?php foreach ($services as $service): ?>
                    <?php
                        $searchText = strtolower(
                            html_entity_decode(($service['service_name'] ?? '') . ' ' .
                            ($service['category'] ?? '') . ' ' .
                            ($service['description'] ?? '') . ' ' .
                            ($service['full_description'] ?? '') . ' ' .
                            ($service['features'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')
                        );

                        $basePrice = floatval($service['base_price'] ?? 0);
                        $serviceImage = !empty($service['image']) ? '../assets/images/services/' . $service['image'] : '';
                        $warrantyDays = intval($service['warranty_days'] ?? 0);
                    ?>
                    <a
                        href="customer_service_details.php?id=<?php echo intval($service['service_id']); ?>"
                        class="cp-item service-card"
                        data-search="<?php echo htmlspecialchars($searchText); ?>"
                        data-cat="<?php echo htmlspecialchars(md5($category)); ?>"
                    >
                        <div class="cp-item-media cp-item-media-wide">
                            <?php echo cpThumb($serviceImage, $service['service_name'] ?? 'Service'); ?>
                            <?php if (intval($service['requires_down_payment'] ?? 0)): ?>
                                <span class="st pend">Down payment</span>
                            <?php endif; ?>
                        </div>
                        <span class="cp-item-cat"><?php echo displayText($service['category'] ?? ''); ?><?php if ($warrantyDays > 0): ?> · <?php echo $warrantyDays; ?>-day warranty<?php endif; ?></span>
                        <h2 class="cp-item-name"><?php echo displayText($service['service_name'] ?? ''); ?></h2>
                        <p class="cp-item-desc"><?php echo shortText($service['description'] ?? ''); ?></p>
                        <div class="cp-item-foot">
                            <?php if ($basePrice > 0): ?>
                                <span class="cp-item-price"><span class="cp-faint small">From</span> ₱<?php echo number_format($basePrice, 2); ?></span>
                            <?php else: ?>
                                <span class="cp-item-price small">Cost after assessment</span>
                            <?php endif; ?>
                            <span class="cp-item-go">View ↗</span>
                        </div>
                        <span class="cp-hint mt-1"><?php echo $basePrice > 0 ? 'Final cost depends on assessment.' : 'Final price will be confirmed by the shop.'; ?></span>
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