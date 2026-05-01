<?php
$basePath = '../';

require_once '../config/Database.php';

$database = new Database();
$db = $database->getConnection();

$selectedId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$selectedService = null;
$relatedServices = [];
$dbError = '';

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

    return '../assets/images/services/' . htmlspecialchars($image);
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
        <?= $selectedService ? htmlspecialchars($selectedService['service_name']) . ' | Services' : 'Service Not Found | Services' ?>
    </title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .service-detail-page {
            background: var(--white);
            border-top: 1px solid var(--border-light);
        }

        .service-detail-wrapper {
            max-width: 1180px;
            margin: 0 auto;
            padding: 3.5rem 1rem 5rem;
        }

        .back-link {
            display: inline-flex;
            color: var(--muted-gray);
            text-decoration: none;
            font-size: 0.95rem;
            margin-bottom: 2.5rem;
            transition: color 0.2s ease;
        }

        .back-link:hover {
            color: var(--yellow-dark);
        }

        .service-detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
        }

        .service-detail-image {
            min-height: 360px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .service-detail-image img {
            max-width: 100%;
            max-height: 330px;
            object-fit: contain;
            display: block;
        }

        .service-detail-info {
            max-width: 540px;
        }

        .breadcrumb-text {
            color: var(--muted-gray);
            font-size: 0.85rem;
            margin-bottom: 1rem;
        }

        .breadcrumb-text a {
            color: var(--yellow-dark);
            text-decoration: none;
            font-weight: 800;
        }

        .service-detail-title {
            color: var(--black);
            font-size: clamp(1.8rem, 4vw, 2.8rem);
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 3px;
            line-height: 1.1;
            margin-bottom: 0.9rem;
        }

        .service-detail-type {
            display: block;
            color: var(--yellow-dark);
            font-size: 0.78rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 1rem;
        }

        .service-detail-status {
            color: var(--black);
            font-size: 0.95rem;
            font-weight: 900;
            margin-bottom: 1.1rem;
            line-height: 1.6;
        }

        .service-detail-description {
            color: var(--muted-gray);
            font-size: 1rem;
            line-height: 1.8;
            margin-bottom: 1.5rem;
        }

        .walk-in-note {
            background: rgba(245, 197, 24, 0.12);
            border-left: 4px solid var(--primary-yellow);
            padding: 1rem 1.1rem;
            margin-bottom: 1.7rem;
        }

        .walk-in-note h2 {
            color: var(--black);
            font-size: 0.82rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 1.3px;
            margin-bottom: 0.45rem;
        }

        .walk-in-note p {
            color: var(--muted-gray);
            font-size: 0.92rem;
            line-height: 1.7;
            margin-bottom: 0;
        }

        .service-features-box {
            border-top: 1px solid var(--border-light);
            border-bottom: 1px solid var(--border-light);
            padding: 1rem 0;
            margin-bottom: 2rem;
        }

        .service-features-box h2 {
            color: var(--black);
            font-size: 0.8rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 0.7rem;
        }

        .service-features-box ul {
            margin: 0;
            padding-left: 1.1rem;
        }

        .service-features-box li {
            color: var(--muted-gray);
            font-size: 0.92rem;
            line-height: 1.7;
        }

        .detail-actions {
            display: flex;
            gap: 0.8rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
        }

        .detail-primary-btn,
        .detail-secondary-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.65rem 1.2rem;
            font-size: 0.78rem;
            font-weight: 900;
            text-transform: uppercase;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .detail-primary-btn {
            background: var(--primary-yellow);
            color: var(--black);
            border: 1px solid var(--primary-yellow);
        }

        .detail-secondary-btn {
            background: var(--black);
            color: var(--white);
            border: 1px solid var(--black);
        }

        .detail-primary-btn:hover,
        .detail-secondary-btn:hover {
            background: var(--yellow-dark);
            color: var(--black);
            border-color: var(--yellow-dark);
        }

        .detail-note {
            color: var(--muted-gray);
            font-size: 0.9rem;
            line-height: 1.7;
        }

        .related-section {
            margin-top: 5rem;
            padding-top: 3rem;
            border-top: 1px solid var(--border-light);
        }

        .related-title {
            color: var(--black);
            font-size: clamp(1rem, 2vw, 1.5rem);
            font-weight: 900;
            letter-spacing: 2.5px;
            text-transform: uppercase;
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .related-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
        }

        .related-card {
            text-align: center;
        }

        .related-card a {
            color: inherit;
            text-decoration: none;
        }

        .related-card img {
            max-width: 88%;
            height: 150px;
            object-fit: contain;
            margin-bottom: 1rem;
            transition: transform 0.2s ease;
        }

        .related-card:hover img {
            transform: scale(1.03);
        }

        .related-card h3 {
            color: var(--black);
            font-size: 0.82rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0.4rem;
        }

        .related-card span {
            color: var(--yellow-dark);
            font-size: 0.65rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }

        .not-found-box {
            max-width: 720px;
            margin: 0 auto;
            text-align: center;
        }

        .not-found-box h1 {
            color: var(--black);
            font-size: 2rem;
            font-weight: 900;
            margin-bottom: 1rem;
        }

        .not-found-box p {
            color: var(--muted-gray);
            line-height: 1.7;
            margin-bottom: 2rem;
        }

        @media (max-width: 991.98px) {
            .service-detail-grid {
                grid-template-columns: 1fr;
                gap: 2.5rem;
            }

            .service-detail-info {
                max-width: 100%;
                text-align: center;
            }

            .service-features-box,
            .walk-in-note {
                text-align: left;
            }

            .detail-actions {
                justify-content: center;
            }

            .related-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 575.98px) {
            .service-detail-wrapper {
                padding: 2.5rem 1rem 4rem;
            }

            .service-detail-image {
                min-height: 240px;
            }

            .service-detail-image img {
                max-height: 220px;
            }

            .related-grid {
                grid-template-columns: 1fr;
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
        <a href="services.php" class="back-link">← Back to Services</a>

        <?php if ($dbError): ?>
            <div class="not-found-box">
                <h1>Unable to Load Service</h1>
                <p><?= htmlspecialchars($dbError) ?></p>

                <a href="services.php" class="detail-primary-btn">
                    Browse Services
                </a>
            </div>
        <?php elseif (!$selectedService): ?>
            <div class="not-found-box">
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
            <div class="service-detail-grid">
                <div class="service-detail-image">
                    <img
                        src="<?= serviceImagePath($selectedService['image'] ?? '') ?>"
                        alt="<?= htmlspecialchars($selectedService['service_name']) ?>"
                    >
                </div>

                <div class="service-detail-info">
                    <p class="breadcrumb-text">
                        <a href="services.php">Services</a> › <?= htmlspecialchars($selectedService['category']) ?>
                    </p>

                    <h1 class="service-detail-title">
                        <?= htmlspecialchars($selectedService['service_name']) ?>
                    </h1>

                    <span class="service-detail-type">
                        <?= htmlspecialchars($selectedService['category']) ?>
                    </span>

                    <p class="service-detail-status">
                        Walk-in service only. Vehicle assessment and service processing are handled at the shop.
                    </p>

                    <p class="service-detail-description">
                        <?= nl2br(htmlspecialchars($selectedService['full_description'] ?: $selectedService['description'] ?: 'No detailed description available.')) ?>
                    </p>

                    <div class="walk-in-note">
                        <h2>Service Processing Notice</h2>
                        <p>
                            Please visit Norily's Vehicle Repair Shop for vehicle assessment. The owner, cashier,
                            or authorized shop staff will encode the job order, confirm the needed service,
                            provide the cost estimate, and process payment manually at the shop.
                        </p>
                    </div>

                    <?php $features = parseFeatures($selectedService['features'] ?? ''); ?>

                    <?php if (!empty($features)): ?>
                        <div class="service-features-box">
                            <h2>Key Features</h2>

                            <ul>
                                <?php foreach ($features as $feature): ?>
                                    <li><?= htmlspecialchars($feature) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <div class="detail-actions">
                        <a href="services.php" class="detail-secondary-btn">
                            Browse More Services
                        </a>
                    </div>

                    <p class="detail-note">
                        This page is for service information only. Online service booking is not available in the system.
                    </p>
                </div>
            </div>

            <?php if (!empty($relatedServices)): ?>
                <div class="related-section">
                    <h2 class="related-title">Related Services</h2>

                    <div class="related-grid">
                        <?php foreach ($relatedServices as $related): ?>
                            <article class="related-card">
                                <a href="service-details.php?id=<?= urlencode($related['service_id']) ?>">
                                    <img
                                        src="<?= serviceImagePath($related['image'] ?? '') ?>"
                                        alt="<?= htmlspecialchars($related['service_name']) ?>"
                                    >

                                    <h3><?= htmlspecialchars($related['service_name']) ?></h3>
                                    <span><?= htmlspecialchars($related['category']) ?></span>
                                </a>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</main>

<?php include '../partials/public-footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>