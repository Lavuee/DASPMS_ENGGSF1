<?php
session_start();

$basePath = '../';

require_once '../config/Database.php';

$database = new Database();
$db = $database->getConnection();

$partCategories = [];
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
        SELECT part_id, category, part_name, description, image
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Parts | Norily's Vehicle Repair Shop</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/style.css">

<style>
    .parts-page {
        background: #f4f5f7;
        min-height: 100vh;
    }

    .parts-hero {
        padding: 3rem 0 1.2rem;
    }

    .parts-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        flex-wrap: wrap;
        padding-bottom: 1.1rem;
        border-bottom: 1px solid rgba(17, 24, 39, 0.10);
    }

    .parts-kicker {
        color: #f0b400;
        font-size: 0.78rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.9px;
        margin-bottom: 0.45rem;
    }

    .parts-title {
        color: #111827;
        font-size: clamp(1.8rem, 3vw, 2.35rem);
        font-weight: 900;
        line-height: 1.05;
        letter-spacing: -0.7px;
        margin-bottom: 0.45rem;
    }

    .parts-subtext {
        color: #5f6b7a;
        font-size: 0.98rem;
        line-height: 1.6;
        max-width: 640px;
        margin-bottom: 0;
    }

    .parts-login-btn {
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

    .parts-login-btn:hover {
        background: #111827;
        border-color: #111827;
        color: #fff;
    }

    .parts-catalog {
        padding: 1rem 0 4.2rem;
    }

    .parts-toolbar {
        display: grid;
        grid-template-columns: minmax(260px, 1fr) 150px;
        gap: 0.8rem;
        align-items: end;
        margin-bottom: 1.05rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid rgba(17, 24, 39, 0.08);
        position: relative;
    }

    .parts-filter-label {
        display: block;
        color: #6b7280;
        font-size: 0.76rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.35px;
        margin-bottom: 0.35rem;
    }

    .parts-search-wrap {
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

    .parts-search-wrap:focus-within {
        background: rgba(255, 255, 255, 0.88);
        border-color: rgba(245, 197, 24, 0.72);
        box-shadow: 0 0 0 4px rgba(245, 197, 24, 0.12);
    }

    .parts-search-wrap i {
        color: #5f6b7a;
        font-size: 1.05rem;
        flex-shrink: 0;
    }

    .parts-search {
        width: 100%;
        border: none;
        outline: none;
        background: transparent;
        color: #111827;
        font-size: 0.92rem;
        font-weight: 500;
    }

    .parts-search::placeholder {
        color: #6b7280;
    }

    .parts-clear-btn {
        min-height: 44px;
        border-radius: 999px;
        border: 1px solid rgba(17, 24, 39, 0.10);
        background: rgba(255, 255, 255, 0.50);
        color: #111827;
        font-size: 0.88rem;
        font-weight: 900;
        transition: 0.2s ease;
    }

    .parts-clear-btn:hover {
        background: #f5c518;
        border-color: #f5c518;
        color: #111827;
    }

    .parts-suggestions {
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

    .parts-suggestion-item {
        padding: 0.72rem 0.9rem;
        cursor: pointer;
        color: #111827;
        font-size: 0.84rem;
        font-weight: 800;
        border-bottom: 1px solid rgba(17, 24, 39, 0.06);
    }

    .parts-suggestion-item:last-child {
        border-bottom: 0;
    }

    .parts-suggestion-item:hover {
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
        color: #111827;
        font-size: 0.88rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.55px;
        margin-bottom: 0;
    }

    .parts-category-count {
        color: #6b7280;
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
        border: 1px solid rgba(17, 24, 39, 0.10);
        border-radius: 18px;
        background: transparent;
        transition: 0.2s ease;
    }

    .part-card:hover {
        transform: translateY(-3px);
        border-color: rgba(245, 197, 24, 0.50);
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
        filter: drop-shadow(0 18px 18px rgba(17, 24, 39, 0.10));
        transition: 0.2s ease;
    }

    .part-card:hover .part-image-wrap img {
        transform: scale(1.035);
        filter: drop-shadow(0 22px 20px rgba(17, 24, 39, 0.13));
    }

    .part-name {
        width: 100%;
        color: #111827;
        font-size: 1rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.35px;
        line-height: 1.28;
        margin-bottom: 0.35rem;
    }

    .part-category-label {
        color: #f0b400;
        font-size: 0.72rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        margin-bottom: 0.65rem;
    }

    .part-desc {
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

    .part-details-btn {
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

    .part-details-btn:hover {
        background: #f5c518;
        border-color: #f5c518;
        color: #111827;
    }

    .parts-no-results,
    .parts-empty-state {
        border-top: 1px solid rgba(17, 24, 39, 0.10);
        border-bottom: 1px solid rgba(17, 24, 39, 0.10);
        padding: 3rem 1rem;
        text-align: center;
        color: #5f6b7a;
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
        .parts-hero {
            padding: 2.3rem 0 1rem;
        }

        .parts-header {
            flex-direction: column;
            align-items: stretch;
        }

        .parts-login-btn {
            width: 100%;
            justify-content: center;
        }

        .parts-toolbar {
            grid-template-columns: 1fr;
        }

        .parts-clear-btn {
            width: 100%;
        }

        .parts-suggestions {
            right: 0;
            top: calc(100% - 4.05rem);
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
        .parts-title {
            font-size: 1.55rem;
        }

        .parts-subtext {
            font-size: 0.9rem;
        }
    }
</style>
</head>

<body>

<?php include '../partials/public-navbar.php'; ?>

<main class="parts-page">
    <section class="parts-hero">
        <div class="container">
            <div class="parts-header">
                <div>
                    <div class="parts-kicker">Auto Parts</div>

                    <h1 class="parts-title">
                        Available Parts and Repair Supplies
                    </h1>

                    <p class="parts-subtext">
                        Browse selected auto parts, electrical supplies, and repair materials
                        available from Norily's Vehicle Repair Shop.
                    </p>
                </div>

                <a href="../views/login.php" class="parts-login-btn">
                    <i class="bi bi-box-arrow-in-right"></i>
                    Login to Reserve
                </a>
            </div>
        </div>
    </section>

    <section class="parts-catalog">
        <div class="container">
            <div class="parts-toolbar">
                <div>
                    <label class="parts-filter-label" for="partsSearch">Search</label>

                    <div class="parts-search-wrap">
                        <i class="bi bi-search"></i>
                        <input
                            type="text"
                            class="parts-search"
                            id="partsSearch"
                            placeholder="Search parts by name, category, or description..."
                            autocomplete="off"
                        >
                    </div>
                </div>

                <button type="button" class="parts-clear-btn" id="partsSearchBtn">
                    Clear
                </button>

                <div class="parts-suggestions" id="partsSuggestions"></div>
            </div>

            <?php if (!$dbError && !empty($partCategories)): ?>
                <div class="category-nav">
                    <?php foreach ($partCategories as $category => $parts): ?>
                        <a href="#cat-<?= htmlspecialchars(md5($category), ENT_QUOTES, 'UTF-8') ?>" class="category-chip">
                            <?= displayText($category) ?>
                            <span>(<?= count($parts) ?>)</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <p class="parts-no-results" id="partsNoResults">
                No matching parts found. Try searching for another part name, category, or type.
            </p>

            <?php if ($dbError): ?>
                <p class="parts-empty-state"><?= htmlspecialchars($dbError, ENT_QUOTES, 'UTF-8') ?></p>
            <?php elseif (empty($partCategories)): ?>
                <p class="parts-empty-state">No parts are currently available.</p>
            <?php else: ?>
                <?php foreach ($partCategories as $category => $parts): ?>
                    <section class="parts-category" id="cat-<?= htmlspecialchars(md5($category), ENT_QUOTES, 'UTF-8') ?>">
                        <div class="parts-category-header">
                            <h2 class="parts-category-title">
                                <?= displayText($category) ?>
                            </h2>

                            <span class="parts-category-count">
                                <?= count($parts) ?> item<?= count($parts) !== 1 ? 's' : '' ?>
                            </span>
                        </div>

                        <div class="parts-grid">
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
const partsSearchBtn = document.getElementById('partsSearchBtn');
const partsSuggestions = document.getElementById('partsSuggestions');
const partCards = document.querySelectorAll('.part-card');
const partCategories = document.querySelectorAll('.parts-category');
const partsNoResults = document.getElementById('partsNoResults');

const partNames = Array.from(partCards).map(function (card) {
    return card.dataset.partName || '';
});

function formatPartName(name) {
    return name.replace(/\b\w/g, function (char) {
        return char.toUpperCase();
    });
}

function filterParts(searchValue) {
    const value = searchValue.toLowerCase().trim();
    let totalVisibleCards = 0;

    partCards.forEach(function (card) {
        const name = card.dataset.partName || '';
        const category = card.dataset.partCategory || '';
        const type = card.dataset.partType || '';
        const description = card.dataset.partDescription || '';

        const isMatch =
            value === '' ||
            name.includes(value) ||
            category.includes(value) ||
            type.includes(value) ||
            description.includes(value);

        card.style.display = isMatch ? 'flex' : 'none';

        if (isMatch) {
            totalVisibleCards++;
        }
    });

    partCategories.forEach(function (categoryBlock) {
        const visibleCards = categoryBlock.querySelectorAll('.part-card:not([style*="display: none"])');
        categoryBlock.style.display = visibleCards.length > 0 ? 'block' : 'none';
    });

    if (partsNoResults) {
        partsNoResults.style.display = totalVisibleCards === 0 && partCards.length > 0 ? 'block' : 'none';
    }
}

function showSuggestions(searchValue) {
    const value = searchValue.toLowerCase().trim();
    partsSuggestions.innerHTML = '';

    if (value === '') {
        partsSuggestions.style.display = 'none';
        return;
    }

    const matches = partNames
        .filter(function (name) {
            return name.includes(value);
        })
        .slice(0, 5);

    if (matches.length === 0) {
        partsSuggestions.style.display = 'none';
        return;
    }

    matches.forEach(function (name) {
        const item = document.createElement('div');
        item.className = 'parts-suggestion-item';
        item.textContent = formatPartName(name);

        item.addEventListener('click', function () {
            partsSearch.value = item.textContent;
            partsSuggestions.style.display = 'none';
            filterParts(partsSearch.value);
        });

        partsSuggestions.appendChild(item);
    });

    partsSuggestions.style.display = 'block';
}

if (partsSearch) {
    partsSearch.addEventListener('input', function () {
        showSuggestions(this.value);
        filterParts(this.value);
    });
}

if (partsSearchBtn) {
    partsSearchBtn.addEventListener('click', function () {
        partsSearch.value = '';
        partsSuggestions.style.display = 'none';
        filterParts('');
        partsSearch.focus();
    });
}

document.addEventListener('click', function (event) {
    if (!event.target.closest('.parts-toolbar')) {
        partsSuggestions.style.display = 'none';
    }
});
</script>

</body>
</html>