<?php
session_start();

$basePath = '';

require_once 'config/Database.php';

$database = new Database();
$db = $database->getConnection();

$featuredParts = [];
$featuredServices = [];

try {
    $partsStmt = $db->prepare("
        SELECT 
            part_id,
            part_name,
            category,
            description,
            image
        FROM part
        WHERE is_active = 1
        ORDER BY part_id DESC
        LIMIT 4
    ");
    $partsStmt->execute();
    $featuredParts = $partsStmt->fetchAll(PDO::FETCH_ASSOC);

    $servicesStmt = $db->prepare("
        SELECT 
            service_id,
            service_name,
            category,
            description,
            image
        FROM service
        WHERE is_active = 1
        ORDER BY service_id DESC
        LIMIT 4
    ");
    $servicesStmt->execute();
    $featuredServices = $servicesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $featuredParts = [];
    $featuredServices = [];
}

function plainText($value, $fallback = '')
{
    $value = trim(html_entity_decode((string)($value ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    return $value !== '' ? $value : $fallback;
}

function displayText($value, $fallback = 'N/A')
{
    return htmlspecialchars(plainText($value, $fallback), ENT_QUOTES, 'UTF-8');
}

function limitText($text, $limit = 95)
{
    $text = plainText($text, 'No description available.');

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        return mb_substr($text, 0, $limit) . '...';
    }

    if (strlen($text) <= $limit) {
        return $text;
    }

    return substr($text, 0, $limit) . '...';
}

function displayLimitText($text, $limit = 95)
{
    return htmlspecialchars(limitText($text, $limit), ENT_QUOTES, 'UTF-8');
}

function partImagePath($image)
{
    if (!$image) {
        return 'assets/images/parts/default.png';
    }

    return 'assets/images/parts/' . htmlspecialchars($image, ENT_QUOTES, 'UTF-8');
}

function serviceImagePath($image)
{
    if (!$image) {
        return 'assets/images/services/default.png';
    }

    return 'assets/images/services/' . htmlspecialchars($image, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Norily's Vehicle Repair Shop | DASPMS</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<?php include 'partials/public-navbar.php'; ?>

<main>
    <section class="landing-hero">
        <div class="hero-editorial">
            <div class="hero-bg-word">AUTO</div>
            <div class="hero-yellow-block"></div>

            <div class="container hero-editorial-container">
                <div class="hero-copy">
                    <p class="hero-year">DASPMS</p>

                    <p class="hero-label">
                        Digital Auto Service and Parts Management System
                    </p>

                    <h1 class="hero-main-title">
                        Repair Smarter.<br>
                        Drive Safer.
                    </h1>

                    <p class="hero-main-text">
                        Book service requests, browse available parts, and track repair updates
                        through Norily’s Vehicle Repair Shop customer portal.
                    </p>

                    <div class="hero-actions">
                        <a href="public_pages/services.php" class="btn hero-primary-btn">
                            Explore Services
                        </a>

                        <a href="public_pages/parts.php" class="btn hero-secondary-btn">
                            Browse Parts
                        </a>
                    </div>
                </div>

                <div class="hero-car-area">
                    <div class="hero-car-wrap">
                        <img
                            src="assets/images/landing/yellow-car-hero.png"
                            alt="Yellow car hero image"
                            class="hero-car-img"
                            onerror="this.style.display='none'; this.closest('.hero-car-wrap').classList.add('no-car-image');"
                        >

                        <div class="hero-car-placeholder">
                            Add yellow car image here
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="offerings" class="offerings-section">
        <div class="container">
            <div class="offerings-tabs">
                <button class="offerings-tab active" type="button" data-offering-tab="parts">
                    Parts
                </button>

                <button class="offerings-tab" type="button" data-offering-tab="services">
                    Services
                </button>
            </div>

            <div class="offerings-panel active" id="parts-panel">
                <div class="row g-4 justify-content-center">
                    <?php if (empty($featuredParts)): ?>
                        <div class="col-12 text-center">
                            <p class="section-text mb-0">
                                No parts are currently available.
                            </p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($featuredParts as $part): ?>
                            <div class="col-sm-6 col-lg-3">
                                <a
                                    href="public_pages/part-details.php?id=<?= urlencode($part['part_id']) ?>"
                                    class="text-decoration-none text-reset offering-item-link"
                                >
                                    <div class="offering-card">
                                        <div class="offering-visual">
                                            <img
                                                src="<?= partImagePath($part['image'] ?? '') ?>"
                                                alt="<?= displayText($part['part_name'] ?? 'Part image') ?>"
                                            >
                                        </div>

                                        <h5>
                                            <?= displayText($part['part_name'] ?? '') ?>
                                        </h5>

                                        <p>
                                            <?= displayLimitText($part['description'] ?? '', 95) ?>
                                        </p>

                                        <strong>
                                            <?= displayText($part['category'] ?? '') ?>
                                        </strong>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="offerings-panel" id="services-panel">
                <div class="row g-4 justify-content-center">
                    <?php if (empty($featuredServices)): ?>
                        <div class="col-12 text-center">
                            <p class="section-text mb-0">
                                No services are currently available.
                            </p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($featuredServices as $service): ?>
                            <div class="col-sm-6 col-lg-3">
                                <a
                                    href="public_pages/service-details.php?id=<?= urlencode($service['service_id']) ?>"
                                    class="text-decoration-none text-reset offering-item-link"
                                >
                                    <div class="offering-card">
                                        <div class="offering-visual">
                                            <img
                                                src="<?= serviceImagePath($service['image'] ?? '') ?>"
                                                alt="<?= displayText($service['service_name'] ?? 'Service image') ?>"
                                            >
                                        </div>

                                        <h5>
                                            <?= displayText($service['service_name'] ?? '') ?>
                                        </h5>

                                        <p>
                                            <?= displayLimitText($service['description'] ?? '', 95) ?>
                                        </p>

                                        <strong>
                                            <?= displayText($service['category'] ?? '') ?>
                                        </strong>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="text-center mt-5">
                <a href="public_pages/parts.php" class="btn btn-offerings-outline" id="offeringsCta">
                    View All Parts
                </a>
            </div>
        </div>
    </section>

    <section id="about" class="about-preview-section section-padding">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="about-preview-content">
                        <p class="section-kicker about-preview-kicker">About the Shop</p>

                        <div class="row g-4 align-items-start">
                            <div class="col-lg-7">
                                <h2 class="about-preview-title">
                                    Trusted Local Auto Repair Since 1998
                                </h2>

                                <p class="about-preview-text">
                                    Norily’s Vehicle Repair Shop, founded by Mr. Oliver P. Dagohoy
                                    and Mrs. Norily B. Dagohoy, has been serving customers in
                                    Vila Rosario, La Union through reliable automotive repair,
                                    rewinding services, and auto parts support.
                                </p>
                            </div>

                            <div class="col-lg-5">
                                <div class="about-preview-side">
                                    <p class="about-preview-side-text">
                                        The shop continues to assist regular and walk-in customers
                                        with practical repair experience, dependable workmanship,
                                        and fair service.
                                    </p>

                                    <a href="public_pages/about.php" class="btn btn-dark-custom about-preview-btn">
                                        Learn More About Us
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="customer-access" class="customer-access-section section-padding section-light">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="customer-login-cta">
                        <p class="section-kicker">Customer Access</p>

                        <h2 class="section-title mb-3">
                            Already have a request or repair record?
                        </h2>

                        <p class="section-text customer-access-text">
                            Log in to check your pickup request or repair status.
                        </p>

                        <a href="views/login.php" class="btn btn-dark-custom">
                            Login to Account
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include 'partials/public-footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    const offeringTabs = document.querySelectorAll('[data-offering-tab]');
    const offeringNavLinks = document.querySelectorAll('[data-nav-offering]');
    const partsPanel = document.getElementById('parts-panel');
    const servicesPanel = document.getElementById('services-panel');
    const offeringsCta = document.getElementById('offeringsCta');

    function switchOfferingTab(selectedTab) {
        if (!partsPanel || !servicesPanel || !offeringsCta) {
            return;
        }

        offeringTabs.forEach((tab) => {
            tab.classList.remove('active');

            if (tab.getAttribute('data-offering-tab') === selectedTab) {
                tab.classList.add('active');
            }
        });

        if (selectedTab === 'parts') {
            partsPanel.classList.add('active');
            servicesPanel.classList.remove('active');
            offeringsCta.textContent = 'View All Parts';
            offeringsCta.href = 'public_pages/parts.php';
        }

        if (selectedTab === 'services') {
            servicesPanel.classList.add('active');
            partsPanel.classList.remove('active');
            offeringsCta.textContent = 'View All Services';
            offeringsCta.href = 'public_pages/services.php';
        }
    }

    offeringTabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            const selectedTab = tab.getAttribute('data-offering-tab');
            switchOfferingTab(selectedTab);
        });
    });

    offeringNavLinks.forEach((link) => {
        link.addEventListener('click', () => {
            const selectedTab = link.getAttribute('data-nav-offering');

            if (selectedTab) {
                switchOfferingTab(selectedTab);
            }
        });
    });

    const urlParams = new URLSearchParams(window.location.search);
    const selectedTabFromUrl = urlParams.get('tab');

    if (selectedTabFromUrl === 'parts' || selectedTabFromUrl === 'services') {
        switchOfferingTab(selectedTabFromUrl);
    }
</script>

</body>
</html>