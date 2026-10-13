<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
$basePath = '../';

require_once '../config/Database.php';

$database = new Database();
$db = $database->getConnection();

$serviceCategories = [];
$dbError = '';

try {
    $query = "
        SELECT
            service_id,
            service_name,
            category,
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
        $categoryKey = plainText($service['category'] ?? '', 'Uncategorized');
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

    return '../assets/images/services/' . htmlspecialchars($image, ENT_QUOTES, 'UTF-8');
}

// Short filter label: "Rewinding Services" -> "Rewinding"; "Other Services" stays "Other services".
function categoryFilterLabel($category)
{
    $label = preg_replace('/\s+services\s*$/i', '', $category);

    if ($label === '' || strtolower($label) === 'other') {
        $label = $category;
    }

    return ucfirst(strtolower($label));
}

function categoryKey($category)
{
    return md5(strtolower($category));
}

// Symptom guide: each row links to its service only when that service exists and is active.
$serviceIdsByName = [];
foreach ($serviceCategories as $categoryServices) {
    foreach ($categoryServices as $service) {
        $serviceIdsByName[strtolower(plainText($service['service_name'] ?? ''))] = $service['service_id'];
    }
}

$symptomGuide = [];
foreach ([
    'Battery drains or the charging light is on' => 'Alternator Repair',
    'Clicking or weak cranking when starting' => 'Starter Repair',
    'Engine overheats or coolant leaks' => 'Radiator Repair',
    'Overheating with poor coolant flow' => 'Water Pump Repair',
    'Wipers are slow or not moving' => 'Wiper Repair',
    'Lights or accessories keep cutting out' => 'Automotive Wiring',
] as $symptom => $serviceName) {
    $serviceId = $serviceIdsByName[strtolower($serviceName)] ?? null;

    if ($serviceId !== null) {
        $symptomGuide[] = [
            'symptom' => $symptom,
            'service' => $serviceName,
            'url' => 'service-details.php?id=' . urlencode($serviceId),
        ];
    }
}

$serviceCount = array_sum(array_map('count', $serviceCategories));

// The request button sends visitors to log in, customers to the request form, and staff to their dashboard.
if (isLoggedIn()) {
    $isCustomer = ($_SESSION['role'] ?? '') === 'Customer';
    $requestUrl = '../views/' . ($isCustomer ? 'customer_request_service.php' : dashboardForRole($_SESSION['role'] ?? ''));
    $requestLabel = $isCustomer ? 'Request a service' : 'Go to dashboard';
} else {
    $requestUrl = '../views/login.php';
    $requestLabel = 'Log in to request';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Services | Norily's Vehicle Repair Shop</title>
<link rel="icon" type="image/png" href="../assets/images/logo.png">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
<script>document.documentElement.classList.add('js');window.__motionFallback=setTimeout(function(){document.documentElement.classList.add('reveal-all');},3000);</script>
<script src="../assets/js/motion.js?v=<?= filemtime(__DIR__ . '/../assets/js/motion.js') ?>" defer></script>

<style>
    .services-page {
        background: #eeeae2;
        min-height: 100vh;
    }

    .services-hero {
        padding: 1.5rem 0 0;
    }

    .services-breadcrumb {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        list-style: none;
        padding: 0;
        margin: 0 0 3.5rem;
        color: var(--ink-2);
        font-size: var(--fs-crumb);
        letter-spacing: 0.14em;
        text-transform: uppercase;
    }

    .services-breadcrumb a {
        color: var(--ink-2);
        text-decoration: none;
        transition: color 0.2s ease;
    }

    .services-breadcrumb a:hover {
        color: var(--ink);
    }

    .services-breadcrumb li + li::before {
        content: "/";
        margin-right: 0.6rem;
    }

    .services-title {
        color: var(--ink);
        font-size: var(--fs-page-title);
        font-weight: 400;
        font-stretch: 125%;
        text-transform: uppercase;
        line-height: 1;
        margin-bottom: 2.75rem;
    }




    .services-subtext {
        color: var(--ink-2);
        font-size: var(--fs-lead);
        line-height: 1.75;
        max-width: 540px;
        margin-bottom: 1.75rem;
    }

    .services-login-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 64px;
        padding: 0 2.1rem;
        background: var(--ink);
        border: 1px solid var(--ink);
        color: var(--sheet);
        font-size: 0.9rem;
        font-weight: 700;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        text-decoration: none;
        transition: background 0.2s ease, border-color 0.2s ease;
    }

    .services-login-btn:hover {
        background: var(--olive);
        border-color: var(--olive);
        color: var(--sheet);
    }

    /* Symptom guide */
    .symptom-guide {
        display: grid;
        grid-template-columns: 5fr 7fr;
        gap: 2rem 3rem;
        margin-top: 2.25rem;
        padding-top: 1rem;
        border-top: 1px solid var(--ink);
    }

    .symptom-kicker {
        color: var(--ink-2);
        font-size: 0.85rem;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        margin: 1rem 0;
    }

    .symptom-title {
        color: var(--ink);
        font-size: var(--fs-section-title);
        font-weight: 400;
        font-stretch: 125%;
        text-transform: uppercase;
        line-height: 1;
        margin: 0;
    }

    .symptom-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .symptom-list a {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1.5rem;
        padding: 1.25rem 0;
        border-bottom: 1px solid var(--line);
        color: var(--ink);
        text-decoration: none;
    }

    .symptom-text {
        font-size: 1.1rem;
    }

    .symptom-service {
        flex-shrink: 0;
        font-size: 0.9rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        transition: color 0.2s ease;
    }

    .symptom-list a:hover .symptom-service {
        color: var(--olive);
    }

    .symptom-note {
        color: var(--ink-2);
        font-size: 1rem;
        line-height: 1.7;
        margin: 1.5rem 0 0;
    }

    .services-catalog {
        padding: 0 0 4.2rem;
    }

    .services-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1.5rem;
        flex-wrap: wrap;
        margin: 2.5rem 0;
    }

    /* Without the symptom guide, the toolbar carries the divider line like the parts page */
    .services-toolbar.with-rule {
        padding-top: 1.75rem;
        border-top: 1px solid var(--ink);
        margin-top: 2.25rem;
    }

    .category-nav {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.4rem 1.1rem;
    }

    .category-chip {
        background: transparent;
        border: 1px solid transparent;
        border-radius: 50%;
        color: var(--ink-2);
        font-size: 1.05rem;
        padding: 0.35rem 0.25rem;
        cursor: pointer;
        transition: color 0.2s ease, border-color 0.2s ease;
    }

    .category-chip:hover {
        color: var(--ink);
    }

    .category-chip.active {
        color: var(--ink);
        border-color: var(--ink);
    }

    .services-search-box {
        position: relative;
        flex: 0 1 450px;
    }

    .services-search-wrap {
        display: flex;
        align-items: center;
        gap: 1rem;
        min-height: 60px;
        padding: 0 1.1rem;
        background: var(--sheet);
        border: 1px solid var(--ink);
        transition: box-shadow 0.2s ease;
    }

    .services-search-wrap:focus-within {
        box-shadow: 0 0 0 3px rgba(94, 107, 52, 0.2);
    }

    .services-search-label {
        color: var(--ink-2);
        font-size: 0.8rem;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        margin: 0;
        flex-shrink: 0;
    }

    .services-search {
        width: 100%;
        border: none;
        outline: none;
        background: transparent;
        color: var(--ink);
        font-size: 1.05rem;
    }

    .services-search::placeholder {
        color: var(--ink-3);
    }

    .services-clear-btn {
        display: none;
        background: transparent;
        border: 0;
        color: var(--ink-2);
        font-size: 1.4rem;
        line-height: 1;
        padding: 0 0.1rem;
        cursor: pointer;
    }

    .services-clear-btn.visible {
        display: block;
    }

    .services-suggestions {
        display: none;
        position: absolute;
        left: 0;
        right: 0;
        top: calc(100% + 4px);
        background: var(--sheet);
        border: 1px solid var(--ink);
        z-index: 20;
    }

    .services-suggestion-item {
        padding: 0.75rem 1.1rem;
        cursor: pointer;
        color: var(--ink);
        font-size: 0.95rem;
        border-bottom: 1px solid var(--line);
    }

    .services-suggestion-item:last-child {
        border-bottom: 0;
    }

    .services-suggestion-item:hover {
        background: var(--tile);
    }

    .services-category {
        margin-bottom: 2.5rem;
    }

    .services-category:last-child {
        margin-bottom: 0;
    }

    .services-category-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .services-category-title {
        color: #26241f;
        font-size: 0.88rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.55px;
        margin-bottom: 0;
    }

    .services-category-count {
        color: #5e5a51;
        font-size: 0.82rem;
        font-weight: 800;
    }

    .services-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 1.45rem;
    }

    .service-card {
        min-width: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        padding: 1.25rem 1rem 1.15rem;
        border: 1px solid rgba(38, 36, 31, 0.10);
        border-radius: 18px;
        background: transparent;
        transition: 0.2s ease;
    }

    .service-card:hover {
        transform: translateY(-3px);
        border-color: rgba(94, 107, 52, 0.50);
        background: rgba(255, 255, 255, 0.12);
    }

    .service-link {
        color: inherit;
        text-decoration: none;
    }

    .service-image-wrap {
        width: 100%;
        min-height: 205px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0.6rem 0.5rem 0.9rem;
        margin-bottom: 0.75rem;
        overflow: visible;
    }

    .service-image-wrap img {
        max-width: 100%;
        max-height: 170px;
        object-fit: contain;
        display: block;
        filter: drop-shadow(0 18px 18px rgba(38, 36, 31, 0.10));
        transition: 0.2s ease;
    }

    .service-card:hover .service-image-wrap img {
        transform: scale(1.035);
        filter: drop-shadow(0 22px 20px rgba(38, 36, 31, 0.13));
    }

    .service-name {
        width: 100%;
        color: #26241f;
        font-size: 1rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.35px;
        line-height: 1.28;
        margin-bottom: 0.35rem;
    }

    .service-category-label {
        color: #5e6b34;
        font-size: 0.72rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        margin-bottom: 0.65rem;
    }

    .service-desc {
        color: #5e5a51;
        font-size: 0.82rem;
        font-weight: 600;
        line-height: 1.5;
        min-height: 3.7rem;
        margin-bottom: 0.95rem;
        max-width: 95%;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .service-details-btn {
        border: 1px solid rgba(38, 36, 31, 0.12);
        background: rgba(255, 255, 255, 0.42);
        color: #26241f;
        border-radius: 999px;
        padding: 0.5rem 0.95rem;
        font-size: 0.78rem;
        font-weight: 900;
        text-decoration: none;
        white-space: nowrap;
        transition: 0.2s ease;
        margin-top: auto;
    }

    .service-details-btn:hover {
        background: #5e6b34;
        border-color: #5e6b34;
        color: #f8f6f1;
    }

    .services-no-results,
    .services-empty-state {
        border-top: 1px solid rgba(38, 36, 31, 0.10);
        border-bottom: 1px solid rgba(38, 36, 31, 0.10);
        padding: 3rem 1rem;
        text-align: center;
        color: #5e5a51;
        background: transparent;
        margin-top: 1rem;
        font-size: 0.92rem;
    }

    .services-no-results {
        display: none;
    }

    .services-empty-state {
        display: block;
    }

    @media (max-width: 991.98px) {
        .services-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 991.98px) {
        .symptom-guide {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 767.98px) {
        .services-breadcrumb {
            margin-bottom: 2.25rem;
        }

        .services-login-btn {
            width: 100%;
        }

        .symptom-list a {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.4rem;
        }

        .services-search-box {
            flex-basis: 100%;
        }

        .services-grid {
            grid-template-columns: 1fr;
        }

        .service-image-wrap {
            min-height: 190px;
        }

        .service-image-wrap img {
            max-height: 155px;
        }
    }

    @media (max-width: 575.98px) {
        .services-subtext {
            font-size: var(--fs-lead);
        }

        .category-chip {
            font-size: 0.95rem;
        }
    }
</style>
</head>

<body>

<?php $activePage = 'services'; include '../partials/public-navbar.php'; ?>

<main class="services-page">
    <section class="services-hero">
        <div class="container">
            <nav aria-label="Breadcrumb">
                <ol class="services-breadcrumb">
                    <li><a href="../" class="link-grow">Home</a></li>
                    <li aria-current="page">Services</li>
                </ol>
            </nav>

            <h1 class="services-title">
                Auto
                <span class="title-band">Services</span>
            </h1>

            <p class="services-subtext">
                <?= $serviceCount ?> service<?= $serviceCount !== 1 ? 's' : '' ?> in repair, rewinding, electrical and wiring.
                Prices are starting rates; the final cost is set after the
                mechanic checks your vehicle.
            </p>

            <a href="<?= htmlspecialchars($requestUrl, ENT_QUOTES, 'UTF-8') ?>" class="services-login-btn">
                <?= htmlspecialchars($requestLabel, ENT_QUOTES, 'UTF-8') ?>
            </a>

            <?php if (!empty($symptomGuide)): ?>
                <div class="symptom-guide" data-reveal>
                    <div>
                        <p class="symptom-kicker">Not sure what you need?</p>
                        <h2 class="symptom-title">Start with what you notice</h2>
                    </div>

                    <div>
                        <ul class="symptom-list">
                            <?php foreach ($symptomGuide as $row): ?>
                                <li>
                                    <a href="<?= htmlspecialchars($row['url'], ENT_QUOTES, 'UTF-8') ?>">
                                        <span class="symptom-text"><?= htmlspecialchars($row['symptom'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="symptom-service"><?= htmlspecialchars($row['service'], ENT_QUOTES, 'UTF-8') ?> <span class="arrow" aria-hidden="true">&#8599;</span></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                        <p class="symptom-note">
                            The head mechanic always checks the vehicle first and gives you an estimate
                            before any work starts.
                        </p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="services-catalog">
        <div class="container">
            <div class="services-toolbar<?= empty($symptomGuide) ? ' with-rule' : '' ?>">
                <div class="category-nav" role="group" aria-label="Filter by category">
                    <button type="button" class="category-chip active" data-category="all" aria-pressed="true">All services</button>

                    <?php if (!$dbError): ?>
                        <?php foreach (array_keys($serviceCategories) as $category): ?>
                            <button
                                type="button"
                                class="category-chip"
                                data-category="<?= categoryKey($category) ?>"
                                aria-pressed="false"
                            ><?= htmlspecialchars(categoryFilterLabel($category), ENT_QUOTES, 'UTF-8') ?></button>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="services-search-box">
                    <div class="services-search-wrap">
                        <label class="services-search-label" for="servicesSearch">Search</label>

                        <input
                            type="text"
                            class="services-search"
                            id="servicesSearch"
                            placeholder="Service name or keyword"
                            autocomplete="off"
                        >

                        <button type="button" class="services-clear-btn" id="servicesClearBtn" aria-label="Clear search">&times;</button>
                    </div>

                    <div class="services-suggestions" id="servicesSuggestions"></div>
                </div>
            </div>

            <p class="services-no-results" id="servicesNoResults">
                No matching services found. Try searching for another service name, category, or keyword.
            </p>

            <?php if ($dbError): ?>
                <p class="services-empty-state"><?= htmlspecialchars($dbError, ENT_QUOTES, 'UTF-8') ?></p>
            <?php elseif (empty($serviceCategories)): ?>
                <p class="services-empty-state">No services are currently available.</p>
            <?php else: ?>
                <?php foreach ($serviceCategories as $category => $services): ?>
                    <section class="services-category" data-category="<?= categoryKey($category) ?>">
                        <div class="services-category-header">
                            <h2 class="services-category-title">
                                <?= displayText($category) ?>
                            </h2>

                            <span class="services-category-count">
                                <?= count($services) ?> service<?= count($services) !== 1 ? 's' : '' ?>
                            </span>
                        </div>

                        <div class="services-grid" data-reveal-group>
                            <?php foreach ($services as $service): ?>
                                <?php
                                    $detailsUrl = 'service-details.php?id=' . urlencode($service['service_id']);
                                    $serviceNamePlain = plainText($service['service_name'] ?? '');
                                    $categoryPlain = plainText($service['category'] ?? '');
                                    $descriptionPlain = plainText($service['description'] ?? '');
                                    $featuresPlain = plainText($service['features'] ?? '');
                                ?>

                                <article
                                    class="service-card"
                                    data-service-name="<?= htmlspecialchars(strtolower($serviceNamePlain), ENT_QUOTES, 'UTF-8') ?>"
                                    data-service-category="<?= htmlspecialchars(strtolower($categoryPlain), ENT_QUOTES, 'UTF-8') ?>"
                                    data-service-description="<?= htmlspecialchars(strtolower($descriptionPlain), ENT_QUOTES, 'UTF-8') ?>"
                                    data-service-features="<?= htmlspecialchars(strtolower($featuresPlain), ENT_QUOTES, 'UTF-8') ?>"
                                >
                                    <a href="<?= htmlspecialchars($detailsUrl, ENT_QUOTES, 'UTF-8') ?>" class="service-link service-image-wrap">
                                        <img
                                            src="<?= serviceImagePath($service['image'] ?? '') ?>"
                                            alt="<?= displayText($service['service_name'] ?? 'Service image') ?>"
                                        >
                                    </a>

                                    <a href="<?= htmlspecialchars($detailsUrl, ENT_QUOTES, 'UTF-8') ?>" class="service-link">
                                        <h3 class="service-name">
                                            <?= displayText($service['service_name'] ?? '') ?>
                                        </h3>
                                    </a>

                                    <div class="service-category-label">
                                        <?= displayText($service['category'] ?? '') ?>
                                    </div>

                                    <p class="service-desc">
                                        <?= displayText($service['description'] ?? '', 'Service details are available upon shop assessment.') ?>
                                    </p>

                                    <a href="<?= htmlspecialchars($detailsUrl, ENT_QUOTES, 'UTF-8') ?>" class="service-details-btn">
                                        View Details
                                    </a>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php include '../partials/public-footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
const servicesSearch = document.getElementById('servicesSearch');
const servicesClearBtn = document.getElementById('servicesClearBtn');
const servicesSuggestions = document.getElementById('servicesSuggestions');
const categoryChips = document.querySelectorAll('.category-chip');
const serviceCards = document.querySelectorAll('.service-card');
const serviceCategories = document.querySelectorAll('.services-category');
const servicesNoResults = document.getElementById('servicesNoResults');

let activeCategory = 'all';

const serviceNames = Array.from(serviceCards).map(function (card) {
    return card.dataset.serviceName || '';
});

function formatServiceName(name) {
    return name.replace(/\b\w/g, function (char) {
        return char.toUpperCase();
    });
}

// Applies the category filter and the search text together.
function filterServices() {
    const value = servicesSearch.value.toLowerCase().trim();
    let totalVisibleCards = 0;

    serviceCategories.forEach(function (categoryBlock) {
        const inCategory = activeCategory === 'all' || categoryBlock.dataset.category === activeCategory;
        let visibleInBlock = 0;

        categoryBlock.querySelectorAll('.service-card').forEach(function (card) {
            const searchable = [
                card.dataset.serviceName,
                card.dataset.serviceCategory,
                card.dataset.serviceDescription,
                card.dataset.serviceFeatures
            ].join(' ');

            const isMatch = inCategory && (value === '' || searchable.includes(value));
            card.style.display = isMatch ? 'flex' : 'none';

            if (isMatch) {
                visibleInBlock++;
            }
        });

        categoryBlock.style.display = visibleInBlock > 0 ? 'block' : 'none';
        totalVisibleCards += visibleInBlock;
    });

    if (servicesNoResults) {
        servicesNoResults.style.display = totalVisibleCards === 0 && serviceCards.length > 0 ? 'block' : 'none';
    }

    servicesClearBtn.classList.toggle('visible', value !== '');
}

function showServiceSuggestions() {
    const value = servicesSearch.value.toLowerCase().trim();
    servicesSuggestions.innerHTML = '';

    const matches = value === ''
        ? []
        : serviceNames.filter(function (name) {
            return name.includes(value);
        }).slice(0, 5);

    matches.forEach(function (name) {
        const item = document.createElement('div');
        item.className = 'services-suggestion-item';
        item.textContent = formatServiceName(name);

        item.addEventListener('click', function () {
            servicesSearch.value = item.textContent;
            servicesSuggestions.style.display = 'none';
            filterServices();
        });

        servicesSuggestions.appendChild(item);
    });

    servicesSuggestions.style.display = matches.length > 0 ? 'block' : 'none';
}

categoryChips.forEach(function (chip) {
    chip.addEventListener('click', function () {
        activeCategory = chip.dataset.category;

        categoryChips.forEach(function (other) {
            const isActive = other === chip;
            other.classList.toggle('active', isActive);
            other.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });

        filterServices();
    });
});

servicesSearch.addEventListener('input', function () {
    showServiceSuggestions();
    filterServices();
});

servicesSearch.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        servicesSuggestions.style.display = 'none';
    }
});

servicesClearBtn.addEventListener('click', function () {
    servicesSearch.value = '';
    servicesSuggestions.style.display = 'none';
    filterServices();
    servicesSearch.focus();
});

document.addEventListener('click', function (event) {
    if (!event.target.closest('.services-search-box')) {
        servicesSuggestions.style.display = 'none';
    }
});
</script>

</body>
</html>