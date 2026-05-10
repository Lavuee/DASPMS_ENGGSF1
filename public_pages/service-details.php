<?php
session_start();

$basePath = '../';

require_once '../config/Database.php';

$database = new Database();
$db = $database->getConnection();

$selectedId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$selectedService = null;
$relatedServices = [];
$dbError = '';

function plainText($value, $fallback = '')
{
    $value = trim(html_entity_decode((string)($value ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    return $value !== '' ? $value : $fallback;
}

function displayText($value, $fallback = 'N/A')
{
    $value = plainText($value, $fallback);
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function displayMultiline($value, $fallback = 'No detailed description available.')
{
    $value = plainText($value, $fallback);
    return nl2br(htmlspecialchars($value, ENT_QUOTES, 'UTF-8'));
}

try {
    if ($selectedId) {
        $stmt = $db->prepare("
            SELECT
                service_id,
                service_name,
                category,
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
            $relatedStmt = $db->prepare("
                SELECT
                    service_id,
                    service_name,
                    category,
                    image
                FROM service
                WHERE is_active = 1
                  AND service_id != :service_id
                  AND category = :category
                ORDER BY service_name ASC
                LIMIT 3
            ");

            $relatedStmt->bindParam(':service_id', $selectedId, PDO::PARAM_INT);
            $relatedStmt->bindParam(':category', $selectedService['category']);
            $relatedStmt->execute();

            $relatedServices = $relatedStmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($relatedServices) === 0) {
                $fallbackStmt = $db->prepare("
                    SELECT
                        service_id,
                        service_name,
                        category,
                        image
                    FROM service
                    WHERE is_active = 1
                      AND service_id != :service_id
                    ORDER BY category ASC, service_name ASC
                    LIMIT 3
                ");

                $fallbackStmt->bindParam(':service_id', $selectedId, PDO::PARAM_INT);
                $fallbackStmt->execute();

                $relatedServices = $fallbackStmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    }
} catch (Exception $e) {
    $dbError = 'Unable to load service details at the moment.';
}

function serviceImagePath($image)
{
    $fallback = 'default.png';

    if (!$image) {
        return '../assets/images/services/' . $fallback;
    }

    return '../assets/images/services/' . htmlspecialchars($image, ENT_QUOTES, 'UTF-8');
}

function parseFeatures($features)
{
    if (!$features) {
        return [];
    }

    $features = str_replace(["\r\n", "\r"], "\n", $features);

    return array_values(array_filter(array_map('trim', explode("\n", $features))));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>
    <?= $selectedService ? displayText($selectedService['service_name']) . ' | Services' : 'Service Not Found | Services' ?>
</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/style.css">

<style>
    .service-detail-page {
        background: #f4f5f7;
        min-height: 100vh;
    }

    .service-detail-wrapper {
        max-width: 1180px;
        margin: 0 auto;
        padding: 3rem 1rem 5rem;
    }

    .detail-topbar {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        flex-wrap: wrap;
        padding-bottom: 1rem;
        margin-bottom: 1.3rem;
        border-bottom: 1px solid rgba(17, 24, 39, 0.10);
    }

    .detail-topbar h2 {
        color: #111827;
        font-size: 2rem;
        font-weight: 900;
        line-height: 1.1;
        margin-bottom: 0.25rem;
    }

    .detail-topbar p {
        color: #5f6b7a;
        font-size: 0.95rem;
        font-weight: 500;
        margin-bottom: 0;
    }

    .back-btn {
        min-height: 42px;
        border-radius: 999px;
        padding: 0.6rem 1rem;
        border: 1px solid rgba(17, 24, 39, 0.14);
        background: rgba(255, 255, 255, 0.42);
        color: #111827;
        font-size: 0.86rem;
        font-weight: 900;
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        text-decoration: none;
        white-space: nowrap;
        transition: 0.2s ease;
    }

    .back-btn:hover {
        background: #f5c518;
        border-color: #f5c518;
        color: #111827;
    }

    .service-detail-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.05fr) minmax(360px, 0.95fr);
        gap: 2.4rem;
        align-items: start;
    }

    .service-media-panel {
        min-width: 0;
        position: sticky;
        top: 1.2rem;
    }

    .service-media-stage {
        min-height: 460px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0.9rem;
        background:
            radial-gradient(circle at center, rgba(255, 255, 255, 0.55) 0%, rgba(255, 255, 255, 0.20) 42%, rgba(255, 255, 255, 0) 72%);
        overflow: visible;
    }

    .service-media-stage img {
        max-width: 100%;
        max-height: 420px;
        object-fit: contain;
        display: block;
        filter: drop-shadow(0 22px 24px rgba(17, 24, 39, 0.13));
        transition: 0.25s ease;
    }

    .service-media-stage:hover img {
        transform: scale(1.025);
    }

    .service-info-panel {
        min-width: 0;
        padding-top: 0.3rem;
    }

    .breadcrumb-text {
        color: #6b7280;
        font-size: 0.82rem;
        font-weight: 700;
        margin-bottom: 0.7rem;
    }

    .breadcrumb-text a {
        color: #111827;
        text-decoration: underline;
        text-underline-offset: 3px;
        font-weight: 900;
    }

    .service-category-label {
        color: #f0b400;
        font-size: 0.74rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.95px;
        margin-bottom: 0.5rem;
    }

    .service-detail-title {
        color: #111827;
        font-size: clamp(1.75rem, 3vw, 2.3rem);
        font-weight: 900;
        line-height: 1.05;
        letter-spacing: -0.6px;
        margin-bottom: 0.75rem;
    }

    .service-summary-line {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        flex-wrap: wrap;
        margin-bottom: 1rem;
    }

    .service-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        padding: 0.42rem 0.75rem;
        font-size: 0.76rem;
        font-weight: 900;
        white-space: nowrap;
    }

    .service-pill.available {
        background: #d7fbe5;
        color: #047857;
    }

    .service-pill.assessment {
        background: #fff3cd;
        color: #9a5b00;
    }

    .detail-tabs {
        display: flex;
        align-items: center;
        gap: 1rem;
        border-bottom: 1px solid rgba(17, 24, 39, 0.13);
        margin-top: 0.9rem;
        margin-bottom: 0.85rem;
        overflow-x: auto;
        scrollbar-width: none;
    }

    .detail-tabs::-webkit-scrollbar {
        display: none;
    }

    .detail-tab-btn {
        border: none;
        background: transparent;
        color: #6b7280;
        font-size: 0.76rem;
        font-weight: 900;
        padding: 0 0 0.55rem 0;
        white-space: nowrap;
        position: relative;
        transition: 0.2s ease;
    }

    .detail-tab-btn::after {
        content: '';
        position: absolute;
        left: 0;
        right: 0;
        bottom: -1px;
        height: 2px;
        background: transparent;
        transition: 0.2s ease;
    }

    .detail-tab-btn:hover,
    .detail-tab-btn.active {
        color: #111827;
    }

    .detail-tab-btn.active::after {
        background: #f5c518;
    }

    .tab-panels {
       min-height: 95px;
       max-height: 115px;
       overflow-y: auto;
       padding-right: 0.25rem;
       margin-bottom: 0.65rem;
       scrollbar-width: thin;
    }

    .tab-panel {
        display: none;
        color: #5f6b7a;
        font-size: 0.86rem;
        line-height: 1.65;
    }

    .tab-panel.active {
        display: block;
    }

    .simple-list {
        margin: 0;
        padding-left: 1.05rem;
        color: #5f6b7a;
    }

    .simple-list li {
        color: #5f6b7a;
        font-size: 0.8rem;
        line-height: 1.55;
        margin-bottom: 0.42rem;
    }

    .simple-list li::marker {
        color: #111827;
    }

    .service-meta-grid {
       display: grid;
       grid-template-columns: repeat(2, minmax(0, 1fr));
       gap: 0.65rem;
       margin: 0.35rem 0 0.85rem;
    }

    .meta-box {
        border: 1px solid rgba(17, 24, 39, 0.08);
        border-radius: 14px;
        padding: 0.68rem 0.75rem;
        background: rgba(255, 255, 255, 0.26);
    }

    .meta-label {
        display: block;
        color: #6b7280;
        font-size: 0.62rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.38px;
        margin-bottom: 0.2rem;
    }

    .meta-value {
        color: #111827;
        font-size: 0.8rem;
        font-weight: 900;
        line-height: 1.4;
    }

    .detail-actions {
        display: flex;
        gap: 0.65rem;
        flex-wrap: wrap;
        padding-top: 0.85rem;
        border-top: 1px solid rgba(17, 24, 39, 0.10);
        margin-top: 0.9rem;
    }

    .detail-primary-btn,
    .detail-secondary-btn {
        min-height: 42px;
        border-radius: 999px;
        padding: 0.6rem 1rem;
        font-size: 0.84rem;
        font-weight: 900;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.45rem;
        transition: 0.2s ease;
    }

    .detail-primary-btn {
        background: #f5c518;
        border: 1px solid #f5c518;
        color: #111827;
    }

    .detail-primary-btn:hover {
        background: #111827;
        border-color: #111827;
        color: #fff;
    }

    .detail-secondary-btn {
        background: rgba(255, 255, 255, 0.42);
        border: 1px solid rgba(17, 24, 39, 0.14);
        color: #111827;
    }

    .detail-secondary-btn:hover {
        background: rgba(245, 197, 24, 0.18);
        border-color: #f5c518;
        color: #111827;
    }

    .detail-note {
        margin-top: 1rem;
        color: #6b7280;
        font-size: 0.82rem;
        line-height: 1.55;
    }

    .related-section {
        margin-top: 3.6rem;
        padding-top: 2.2rem;
        border-top: 1px solid rgba(17, 24, 39, 0.10);
    }

    .related-title {
        color: #111827;
        font-size: 1.35rem;
        font-weight: 900;
        letter-spacing: 2px;
        text-transform: uppercase;
        text-align: center;
        margin-bottom: 2rem;
    }

    .related-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 2.2rem;
        max-width: 1040px;
        margin: 0 auto;
    }

    .related-card {
        text-align: center;
        transition: 0.2s ease;
    }

    .related-card:hover {
        transform: translateY(-3px);
    }

    .related-card a {
        color: inherit;
        text-decoration: none;
    }

    .related-image {
        width: 100%;
        min-height: 200px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0.8rem 0.4rem 1rem;
        margin-bottom: 0.85rem;
    }

    .related-card img {
        max-width: 100%;
        max-height: 160px;
        object-fit: contain;
        filter: drop-shadow(0 18px 18px rgba(17, 24, 39, 0.10));
        transition: 0.2s ease;
    }

    .related-card:hover img {
        transform: scale(1.04);
    }

    .related-card h3 {
        color: #111827;
        font-size: 1rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.75px;
        line-height: 1.28;
        margin-bottom: 0.4rem;
    }

    .related-card span {
        color: #f0b400;
        font-size: 0.7rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .not-found-box {
        max-width: 720px;
        margin: 0 auto;
        text-align: center;
        padding: 3rem 1rem;
        border-top: 1px solid rgba(17, 24, 39, 0.10);
        border-bottom: 1px solid rgba(17, 24, 39, 0.10);
    }

    .not-found-box i {
        display: block;
        color: #d1d5db;
        font-size: 2.3rem;
        margin-bottom: 0.8rem;
    }

    .not-found-box h1 {
        color: #111827;
        font-size: 1.7rem;
        font-weight: 900;
        margin-bottom: 0.55rem;
    }

    .not-found-box p {
        color: #5f6b7a;
        line-height: 1.65;
        margin-bottom: 1.4rem;
    }

    @media (max-width: 1199.98px) {
        .service-detail-grid {
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }

        .service-media-panel {
            position: static;
        }

        .service-media-stage {
            min-height: 320px;
        }

        .service-media-stage img {
            max-height: 300px;
        }

        .tab-panels {
            max-height: none;
            min-height: 120px;
            overflow-y: visible;
        }

        .related-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            max-width: 760px;
        }
    }

    @media (max-width: 767.98px) {
        .service-detail-wrapper {
            padding: 2.3rem 1rem 4rem;
        }

        .detail-topbar {
            flex-direction: column;
            align-items: stretch;
        }

        .back-btn {
            width: 100%;
            justify-content: center;
        }

        .service-detail-title {
            font-size: 1.55rem;
        }

        .service-media-stage {
            min-height: 245px;
            padding: 0.45rem;
        }

        .service-media-stage img {
            max-height: 225px;
        }

        .service-meta-grid,
        .related-grid {
            grid-template-columns: 1fr;
        }

        .related-grid {
            max-width: 360px;
        }

        .detail-primary-btn,
        .detail-secondary-btn {
            width: 100%;
        }
    }
</style>
</head>

<body>

<?php include '../partials/public-navbar.php'; ?>

<main class="service-detail-page">
    <section class="service-detail-wrapper">
        <div class="detail-topbar">
            <div>
                <h2>Service Details</h2>
                <p>Review service information before logging in to submit an online request.</p>
            </div>

            <a href="services.php" class="back-btn">
                <i class="bi bi-arrow-left"></i>
                Back to Services
            </a>
        </div>

        <?php if ($dbError): ?>
            <div class="not-found-box">
                <i class="bi bi-exclamation-circle"></i>
                <h1>Unable to Load Service</h1>
                <p><?= htmlspecialchars($dbError, ENT_QUOTES, 'UTF-8') ?></p>

                <a href="services.php" class="detail-primary-btn">
                    Browse Services
                </a>
            </div>

        <?php elseif (!$selectedService): ?>
            <div class="not-found-box">
                <i class="bi bi-wrench-adjustable-circle"></i>
                <h1>Service Not Found</h1>
                <p>
                    The service you are looking for does not exist, is inactive, or may have been removed.
                    Please return to the services page and select another service.
                </p>

                <a href="services.php" class="detail-primary-btn">
                    Browse Services
                </a>
            </div>

        <?php else: ?>
            <?php
                $features = parseFeatures($selectedService['features'] ?? '');
                $isCustomerLoggedIn = isset($_SESSION['logged_in']) && ($_SESSION['role'] ?? '') === 'Customer';
                $requestUrl = '../views/customer_request_service.php?service_id=' . urlencode($selectedService['service_id']);
            ?>

            <div class="service-detail-grid">
                <div class="service-media-panel">
                    <div class="service-media-stage">
                        <img
                            src="<?= serviceImagePath($selectedService['image'] ?? '') ?>"
                            alt="<?= displayText($selectedService['service_name']) ?>"
                        >
                    </div>
                </div>

                <div class="service-info-panel">
                    <p class="breadcrumb-text">
                        <a href="services.php">Services</a> / <?= displayText($selectedService['category']) ?>
                    </p>

                    <div class="service-category-label">
                        <?= displayText($selectedService['category']) ?>
                    </div>

                    <h1 class="service-detail-title">
                        <?= displayText($selectedService['service_name']) ?>
                    </h1>

                    <div class="service-summary-line">
                        <span class="service-pill available">
                            Available Service
                        </span>

                        <span class="service-pill assessment">
                            Cost after assessment
                        </span>
                    </div>

                    <div class="detail-tabs" role="tablist" aria-label="Service detail sections">
                        <button type="button" class="detail-tab-btn active" data-tab="overview">Overview</button>
                        <button type="button" class="detail-tab-btn" data-tab="features">Features</button>
                        <button type="button" class="detail-tab-btn" data-tab="request">Request Process</button>
                    </div>

                    <div class="tab-panels">
                        <div class="tab-panel active" id="tab-overview">
                            <?= displayMultiline($selectedService['full_description'] ?: $selectedService['description'], 'No detailed description available.') ?>
                        </div>

                        <div class="tab-panel" id="tab-features">
                            <?php if (!empty($features)): ?>
                                <ul class="simple-list">
                                    <?php foreach ($features as $feature): ?>
                                        <li><?= displayText($feature) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <ul class="simple-list">
                                    <li>Service details will be confirmed by the shop after assessment.</li>
                                    <li>Actual repair requirements may vary depending on vehicle condition.</li>
                                    <li>Estimated cost and timeline will be reviewed by authorized shop staff.</li>
                                </ul>
                            <?php endif; ?>
                        </div>

                        <div class="tab-panel" id="tab-request">
                            <ul class="simple-list">
                                <li>Customers may submit an online service request after logging in.</li>
                                <li>The shop reviews the request, vehicle details, concern, and preferred appointment schedule.</li>
                                <li>Approved requests are converted into official job orders for repair processing.</li>
                                <li>Final cost, payment, and completion details are confirmed by the shop.</li>
                            </ul>
                        </div>
                    </div>

                    <div class="service-meta-grid">
                        <div class="meta-box">
                            <span class="meta-label">Category</span>
                            <div class="meta-value">
                                <?= displayText($selectedService['category']) ?>
                            </div>
                        </div>

                        <div class="meta-box">
                            <span class="meta-label">Request Type</span>
                            <div class="meta-value">
                                Online request / shop review
                            </div>
                        </div>
                    </div>

                    <div class="detail-actions">
                        <?php if ($isCustomerLoggedIn): ?>
                            <a href="<?= htmlspecialchars($requestUrl, ENT_QUOTES, 'UTF-8') ?>" class="detail-primary-btn">
                                <i class="bi bi-calendar-plus"></i>
                                Request Service
                            </a>
                        <?php else: ?>
                            <a href="../views/login.php" class="detail-primary-btn">
                                <i class="bi bi-box-arrow-in-right"></i>
                                Login to Request
                            </a>
                        <?php endif; ?>

                        <a href="services.php" class="detail-secondary-btn">
                            <i class="bi bi-wrench-adjustable-circle"></i>
                            Browse Services
                        </a>
                    </div>

                    <p class="detail-note">
                        Online service requests are subject to shop review. Vehicle assessment, estimated cost,
                        mechanic assignment, and job order creation are finalized by authorized shop staff.
                    </p>
                </div>
            </div>

            <?php if (!empty($relatedServices)): ?>
                <section class="related-section">
                    <h2 class="related-title">Related Services</h2>

                    <div class="related-grid">
                        <?php foreach ($relatedServices as $related): ?>
                            <article class="related-card">
                                <a href="service-details.php?id=<?= urlencode($related['service_id']) ?>">
                                    <div class="related-image">
                                        <img
                                            src="<?= serviceImagePath($related['image'] ?? '') ?>"
                                            alt="<?= displayText($related['service_name']) ?>"
                                        >
                                    </div>

                                    <h3><?= displayText($related['service_name']) ?></h3>
                                    <span><?= displayText($related['category']) ?></span>
                                </a>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</main>

<?php include '../partials/public-footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
const detailTabButtons = document.querySelectorAll('.detail-tab-btn');
const detailTabPanels = document.querySelectorAll('.tab-panel');

detailTabButtons.forEach(button => {
    button.addEventListener('click', function () {
        const target = this.dataset.tab;

        detailTabButtons.forEach(btn => btn.classList.remove('active'));
        detailTabPanels.forEach(panel => panel.classList.remove('active'));

        this.classList.add('active');

        const activePanel = document.getElementById('tab-' + target);

        if (activePanel) {
            activePanel.classList.add('active');
        }
    });
});
</script>

</body>
</html>