<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requirePermission('shop.purchase');

require_once '../config/Database.php';

$db = (new Database())->getConnection();

$service_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$service = null;
$relatedServices = [];
$dbError = '';

try {
    if ($service_id) {
        $stmt = $db->prepare("
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
            WHERE service_id = ?
              AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$service_id]);
        $service = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($service) {
            $relatedStmt = $db->prepare("
                SELECT
                    service_id,
                    service_name,
                    category,
                    base_price,
                    description,
                    image
                FROM service
                WHERE is_active = 1
                  AND service_id != ?
                  AND category = ?
                ORDER BY service_name ASC
                LIMIT 3
            ");
            $relatedStmt->execute([
                intval($service['service_id']),
                $service['category']
            ]);
            $relatedServices = $relatedStmt->fetchAll(PDO::FETCH_ASSOC);

            // Same-category services first, then other services, so "Other services" is never nearly empty.
            if (count($relatedServices) < 6) {
                $excludeIds = array_merge([intval($service['service_id'])], array_map('intval', array_column($relatedServices, 'service_id')));
                $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));

                $fallbackStmt = $db->prepare("
                    SELECT
                        service_id,
                        service_name,
                        category,
                        base_price,
                        description,
                        image
                    FROM service
                    WHERE is_active = 1
                      AND service_id NOT IN ($placeholders)
                    ORDER BY category ASC, service_name ASC
                    LIMIT " . (6 - count($relatedServices)) . "
                ");
                $fallbackStmt->execute($excludeIds);
                $relatedServices = array_merge($relatedServices, $fallbackStmt->fetchAll(PDO::FETCH_ASSOC));
            }
        }
    }
} catch (Exception $e) {
    $dbError = 'Unable to load service details.';
}

function serviceImagePath($image)
{
    $fallback = 'default.png';

    if (!$image) {
        return '../assets/images/services/' . $fallback;
    }

    return '../assets/images/services/' . htmlspecialchars($image);
}

function displayMultiline($value, $fallback = 'No information available.')
{
    $value = trim(html_entity_decode((string)($value ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    if ($value === '') {
        $value = $fallback;
    }

    return nl2br(htmlspecialchars($value));
}

function shortText($value, $fallback = 'Service details will be confirmed by the shop after assessment.', $limit = 95)
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
<title><?php echo $service ? displayText($service['service_name'] ?? '') . ' - ' : ''; ?>Service Details - Norily's Customer Portal</title>
<link rel="icon" type="image/png" href="../assets/images/logo.png">
<?php require_once __DIR__ . '/../includes/customer_ui.php'; cpHeadAssets(); ?>
</head>

<body class="cp-body">
<?php include '../includes/sidebar.php'; ?>

<main class="cp-main" id="cpMain">

    <ol class="cp-crumb">
        <li><a href="customer_services.php">Services offered</a></li>
        <?php if ($service): ?>
            <li><?php echo displayText($service['category'] ?? ''); ?></li>
            <li aria-current="page"><?php echo displayText($service['service_name'] ?? ''); ?></li>
        <?php else: ?>
            <li aria-current="page">Service details</li>
        <?php endif; ?>
    </ol>

    <?php if ($dbError): ?>
        <div class="cp-empty">
            <strong>Something went wrong</strong>
            <?php echo htmlspecialchars($dbError); ?>
            <div><a href="customer_services.php" class="cp-btn cp-btn-line">Back to services</a></div>
        </div>

    <?php elseif (!$service): ?>
        <div class="cp-empty">
            <strong>Service not found</strong>
            Service not found or currently unavailable.
            <div><a href="customer_services.php" class="cp-btn cp-btn-line">Back to services</a></div>
        </div>

    <?php else: ?>
        <?php
            $basePrice = floatval($service['base_price'] ?? 0);
            $warrantyDays = intval($service['warranty_days'] ?? 0);
            $requiresDownPayment = intval($service['requires_down_payment'] ?? 0);

            // Main photo first, then the extra angles saved for this service (same as the public service page).
            require_once __DIR__ . '/../models/ServiceImage.php';
            $galleryImages = array_values(array_filter(array_merge(
                [$service['image'] ?? ''],
                array_column((new ServiceImage($db))->listForItem(intval($service['service_id'])), 'image')
            )));

            // Inclusions are saved one per line; show them as a list when there are several.
            $featureLines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', plainText($service['features'] ?? '')))));
            $requestUrl = 'customer_request_service.php?service_id=' . intval($service['service_id']);
            $warrantyText = $warrantyDays > 0 ? $warrantyDays . ' day' . ($warrantyDays !== 1 ? 's' : '') : 'Depends on assessment';
        ?>
        <div class="cpd-grid cpd-photos">

            <div class="cpd-left">
            <div class="cpd-media" data-gallery>
                <?php if (count($galleryImages) > 1): ?>
                    <div class="cpd-thumbs" role="group" aria-label="Service photos">
                        <?php foreach ($galleryImages as $index => $image): ?>
                            <button
                                type="button"
                                class="cpd-thumb<?php echo $index === 0 ? ' active' : ''; ?>"
                                data-full="<?php echo serviceImagePath($image); ?>"
                                aria-label="Show photo <?php echo $index + 1; ?>"
                                aria-pressed="<?php echo $index === 0 ? 'true' : 'false'; ?>"
                            >
                                <img src="<?php echo serviceImagePath($image); ?>" alt="" loading="lazy">
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="cpd-stage">
                    <?php if (!empty($galleryImages)): ?>
                        <img
                            data-gallery-main
                            src="<?php echo serviceImagePath($galleryImages[0]); ?>"
                            alt="<?php echo displayText($service['service_name'] ?? 'Service image'); ?>"
                            data-letter="<?php echo htmlspecialchars(mb_strtoupper(mb_substr(plainText($service['service_name'] ?? '?'), 0, 1))); ?>"
                            onerror="cpLetterFallback(this)"
                        >
                    <?php else: ?>
                        <?php echo cpThumb('', $service['service_name'] ?? 'Service'); ?>
                    <?php endif; ?>
                </div>
            </div>

            </div>

            <div class="cpd-panel">
                <div class="cpd-panel-head">
                    <h1 class="cpd-title"><?php echo displayText($service['service_name'] ?? ''); ?></h1>
                    <div class="cpd-price">
                        <?php if ($basePrice > 0): ?>
                            <small>Starts at</small> ₱<?php echo number_format($basePrice, 2); ?>
                        <?php else: ?>
                            <small>Cost after assessment</small>
                        <?php endif; ?>
                    </div>
                </div>

                <p class="cpd-code"><?php echo displayText($service['category'] ?? ''); ?></p>

                <div class="offerings-tabs cpd-tabs" role="tablist" aria-label="Service details" data-tablist>
                    <button class="offerings-tab active" type="button" role="tab" id="service-overview-tab" aria-selected="true" aria-controls="service-overview-panel" data-offering-tab="overview">Overview</button>
                    <button class="offerings-tab" type="button" role="tab" id="service-included-tab" aria-selected="false" aria-controls="service-included-panel" tabindex="-1" data-offering-tab="included">Included</button>
                    <button class="offerings-tab" type="button" role="tab" id="service-warranty-tab" aria-selected="false" aria-controls="service-warranty-panel" tabindex="-1" data-offering-tab="warranty">Warranty</button>
                    <button class="offerings-tab" type="button" role="tab" id="service-how-tab" aria-selected="false" aria-controls="service-how-panel" tabindex="-1" data-offering-tab="how">How it works</button>
                    <span class="offerings-tab-indicator" aria-hidden="true"></span>
                </div>

                <div class="cpd-tabpanels">
                    <div class="offerings-panel cpd-tabpanel active" id="service-overview-panel" role="tabpanel" aria-labelledby="service-overview-tab" tabindex="0">
                        <p class="cpd-text"><?php echo displayMultiline($service['full_description'] ?: $service['description'], 'No description available.'); ?></p>
                    </div>
                    <div class="offerings-panel cpd-tabpanel" id="service-included-panel" role="tabpanel" aria-labelledby="service-included-tab" tabindex="0">
                        <h2 class="cpd-section-title">What's included</h2>
                        <?php if (count($featureLines) > 1): ?>
                            <ul class="cpd-features">
                                <?php foreach ($featureLines as $line): ?>
                                    <li><?php echo htmlspecialchars(ltrim($line, "-•* \t")); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="cpd-text"><?php echo displayMultiline($service['features'] ?? '', 'Service inclusions will be confirmed by the shop after assessment.'); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="offerings-panel cpd-tabpanel" id="service-warranty-panel" role="tabpanel" aria-labelledby="service-warranty-tab" tabindex="0">
                        <h2 class="cpd-section-title">Warranty &amp; payment</h2>
                        <dl class="cpd-list mb-2">
                            <dt>Category</dt>
                            <dd><?php echo displayText($service['category'] ?? ''); ?></dd>

                            <dt>Warranty</dt>
                            <dd><?php echo htmlspecialchars($warrantyText); ?></dd>
                        </dl>
                        <ul class="cpd-steps">
                            <?php if ($warrantyDays > 0): ?>
                                <li><span>—</span><span>Warranty coverage: <?php echo $warrantyDays; ?> day<?php echo $warrantyDays !== 1 ? 's' : ''; ?> after service completion.</span></li>
                            <?php else: ?>
                                <li><span>—</span><span>Warranty depends on the actual service performed and shop assessment.</span></li>
                            <?php endif; ?>

                            <li><span>—</span><span>Final cost may change depending on inspection, labor, and parts needed.</span></li>

                            <?php if ($requiresDownPayment): ?>
                                <li><span>—</span><span>This service may require down payment after shop confirmation.</span></li>
                            <?php else: ?>
                                <li><span>—</span><span>Payment details will be confirmed by the shop after assessment.</span></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <div class="offerings-panel cpd-tabpanel" id="service-how-panel" role="tabpanel" aria-labelledby="service-how-tab" tabindex="0">
                        <h2 class="cpd-section-title">How the request works</h2>
                        <ol class="cpd-steps">
                            <li><span>01</span><span><strong>Send a request.</strong> Pick your vehicle, a preferred date and describe the concern.</span></li>
                            <li><span>02</span><span><strong>The shop reviews it.</strong> Submitting a request does not automatically create an official job order until approved by the shop.</span></li>
                            <li><span>03</span><span><strong>Job order is created.</strong> Once approved, you can follow the repair from your dashboard.</span></li>
                            <li><span>04</span><span><strong>Pick up your vehicle.</strong> Settle any remaining balance at the shop.</span></li>
                        </ol>
                    </div>
                </div>

                <div class="cpd-facts">
                    <div class="cpd-fact">
                        <p class="cpd-fact-label">Payment</p>
                        <?php if ($requiresDownPayment): ?>
                            <span class="cpd-pill pend">Down payment may be required</span>
                        <?php else: ?>
                            <span class="cpd-pill done">Assessment required</span>
                        <?php endif; ?>
                    </div>

                    <div class="cpd-fact">
                        <p class="cpd-fact-label">Warranty</p>
                        <span class="cpd-fact-value"><?php echo htmlspecialchars($warrantyText); ?></span>
                    </div>
                </div>

                <a href="<?php echo $requestUrl; ?>" class="cpd-action">Request this service</a>

                <div class="cpd-subline">
                    <span><?php echo $basePrice > 0 ? 'Final cost depends on vehicle assessment and required parts.' : 'Final price will be confirmed by the shop after review.'; ?></span>
                    <a href="customer_services.php" class="flex-shrink-0">Browse services</a>
                </div>
            </div>
        </div>

        <?php if (!empty($relatedServices)): ?>
            <section class="cpd-more offerings-panel-photos" aria-labelledby="otherServices">
                <h2 class="cpd-more-title" id="otherServices">Other services</h2>

                <div class="offerings-carousel">
                    <button class="offerings-arrow offerings-arrow-prev" type="button" aria-label="Previous" data-scroll="-1">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 4 7 12l8 8"/></svg>
                    </button>

                    <div class="offerings-track">
                        <?php foreach ($relatedServices as $related): ?>
                            <?php
                                $relatedPrice = floatval($related['base_price'] ?? 0);
                                $relatedImage = !empty($related['image']) ? '../assets/images/services/' . $related['image'] : '';
                            ?>
                            <a href="customer_service_details.php?id=<?php echo intval($related['service_id']); ?>" class="offering-item">
                                <div class="offering-visual">
                                    <?php echo cpThumb($relatedImage, $related['service_name'] ?? 'Service'); ?>
                                </div>

                                <h3 class="offering-name"><?php echo displayText($related['service_name'] ?? ''); ?></h3>
                                <span class="offering-price cp-mono">
                                    <?php if ($relatedPrice > 0): ?>
                                        From ₱<?php echo number_format($relatedPrice, 2); ?>
                                    <?php else: ?>
                                        Cost after assessment
                                    <?php endif; ?>
                                </span>
                                <span class="cp-hint mt-1"><?php echo displayText($related['category'] ?? ''); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <button class="offerings-arrow offerings-arrow-next" type="button" aria-label="Next" data-scroll="1">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 4 8 8-8 8"/></svg>
                    </button>
                </div>
            </section>
        <?php endif; ?>
    <?php endif; ?>

</main>

<script src="../assets/js/offerings-carousel.js?v=<?= filemtime(__DIR__ . '/../assets/js/offerings-carousel.js') ?>"></script>
<script>if (typeof updateOfferingArrows === "function") updateOfferingArrows();</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>