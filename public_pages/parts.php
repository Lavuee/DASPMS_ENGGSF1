<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$basePath = '../';

require_once '../config/Database.php';

$database = new Database();
$db = $database->getConnection();

$partCategories = [];
$dbError = '';

try {
    $query = "
        SELECT part_id, category, brand, part_name, description, image
        FROM part
        WHERE is_active = 1
        ORDER BY category ASC, part_name ASC
    ";

    $stmt = $db->prepare($query);
    $stmt->execute();
    $parts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($parts as $part) {
        $categoryKey = plainText($part['category'] ?? '', 'Uncategorized');
        $partCategories[$categoryKey][] = $part;
    }
} catch (Exception $e) {
    $dbError = 'Unable to load parts at the moment.';
}

function partImagePath($image)
{
    return '../assets/images/parts/' . htmlspecialchars($image ?: 'default.png', ENT_QUOTES, 'UTF-8');
}

function categoryKey($category)
{
    return md5(strtolower($category));
}

// The reserve button sends visitors to log in, customers to the reservation page, and staff to their dashboard.
if (isLoggedIn()) {
    $isCustomer = ($_SESSION['role'] ?? '') === 'Customer';
    $reserveUrl = '../views/' . ($isCustomer ? 'customer_parts.php' : dashboardForRole($_SESSION['role'] ?? ''));
    $reserveLabel = $isCustomer ? 'Reserve parts' : 'Go to dashboard';
} else {
    $reserveUrl = '../views/login.php';
    $reserveLabel = 'Log in to reserve';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Parts | Norily's Vehicle Repair Shop</title>
<link rel="icon" type="image/png" href="../assets/images/logo.png">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
<script>document.documentElement.classList.add('js');window.__motionFallback=setTimeout(function(){document.documentElement.classList.add('reveal-all');},3000);</script>
<script src="../assets/js/motion.js?v=<?= filemtime(__DIR__ . '/../assets/js/motion.js') ?>" defer></script>

<style>
    .parts-page {
        background: #eeeae2;
        min-height: 100vh;
    }

    .parts-hero {
        padding: 1.5rem 0 0;
    }

    .parts-breadcrumb {
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

    .parts-breadcrumb a {
        color: var(--ink-2);
        text-decoration: none;
        transition: color 0.2s ease;
    }

    .parts-breadcrumb a:hover {
        color: var(--ink);
    }

    .parts-breadcrumb li + li::before {
        content: "/";
        margin-right: 0.6rem;
    }

    .parts-title {
        color: var(--ink);
        font-size: var(--fs-page-title);
        font-weight: 400;
        font-stretch: 125%;
        text-transform: uppercase;
        line-height: 1;
        margin-bottom: 2.5rem;
    }


    .parts-subtext {
        color: var(--ink-2);
        font-size: var(--fs-lead);
        line-height: 1.75;
        max-width: 560px;
        margin-bottom: 1.75rem;
    }

    .parts-login-btn {
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

    .parts-login-btn:hover {
        background: var(--olive);
        border-color: var(--olive);
        color: var(--sheet);
    }

    .parts-catalog {
        padding: 0 0 4.2rem;
    }

    .parts-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1.5rem;
        flex-wrap: wrap;
        margin: 2.25rem 0 2.5rem;
        padding-top: 1.75rem;
        border-top: 1px solid var(--ink);
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

    /* Active filter: hand-drawn style oval around the label */
    .category-chip.active {
        color: var(--ink);
        border-color: var(--ink);
    }

    .parts-search-box {
        position: relative;
        flex: 0 1 450px;
    }

    .parts-search-wrap {
        display: flex;
        align-items: center;
        gap: 1rem;
        min-height: 60px;
        padding: 0 1.1rem;
        background: var(--sheet);
        border: 1px solid var(--ink);
        transition: box-shadow 0.2s ease;
    }

    .parts-search-wrap:focus-within {
        box-shadow: 0 0 0 3px rgba(94, 107, 52, 0.2);
    }

    .parts-search-label {
        color: var(--ink-2);
        font-size: 0.8rem;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        margin: 0;
        flex-shrink: 0;
    }

    .parts-search {
        width: 100%;
        border: none;
        outline: none;
        background: transparent;
        color: var(--ink);
        font-size: 1.05rem;
    }

    .parts-search::placeholder {
        color: var(--ink-3);
    }

    .parts-clear-btn {
        display: none;
        background: transparent;
        border: 0;
        color: var(--ink-2);
        font-size: 1.4rem;
        line-height: 1;
        padding: 0 0.1rem;
        cursor: pointer;
    }

    .parts-clear-btn.visible {
        display: block;
    }

    .parts-suggestions {
        display: none;
        position: absolute;
        left: 0;
        right: 0;
        top: calc(100% + 4px);
        background: var(--sheet);
        border: 1px solid var(--ink);
        z-index: 20;
    }

    .parts-suggestion-item {
        padding: 0.75rem 1.1rem;
        cursor: pointer;
        color: var(--ink);
        font-size: 0.95rem;
        border-bottom: 1px solid var(--line);
    }

    .parts-suggestion-item:last-child {
        border-bottom: 0;
    }

    .parts-suggestion-item:hover {
        background: var(--tile);
    }


    .parts-category {
        margin-bottom: 2.5rem;
    }

    .parts-category:last-child {
        margin-bottom: 0;
    }

    .parts-category-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .parts-category-title {
        color: #26241f;
        font-size: 0.88rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.55px;
        margin-bottom: 0;
    }

    .parts-category-count {
        color: #5e5a51;
        font-size: 0.82rem;
        font-weight: 800;
    }

    .parts-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 1.45rem;
    }

    .part-card {
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

    .part-card:hover {
        transform: translateY(-3px);
        border-color: rgba(94, 107, 52, 0.50);
        background: rgba(255, 255, 255, 0.12);
    }

    .part-link {
        color: inherit;
        text-decoration: none;
    }

    .part-image-wrap {
        width: 100%;
        min-height: 205px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0.6rem 0.5rem 0.9rem;
        margin-bottom: 0.75rem;
        overflow: visible;
    }

    .part-image-wrap img {
        max-width: 100%;
        max-height: 170px;
        object-fit: contain;
        display: block;
        filter: drop-shadow(0 18px 18px rgba(38, 36, 31, 0.10));
        transition: 0.2s ease;
    }

    .part-card:hover .part-image-wrap img {
        transform: scale(1.035);
        filter: drop-shadow(0 22px 20px rgba(38, 36, 31, 0.13));
    }

    .part-name {
        width: 100%;
        color: #26241f;
        font-size: 1rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.35px;
        line-height: 1.28;
        margin-bottom: 0.35rem;
    }

    .part-category-label {
        color: #5e6b34;
        font-size: 0.72rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        margin-bottom: 0.65rem;
    }

    .part-desc {
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

    .part-details-btn {
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

    .part-details-btn:hover {
        background: #5e6b34;
        border-color: #5e6b34;
        color: #f8f6f1;
    }

    .parts-no-results,
    .parts-empty-state {
        border-top: 1px solid rgba(38, 36, 31, 0.10);
        border-bottom: 1px solid rgba(38, 36, 31, 0.10);
        padding: 3rem 1rem;
        text-align: center;
        color: #5e5a51;
        background: transparent;
        margin-top: 1rem;
        font-size: 0.92rem;
    }

    .parts-no-results {
        display: none;
    }

    .parts-empty-state {
        display: block;
    }

    @media (max-width: 991.98px) {
        .parts-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 767.98px) {
        .parts-breadcrumb {
            margin-bottom: 2.25rem;
        }

        .parts-login-btn {
            width: 100%;
        }

        .parts-search-box {
            flex-basis: 100%;
        }

        .parts-grid {
            grid-template-columns: 1fr;
        }

        .part-image-wrap {
            min-height: 190px;
        }

        .part-image-wrap img {
            max-height: 155px;
        }
    }

    @media (max-width: 575.98px) {
        .parts-subtext {
            font-size: var(--fs-lead);
        }

        .category-chip {
            font-size: 0.95rem;
        }
    }
</style>
</head>

<body>

<?php $activePage = 'parts'; include '../partials/public-navbar.php'; ?>

<main class="parts-page">
    <section class="parts-hero">
        <div class="container">
            <nav aria-label="Breadcrumb">
                <ol class="parts-breadcrumb">
                    <li><a href="../" class="link-grow">Home</a></li>
                    <li aria-current="page">Parts</li>
                </ol>
            </nav>

            <h1 class="parts-title">
                Auto <span class="title-band">Parts</span>
            </h1>

            <p class="parts-subtext">
                Electrical supplies and repair parts in stock at the shop.
                Reserve online and pick up in Vila Rosario; staff confirm
                stock before release.
            </p>

            <a href="<?= htmlspecialchars($reserveUrl, ENT_QUOTES, 'UTF-8') ?>" class="parts-login-btn">
                <?= htmlspecialchars($reserveLabel, ENT_QUOTES, 'UTF-8') ?>
            </a>
        </div>
    </section>

    <section class="parts-catalog">
        <div class="container">
            <div class="parts-toolbar">
                <div class="category-nav" role="group" aria-label="Filter by category">
                    <button type="button" class="category-chip active" data-category="all" aria-pressed="true">All parts</button>

                    <?php if (!$dbError): ?>
                        <?php foreach (array_keys($partCategories) as $category): ?>
                            <button
                                type="button"
                                class="category-chip"
                                data-category="<?= categoryKey($category) ?>"
                                aria-pressed="false"
                            ><?= htmlspecialchars(partCategoryLabel($category), ENT_QUOTES, 'UTF-8') ?></button>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="parts-search-box">
                    <div class="parts-search-wrap">
                        <label class="parts-search-label" for="partsSearch">Search</label>

                        <input
                            type="text"
                            class="parts-search"
                            id="partsSearch"
                            placeholder="Part name, brand or category"
                            autocomplete="off"
                        >

                        <button type="button" class="parts-clear-btn" id="partsClearBtn" aria-label="Clear search">&times;</button>
                    </div>

                    <div class="parts-suggestions" id="partsSuggestions"></div>
                </div>
            </div>


            <p class="parts-no-results" id="partsNoResults">
                No matching parts found. Try searching for another part name, category, or type.
            </p>

            <?php if ($dbError): ?>
                <p class="parts-empty-state"><?= htmlspecialchars($dbError, ENT_QUOTES, 'UTF-8') ?></p>
            <?php elseif (empty($partCategories)): ?>
                <p class="parts-empty-state">No parts are currently available.</p>
            <?php else: ?>
                <?php foreach ($partCategories as $category => $parts): ?>
                    <section class="parts-category" data-category="<?= categoryKey($category) ?>">
                        <div class="parts-category-header">
                            <h2 class="parts-category-title">
                                <?= displayText($category) ?>
                            </h2>

                            <span class="parts-category-count">
                                <?= count($parts) ?> item<?= count($parts) !== 1 ? 's' : '' ?>
                            </span>
                        </div>

                        <div class="parts-grid" data-reveal-group>
                            <?php foreach ($parts as $part): ?>
                                <?php
                                    $detailsUrl = 'part-details.php?id=' . urlencode($part['part_id']);
                                    $partNamePlain = plainText($part['part_name'] ?? '');
                                    $categoryPlain = plainText($part['category'] ?? '');
                                    $descriptionPlain = plainText($part['description'] ?? '');
                                ?>

                                <article
                                    class="part-card"
                                    data-part-name="<?= htmlspecialchars(strtolower($partNamePlain), ENT_QUOTES, 'UTF-8') ?>"
                                    data-part-category="<?= htmlspecialchars(strtolower($categoryPlain), ENT_QUOTES, 'UTF-8') ?>"
                                    data-part-type="<?= htmlspecialchars(strtolower($categoryPlain), ENT_QUOTES, 'UTF-8') ?>"
                                    data-part-description="<?= htmlspecialchars(strtolower($descriptionPlain), ENT_QUOTES, 'UTF-8') ?>"
                                    data-part-brand="<?= htmlspecialchars(strtolower(plainText($part['brand'] ?? '')), ENT_QUOTES, 'UTF-8') ?>"
                                >
                                    <a href="<?= htmlspecialchars($detailsUrl, ENT_QUOTES, 'UTF-8') ?>" class="part-link part-image-wrap">
                                        <img
                                            src="<?= partImagePath($part['image'] ?? '') ?>"
                                            alt="<?= displayText($part['part_name'] ?? 'Part image') ?>"
                                        >
                                    </a>

                                    <a href="<?= htmlspecialchars($detailsUrl, ENT_QUOTES, 'UTF-8') ?>" class="part-link">
                                        <h3 class="part-name">
                                            <?= displayText($part['part_name'] ?? '') ?>
                                        </h3>
                                    </a>

                                    <div class="part-category-label">
                                        <?= displayText($part['category'] ?? '') ?>
                                    </div>

                                    <p class="part-desc">
                                        <?= displayText($part['description'] ?? '', 'Part details are available upon shop confirmation.') ?>
                                    </p>

                                    <a href="<?= htmlspecialchars($detailsUrl, ENT_QUOTES, 'UTF-8') ?>" class="part-details-btn">
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
const partsSearch = document.getElementById('partsSearch');
const partsClearBtn = document.getElementById('partsClearBtn');
const partsSuggestions = document.getElementById('partsSuggestions');
const categoryChips = document.querySelectorAll('.category-chip');
const partCards = document.querySelectorAll('.part-card');
const partCategories = document.querySelectorAll('.parts-category');
const partsNoResults = document.getElementById('partsNoResults');

let activeCategory = 'all';

const partNames = Array.from(partCards).map(function (card) {
    return card.dataset.partName || '';
});

function formatPartName(name) {
    return name.replace(/\b\w/g, function (char) {
        return char.toUpperCase();
    });
}

// Applies the category filter and the search text together.
function filterParts() {
    const value = partsSearch.value.toLowerCase().trim();
    let totalVisibleCards = 0;

    partCategories.forEach(function (categoryBlock) {
        const inCategory = activeCategory === 'all' || categoryBlock.dataset.category === activeCategory;
        let visibleInBlock = 0;

        categoryBlock.querySelectorAll('.part-card').forEach(function (card) {
            const searchable = [
                card.dataset.partName,
                card.dataset.partCategory,
                card.dataset.partBrand,
                card.dataset.partDescription
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

    if (partsNoResults) {
        partsNoResults.style.display = totalVisibleCards === 0 && partCards.length > 0 ? 'block' : 'none';
    }

    partsClearBtn.classList.toggle('visible', value !== '');
}

function showSuggestions() {
    const value = partsSearch.value.toLowerCase().trim();
    partsSuggestions.innerHTML = '';

    const matches = value === ''
        ? []
        : partNames.filter(function (name) {
            return name.includes(value);
        }).slice(0, 5);

    matches.forEach(function (name) {
        const item = document.createElement('div');
        item.className = 'parts-suggestion-item';
        item.textContent = formatPartName(name);

        item.addEventListener('click', function () {
            partsSearch.value = item.textContent;
            partsSuggestions.style.display = 'none';
            filterParts();
        });

        partsSuggestions.appendChild(item);
    });

    partsSuggestions.style.display = matches.length > 0 ? 'block' : 'none';
}

categoryChips.forEach(function (chip) {
    chip.addEventListener('click', function () {
        activeCategory = chip.dataset.category;

        categoryChips.forEach(function (other) {
            const isActive = other === chip;
            other.classList.toggle('active', isActive);
            other.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });

        filterParts();
    });
});

partsSearch.addEventListener('input', function () {
    showSuggestions();
    filterParts();
});

partsSearch.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        partsSuggestions.style.display = 'none';
    }
});

partsClearBtn.addEventListener('click', function () {
    partsSearch.value = '';
    partsSuggestions.style.display = 'none';
    filterParts();
    partsSearch.focus();
});

document.addEventListener('click', function (event) {
    if (!event.target.closest('.parts-search-box')) {
        partsSuggestions.style.display = 'none';
    }
});
</script>

</body>
</html>