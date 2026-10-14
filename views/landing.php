<?php
// This page is served through the project root (index.php); its paths are relative to it.
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    header("Location: ../");
    exit;
}

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$basePath = '';

require_once __DIR__ . '/../config/Database.php';

$database = new Database();
$db = $database->getConnection();

$featuredParts = [];
$featuredServices = [];

try {
    $partsStmt = $db->prepare("
        SELECT 
            part_id,
            part_name,
            unit_price,
            image
        FROM part
        WHERE is_active = 1
        ORDER BY part_id DESC
        LIMIT 8
    ");
    $partsStmt->execute();
    $featuredParts = $partsStmt->fetchAll(PDO::FETCH_ASSOC);

    $servicesStmt = $db->prepare("
        SELECT 
            service_id,
            service_name,
            base_price,
            image
        FROM service
        WHERE is_active = 1
        ORDER BY service_id DESC
        LIMIT 8
    ");
    $servicesStmt->execute();
    $featuredServices = $servicesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $featuredParts = [];
    $featuredServices = [];
}

function displayPrice($amount, $prefix = '')
{
    $amount = (float) $amount;

    if ($amount <= 0) {
        return 'Ask for price';
    }

    return $prefix . '₱' . number_format($amount, 2);
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
    <link rel="icon" type="image/png" href="assets/images/logo.png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
    <script>document.documentElement.classList.add('js');window.__motionFallback=setTimeout(function(){document.documentElement.classList.add('reveal-all');},3000);</script>
    <script src="assets/js/motion.js?v=<?= filemtime(__DIR__ . '/../assets/js/motion.js') ?>" defer></script>
</head>
<body>

<?php $activePage = ''; include 'partials/public-navbar.php'; ?>

<main>
    <section class="landing-hero">
        <div class="hero-bg-word" aria-hidden="true">NORILY'S</div>

        <div class="container hero-grid">
            <div class="hero-side hero-side-left">
                <p class="hero-eyebrow">repairs · rewinding · parts</p>

                <h1 class="hero-title">
                    Vehicle<br>
                    Repair Shop
                </h1>

                <a href="public_pages/parts.php" class="hero-link">
                    Browse Parts <span class="arrow" aria-hidden="true">&#8599;</span>
                </a>
            </div>

            <div class="hero-car-wrap">
                <img
                    src="assets/images/landing/hero-car.png"
                    alt="Black classic car"
                    class="hero-car-img"
                    onerror="this.style.display='none'; this.closest('.hero-car-wrap').classList.add('no-car-image');"
                >

                <div class="hero-car-placeholder">
                    Add car image here
                </div>
            </div>

            <div class="hero-side hero-side-right">
                <p class="hero-eyebrow">Vila Rosario, La Union</p>

                <p class="hero-since">
                    since<br>
                    1998
                </p>

                <a href="views/login.php" class="hero-link">
                    Request a Service <span class="arrow" aria-hidden="true">&#8599;</span>
                </a>
            </div>
        </div>
    </section>

    <section id="offerings" class="offerings-section">
        <div class="container">
            <div class="offerings-tabs" role="tablist" aria-label="Browse" data-tablist data-reveal>
                <button class="offerings-tab active" type="button" role="tab" id="parts-tab" aria-selected="true" aria-controls="parts-panel" data-offering-tab="parts">
                    Parts
                </button>

                <button class="offerings-tab" type="button" role="tab" id="services-tab" aria-selected="false" aria-controls="services-panel" tabindex="-1" data-offering-tab="services">
                    Services
                </button>

                <span class="offerings-tab-indicator" aria-hidden="true"></span>
            </div>

            <?php
            $offeringPanels = [
                'parts' => [
                    'active' => true,
                    'photos' => false, // product cutouts
                    'empty' => 'No parts are currently available.',
                    'items' => array_map(fn ($part) => [
                        'href' => 'public_pages/part-details.php?id=' . urlencode($part['part_id']),
                        'image' => partImagePath($part['image'] ?? ''),
                        'name' => $part['part_name'] ?? '',
                        'price' => displayPrice($part['unit_price'] ?? 0),
                    ], $featuredParts),
                ],
                'services' => [
                    'active' => false,
                    'photos' => true, // full workshop photos
                    'empty' => 'No services are currently available.',
                    'items' => array_map(fn ($service) => [
                        'href' => 'public_pages/service-details.php?id=' . urlencode($service['service_id']),
                        'image' => serviceImagePath($service['image'] ?? ''),
                        'name' => $service['service_name'] ?? '',
                        'price' => displayPrice($service['base_price'] ?? 0, 'from '),
                    ], $featuredServices),
                ],
            ];
            ?>

            <?php foreach ($offeringPanels as $panelKey => $panel): ?>
                <div class="offerings-panel<?= $panel['active'] ? ' active' : '' ?><?= $panel['photos'] ? ' offerings-panel-photos' : '' ?>" id="<?= $panelKey ?>-panel" role="tabpanel" aria-labelledby="<?= $panelKey ?>-tab">
                    <?php if (empty($panel['items'])): ?>
                        <p class="section-text text-center mb-0"><?= $panel['empty'] ?></p>
                    <?php else: ?>
                        <div class="offerings-carousel">
                            <button class="offerings-arrow offerings-arrow-prev" type="button" aria-label="Previous" data-scroll="-1">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 4 7 12l8 8"/></svg>
                            </button>

                            <div class="offerings-track" data-reveal-group>
                                <?php foreach ($panel['items'] as $index => $item): ?>
                                    <a href="<?= $item['href'] ?>" class="offering-item">
                                        <div class="offering-visual">
                                            <img src="<?= $item['image'] ?>" alt="<?= displayText($item['name']) ?>" loading="lazy">
                                        </div>

                                        <span class="offering-index"><?= str_pad($index + 1, 2, '0', STR_PAD_LEFT) ?></span>
                                        <h3 class="offering-name"><?= displayText($item['name']) ?></h3>
                                        <span class="offering-price"><?= htmlspecialchars($item['price'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>

                            <button class="offerings-arrow offerings-arrow-next" type="button" aria-label="Next" data-scroll="1">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 4 8 8-8 8"/></svg>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <div class="text-center offerings-cta-wrap">
                <a href="public_pages/parts.php" class="btn-offerings-outline" id="offeringsCta">
                    View All Parts
                </a>
            </div>
        </div>
    </section>

    <section id="about" class="about-preview-section">
        <div class="container about-preview-grid">
            <figure class="about-preview-media" data-reveal>
                <img src="assets/images/landing/about-bench.jpg" alt="Mechanic repairing a starter armature at the workbench" loading="lazy">

                <figcaption class="about-preview-caption">
                    <span class="about-preview-caption-label">Rewinding bench</span>
                    <span class="about-preview-since">since<br>1998</span>
                </figcaption>
            </figure>

            <div class="about-preview-body" data-reveal>
                <p class="about-preview-kicker">About the shop</p>

                <h2 class="about-preview-title">A family shop<br>in La Union</h2>

                <p class="about-preview-text">
                    Founded by Mr. Oliver P. Dagohoy and Mrs. Norily B. Dagohoy, Norily's has served
                    Rosario for more than 25 years with general repairs, electrical rewinding and auto parts.
                    Long-time customers and local engineers come back for the fair fees and solid technical work.
                </p>

                <dl class="about-facts">
                    <div class="about-fact">
                        <dt>Specialty</dt>
                        <dd>Alternator &amp; water pump rewinding</dd>
                    </div>

                    <div class="about-fact">
                        <dt>Warranty</dt>
                        <dd>60&ndash;90 days on rewinding</dd>
                    </div>

                    <div class="about-fact">
                        <dt>Open</dt>
                        <dd>Mon&ndash;Sat &middot; 8 AM&ndash;5 PM</dd>
                    </div>

                    <div class="about-fact">
                        <dt>Walk-ins</dt>
                        <dd>Welcome, no account needed</dd>
                    </div>
                </dl>

                <a href="public_pages/about.php" class="btn-square btn-square-outline">
                    More about the shop <span class="arrow" aria-hidden="true">&#8599;</span>
                </a>
            </div>
        </div>
    </section>

    <section id="customer-access" class="customer-access-section">
        <div class="container">
            <div class="portal-card" data-reveal>
                <div class="portal-heading">
                    <p class="portal-kicker">Customer portal</p>
                    <h2 class="portal-title">Is my car <span class="portal-highlight">ready</span>?</h2>
                </div>

                <div class="portal-rule" aria-hidden="true"></div>

                <p class="portal-text">
                    Log in to check your repair status, service requests and part orders,
                    without calling the shop.
                </p>

                <div class="portal-actions">
                    <a href="views/login.php" class="btn-square btn-square-solid">Log in</a>
                    <a href="views/register.php" class="btn-square btn-square-outline">Create account</a>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include 'partials/public-footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/offerings-carousel.js"></script>

<script>
    // Parts / Services tabs. Selection and the switch animation are handled by motion.js;
    // this keeps the "View all" button and the carousel arrows in sync.
    (function () {
        const tablist = document.querySelector('[data-tablist]');
        const offeringsCta = document.getElementById('offeringsCta');
        const ctaFor = {
            parts: ['View All Parts', 'public_pages/parts.php'],
            services: ['View All Services', 'public_pages/services.php'],
        };

        if (!tablist || !offeringsCta) {
            return;
        }

        tablist.addEventListener('tabchange', function (event) {
            const cta = ctaFor[event.detail.tab];
            offeringsCta.textContent = cta[0];
            offeringsCta.href = cta[1];
        });

        tablist.addEventListener('tabshown', updateOfferingArrows);

        document.addEventListener('DOMContentLoaded', function () {
            const tab = new URLSearchParams(window.location.search).get('tab');

            if (tablist.selectTab) {
                if (tab === 'services') {
                    tablist.selectTab(tab);
                }
                return;
            }

            // motion.js did not load: plain tab switching without animation.
            tablist.querySelectorAll('[role="tab"]').forEach(function (button) {
                button.addEventListener('click', function () {
                    tablist.querySelectorAll('[role="tab"]').forEach(function (other) {
                        const selected = other === button;
                        other.classList.toggle('active', selected);
                        other.setAttribute('aria-selected', selected ? 'true' : 'false');
                        document.getElementById(other.getAttribute('aria-controls')).classList.toggle('active', selected);
                    });
                    tablist.dispatchEvent(new CustomEvent('tabchange', { detail: { tab: button.dataset.offeringTab } }));
                    updateOfferingArrows();
                });
            });
        });
    })();
</script>

</body>
</html>