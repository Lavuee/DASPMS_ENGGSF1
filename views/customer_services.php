<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Customer') {
    header("Location: login.php");
    exit;
}

require_once '../config/Database.php';

$db = (new Database())->getConnection();

$serviceCategories = [];
$dbError = '';

try {
    $query = "
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
        WHERE is_active = 1
        ORDER BY category ASC, service_name ASC
    ";

    $stmt = $db->prepare($query);
    $stmt->execute();
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($services as $service) {
        $categoryKey = trim(html_entity_decode((string)($service['category'] ?? 'Uncategorized'), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $categoryKey = $categoryKey !== '' ? $categoryKey : 'Uncategorized';

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

function shortText($value, $fallback = 'Service details will be confirmed by the shop after assessment.', $limit = 115)
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
<title>Services Offered - Norily's Repair Shop</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/style.css">

<style>
    .main-content {
        overflow-x: hidden;
    }

    .customer-services-page {
        width: 100%;
        max-width: 100%;
    }

    .services-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 1.35rem;
    }

    .services-header h2 {
        font-size: 2rem;
        font-weight: 900;
        color: var(--dashboard-text-main);
        margin-bottom: 0.25rem;
        line-height: 1.1;
    }

    .services-header p {
        color: var(--dashboard-text-muted);
        font-size: 0.95rem;
        font-weight: 500;
        margin-bottom: 0;
    }

    .request-pill-btn {
        height: 44px;
        border-radius: 999px;
        padding: 0.6rem 1rem;
        font-size: 0.9rem;
        font-weight: 900;
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        white-space: nowrap;
        background: var(--dashboard-primary);
        border: 1px solid var(--dashboard-primary);
        color: var(--black);
        text-decoration: none;
        transition: 0.2s ease;
    }

    .request-pill-btn:hover {
        background: var(--black);
        border-color: var(--black);
        color: var(--white);
    }

    .services-toolbar {
        display: grid;
        grid-template-columns: minmax(260px, 1fr) 160px;
        gap: 0.85rem;
        align-items: end;
        margin-bottom: 1.4rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid #e5e7eb;
    }

    .services-filter-label {
        display: block;
        color: var(--dashboard-text-muted);
        font-size: 0.76rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.35px;
        margin-bottom: 0.35rem;
    }

    .services-search-bar {
        min-height: 44px;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        background: rgba(255, 255, 255, 0.56);
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 14px;
        padding: 0.65rem 0.9rem;
        box-shadow: none;
        transition: 0.2s ease;
    }

    .services-search-bar:focus-within {
        border-color: rgba(245, 197, 24, 0.65);
        box-shadow: 0 0 0 4px rgba(245, 197, 24, 0.12);
        background: rgba(255, 255, 255, 0.90);
    }

    .services-search-bar i {
        font-size: 1.05rem;
        color: var(--dashboard-text-muted);
        flex-shrink: 0;
    }

    .services-search-bar input {
        width: 100%;
        border: none;
        outline: none;
        background: transparent;
        color: var(--dashboard-text-main);
        font-size: 0.92rem;
    }

    .services-clear-btn {
        height: 44px;
        border-radius: 999px;
        padding: 0.55rem 0.85rem;
        font-size: 0.9rem;
        font-weight: 800;
        border: 1px solid rgba(15, 23, 42, 0.10);
        background: rgba(255, 255, 255, 0.56);
        color: var(--dashboard-text-main);
        transition: 0.2s ease;
    }

    .services-clear-btn:hover {
        background: rgba(255, 255, 255, 0.90);
        color: var(--black);
    }

    .category-nav {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        flex-wrap: wrap;
        margin-bottom: 1.5rem;
    }

    .category-chip {
        border: 1px solid rgba(15, 23, 42, 0.08);
        background: rgba(255, 255, 255, 0.45);
        color: var(--dashboard-text-muted);
        border-radius: 999px;
        padding: 0.48rem 0.8rem;
        font-size: 0.82rem;
        font-weight: 800;
        text-decoration: none;
        transition: 0.2s ease;
    }

    .category-chip:hover {
        background: rgba(245, 197, 24, 0.16);
        border-color: rgba(245, 197, 24, 0.35);
        color: var(--black);
    }

    .services-category {
        margin-bottom: 2.8rem;
    }

    .services-category-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .services-category-title {
        font-size: 0.88rem;
        font-weight: 900;
        color: var(--dashboard-text-main);
        text-transform: uppercase;
        letter-spacing: 0.55px;
        margin-bottom: 0;
    }

    .services-category-count {
        color: var(--dashboard-text-muted);
        font-size: 0.82rem;
        font-weight: 700;
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
        padding: 1.3rem 1.05rem 1.2rem;
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 18px;
        background: transparent;
        transition: 0.2s ease;
    }

    .service-card:hover {
        transform: translateY(-3px);
        border-color: rgba(245, 197, 24, 0.52);
        background: rgba(255, 255, 255, 0.12);
    }

    .service-image-wrap {
        width: 100%;
        min-height: 210px;
        background: transparent;
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0.6rem 0.5rem 0.9rem;
        margin-bottom: 0.85rem;
        overflow: visible;
    }

    .service-image-wrap img {
        max-width: 100%;
        max-height: 175px;
        object-fit: contain;
        filter: drop-shadow(0 18px 18px rgba(17, 24, 39, 0.10));
        transition: 0.2s ease;
    }

    .service-card:hover .service-image-wrap img {
        transform: scale(1.035);
        filter: drop-shadow(0 22px 20px rgba(17, 24, 39, 0.13));
    }

    .service-info {
        width: 100%;
        min-width: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    .service-name {
        color: var(--dashboard-text-main);
        font-size: 1.03rem;
        font-weight: 900;
        margin-bottom: 0.35rem;
        line-height: 1.3;
        max-width: 100%;
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }

    .service-category-label {
        color: var(--dashboard-primary);
        font-size: 0.72rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        margin-bottom: 0.55rem;
        max-width: 100%;
    }

    .service-desc {
        color: var(--dashboard-text-muted);
        font-size: 0.82rem;
        font-weight: 600;
        line-height: 1.5;
        min-height: 3.6rem;
        margin-bottom: 0.85rem;
        max-width: 95%;
    }

    .service-price {
        color: #047857;
        font-size: 0.95rem;
        font-weight: 900;
        margin-bottom: 0.15rem;
    }

    .service-price-note {
        color: var(--dashboard-text-muted);
        font-size: 0.7rem;
        font-weight: 700;
        margin-bottom: 0.85rem;
    }

    .service-details-link {
        border: 1px solid rgba(15, 23, 42, 0.12);
        background: rgba(255, 255, 255, 0.42);
        color: var(--dashboard-text-main);
        border-radius: 999px;
        padding: 0.5rem 0.95rem;
        font-size: 0.78rem;
        font-weight: 900;
        text-decoration: none;
        white-space: nowrap;
        transition: 0.2s ease;
    }

    .service-details-link:hover {
        background: var(--dashboard-primary);
        border-color: var(--dashboard-primary);
        color: var(--black);
    }

    .empty-state {
        background: rgba(255, 255, 255, 0.34);
        border: 1px solid rgba(15, 23, 42, 0.05);
        border-radius: 16px;
        padding: 3rem 1rem;
        text-align: center;
        color: var(--dashboard-text-muted);
    }

    .empty-state i {
        display: block;
        font-size: 2rem;
        color: var(--dashboard-primary);
        margin-bottom: 0.65rem;
    }

    .no-results {
        display: none;
        margin-bottom: 1.5rem;
    }

    .services-alert {
        border-radius: 16px;
        font-size: 0.92rem;
    }

    @media (max-width: 1199.98px) {
        .services-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 767.98px) {
        .services-header {
            flex-direction: column;
            align-items: stretch;
        }

        .request-pill-btn {
            width: 100%;
            justify-content: center;
        }

        .services-header h2 {
            font-size: 1.75rem;
        }

        .services-toolbar {
            grid-template-columns: 1fr;
        }

        .services-clear-btn {
            width: 100%;
        }

        .services-grid {
            grid-template-columns: 1fr;
        }

        .service-image-wrap {
            min-height: 195px;
        }

        .service-image-wrap img {
            max-height: 155px;
        }
    }
</style>
</head>

<body>

<div class="app-wrapper">
<?php include '../includes/sidebar.php'; ?>

<main class="main-content">
    <div class="customer-services-page">

        <div class="services-header">
            <div>
                <h2>Services Offered</h2>
                <p>Browse available repair services before submitting a service request.</p>
            </div>

            <a href="customer_service_requests.php" class="request-pill-btn">
                <i class="bi bi-calendar-check"></i>
                My Requests
            </a>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show services-alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show services-alert">
                <i class="bi bi-exclamation-circle-fill me-2"></i>
                <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="services-toolbar">
            <div>
                <label class="services-filter-label" for="servicesSearch">Search</label>
                <div class="services-search-bar">
                    <i class="bi bi-search"></i>
                    <input
                        type="text"
                        id="servicesSearch"
                        placeholder="Search by service name, category, description, or features..."
                    >
                </div>
            </div>

            <button type="button" id="clearSearch" class="services-clear-btn">
                Clear
            </button>
        </div>

        <?php if (!$dbError && !empty($serviceCategories)): ?>
            <div class="category-nav">
                <?php foreach ($serviceCategories as $category => $services): ?>
                    <a href="#cat-<?php echo htmlspecialchars(md5($category)); ?>" class="category-chip">
                        <?php echo displayText($category); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="empty-state no-results" id="noResults">
            <i class="bi bi-search"></i>
            <div class="fw-bold mb-1">No matching services found</div>
            <div>Try another keyword or browse the categories below.</div>
        </div>

        <?php if ($dbError): ?>
            <div class="empty-state">
                <i class="bi bi-exclamation-circle"></i>
                <div class="fw-bold mb-1">Services unavailable</div>
                <div><?php echo htmlspecialchars($dbError); ?></div>
            </div>

        <?php elseif (empty($serviceCategories)): ?>
            <div class="empty-state">
                <i class="bi bi-wrench-adjustable-circle"></i>
                <div class="fw-bold mb-1">No services available</div>
                <div>No services are currently available for online request.</div>
            </div>

        <?php else: ?>
            <?php foreach ($serviceCategories as $category => $services): ?>
                <section class="services-category" id="cat-<?php echo htmlspecialchars(md5($category)); ?>">
                    <div class="services-category-header">
                        <h5 class="services-category-title">
                            <?php echo displayText($category); ?>
                        </h5>

                        <span class="services-category-count">
                            <?php echo count($services); ?> service<?php echo count($services) !== 1 ? 's' : ''; ?>
                        </span>
                    </div>

                    <div class="services-grid">
                        <?php foreach ($services as $service): ?>
                            <?php
                                $searchText = strtolower(
                                    html_entity_decode(($service['service_name'] ?? '') . ' ' .
                                    ($service['category'] ?? '') . ' ' .
                                    ($service['description'] ?? '') . ' ' .
                                    ($service['full_description'] ?? '') . ' ' .
                                    ($service['features'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')
                                );

                                $basePrice = floatval($service['base_price'] ?? 0);
                            ?>

                            <div
                                class="service-card"
                                data-search="<?php echo htmlspecialchars($searchText); ?>"
                            >
                                <div class="service-image-wrap">
                                    <img
                                        src="<?php echo serviceImagePath($service['image'] ?? ''); ?>"
                                        alt="<?php echo displayText($service['service_name'] ?? 'Service image'); ?>"
                                    >
                                </div>

                                <div class="service-info">
                                    <div class="service-name" title="<?php echo displayText($service['service_name'] ?? ''); ?>">
                                        <?php echo displayText($service['service_name'] ?? ''); ?>
                                    </div>

                                    <div class="service-category-label" title="<?php echo displayText($service['category'] ?? ''); ?>">
                                        <?php echo displayText($service['category'] ?? ''); ?>
                                    </div>

                                    <div class="service-desc">
                                        <?php echo shortText($service['description'] ?? ''); ?>
                                    </div>

                                    <?php if ($basePrice > 0): ?>
                                        <div class="service-price">
                                            Starts at ₱<?php echo number_format($basePrice, 2); ?>
                                        </div>
                                        <div class="service-price-note">
                                            Final cost depends on assessment.
                                        </div>
                                    <?php else: ?>
                                        <div class="service-price">
                                            Cost after assessment
                                        </div>
                                        <div class="service-price-note">
                                            Final price will be confirmed by the shop.
                                        </div>
                                    <?php endif; ?>

                                    <a
                                        href="customer_service_details.php?id=<?php echo intval($service['service_id']); ?>"
                                        class="service-details-link"
                                    >
                                        View Details
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        <?php endif; ?>

    </div>
</main>
</div>

<script>
const searchInput = document.getElementById('servicesSearch');
const clearSearch = document.getElementById('clearSearch');
const cards = document.querySelectorAll('.service-card');
const noResults = document.getElementById('noResults');
const categories = document.querySelectorAll('.services-category');

function applyServicesSearch() {
    const value = searchInput.value.trim().toLowerCase();
    let visibleCount = 0;

    cards.forEach(card => {
        const text = card.dataset.search || '';

        if (text.includes(value)) {
            card.style.display = '';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });

    categories.forEach(category => {
        const visibleCards = category.querySelectorAll('.service-card:not([style*="display: none"])');
        category.style.display = visibleCards.length > 0 ? '' : 'none';
    });

    if (noResults) {
        noResults.style.display = visibleCount === 0 && cards.length > 0 ? 'block' : 'none';
    }
}

if (searchInput) {
    searchInput.addEventListener('input', applyServicesSearch);
}

if (clearSearch) {
    clearSearch.addEventListener('click', function () {
        searchInput.value = '';
        applyServicesSearch();
    });
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>