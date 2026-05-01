<?php
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
        $serviceCategories[$service['category']][] = $service;
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Services | Norily's Vehicle Repair Shop</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .services-page {
            background: var(--white);
        }

        .services-hero {
            padding: 2.8rem 0 1.2rem;
            text-align: center;
        }

        .services-hero .section-kicker {
            font-size: clamp(1.5rem, 3vw, 2.3rem);
            line-height: 1;
            letter-spacing: 3px;
            margin-bottom: 0.8rem;
        }

        .services-title {
            color: var(--black);
            font-size: clamp(1.2rem, 2vw, 1.6rem);
            font-weight: 900;
            line-height: 1.1;
            letter-spacing: -0.5px;
            margin-bottom: 0.65rem;
        }

        .services-subtext {
            color: var(--muted-gray);
            font-size: 0.88rem;
            line-height: 1.6;
            max-width: 620px;
            margin: 0 auto;
        }

        .services-catalog {
            padding: 1.2rem 0 4rem;
        }

        .services-tools {
            max-width: 680px;
            margin: 0 auto 3rem;
            position: relative;
        }

        .services-search-row {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 0.65rem;
            align-items: end;
        }

        .services-search {
            width: 100%;
            border: 0;
            border-bottom: 1px solid var(--border-light);
            padding: 0.55rem 0;
            font-size: 0.85rem;
            outline: none;
            color: var(--black);
            background: transparent;
        }

        .services-search:focus {
            border-bottom-color: var(--primary-yellow);
        }

        .services-search-btn {
            background: var(--black);
            color: var(--white);
            border: 2px solid var(--black);
            padding: 0.5rem 1.05rem;
            font-size: 0.75rem;
            font-weight: 900;
            text-transform: uppercase;
            transition: all 0.2s ease;
        }

        .services-search-btn:hover {
            background: var(--primary-yellow);
            color: var(--black);
            border-color: var(--primary-yellow);
        }

        .services-suggestions {
            display: none;
            position: absolute;
            left: 0;
            right: 95px;
            top: calc(100% + 0.5rem);
            background: var(--white);
            border: 1px solid var(--border-light);
            box-shadow: 0 12px 30px rgba(17, 17, 17, 0.08);
            z-index: 20;
        }

        .services-suggestion-item {
            padding: 0.65rem 0.8rem;
            cursor: pointer;
            color: var(--black);
            font-size: 0.82rem;
            font-weight: 700;
            border-bottom: 1px solid var(--border-light);
        }

        .services-suggestion-item:last-child {
            border-bottom: 0;
        }

        .services-suggestion-item:hover {
            background: var(--light-gray);
        }

        .services-no-results,
        .services-empty-state {
            display: none;
            max-width: 680px;
            margin: 0 auto 3rem;
            text-align: center;
            color: var(--muted-gray);
            font-size: 0.88rem;
            line-height: 1.6;
        }

        .services-empty-state {
            display: block;
        }

        .services-category {
            margin-bottom: 3.5rem;
        }

        .services-category:last-child {
            margin-bottom: 0;
        }

        .services-category-title {
            color: var(--black);
            font-size: clamp(1rem, 2vw, 1.5rem);
            font-weight: 900;
            letter-spacing: 2.5px;
            text-transform: uppercase;
            text-align: center;
            margin-bottom: 2rem;
        }

        .services-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
            align-items: start;
        }

        .service-card {
            background: var(--white);
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100%;
        }

        .service-link {
            color: inherit;
            text-decoration: none;
        }

        .service-link:hover {
            color: var(--yellow-dark);
        }

        .service-image-wrap {
            width: 100%;
            height: 165px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
        }

        .service-image-wrap img {
            max-width: 88%;
            max-height: 155px;
            object-fit: contain;
            display: block;
            transition: transform 0.2s ease;
        }

        .service-image-wrap:hover img {
            transform: scale(1.03);
        }

        .service-card h3 {
            width: 100%;
            color: var(--black);
            font-size: 0.82rem;
            font-weight: 900;
            letter-spacing: 1px;
            text-transform: uppercase;
            line-height: 1.35;
            padding: 0.6rem 0 0.25rem;
            margin: 0;
            border-top: 1px solid var(--border-light);
        }

        .service-type {
            display: block;
            width: 100%;
            color: var(--yellow-dark);
            font-size: 0.65rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            padding: 0.2rem 0 0.65rem;
            border-bottom: 1px solid var(--border-light);
            margin-bottom: 0.9rem;
        }

        .service-details-btn {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            background: var(--black);
            color: var(--white);
            border: 1px solid var(--black);
            padding: 0.45rem 1rem;
            font-size: 0.7rem;
            font-weight: 900;
            text-transform: uppercase;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .service-details-btn:hover {
            background: var(--primary-yellow);
            color: var(--black);
            border-color: var(--primary-yellow);
        }

        @media (max-width: 991.98px) {
            .services-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 575.98px) {
            .services-hero {
                padding: 2.6rem 0 1.2rem;
            }

            .services-hero .section-kicker {
                font-size: 1.8rem;
                letter-spacing: 2px;
            }

            .services-title {
                font-size: 1.3rem;
            }

            .services-search-row {
                grid-template-columns: 1fr;
            }

            .services-suggestions {
                right: 0;
            }

            .services-search-btn {
                width: 100%;
            }

            .services-grid {
                grid-template-columns: 1fr;
                gap: 2.2rem;
            }

            .services-category-title {
                font-size: 1.2rem;
            }

            .service-image-wrap {
                height: 160px;
            }

            .service-image-wrap img {
                max-height: 150px;
            }
        }
    </style>
</head>
<body>

<?php include '../partials/public-navbar.php'; ?>

<main class="services-page">
    <section class="services-hero">
        <div class="container">
            <p class="section-kicker">Auto Services</p>

            <h1 class="services-title">
                Available Repair and Maintenance Services
            </h1>

            <p class="services-subtext">
                Browse repair, rewinding, electrical, wiring, and automotive support services
                offered by Norily's Vehicle Repair Shop.
            </p>
        </div>
    </section>

    <section class="services-catalog">
        <div class="container">
            <div class="services-tools">
                <div class="services-search-row">
                    <input
                        type="text"
                        class="services-search"
                        id="servicesSearch"
                        placeholder="Search services by name or category"
                        autocomplete="off"
                    >

                    <button type="button" class="services-search-btn" id="servicesSearchBtn">
                        Search
                    </button>
                </div>

                <div class="services-suggestions" id="servicesSuggestions"></div>
            </div>

            <p class="services-no-results" id="servicesNoResults">
                No matching services found. Try searching for another service name, category, or keyword.
            </p>

            <?php if ($dbError): ?>
                <p class="services-empty-state"><?= htmlspecialchars($dbError) ?></p>
            <?php elseif (empty($serviceCategories)): ?>
                <p class="services-empty-state">No services are currently available.</p>
            <?php else: ?>
                <?php foreach ($serviceCategories as $category => $services): ?>
                    <div class="services-category">
                        <h2 class="services-category-title">
                            <?= htmlspecialchars($category) ?>
                        </h2>

                        <div class="services-grid">
                            <?php foreach ($services as $service): ?>
                                <?php $detailsUrl = 'service-details.php?id=' . urlencode($service['service_id']); ?>

                                <article
                                    class="service-card"
                                    data-service-name="<?= strtolower(htmlspecialchars($service['service_name'])) ?>"
                                    data-service-category="<?= strtolower(htmlspecialchars($service['category'])) ?>"
                                    data-service-description="<?= strtolower(htmlspecialchars($service['description'] ?? '')) ?>"
                                    data-service-features="<?= strtolower(htmlspecialchars($service['features'] ?? '')) ?>"
                                >
                                    <a href="<?= htmlspecialchars($detailsUrl) ?>" class="service-link service-image-wrap">
                                        <img
                                            src="<?= serviceImagePath($service['image'] ?? '') ?>"
                                            alt="<?= htmlspecialchars($service['service_name']) ?>"
                                        >
                                    </a>

                                    <a href="<?= htmlspecialchars($detailsUrl) ?>" class="service-link">
                                        <h3>
                                            <?= htmlspecialchars($service['service_name']) ?>
                                        </h3>
                                    </a>

                                    <span class="service-type">
                                        <?= htmlspecialchars($service['category']) ?>
                                    </span>

                                    <a href="<?= htmlspecialchars($detailsUrl) ?>" class="service-details-btn">
                                        View Details
                                    </a>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>
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

    if (servicesSearch && servicesSearchBtn) {
        servicesSearch.addEventListener('input', function () {
            showServiceSuggestions(this.value);
            filterServices(this.value);
        });

        servicesSearchBtn.addEventListener('click', function () {
            servicesSuggestions.style.display = 'none';
            filterServices(servicesSearch.value);
        });
    }

    document.addEventListener('click', function (event) {
        if (!event.target.closest('.services-tools')) {
            servicesSuggestions.style.display = 'none';
        }
    });
</script>

</body>
</html>