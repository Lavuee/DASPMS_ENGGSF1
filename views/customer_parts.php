<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Customer') {
    header("Location: login.php");
    exit;
}

require_once '../config/Database.php';

$db = (new Database())->getConnection();

$partCategories = [];
$dbError = '';

try {
    $query = "
        SELECT
            part_id,
            category,
            part_name,
            description,
            image,
            unit_price,
            quantity_on_hand
        FROM part
        WHERE is_active = 1
        AND quantity_on_hand > 0
        ORDER BY category ASC, part_name ASC
    ";

    $stmt = $db->prepare($query);
    $stmt->execute();
    $parts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($parts as $part) {
        $partCategories[$part['category']][] = $part;
    }
} catch (Exception $e) {
    $dbError = 'Unable to load parts at the moment.';
}

function partImagePath($image)
{
    $fallback = 'default.png';

    if (!$image) {
        return '../assets/images/parts/' . $fallback;
    }

    return '../assets/images/parts/' . htmlspecialchars($image);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Browse Parts - Norily's Repair Shop</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/style.css">

<style>
    .main-content {
        overflow-x: hidden;
    }

    .customer-parts-page {
        width: 100%;
        max-width: 100%;
    }

    .parts-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 1.35rem;
    }

    .parts-header h2 {
        font-size: 2rem;
        font-weight: 900;
        color: var(--dashboard-text-main);
        margin-bottom: 0.25rem;
        line-height: 1.1;
    }

    .parts-header p {
        color: var(--dashboard-text-muted);
        font-size: 0.95rem;
        font-weight: 500;
        margin-bottom: 0;
    }

    .cart-pill-btn {
        height: 44px;
        border-radius: 999px;
        padding: 0.6rem 1rem;
        font-size: 0.9rem;
        font-weight: 900;
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        white-space: nowrap;
    }

    .parts-toolbar {
        display: grid;
        grid-template-columns: minmax(260px, 1fr) 160px;
        gap: 0.85rem;
        align-items: end;
        margin-bottom: 1.4rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid #e5e7eb;
    }

    .parts-filter-label {
        display: block;
        color: var(--dashboard-text-muted);
        font-size: 0.76rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.35px;
        margin-bottom: 0.35rem;
    }

    .parts-search-bar {
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

    .parts-search-bar:focus-within {
        border-color: rgba(245, 197, 24, 0.65);
        box-shadow: 0 0 0 4px rgba(245, 197, 24, 0.12);
        background: rgba(255, 255, 255, 0.90);
    }

    .parts-search-bar i {
        font-size: 1.05rem;
        color: var(--dashboard-text-muted);
        flex-shrink: 0;
    }

    .parts-search-bar input {
        width: 100%;
        border: none;
        outline: none;
        background: transparent;
        color: var(--dashboard-text-main);
        font-size: 0.92rem;
    }

    .parts-clear-btn {
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

    .parts-clear-btn:hover {
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

    .parts-category {
        margin-bottom: 2.2rem;
    }

    .parts-category-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        margin-bottom: 0.9rem;
    }

    .parts-category-title {
        font-size: 0.88rem;
        font-weight: 900;
        color: var(--dashboard-text-main);
        text-transform: uppercase;
        letter-spacing: 0.55px;
        margin-bottom: 0;
    }

    .parts-category-count {
        color: var(--dashboard-text-muted);
        font-size: 0.82rem;
        font-weight: 700;
    }

    .parts-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 1.25rem;
    }

    .part-card {
        background: transparent;
        border: none;
        border-radius: 0;
        padding: 0;
        min-width: 0;
        display: flex;
        flex-direction: column;
        transition: 0.2s ease;
    }

    .part-card:hover {
        transform: translateY(-2px);
    }

    .part-image-wrap {
        width: 100%;
        aspect-ratio: 1 / 0.88;
        background: rgba(255, 255, 255, 0.48);
        border: 1px solid rgba(15, 23, 42, 0.045);
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1.1rem;
        margin-bottom: 0.75rem;
        position: relative;
        overflow: hidden;
    }

    .part-image-wrap img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
        transition: 0.2s ease;
    }

    .part-card:hover .part-image-wrap img {
        transform: scale(1.03);
    }

    .stock-corner {
        position: absolute;
        top: 0.65rem;
        right: 0.65rem;
        background: rgba(245, 197, 24, 0.92);
        color: var(--black);
        border-radius: 999px;
        padding: 0.25rem 0.5rem;
        font-size: 0.68rem;
        font-weight: 900;
        line-height: 1;
    }

    .part-name {
        color: var(--dashboard-text-main);
        font-size: 0.92rem;
        font-weight: 900;
        margin-bottom: 0.2rem;
        line-height: 1.3;
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .part-category {
        color: var(--dashboard-text-muted);
        font-size: 0.76rem;
        font-weight: 700;
        margin-bottom: 0.45rem;
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .part-desc {
        color: var(--dashboard-text-muted);
        font-size: 0.8rem;
        line-height: 1.45;
        margin-bottom: 0.6rem;
        min-height: 2.3rem;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .part-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.8rem;
        margin-top: auto;
    }

    .part-price {
        color: #047857;
        font-size: 0.9rem;
        font-weight: 900;
        white-space: nowrap;
    }

    .part-details-link {
        border: 1px solid rgba(15, 23, 42, 0.10);
        background: rgba(255, 255, 255, 0.56);
        color: var(--dashboard-text-main);
        border-radius: 999px;
        padding: 0.42rem 0.75rem;
        font-size: 0.78rem;
        font-weight: 900;
        text-decoration: none;
        white-space: nowrap;
        transition: 0.2s ease;
    }

    .part-details-link:hover {
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

    .parts-alert {
        border-radius: 16px;
        font-size: 0.92rem;
    }

    @media (max-width: 1199.98px) {
        .parts-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 767.98px) {
        .parts-header {
            flex-direction: column;
            align-items: stretch;
        }

        .cart-pill-btn {
            width: 100%;
            justify-content: center;
        }

        .parts-header h2 {
            font-size: 1.75rem;
        }

        .parts-toolbar {
            grid-template-columns: 1fr;
        }

        .parts-clear-btn {
            width: 100%;
        }

        .parts-grid {
            grid-template-columns: 1fr;
        }

        .part-image-wrap {
            aspect-ratio: 1 / 0.72;
        }
    }
</style>
</head>

<body>

<div class="app-wrapper">
<?php include '../includes/sidebar.php'; ?>

<main class="main-content">
    <div class="customer-parts-page">

        <div class="parts-header">
            <div>
                <h2>Browse Parts</h2>
                <p>Select available parts and add them to your cart for reservation.</p>
            </div>

            <a href="customer_cart.php" class="btn btn-outline-primary cart-pill-btn">
                <i class="bi bi-cart3"></i>
                View Cart
            </a>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show parts-alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show parts-alert">
                <i class="bi bi-exclamation-circle-fill me-2"></i>
                <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="parts-toolbar">
            <div>
                <label class="parts-filter-label" for="partsSearch">Search</label>
                <div class="parts-search-bar">
                    <i class="bi bi-search"></i>
                    <input
                        type="text"
                        id="partsSearch"
                        placeholder="Search by part name, category, or description..."
                    >
                </div>
            </div>

            <button type="button" id="clearSearch" class="parts-clear-btn">
                Clear
            </button>
        </div>

        <?php if (!$dbError && !empty($partCategories)): ?>
            <div class="category-nav">
                <?php foreach ($partCategories as $category => $parts): ?>
                    <a href="#cat-<?php echo htmlspecialchars(md5($category)); ?>" class="category-chip">
                        <?php echo htmlspecialchars($category); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="empty-state no-results" id="noResults">
            <i class="bi bi-search"></i>
            <div class="fw-bold mb-1">No matching parts found</div>
            <div>Try another keyword or browse the categories below.</div>
        </div>

        <?php if ($dbError): ?>
            <div class="empty-state">
                <i class="bi bi-exclamation-circle"></i>
                <div class="fw-bold mb-1">Parts unavailable</div>
                <div><?php echo htmlspecialchars($dbError); ?></div>
            </div>

        <?php elseif (empty($partCategories)): ?>
            <div class="empty-state">
                <i class="bi bi-box-seam"></i>
                <div class="fw-bold mb-1">No parts available</div>
                <div>No parts are currently available for reservation.</div>
            </div>

        <?php else: ?>
            <?php foreach ($partCategories as $category => $parts): ?>
                <section class="parts-category" id="cat-<?php echo htmlspecialchars(md5($category)); ?>">
                    <div class="parts-category-header">
                        <h5 class="parts-category-title">
                            <?php echo htmlspecialchars($category); ?>
                        </h5>

                        <span class="parts-category-count">
                            <?php echo count($parts); ?> item<?php echo count($parts) !== 1 ? 's' : ''; ?>
                        </span>
                    </div>

                    <div class="parts-grid">
                        <?php foreach ($parts as $part): ?>
                            <div
                                class="part-card"
                                data-search="<?php echo htmlspecialchars(strtolower(
                                    $part['part_name'] . ' ' .
                                    $part['category'] . ' ' .
                                    ($part['description'] ?? '')
                                )); ?>"
                            >
                                <div class="part-image-wrap">
                                    <span class="stock-corner">
                                        <?php echo intval($part['quantity_on_hand']); ?> left
                                    </span>

                                    <img
                                        src="<?php echo partImagePath($part['image'] ?? ''); ?>"
                                        alt="<?php echo htmlspecialchars($part['part_name']); ?>"
                                    >
                                </div>

                                <div class="part-name" title="<?php echo htmlspecialchars($part['part_name']); ?>">
                                    <?php echo htmlspecialchars($part['part_name']); ?>
                                </div>

                                <div class="part-category" title="<?php echo htmlspecialchars($part['category']); ?>">
                                    <?php echo htmlspecialchars($part['category']); ?>
                                </div>

                                <div class="part-desc">
                                    <?php echo htmlspecialchars($part['description'] ?: 'No description available.'); ?>
                                </div>

                                <div class="part-footer">
                                    <span class="part-price">
                                        ₱<?php echo number_format(floatval($part['unit_price']), 2); ?>
                                    </span>

                                    <a
                                        href="customer_part_details.php?id=<?php echo intval($part['part_id']); ?>"
                                        class="part-details-link"
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
const searchInput = document.getElementById('partsSearch');
const clearSearch = document.getElementById('clearSearch');
const cards = document.querySelectorAll('.part-card');
const noResults = document.getElementById('noResults');
const categories = document.querySelectorAll('.parts-category');

function applyPartsSearch() {
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
        const visibleCards = category.querySelectorAll('.part-card:not([style*="display: none"])');
        category.style.display = visibleCards.length > 0 ? '' : 'none';
    });

    if (noResults) {
        noResults.style.display = visibleCount === 0 && cards.length > 0 ? 'block' : 'none';
    }
}

if (searchInput) {
    searchInput.addEventListener('input', applyPartsSearch);
}

if (clearSearch) {
    clearSearch.addEventListener('click', function () {
        searchInput.value = '';
        applyPartsSearch();
    });
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>