<?php
session_start();

$basePath = '../';

require_once '../config/Database.php';

$database = new Database();
$db = $database->getConnection();

$partCategories = [];
$dbError = '';

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
        $partCategories[$part['category']][] = $part;
    }
} catch (Exception $e) {
    $dbError = 'Unable to load parts at the moment.';
}

function partImagePath($image)
{
    return '../assets/images/parts/' . ($image ?: 'default.png');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parts | Norily's Vehicle Repair Shop</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .parts-page {
            background: var(--white);
        }

        .parts-hero {
            padding: 2.8rem 0 1.2rem;
            text-align: center;
        }

        .parts-hero .section-kicker {
            font-size: clamp(1.5rem, 3vw, 2.3rem);
            line-height: 1;
            letter-spacing: 3px;
            margin-bottom: 0.8rem;
        }

        .parts-title {
            color: var(--black);
            font-size: clamp(1rem, 2vw, 1.5rem);
            font-weight: 900;
            line-height: 1.1;
            letter-spacing: -0.5px;
            margin-bottom: 0.65rem;
        }

        .parts-subtext {
            color: var(--muted-gray);
            font-size: 0.88rem;
            line-height: 1.6;
            max-width: 600px;
            margin: 0 auto;
        }

        .parts-catalog {
            padding: 1.2rem 0 4rem;
        }

        .parts-tools {
            max-width: 680px;
            margin: 0 auto 3rem;
            position: relative;
        }

        .parts-search-row {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 0.65rem;
            align-items: end;
        }

        .parts-search {
            width: 100%;
            border: 0;
            border-bottom: 1px solid var(--border-light);
            padding: 0.55rem 0;
            font-size: 0.85rem;
            outline: none;
            color: var(--black);
            background: transparent;
        }

        .parts-search:focus {
            border-bottom-color: var(--primary-yellow);
        }

        .parts-search-btn {
            background: var(--black);
            color: var(--white);
            border: 2px solid var(--black);
            padding: 0.5rem 1.05rem;
            font-size: 0.75rem;
            font-weight: 900;
            text-transform: uppercase;
            transition: all 0.2s ease;
        }

        .parts-search-btn:hover {
            background: var(--primary-yellow);
            color: var(--black);
            border-color: var(--primary-yellow);
        }

        .parts-suggestions {
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

        .parts-suggestion-item {
            padding: 0.65rem 0.8rem;
            cursor: pointer;
            color: var(--black);
            font-size: 0.82rem;
            font-weight: 700;
            border-bottom: 1px solid var(--border-light);
        }

        .parts-suggestion-item:last-child {
            border-bottom: 0;
        }

        .parts-suggestion-item:hover {
            background: var(--light-gray);
        }

        .parts-category {
            margin-bottom: 3.5rem;
        }

        .parts-category:last-child {
            margin-bottom: 0;
        }

        .parts-category-title {
            color: var(--black);
            font-size: clamp(1rem, 2vw, 1.5rem);
            font-weight: 900;
            letter-spacing: 2.5px;
            text-transform: uppercase;
            text-align: center;
            margin-bottom: 2rem;
        }

        .parts-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
            align-items: start;
        }

        .part-card {
            background: var(--white);
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100%;
        }

        .part-link {
            color: inherit;
            text-decoration: none;
        }

        .part-link:hover {
            color: var(--yellow-dark);
        }

        .part-image-wrap {
            width: 100%;
            height: 165px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
        }

        .part-image-wrap img {
            max-width: 88%;
            max-height: 155px;
            object-fit: contain;
            display: block;
            transition: transform 0.2s ease;
        }

        .part-image-wrap:hover img {
            transform: scale(1.03);
        }

        .part-card h3 {
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

        .part-type {
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

        .part-details-btn {
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

        .part-details-btn:hover {
            background: var(--primary-yellow);
            color: var(--black);
            border-color: var(--primary-yellow);
        }

        .parts-no-results,
        .parts-empty-state {
            display: none;
            max-width: 680px;
            margin: 0 auto 3rem;
            text-align: center;
            color: var(--muted-gray);
            font-size: 0.88rem;
            line-height: 1.6;
        }

        .parts-empty-state {
            display: block;
        }

        @media (max-width: 991.98px) {
            .parts-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 575.98px) {
            .parts-hero {
                padding: 2.6rem 0 1.2rem;
            }

            .parts-hero .section-kicker {
                font-size: 1.8rem;
                letter-spacing: 2px;
            }

            .parts-title {
                font-size: 1.3rem;
            }

            .parts-search-row {
                grid-template-columns: 1fr;
            }

            .parts-suggestions {
                right: 0;
            }

            .parts-search-btn {
                width: 100%;
            }

            .parts-grid {
                grid-template-columns: 1fr;
                gap: 2.2rem;
            }

            .parts-category-title {
                font-size: 1.2rem;
            }

            .part-image-wrap {
                height: 160px;
            }

            .part-image-wrap img {
                max-height: 150px;
            }
        }
    </style>
</head>
<body>

<?php include '../partials/public-navbar.php'; ?>

<main class="parts-page">
    <section class="parts-hero">
        <div class="container">
            <p class="section-kicker">Auto Parts</p>

            <h1 class="parts-title">
                Available Parts and Repair Supplies
            </h1>

            <p class="parts-subtext">
                Browse selected auto parts, electrical supplies, and repair materials
                available from Norily's Vehicle Repair Shop.
            </p>
        </div>
    </section>

    <section class="parts-catalog">
        <div class="container">
            <div class="parts-tools">
                <div class="parts-search-row">
                    <input
                        type="text"
                        class="parts-search"
                        id="partsSearch"
                        placeholder="Search parts by name or category"
                        autocomplete="off"
                    >

                    <button type="button" class="parts-search-btn" id="partsSearchBtn">
                        Search
                    </button>
                </div>

                <div class="parts-suggestions" id="partsSuggestions"></div>
            </div>

            <p class="parts-no-results" id="partsNoResults">
                No matching parts found. Try searching for another part name, category, or type.
            </p>

            <?php if ($dbError): ?>
                <p class="parts-empty-state"><?= htmlspecialchars($dbError) ?></p>
            <?php elseif (empty($partCategories)): ?>
                <p class="parts-empty-state">No parts are currently available.</p>
            <?php else: ?>
                <?php foreach ($partCategories as $category => $parts): ?>
                    <div class="parts-category">
                        <h2 class="parts-category-title">
                            <?= htmlspecialchars($category) ?>
                        </h2>

                        <div class="parts-grid">
                            <?php foreach ($parts as $part): ?>
                                <?php $detailsUrl = 'part-details.php?id=' . urlencode($part['part_id']); ?>

                                <article
                                    class="part-card"
                                    data-part-name="<?= strtolower(htmlspecialchars($part['part_name'])) ?>"
                                    data-part-category="<?= strtolower(htmlspecialchars($part['category'])) ?>"
                                    data-part-type="<?= strtolower(htmlspecialchars($part['category'])) ?>"
                                    data-part-description="<?= strtolower(htmlspecialchars($part['description'] ?? '')) ?>"
                                >
                                    <a href="<?= htmlspecialchars($detailsUrl) ?>" class="part-link part-image-wrap">
                                        <img
                                            src="<?= partImagePath($part['image'] ?? '') ?>"
                                            alt="<?= htmlspecialchars($part['part_name']) ?>"
                                        >
                                    </a>

                                    <a href="<?= htmlspecialchars($detailsUrl) ?>" class="part-link">
                                        <h3>
                                            <?= htmlspecialchars($part['part_name']) ?>
                                        </h3>
                                    </a>

                                    <span class="part-type">
                                        <?= htmlspecialchars($part['category']) ?>
                                    </span>

                                    <a href="<?= htmlspecialchars($detailsUrl) ?>" class="part-details-btn">
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

    if (partsSearch && partsSearchBtn) {
        partsSearch.addEventListener('input', function () {
            showSuggestions(this.value);
            filterParts(this.value);
        });

        partsSearchBtn.addEventListener('click', function () {
            partsSuggestions.style.display = 'none';
            filterParts(partsSearch.value);
        });
    }

    document.addEventListener('click', function (event) {
        if (!event.target.closest('.parts-tools')) {
            partsSuggestions.style.display = 'none';
        }
    });
</script>

</body>
</html>