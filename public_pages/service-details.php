<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$basePath = '../';

require_once '../config/Database.php';
require_once '../models/ServiceImage.php';

$database = new Database();
$db = $database->getConnection();

$selectedId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$selectedService = null;
$moreServices = [];
$galleryImages = [];
$dbError = '';

try {
    if ($selectedId) {
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
            WHERE service_id = :service_id
              AND is_active = 1
            LIMIT 1
        ");

        $stmt->bindParam(':service_id', $selectedId, PDO::PARAM_INT);
        $stmt->execute();
        $selectedService = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($selectedService) {
            // Gallery: the main photo first, then the extra angles in their saved order.
            $galleryImages = array_merge(
                [$selectedService['image'] ?? ''],
                array_column((new ServiceImage($db))->listForItem($selectedId), 'image')
            );

            // Same order and numbering as the landing page carousel, minus the current service.
            $moreStmt = $db->prepare("
                SELECT service_id, service_name, base_price, image
                FROM service
                WHERE is_active = 1
                ORDER BY service_id DESC
            ");
            $moreStmt->execute();

            foreach ($moreStmt->fetchAll(PDO::FETCH_ASSOC) as $index => $service) {
                if ((int) $service['service_id'] !== (int) $selectedId) {
                    $service['number'] = $index + 1;
                    $moreServices[] = $service;
                }
            }
        }
    }
} catch (Exception $e) {
    $dbError = 'Unable to load service details at the moment.';
}

function serviceImagePath($image)
{
    return '../assets/images/services/' . htmlspecialchars($image ?: 'default.png', ENT_QUOTES, 'UTF-8');
}

// Services show a starting rate; the final cost is set after inspection.
function formatRate($price)
{
    $price = (float) $price;
    return $price > 0 ? 'from ₱' . number_format($price, 2) : 'Ask for price';
}

function parseFeatures($features)
{
    $features = str_replace(["\r\n", "\r"], "\n", plainText($features));
    return array_values(array_filter(array_map('trim', explode("\n", $features))));
}

// "Rewinding Services" -> "Rewinding"; "Other Services" stays "Other services".
function serviceCategoryLabel($category)
{
    $category = plainText($category);
    $label = preg_replace('/\s+services\s*$/i', '', $category);

    if ($label === '' || strtolower($label) === 'other') {
        $label = $category;
    }

    return ucfirst(strtolower($label));
}

if ($selectedService) {
    $description = plainText($selectedService['description']) ?: plainText($selectedService['full_description'], 'No description available.');
    $features = parseFeatures($selectedService['features'] ?? '');

    $requiresDownPayment = (int) $selectedService['requires_down_payment'] === 1;
    $warrantyDays = (int) $selectedService['warranty_days'];
    $warrantyLabel = $warrantyDays > 0 ? $warrantyDays . ' days' : 'Confirmed by the shop';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>
    <?= $selectedService ? displayText($selectedService['service_name']) . ' | Services' : 'Service Not Found | Services' ?> | Norily's Vehicle Repair Shop
</title>
<link rel="icon" type="image/png" href="../assets/images/logo.png">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
<script>document.documentElement.classList.add('js');window.__motionFallback=setTimeout(function(){document.documentElement.classList.add('reveal-all');},3000);</script>
<script src="../assets/js/motion.js?v=<?= filemtime(__DIR__ . '/../assets/js/motion.js') ?>" defer></script>

<style>
    .service-detail-page {
        background: var(--paper);
        padding: 1.5rem 0 5rem;
    }

    .detail-breadcrumb {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.6rem;
        list-style: none;
        padding: 0;
        margin: 0 0 2.5rem;
        color: var(--ink-2);
        font-size: var(--fs-crumb);
        letter-spacing: 0.14em;
        text-transform: uppercase;
    }

    .detail-breadcrumb a {
        color: var(--ink-2);
        text-decoration: none;
        transition: color 0.2s ease;
    }

    .detail-breadcrumb a:hover {
        color: var(--ink);
    }

    .detail-breadcrumb li + li::before {
        content: "/";
        margin-right: 0.6rem;
    }

    .service-detail-grid {
        display: grid;
        /* Image and panel sized to their content and centered as a pair */
        grid-template-columns: minmax(0, 520px) minmax(0, 440px);
        justify-content: center;
        gap: 2.5rem;
        align-items: center;
        align-content: start;
        /* Always fill the first screen (minus the breadcrumb) so "More services" starts below
           the fold no matter how short the service's details are. */
        min-height: calc(100vh - 5.5rem - var(--pub-header-h));
        min-height: calc(100svh - 5.5rem - var(--pub-header-h));
    }

    .service-media {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 1.5rem;
    }

    .service-thumbs {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        flex-shrink: 0;
    }

    /* Thumbnails: small versions of the main photo frame */
    .service-thumb {
        width: 76px;
        height: 76px;
        padding: 5px;
        background: transparent;
        border: 1px solid var(--line);
        cursor: pointer;
        transition: border-color 0.2s ease;
    }

    .service-thumb:hover {
        border-color: var(--ink-3);
    }

    .service-thumb.active {
        border-color: var(--ink);
    }

    .service-thumb img {
        display: block;
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    /* Workshop photo in a square frame, matching the part details image */
    .service-stage {
        flex: 0 1 420px;
        min-width: 0;
        aspect-ratio: 1 / 1;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        background: transparent;
        border: 1px solid var(--line);
    }

    .service-stage img {
        display: block;
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    .service-panel {
        width: 100%;
        max-width: 440px;
        border: 1px solid var(--ink);
        padding: 1.4rem 1.4rem 1.2rem;
    }

    .service-panel-head {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        gap: 1rem;
    }

    .service-detail-title {
        min-width: 0;
        overflow-wrap: anywhere;
        color: var(--ink);
        font-size: var(--fs-item-title);
        font-weight: 400;
        font-stretch: 125%;
        text-transform: uppercase;
        line-height: 1;
        margin: 0;
    }

    .mono {
        font-family: ui-monospace, SFMono-Regular, Consolas, monospace;
    }

    .service-detail-price {
        flex-shrink: 0;
        color: var(--ink);
        font-size: 0.95rem;
        font-weight: 600;
        white-space: nowrap;
    }

    .service-code {
        color: var(--ink-2);
        font-size: 0.78rem;
        letter-spacing: 0.06em;
        margin: 0.7rem 0 0;
        padding-bottom: 1rem;
        border-bottom: 1px solid var(--ink);
    }

    .service-section-title {
        color: var(--ink);
        font-size: 0.78rem;
        font-weight: 500;
        font-stretch: 125%;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        margin: 1.1rem 0 0.45rem;
    }

    .service-description {
        color: var(--ink-2);
        font-size: 0.88rem;
        line-height: 1.55;
        margin: 0;
    }

    .service-features {
        list-style: none;
        padding: 0;
        margin: 0;
        font-size: 0.88rem;
        color: var(--ink);
    }

    .service-features li {
        position: relative;
        padding-left: 1.1rem;
        margin-bottom: 0.2rem;
    }

    .service-features li::before {
        content: "";
        position: absolute;
        left: 0;
        top: 0.65em;
        width: 0.45rem;
        height: 1px;
        background: var(--ink-2);
    }

    .service-facts {
        display: grid;
        grid-template-columns: 1fr 1fr;
        border-top: 1px solid var(--ink);
        margin-top: 1.2rem;
    }

    .service-fact {
        padding: 0.8rem 0 0.75rem;
    }

    .service-fact + .service-fact {
        border-left: 1px solid var(--ink);
        padding-left: 1.25rem;
    }

    .service-fact-label {
        color: var(--ink-2);
        font-size: 0.7rem;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        margin: 0;
    }

    .service-fact-value {
        display: block;
        color: var(--ink);
        font-size: 0.85rem;
        margin-top: 0.45rem;
    }

    .status-pill {
        display: inline-block;
        margin-top: 0.45rem;
        padding: 0.15rem 0.55rem;
        border: 1px solid currentColor;
        border-radius: 999px;
        font-size: 0.65rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .status-ok {
        color: var(--status-success);
    }

    .status-warn {
        color: var(--status-warning);
    }

    .service-action {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        min-height: 50px;
        background: transparent;
        border: 1px solid var(--ink);
        color: var(--ink);
        font-size: 0.8rem;
        font-weight: 500;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        text-decoration: none;
        transition: background 0.2s ease, color 0.2s ease;
    }

    .service-action:hover {
        background: var(--ink);
        color: var(--sheet);
    }

    .service-note {
        color: var(--ink-2);
        font-size: 0.78rem;
        line-height: 1.55;
        margin: 0.75rem 0 0;
    }

    .more-items {
        margin-top: 3rem;
    }

    .more-items-title {
        color: var(--ink);
        font-size: clamp(1.15rem, 1.8vw, 1.45rem);
        font-weight: 400;
        font-stretch: 125%;
        text-transform: uppercase;
        text-align: center;
        margin-bottom: 2rem;
    }

    /* Smaller version of the shared carousel, for this page only */
    .more-items .offering-visual {
        margin-bottom: 1.1rem;
    }

    .more-items .offering-index {
        font-size: 0.85rem;
        margin-bottom: 0.2rem;
    }

    .more-items .offering-name {
        font-size: 0.8rem;
        margin-bottom: 0.4rem;
    }

    .more-items .offering-price {
        font-size: 0.8rem;
    }

    .more-items .offerings-arrow {
        top: 22%;
    }

    .not-found-box {
        max-width: 560px;
        padding: 4rem 0;
    }

    .not-found-box h1 {
        color: var(--ink);
        font-size: clamp(2rem, 4vw, 2.75rem);
        font-weight: 400;
        font-stretch: 125%;
        text-transform: uppercase;
        margin-bottom: 1rem;
    }

    .not-found-box p {
        color: var(--ink-2);
        font-size: 1.05rem;
        margin-bottom: 2rem;
    }

    .not-found-box .service-action {
        width: auto;
        display: inline-flex;
        min-height: 64px;
        padding: 0 2rem;
    }

    @media (max-width: 991.98px) {
        .service-detail-grid {
            grid-template-columns: 1fr;
            gap: 1.5rem;
            min-height: 0;
        }

        .service-stage {
            flex-basis: auto;
            width: 100%;
            max-width: 420px;
        }

        .service-panel {
            max-width: none;
            justify-self: stretch;
        }

        /* Thumbnails become a swipe row under the main photo */
        .service-media {
            flex-direction: column-reverse;
            gap: 1rem;
        }

        .service-thumbs {
            flex-direction: row;
            overflow-x: auto;
            max-width: 100%;
            padding: 4px;
            scrollbar-width: none;
        }

        .service-thumb {
            width: 64px;
            height: 64px;
            flex-shrink: 0;
        }
    }

    @media (max-width: 575.98px) {
        .service-panel {
            padding: 1.5rem 1.25rem 1.25rem;
        }

        .service-panel-head {
            flex-direction: column;
            gap: 0.75rem;
        }

        .service-fact + .service-fact {
            padding-left: 1rem;
        }

        .service-stage {
            padding: 0.75rem;
        }
    }
</style>
</head>

<body>

<?php $activePage = 'services'; include '../partials/public-navbar.php'; ?>

<main class="service-detail-page">
    <div class="container">
        <nav aria-label="Breadcrumb">
            <ol class="detail-breadcrumb">
                <li><a href="../" class="link-grow">Home</a></li>
                <li><a href="services.php" class="link-grow">Services</a></li>
                <li aria-current="page"><?= $selectedService ? displayText($selectedService['service_name']) : 'Not found' ?></li>
            </ol>
        </nav>

        <?php if ($dbError || !$selectedService): ?>
            <div class="not-found-box">
                <h1><?= $dbError ? 'Something went wrong' : 'Service not found' ?></h1>
                <p>
                    <?= $dbError
                        ? htmlspecialchars($dbError, ENT_QUOTES, 'UTF-8')
                        : 'This service may have been removed or is no longer available.' ?>
                </p>
                <a href="services.php" class="service-action">Back to services</a>
            </div>
        <?php else: ?>
            <div class="service-detail-grid">
                <div class="service-media" data-gallery>
                    <?php if (count($galleryImages) > 1): ?>
                        <div class="service-thumbs" role="group" aria-label="Service photos">
                            <?php foreach ($galleryImages as $index => $image): ?>
                                <button
                                    type="button"
                                    class="service-thumb<?= $index === 0 ? ' active' : '' ?>"
                                    data-full="<?= serviceImagePath($image) ?>"
                                    aria-label="Show photo <?= $index + 1 ?>"
                                    aria-pressed="<?= $index === 0 ? 'true' : 'false' ?>"
                                >
                                    <img src="<?= serviceImagePath($image) ?>" alt="" loading="lazy">
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="service-stage">
                        <img
                            id="serviceMainImage"
                            data-gallery-main
                            src="<?= serviceImagePath($galleryImages[0] ?? '') ?>"
                            alt="<?= displayText($selectedService['service_name']) ?>"
                        >
                    </div>
                </div>

                <div class="service-panel">
                    <div class="service-panel-head">
                        <h1 class="service-detail-title"><?= displayText($selectedService['service_name']) ?></h1>
                        <div class="service-detail-price mono"><?= formatRate($selectedService['base_price']) ?></div>
                    </div>

                    <p class="service-code mono">
                        <?= htmlspecialchars(serviceCategoryLabel($selectedService['category']), ENT_QUOTES, 'UTF-8') ?>
                    </p>

                    <h2 class="service-section-title">Description</h2>
                    <p class="service-description"><?= nl2br(htmlspecialchars($description, ENT_QUOTES, 'UTF-8')) ?></p>

                    <?php if (!empty($features)): ?>
                        <h2 class="service-section-title">What's included</h2>
                        <ul class="service-features">
                            <?php foreach ($features as $feature): ?>
                                <li><?= htmlspecialchars($feature, ENT_QUOTES, 'UTF-8') ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <div class="service-facts">
                        <div class="service-fact">
                            <p class="service-fact-label">Down payment</p>
                            <span class="status-pill <?= $requiresDownPayment ? 'status-warn' : 'status-ok' ?>">
                                <?= $requiresDownPayment ? 'Required' : 'Not required' ?>
                            </span>
                        </div>

                        <div class="service-fact">
                            <p class="service-fact-label">Warranty</p>
                            <span class="service-fact-value"><?= htmlspecialchars($warrantyLabel, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    </div>

                    <?php if (can('service_requests.submit')): ?>
                        <a href="../views/customer_request_service.php?service_id=<?= urlencode($selectedService['service_id']) ?>" class="service-action">Request this service</a>
                    <?php elseif (isLoggedIn()): ?>
                        <a href="../views/<?= dashboardForRole($_SESSION['role'] ?? '') ?>" class="service-action">Go to dashboard</a>
                    <?php else: ?>
                        <a href="../views/login.php" class="service-action">Log in to request</a>
                    <?php endif; ?>

                    <p class="service-note">
                        The price is a starting rate. The head mechanic checks your vehicle first
                        and confirms the final cost before any work starts.
                    </p>
                </div>
            </div>

            <?php if (!empty($moreServices)): ?>
                <section class="more-items offerings-panel-photos" data-reveal>
                    <h2 class="more-items-title">More services</h2>

                    <div class="offerings-carousel">
                        <button class="offerings-arrow offerings-arrow-prev" type="button" aria-label="Previous" data-scroll="-1">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 4 7 12l8 8"/></svg>
                        </button>

                        <div class="offerings-track">
                            <?php foreach ($moreServices as $service): ?>
                                <a href="service-details.php?id=<?= urlencode($service['service_id']) ?>" class="offering-item">
                                    <div class="offering-visual">
                                        <img src="<?= serviceImagePath($service['image'] ?? '') ?>" alt="<?= displayText($service['service_name']) ?>" loading="lazy">
                                    </div>

                                    <span class="offering-index"><?= str_pad($service['number'], 2, '0', STR_PAD_LEFT) ?></span>
                                    <h3 class="offering-name"><?= displayText($service['service_name']) ?></h3>
                                    <span class="offering-price"><?= formatRate($service['base_price']) ?></span>
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
    </div>
</main>

<?php include '../partials/public-footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/offerings-carousel.js"></script>


</body>
</html>
