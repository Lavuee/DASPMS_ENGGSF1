<?php
$basePath = '../';

require_once '../config/Database.php';

$database = new Database();
$db = $database->getConnection();

$serviceCategories = [];
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Services | Norily's Vehicle Repair Shop</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/style.css">

<style>
    .services-page {
        background: #f4f5f7;
        min-height: 100vh;
    }

    .services-hero {
        padding: 3rem 0 1.2rem;
    }

    .services-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        flex-wrap: wrap;
        padding-bottom: 1.1rem;
        border-bottom: 1px solid rgba(17, 24, 39, 0.10);
    }

    .services-kicker {
        color: #f0b400;
        font-size: 0.78rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.9px;
        margin-bottom: 0.45rem;
    }

    .services-title {
        color: #111827;
        font-size: clamp(1.8rem, 3vw, 2.35rem);
        font-weight: 900;
        line-height: 1.05;
        letter-spacing: -0.7px;
        margin-bottom: 0.45rem;
    }

    .services-subtext {
        color: #5f6b7a;
        font-size: 0.98rem;
        line-height: 1.6;
        max-width: 660px;
        margin-bottom: 0;
    }

    .services-login-btn {
        min-height: 42px;
        border-radius: 999px;
        padding: 0.6rem 1rem;
        background: #f5c518;
        border: 1px solid #f5c518;
        color: #111827;
        font-size: 0.88rem;
        font-weight: 900;
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        text-decoration: none;
        white-space: nowrap;
        transition: 0.2s ease;
    }

    .services-login-btn:hover {
        background: #111827;
        border-color: #111827;
        color: #fff;
    }

    .services-catalog {
        padding: 1rem 0 4.2rem;
    }

    .services-toolbar {
        display: grid;
        grid-template-columns: minmax(260px, 1fr) 150px;
        gap: 0.8rem;
        align-items: end;
        margin-bottom: 1.05rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid rgba(17, 24, 39, 0.08);
        position: relative;
    }

    .services-filter-label {
        display: block;
        color: #6b7280;
        font-size: 0.76rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.35px;
        margin-bottom: 0.35rem;
    }

    .services-search-wrap {
        min-height: 44px;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        background: rgba(255, 255, 255, 0.50);
        border: 1px solid rgba(17, 24, 39, 0.08);
        border-radius: 16px;
        padding: 0.65rem 0.9rem;
        transition: 0.2s ease;
    }

    .services-search-wrap:focus-within {
        background: rgba(255, 255, 255, 0.88);
        border-color: rgba(245, 197, 24, 0.72);
        box-shadow: 0 0 0 4px rgba(245, 197, 24, 0.12);
    }

    .services-search-wrap i {
        color: #5f6b7a;
        font-size: 1.05rem;
        flex-shrink: 0;
    }

    .services-search {
        width: 100%;
        border: none;
        outline: none;
        background: transparent;
        color: #111827;
        font-size: 0.92rem;
        font-weight: 500;
    }

    .services-search::placeholder {
        color: #6b7280;
    }

    .services-clear-btn {
        min-height: 44px;
        border-radius: 999px;
        border: 1px solid rgba(17, 24, 39, 0.10);
        background: rgba(255, 255, 255, 0.50);
        color: #111827;
        font-size: 0.88rem;
        font-weight: 900;
        transition: 0.2s ease;
    }

    .services-clear-btn:hover {
        background: #f5c518;
        border-color: #f5c518;
        color: #111827;
    }

    .services-suggestions {
        display: none;
        position: absolute;
        left: 0;
        right: 166px;
        top: calc(100% - 0.55rem);
        background: #fff;
        border: 1px solid rgba(17, 24, 39, 0.10);
        border-radius: 14px;
        box-shadow: 0 18px 38px rgba(17, 24, 39, 0.10);
        z-index: 20;
        overflow: hidden;
    }

    .services-suggestion-item {
        padding: 0.72rem 0.9rem;
        cursor: pointer;
        color: #111827;
        font-size: 0.84rem;
        font-weight: 800;
        border-bottom: 1px solid rgba(17, 24, 39, 0.06);
    }

    .services-suggestion-item:last-child {
        border-bottom: 0;
    }

    .services-suggestion-item:hover {
        background: rgba(245, 197, 24, 0.10);
    }

    .category-nav {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.6rem;
        margin-bottom: 1.6rem;
    }

    .category-chip {
        min-height: 36px;
        border-radius: 999px;
        border: 1px solid rgba(17, 24, 39, 0.10);
        background: rgba(255, 255, 255, 0.38);
        color: #5f6b7a;
        font-size: 0.82rem;
        font-weight: 900;
        padding: 0.47rem 0.82rem;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        transition: 0.2s ease;
    }

    .category-chip:hover {
        background: #f5c518;
        border-color: #f5c518;
        color: #111827;
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
        color: #111827;
        font-size: 0.88rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.55px;
        margin-bottom: 0;
    }

    .services-category-count {
        color: #6b7280;
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
        border: 1px solid rgba(17, 24, 39, 0.10);
        border-radius: 18px;
        background: transparent;
        transition: 0.2s ease;
    }

    .service-card:hover {
        transform: translateY(-3px);
        border-color: rgba(245, 197, 24, 0.50);
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
        filter: drop-shadow(0 18px 18px rgba(17, 24, 39, 0.10));
        transition: 0.2s ease;
    }

    .service-card:hover .service-image-wrap img {
        transform: scale(1.035);
        filter: drop-shadow(0 22px 20px rgba(17, 24, 39, 0.13));
    }

    .service-name {
        width: 100%;
        color: #111827;
        font-size: 1rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.35px;
        line-height: 1.28;
        margin-bottom: 0.35rem;
    }

    .service-category-label {
        color: #f0b400;
        font-size: 0.72rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        margin-bottom: 0.65rem;
    }

    .service-desc {
        color: #5f6b7a;
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
        border: 1px solid rgba(17, 24, 39, 0.12);
        background: rgba(255, 255, 255, 0.42);
        color: #111827;
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
        background: #f5c518;
        border-color: #f5c518;
        color: #111827;
    }

    .services-no-results,
    .services-empty-state {
        border-top: 1px solid rgba(17, 24, 39, 0.10);
        border-bottom: 1px solid rgba(17, 24, 39, 0.10);
        padding: 3rem 1rem;
        text-align: center;
        color: #5f6b7a;
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

    @media (max-width: 767.98px) {
        .services-hero {
            padding: 2.3rem 0 1rem;
        }

        .services-header {
            flex-direction: column;
            align-items: stretch;
        }

        .services-login-btn {
            width: 100%;
            justify-content: center;
        }

        .services-toolbar {
            grid-template-columns: 1fr;
        }

        .services-clear-btn {
            width: 100%;
        }

        .services-suggestions {
            right: 0;
            top: calc(100% - 4.05rem);
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
        .services-title {
            font-size: 1.55rem;
        }

        .services-subtext {
            font-size: 0.9rem;
        }
    }
</style>
</head>

<body>

<?php include '../partials/public-navbar.php'; ?>

<main class="services-page">
    <section class="services-hero">
        <div class="container">
            <div class="services-header">
                <div>
                    <div class="services-kicker">Auto Services</div>

                    <h1 class="services-title">
                        Available Repair and Maintenance Services
                    </h1>

                    <p class="services-subtext">
                        Browse repair, rewinding, electrical, wiring, and automotive support services
                        offered by Norily's Vehicle Repair Shop.
                    </p>
                </div>

                <a href="../views/login.php" class="services-login-btn">
                    <i class="bi bi-box-arrow-in-right"></i>
                    Login to Request
                </a>
            </div>
        </div>
    </section>

    <section class="services-catalog">
        <div class="container">
            <div class="services-toolbar">
                <div>
                    <label class="services-filter-label" for="servicesSearch">Search</label>

                    <div class="services-search-wrap">
                        <i class="bi bi-search"></i>
                        <input
                            type="text"
                            class="services-search"
                            id="servicesSearch"
                            placeholder="Search services by name, category, description, or features..."
                            autocomplete="off"
                        >
                    </div>
                </div>

                <button type="button" class="services-clear-btn" id="servicesSearchBtn">
                    Clear
                </button>

                <div class="services-suggestions" id="servicesSuggestions"></div>
            </div>

            <?php if (!$dbError && !empty($serviceCategories)): ?>
                <div class="category-nav">
                    <?php foreach ($serviceCategories as $category => $services): ?>
                        <a href="#cat-<?= htmlspecialchars(md5($category), ENT_QUOTES, 'UTF-8') ?>" class="category-chip">
                            <?= displayText($category) ?>
                            <span>(<?= count($services) ?>)</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <p class="services-no-results" id="servicesNoResults">
                No matching services found. Try searching for another service name, category, or keyword.
            </p>

            <?php if ($dbError): ?>
                <p class="services-empty-state"><?= htmlspecialchars($dbError, ENT_QUOTES, 'UTF-8') ?></p>
            <?php elseif (empty($serviceCategories)): ?>
                <p class="services-empty-state">No services are currently available.</p>
            <?php else: ?>
                <?php foreach ($serviceCategories as $category => $services): ?>
                    <section class="services-category" id="cat-<?= htmlspecialchars(md5($category), ENT_QUOTES, 'UTF-8') ?>">
                        <div class="services-category-header">
                            <h2 class="services-category-title">
                                <?= displayText($category) ?>
                            </h2>

                            <span class="services-category-count">
                                <?= count($services) ?> service<?= count($services) !== 1 ? 's' : '' ?>
                            </span>
                        </div>

                        <div class="services-grid">
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
const servicesSearchBtn = document.getElementById('servicesSearchBtn');
const servicesSuggestions = document.getElementById('servicesSuggestions');
const serviceCards = document.querySelectorAll('.service-card');
const serviceCategories = document.querySelectorAll('.services-category');
const servicesNoResults = document.getElementById('servicesNoResults');

const serviceNames = Array.from(serviceCards).map(function (card) {
    return card.dataset.serviceName || '';
});

function formatServiceName(name) {
    return name.replace(/\b\w/g, function (char) {
        return char.toUpperCase();
    });
}

function filterServices(searchValue) {
    const value = searchValue.toLowerCase().trim();
    let totalVisibleCards = 0;

    serviceCards.forEach(function (card) {
        const name = card.dataset.serviceName || '';
        const category = card.dataset.serviceCategory || '';
        const description = card.dataset.serviceDescription || '';
        const features = card.dataset.serviceFeatures || '';

        const isMatch =
            value === '' ||
            name.includes(value) ||
            category.includes(value) ||
            description.includes(value) ||
            features.includes(value);

        card.style.display = isMatch ? 'flex' : 'none';

        if (isMatch) {
            totalVisibleCards++;
        }
    });

    serviceCategories.forEach(function (categoryBlock) {
        const visibleCards = categoryBlock.querySelectorAll('.service-card:not([style*="display: none"])');
        categoryBlock.style.display = visibleCards.length > 0 ? 'block' : 'none';
    });

    if (servicesNoResults) {
        servicesNoResults.style.display = totalVisibleCards === 0 && serviceCards.length > 0 ? 'block' : 'none';
    }
}

function showServiceSuggestions(searchValue) {
    const value = searchValue.toLowerCase().trim();
    servicesSuggestions.innerHTML = '';

    if (value === '') {
        servicesSuggestions.style.display = 'none';
        return;
    }

    const matches = serviceNames
        .filter(function (name) {
            return name.includes(value);
        })
        .slice(0, 5);

    if (matches.length === 0) {
        servicesSuggestions.style.display = 'none';
        return;
    }

    matches.forEach(function (name) {
        const item = document.createElement('div');
        item.className = 'services-suggestion-item';
        item.textContent = formatServiceName(name);

        item.addEventListener('click', function () {
            servicesSearch.value = item.textContent;
            servicesSuggestions.style.display = 'none';
            filterServices(servicesSearch.value);
        });

        servicesSuggestions.appendChild(item);
    });

    servicesSuggestions.style.display = 'block';
}

if (servicesSearch) {
    servicesSearch.addEventListener('input', function () {
        showServiceSuggestions(this.value);
        filterServices(this.value);
    });
}

if (servicesSearchBtn) {
    servicesSearchBtn.addEventListener('click', function () {
        servicesSearch.value = '';
        servicesSuggestions.style.display = 'none';
        filterServices('');
        servicesSearch.focus();
    });
}

document.addEventListener('click', function (event) {
    if (!event.target.closest('.services-toolbar')) {
        servicesSuggestions.style.display = 'none';
    }
});
</script>

</body>
</html>