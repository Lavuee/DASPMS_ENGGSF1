<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Customer') {
    header("Location: login.php");
    exit;
}

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

            if (count($relatedServices) === 0) {
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
                      AND service_id != ?
                    ORDER BY category ASC, service_name ASC
                    LIMIT 3
                ");
                $fallbackStmt->execute([intval($service['service_id'])]);
                $relatedServices = $fallbackStmt->fetchAll(PDO::FETCH_ASSOC);
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

function displayText($value, $fallback = 'N/A')
{
    $value = trim(html_entity_decode((string)($value ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    if ($value === '') {
        return htmlspecialchars($fallback);
    }

    return htmlspecialchars($value);
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
<title>Service Details - Norily's Repair Shop</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/style.css">

<style>
    .main-content {
        overflow-x: hidden;
    }

    .service-detail-page {
        width: 100%;
        max-width: 100%;
    }

    .detail-topbar {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 1.05rem;
    }

    .detail-topbar h2 {
        font-size: 1.65rem;
        font-weight: 900;
        color: var(--dashboard-text-main);
        margin-bottom: 0.2rem;
        line-height: 1.1;
    }

    .detail-topbar p {
        color: var(--dashboard-text-muted);
        margin-bottom: 0;
        font-size: 0.88rem;
        font-weight: 500;
    }

    .back-btn {
        min-height: 40px;
        border-radius: 12px;
        padding: 0.55rem 0.9rem;
        font-size: 0.84rem;
        font-weight: 800;
        white-space: nowrap;
        border: 1px solid rgba(17, 24, 39, 0.18);
        background: transparent;
        color: var(--dashboard-text-main);
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        text-decoration: none;
        transition: 0.2s ease;
    }

    .back-btn:hover {
        background: rgba(255, 255, 255, 0.65);
        color: var(--black);
        border-color: rgba(17, 24, 39, 0.25);
    }

    .service-layout {
        display: grid;
        grid-template-columns: minmax(0, 1.05fr) minmax(360px, 0.95fr);
        gap: 2.35rem;
        align-items: start;
    }

    .service-media-panel {
        min-width: 0;
        position: sticky;
        top: 1.2rem;
    }

    .service-media-stage {
        min-height: 465px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0.9rem;
        background:
            radial-gradient(circle at center, rgba(255, 255, 255, 0.50) 0%, rgba(255, 255, 255, 0.20) 42%, rgba(255, 255, 255, 0) 72%);
        border: none;
        box-shadow: none;
        overflow: visible;
    }

    .service-media-stage img {
        max-width: 100%;
        max-height: 425px;
        object-fit: contain;
        display: block;
        filter: drop-shadow(0 20px 22px rgba(17, 24, 39, 0.12));
        transition: 0.25s ease;
    }

    .service-info-panel {
        background: transparent;
        border: none;
        padding: 0;
        min-width: 0;
    }

    .service-category {
        color: var(--dashboard-text-muted);
        font-size: 0.72rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.9px;
        margin-bottom: 0.45rem;
    }

    .service-title {
        font-size: 2rem;
        font-weight: 900;
        line-height: 1.05;
        color: var(--dashboard-text-main);
        margin-bottom: 0.55rem;
    }

    .service-price-row {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 0.9rem;
    }

    .service-price {
        font-size: 1.35rem;
        font-weight: 900;
        color: #047857;
        line-height: 1.1;
        margin-bottom: 0.22rem;
    }

    .service-price-note {
        color: var(--dashboard-text-muted);
        font-size: 0.76rem;
        font-weight: 700;
    }

    .service-badge {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 0.38rem 0.68rem;
        font-size: 0.76rem;
        font-weight: 900;
        white-space: nowrap;
        background: rgba(245, 197, 24, 0.22);
        color: var(--black);
    }

    .detail-tabs {
        display: flex;
        align-items: center;
        gap: 1.05rem;
        border-bottom: 1px solid rgba(17, 24, 39, 0.13);
        margin-top: 0.72rem;
        margin-bottom: 0.8rem;
        overflow-x: auto;
        scrollbar-width: none;
    }

    .detail-tabs::-webkit-scrollbar {
        display: none;
    }

    .detail-tab-btn {
        border: none;
        background: transparent;
        color: var(--dashboard-text-muted);
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
        color: var(--dashboard-text-main);
    }

    .detail-tab-btn.active::after {
        background: var(--dashboard-primary);
    }

    .tab-panels {
        min-height: 145px;
        max-height: 145px;
        overflow-y: auto;
        margin-bottom: 0.9rem;
        padding-right: 0.25rem;
        scrollbar-width: thin;
    }

    .tab-panel {
        display: none;
        color: var(--dashboard-text-muted);
        font-size: 0.84rem;
        line-height: 1.62;
    }

    .tab-panel.active {
        display: block;
    }

    .simple-list {
        margin: 0;
        padding-left: 1.05rem;
        color: var(--dashboard-text-muted);
    }

    .simple-list li {
        color: var(--dashboard-text-muted);
        font-size: 0.78rem;
        line-height: 1.55;
        margin-bottom: 0.42rem;
        padding-left: 0.15rem;
    }

    .simple-list li::marker {
        color: var(--dashboard-text-main);
        font-size: 0.85rem;
    }

    .service-meta-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.65rem;
        margin: 0.8rem 0;
    }

    .meta-box {
        border: 1px solid rgba(17, 24, 39, 0.07);
        border-radius: 13px;
        padding: 0.62rem 0.72rem;
        background: rgba(255, 255, 255, 0.24);
    }

    .meta-label {
        display: block;
        color: var(--dashboard-text-muted);
        font-size: 0.62rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.38px;
        margin-bottom: 0.2rem;
    }

    .meta-value {
        color: var(--black);
        font-size: 0.78rem;
        font-weight: 850;
        line-height: 1.4;
    }

    .action-buttons {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.65rem;
        padding-top: 0.75rem;
        border-top: 1px solid rgba(17, 24, 39, 0.10);
    }

    .request-btn,
    .browse-btn {
        min-height: 42px;
        border-radius: 0;
        font-size: 0.84rem;
        font-weight: 900;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.45rem;
        text-decoration: none;
        transition: 0.2s ease;
    }

    .request-btn {
        background: var(--dashboard-primary);
        border: 1px solid var(--dashboard-primary);
        color: var(--black);
    }

    .request-btn:hover {
        background: var(--black);
        border-color: var(--black);
        color: var(--white);
    }

    .browse-btn {
        background: transparent;
        border: 1px solid rgba(17, 24, 39, 0.38);
        color: var(--dashboard-text-main);
    }

    .browse-btn:hover {
        background: rgba(245, 197, 24, 0.16);
        border-color: var(--dashboard-primary);
        color: var(--black);
    }

    .related-services-section {
        margin-top: 3.4rem;
        padding-top: 2.15rem;
        border-top: 1px solid rgba(17, 24, 39, 0.10);
    }

    .related-services-title {
        color: var(--dashboard-text-main);
        font-size: 1.38rem;
        font-weight: 900;
        letter-spacing: 2.2px;
        text-transform: uppercase;
        text-align: center;
        margin-bottom: 2.1rem;
    }

    .related-services-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 2.4rem;
        max-width: 1120px;
        margin: 0 auto;
    }

    .related-service-card {
        text-align: center;
        text-decoration: none;
        color: inherit;
        display: block;
        transition: 0.2s ease;
    }

    .related-service-card:hover {
        color: inherit;
        transform: translateY(-3px);
    }

    .related-service-image {
        width: 100%;
        min-height: 210px;
        background: transparent;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0.8rem 0.4rem 1rem 0.4rem;
        margin-bottom: 0.9rem;
    }

    .related-service-image img {
        max-width: 100%;
        max-height: 165px;
        object-fit: contain;
        filter: drop-shadow(0 18px 18px rgba(17, 24, 39, 0.10));
        transition: 0.2s ease;
    }

    .related-service-card:hover .related-service-image img {
        transform: scale(1.04);
    }

    .related-service-name {
        color: var(--dashboard-text-main);
        font-size: 1.02rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.85px;
        margin-bottom: 0.42rem;
        line-height: 1.28;
    }

    .related-service-category {
        color: var(--dashboard-primary);
        font-size: 0.7rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 1.15px;
        margin-bottom: 0.42rem;
    }

    .related-service-price {
        color: #047857;
        font-size: 0.9rem;
        font-weight: 900;
    }

    .empty-state-card {
        background: rgba(255, 255, 255, 0.3);
        border: 1px solid rgba(17, 24, 39, 0.06);
        border-radius: 18px;
        padding: 2.5rem 1.5rem;
        text-align: center;
        color: var(--dashboard-text-muted);
    }

    .empty-state-card i {
        display: block;
        font-size: 1.8rem;
        color: var(--dashboard-primary);
        margin-bottom: 0.8rem;
    }

    @media (max-width: 1199.98px) {
        .service-layout {
            grid-template-columns: 1fr;
            gap: 1.4rem;
        }

        .service-media-panel {
            position: static;
        }

        .service-media-stage {
            min-height: 315px;
        }

        .service-media-stage img {
            max-height: 300px;
        }

        .tab-panels {
            max-height: none;
            min-height: 115px;
            overflow-y: visible;
        }

        .related-services-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            max-width: 760px;
        }
    }

    @media (max-width: 767.98px) {
        .detail-topbar {
            flex-direction: column;
            align-items: stretch;
        }

        .back-btn {
            width: 100%;
            justify-content: center;
        }

        .service-title {
            font-size: 1.55rem;
        }

        .service-media-stage {
            min-height: 240px;
            padding: 0.45rem;
        }

        .service-media-stage img {
            max-height: 225px;
        }

        .service-meta-grid,
        .action-buttons,
        .related-services-grid {
            grid-template-columns: 1fr;
        }

        .related-services-grid {
            max-width: 360px;
        }
    }
</style>
</head>

<body>

<div class="app-wrapper">
<?php include '../includes/sidebar.php'; ?>

<main class="main-content">
    <div class="service-detail-page">

        <div class="detail-topbar">
            <div>
                <h2>Service Details</h2>
                <p>Review service information before submitting an appointment request.</p>
            </div>

            <a href="customer_services.php" class="back-btn">
                <i class="bi bi-arrow-left"></i>
                Back to Services
            </a>
        </div>

        <?php if ($dbError): ?>
            <div class="empty-state-card">
                <i class="bi bi-exclamation-circle"></i>
                <div class="fw-bold mb-2">Something went wrong</div>
                <div><?php echo htmlspecialchars($dbError); ?></div>
            </div>

        <?php elseif (!$service): ?>
            <div class="empty-state-card">
                <i class="bi bi-wrench-adjustable-circle"></i>
                <div class="fw-bold mb-2">Service not found</div>
                <div>Service not found or currently unavailable.</div>
            </div>

        <?php else: ?>
            <?php
                $basePrice = floatval($service['base_price'] ?? 0);
                $warrantyDays = intval($service['warranty_days'] ?? 0);
                $requiresDownPayment = intval($service['requires_down_payment'] ?? 0);
            ?>

            <div class="service-layout">
                <div class="service-media-panel">
                    <div class="service-media-stage">
                        <img
                            src="<?php echo serviceImagePath($service['image'] ?? ''); ?>"
                            alt="<?php echo displayText($service['service_name'] ?? 'Service image'); ?>"
                        >
                    </div>
                </div>

                <div class="service-info-panel">
                    <div class="service-category">
                        <?php echo displayText($service['category'] ?? ''); ?>
                    </div>

                    <h1 class="service-title">
                        <?php echo displayText($service['service_name'] ?? ''); ?>
                    </h1>

                    <div class="service-price-row">
                        <div>
                            <?php if ($basePrice > 0): ?>
                                <div class="service-price">
                                    Starts at ₱<?php echo number_format($basePrice, 2); ?>
                                </div>
                                <div class="service-price-note">
                                    Final cost depends on vehicle assessment and required parts.
                                </div>
                            <?php else: ?>
                                <div class="service-price">
                                    Cost after assessment
                                </div>
                                <div class="service-price-note">
                                    Final price will be confirmed by the shop after review.
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($requiresDownPayment): ?>
                            <span class="service-badge">Down payment may be required</span>
                        <?php else: ?>
                            <span class="service-badge">Assessment required</span>
                        <?php endif; ?>
                    </div>

                    <div class="detail-tabs" role="tablist" aria-label="Service detail sections">
                        <button type="button" class="detail-tab-btn active" data-tab="overview">Overview</button>
                        <button type="button" class="detail-tab-btn" data-tab="features">Inclusions</button>
                        <button type="button" class="detail-tab-btn" data-tab="warranty">Warranty & Payment</button>
                    </div>

                    <div class="tab-panels">
                        <div class="tab-panel active" id="tab-overview">
                            <?php echo displayMultiline($service['full_description'] ?: $service['description'], 'No description available.'); ?>
                        </div>

                        <div class="tab-panel" id="tab-features">
                            <?php echo displayMultiline($service['features'] ?? '', 'Service inclusions will be confirmed by the shop after assessment.'); ?>
                        </div>

                        <div class="tab-panel" id="tab-warranty">
                            <ul class="simple-list">
                                <?php if ($warrantyDays > 0): ?>
                                    <li>Warranty coverage: <?php echo $warrantyDays; ?> day<?php echo $warrantyDays !== 1 ? 's' : ''; ?> after service completion.</li>
                                <?php else: ?>
                                    <li>Warranty depends on the actual service performed and shop assessment.</li>
                                <?php endif; ?>

                                <li>Final cost may change depending on inspection, labor, and parts needed.</li>

                                <?php if ($requiresDownPayment): ?>
                                    <li>This service may require down payment after shop confirmation.</li>
                                <?php else: ?>
                                    <li>Payment details will be confirmed by the shop after assessment.</li>
                                <?php endif; ?>

                                <li>Submitting a request does not automatically create an official job order until approved by the shop.</li>
                            </ul>
                        </div>
                    </div>

                    <div class="service-meta-grid">
                        <div class="meta-box">
                            <span class="meta-label">Category</span>
                            <div class="meta-value">
                                <?php echo displayText($service['category'] ?? ''); ?>
                            </div>
                        </div>

                        <div class="meta-box">
                            <span class="meta-label">Warranty</span>
                            <div class="meta-value">
                                <?php if ($warrantyDays > 0): ?>
                                    <?php echo $warrantyDays; ?> day<?php echo $warrantyDays !== 1 ? 's' : ''; ?>
                                <?php else: ?>
                                    Depends on assessment
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="action-buttons">
                        <a href="customer_request_service.php?service_id=<?php echo intval($service['service_id']); ?>" class="request-btn">
                            <i class="bi bi-calendar-plus"></i>
                            Request Service
                        </a>

                        <a href="customer_services.php" class="browse-btn">
                            <i class="bi bi-wrench-adjustable-circle"></i>
                            Browse Services
                        </a>
                    </div>
                </div>
            </div>

            <?php if (!empty($relatedServices)): ?>
                <section class="related-services-section">
                    <h2 class="related-services-title">Related Services</h2>

                    <div class="related-services-grid">
                        <?php foreach ($relatedServices as $related): ?>
                            <?php $relatedPrice = floatval($related['base_price'] ?? 0); ?>

                            <a
                                href="customer_service_details.php?id=<?php echo intval($related['service_id']); ?>"
                                class="related-service-card"
                            >
                                <div class="related-service-image">
                                    <img
                                        src="<?php echo serviceImagePath($related['image'] ?? ''); ?>"
                                        alt="<?php echo displayText($related['service_name'] ?? 'Service image'); ?>"
                                    >
                                </div>

                                <div class="related-service-name">
                                    <?php echo displayText($related['service_name'] ?? ''); ?>
                                </div>

                                <div class="related-service-category">
                                    <?php echo displayText($related['category'] ?? ''); ?>
                                </div>

                                <div class="related-service-price">
                                    <?php if ($relatedPrice > 0): ?>
                                        Starts at ₱<?php echo number_format($relatedPrice, 2); ?>
                                    <?php else: ?>
                                        Cost after assessment
                                    <?php endif; ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        <?php endif; ?>

    </div>
</main>
</div>

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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>