<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$basePath = '../';

require_once '../config/Database.php';
require_once '../models/PartImage.php';

$database = new Database();
$db = $database->getConnection();

$selectedId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$selectedPart = null;
$moreParts = [];
$galleryImages = [];
$dbError = '';

try {
    if ($selectedId) {
        $stmt = $db->prepare("
            SELECT
                part_id,
                category,
                brand,
                part_name,
                description,
                full_description,
                specification,
                compatibility,
                unit,
                unit_price,
                quantity_on_hand,
                low_stock_threshold,
                image
            FROM part
            WHERE part_id = :part_id
              AND is_active = 1
            LIMIT 1
        ");

        $stmt->bindParam(':part_id', $selectedId, PDO::PARAM_INT);
        $stmt->execute();
        $selectedPart = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($selectedPart) {
            // Gallery: the main photo first, then the extra angles in their saved order.
            $galleryImages = array_merge(
                [$selectedPart['image'] ?? ''],
                array_column((new PartImage($db))->listForItem($selectedId), 'image')
            );

            // Same order and numbering as the landing page carousel, minus the current part.
            $moreStmt = $db->prepare("
                SELECT part_id, part_name, unit_price, image
                FROM part
                WHERE is_active = 1
                ORDER BY part_id DESC
            ");
            $moreStmt->execute();

            foreach ($moreStmt->fetchAll(PDO::FETCH_ASSOC) as $index => $part) {
                if ((int) $part['part_id'] !== (int) $selectedId) {
                    $part['number'] = $index + 1;
                    $moreParts[] = $part;
                }
            }
        }
    }
} catch (Exception $e) {
    $dbError = 'Unable to load part details at the moment.';
}

function partImagePath($image)
{
    return '../assets/images/parts/' . htmlspecialchars($image ?: 'default.png', ENT_QUOTES, 'UTF-8');
}

function formatPrice($price)
{
    return '₱' . number_format((float) $price, 2);
}

// Treats blank and placeholder values ("N/A") as missing so those rows are hidden.
function detailValue($value)
{
    $value = plainText($value);
    return in_array(strtolower($value), ['', 'n/a', 'na', 'none', '-'], true) ? '' : $value;
}

if ($selectedPart) {
    $quantity = (int) $selectedPart['quantity_on_hand'];
    $threshold = (int) ($selectedPart['low_stock_threshold'] ?? 3);

    if ($quantity <= 0) {
        $stockClass = 'stock-out';
        $stockLabel = 'Out of stock';
    } elseif ($quantity <= $threshold) {
        $stockClass = 'stock-low';
        $stockLabel = 'Low stock · ' . $quantity . ' left';
    } else {
        $stockClass = 'stock-available';
        $stockLabel = 'In stock · ' . $quantity . ' available';
    }

    $unitLabel = 'Per ' . strtolower(detailValue($selectedPart['unit']) ?: 'piece');
    $description = plainText($selectedPart['description']) ?: plainText($selectedPart['full_description'], 'No description available.');

    $detailRows = array_filter([
        'Brand' => detailValue($selectedPart['brand']),
        'Specification' => detailValue($selectedPart['specification']),
        'Fits' => detailValue($selectedPart['compatibility']),
    ]);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>
    <?= $selectedPart ? displayText($selectedPart['part_name']) . ' | Parts' : 'Part Not Found | Parts' ?> | Norily's Vehicle Repair Shop
</title>
<link rel="icon" type="image/png" href="../assets/images/logo.png">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
<script>document.documentElement.classList.add('js');window.__motionFallback=setTimeout(function(){document.documentElement.classList.add('reveal-all');},3000);</script>
<script src="../assets/js/motion.js?v=<?= filemtime(__DIR__ . '/../assets/js/motion.js') ?>" defer></script>

<style>
    .part-detail-page {
        background: var(--paper);
        padding: 1.5rem 0 5rem;
    }

    .detail-breadcrumb {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.6rem;
        list-style: none;
        padding: 0;
        margin: 0 0 2.5rem;
        color: var(--ink-2);
        font-size: var(--fs-crumb);
        letter-spacing: 0.14em;
        text-transform: uppercase;
    }

    .detail-breadcrumb a {
        color: var(--ink-2);
        text-decoration: none;
        transition: color 0.2s ease;
    }

    .detail-breadcrumb a:hover {
        color: var(--ink);
    }

    .detail-breadcrumb li + li::before {
        content: "/";
        margin-right: 0.6rem;
    }

    .part-detail-grid {
        display: grid;
        /* Image and panel sized to their content and centered as a pair */
        grid-template-columns: minmax(0, 520px) minmax(0, 460px);
        justify-content: center;
        gap: 2.5rem;
        align-items: center;
        align-content: start;
        /* Always fill the first screen (minus the breadcrumb) so "More parts" starts below
           the fold no matter how short the part's details are. */
        min-height: calc(100vh - 5.5rem - var(--pub-header-h));
        min-height: calc(100svh - 5.5rem - var(--pub-header-h));
    }

    .part-media {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 1.25rem;
    }

    .part-thumbs {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        flex-shrink: 0;
    }

    /* Thumbnails: small versions of the main image frame */
    .part-thumb {
        width: 76px;
        height: 76px;
        padding: 6px;
        background: transparent;
        border: 1px solid var(--line);
        cursor: pointer;
        transition: border-color 0.2s ease;
    }

    .part-thumb:hover {
        border-color: var(--ink-3);
    }

    .part-thumb.active {
        border-color: var(--ink);
    }

    .part-thumb img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        mix-blend-mode: multiply;
    }

    /* Main image in a square frame */
    .part-stage {
        flex: 0 1 420px;
        min-width: 0;
        aspect-ratio: 1 / 1;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2.5rem;
        background: transparent;
        border: 1px solid var(--line);
    }

    .part-stage img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        mix-blend-mode: multiply;
    }

    .part-panel {
        border: 1px solid var(--ink);
        padding: 1.75rem 1.75rem 1.5rem;
    }

    .part-panel-head {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        gap: 1rem;
    }

    .part-detail-title {
        min-width: 0;
        overflow-wrap: anywhere;
        color: var(--ink);
        font-size: var(--fs-item-title);
        font-weight: 400;
        font-stretch: 125%;
        text-transform: uppercase;
        line-height: 1;
        margin: 0;
    }

    .mono {
        font-family: ui-monospace, SFMono-Regular, Consolas, monospace;
    }

    .part-detail-price {
        flex-shrink: 0;
        color: var(--ink);
        font-size: 1.2rem;
        font-weight: 600;
        white-space: nowrap;
    }

    .part-code {
        color: var(--ink-2);
        font-size: 0.85rem;
        letter-spacing: 0.06em;
        margin: 0.9rem 0 0;
        padding-bottom: 1.25rem;
        border-bottom: 1px solid var(--ink);
    }

    .part-section-title {
        color: var(--ink);
        font-size: 0.9rem;
        font-weight: 500;
        font-stretch: 125%;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        margin: 1.35rem 0 0.55rem;
    }

    .part-description {
        color: var(--ink-2);
        font-size: 0.95rem;
        line-height: 1.6;
        margin: 0;
    }

    .part-details-list {
        display: grid;
        grid-template-columns: 7.5rem 1fr;
        gap: 0.35rem 1rem;
        font-size: 0.95rem;
        margin: 0;
    }

    .part-details-list dt {
        color: var(--ink-2);
        font-weight: 400;
    }

    .part-details-list dd {
        color: var(--ink);
        margin: 0;
    }

    .part-facts {
        display: grid;
        grid-template-columns: 1fr 1fr;
        border-top: 1px solid var(--ink);
        margin-top: 1.5rem;
    }

    .part-fact {
        padding: 1rem 0 0.9rem;
    }

    .part-fact + .part-fact {
        border-left: 1px solid var(--ink);
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 1rem;
    }

    .part-fact-label {
        color: var(--ink-2);
        font-size: 0.75rem;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        margin: 0;
    }

    .part-fact-value {
        color: var(--ink);
        font-size: 0.95rem;
    }

    .stock-pill {
        display: inline-block;
        margin-top: 0.55rem;
        padding: 0.2rem 0.6rem;
        border: 1px solid currentColor;
        border-radius: 999px;
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .stock-available {
        color: var(--status-success);
    }

    .stock-low {
        color: var(--status-warning);
    }

    .stock-out {
        color: var(--status-danger);
    }

    .part-action {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        min-height: 58px;
        background: transparent;
        border: 1px solid var(--ink);
        color: var(--ink);
        font-size: 0.9rem;
        font-weight: 500;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        text-decoration: none;
        cursor: pointer;
        transition: background 0.2s ease, color 0.2s ease;
    }

    .part-action:hover {
        background: var(--ink);
        color: var(--sheet);
    }

    .part-action:disabled {
        color: var(--ink-3);
        border-color: var(--line);
        background: transparent;
        cursor: not-allowed;
    }

    /* Customers: quantity + add to cart in one row */
    .cart-form {
        display: flex;
    }

    .qty-input {
        width: 80px;
        flex-shrink: 0;
        border: 1px solid var(--ink);
        border-right: 0;
        background: var(--sheet);
        color: var(--ink);
        font-size: 1.1rem;
        text-align: center;
        outline: none;
    }

    .qty-input:focus {
        box-shadow: inset 0 0 0 2px rgba(94, 107, 52, 0.35);
    }

    .part-note {
        color: var(--ink-2);
        font-size: 0.85rem;
        line-height: 1.6;
        margin: 0.9rem 0 0;
    }

    .more-parts {
        margin-top: 3rem;
    }

    .more-parts-title {
        color: var(--ink);
        font-size: clamp(1.15rem, 1.8vw, 1.45rem);
        font-weight: 400;
        font-stretch: 125%;
        text-transform: uppercase;
        text-align: center;
        margin-bottom: 2rem;
    }

    /* Smaller version of the shared carousel, for this page only */
    .more-parts .offering-visual {
        height: 160px;
        margin-bottom: 1.1rem;
    }

    .more-parts .offering-visual::after {
        bottom: 4px;
        height: 14px;
    }

    .more-parts .offering-visual img {
        width: 75%;
        height: 130px;
    }

    .more-parts .offering-index {
        font-size: 0.85rem;
        margin-bottom: 0.2rem;
    }

    .more-parts .offering-name {
        font-size: 0.8rem;
        margin-bottom: 0.4rem;
    }

    .more-parts .offering-price {
        font-size: 0.8rem;
    }

    .more-parts .offerings-arrow {
        top: 80px;
    }

    .not-found-box {
        max-width: 560px;
        padding: 4rem 0;
    }

    .not-found-box h1 {
        color: var(--ink);
        font-size: clamp(2rem, 4vw, 2.75rem);
        font-weight: 400;
        font-stretch: 125%;
        text-transform: uppercase;
        margin-bottom: 1rem;
    }

    .not-found-box p {
        color: var(--ink-2);
        font-size: 1.05rem;
        margin-bottom: 2rem;
    }

    .not-found-box .part-action {
        width: auto;
        display: inline-flex;
        min-height: 64px;
        padding: 0 2rem;
    }

    @media (max-width: 991.98px) {
        .part-detail-grid {
            grid-template-columns: 1fr;
            gap: 1.5rem;
            min-height: 0;
        }

        .part-stage {
            flex-basis: auto;
            width: 100%;
            max-width: 420px;
        }

        /* Thumbnails become a swipe row under the main image */
        .part-media {
            flex-direction: column-reverse;
            gap: 1rem;
        }

        .part-thumbs {
            flex-direction: row;
            overflow-x: auto;
            max-width: 100%;
            scrollbar-width: none;
        }

        .part-thumb {
            width: 64px;
            height: 64px;
            flex-shrink: 0;
        }
    }

    @media (max-width: 575.98px) {
        .part-panel {
            padding: 1.5rem 1.25rem 1.25rem;
        }

        .part-panel-head {
            flex-direction: column;
            gap: 0.75rem;
        }

        .part-details-list {
            grid-template-columns: 7.5rem 1fr;
        }

        .part-fact + .part-fact {
            padding-left: 1rem;
        }

        .part-stage {
            padding: 1.75rem;
        }
    }
</style>
</head>

<body>

<?php $activePage = 'parts'; include '../partials/public-navbar.php'; ?>

<main class="part-detail-page">
    <div class="container">
        <nav aria-label="Breadcrumb">
            <ol class="detail-breadcrumb">
                <li><a href="../" class="link-grow">Home</a></li>
                <li><a href="parts.php" class="link-grow">Parts</a></li>
                <li aria-current="page"><?= $selectedPart ? displayText($selectedPart['part_name']) : 'Not found' ?></li>
            </ol>
        </nav>

        <?php if ($dbError || !$selectedPart): ?>
            <div class="not-found-box">
                <h1><?= $dbError ? 'Something went wrong' : 'Part not found' ?></h1>
                <p>
                    <?= $dbError
                        ? htmlspecialchars($dbError, ENT_QUOTES, 'UTF-8')
                        : 'This part may have been removed or is no longer available.' ?>
                </p>
                <a href="parts.php" class="part-action">Back to parts</a>
            </div>
        <?php else: ?>
            <div class="part-detail-grid">
                <div class="part-media" data-gallery>
                    <?php if (count($galleryImages) > 1): ?>
                        <div class="part-thumbs" role="group" aria-label="Part photos">
                            <?php foreach ($galleryImages as $index => $image): ?>
                                <button
                                    type="button"
                                    class="part-thumb<?= $index === 0 ? ' active' : '' ?>"
                                    data-full="<?= partImagePath($image) ?>"
                                    aria-label="Show photo <?= $index + 1 ?>"
                                    aria-pressed="<?= $index === 0 ? 'true' : 'false' ?>"
                                >
                                    <img src="<?= partImagePath($image) ?>" alt="" loading="lazy">
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="part-stage">
                        <img
                            id="partMainImage"
                            data-gallery-main
                            src="<?= partImagePath($galleryImages[0] ?? '') ?>"
                            alt="<?= displayText($selectedPart['part_name']) ?>"
                        >
                    </div>
                </div>

                <div class="part-panel">
                    <div class="part-panel-head">
                        <h1 class="part-detail-title"><?= displayText($selectedPart['part_name']) ?></h1>
                        <div class="part-detail-price mono"><?= formatPrice($selectedPart['unit_price']) ?></div>
                    </div>

                    <p class="part-code mono">
                        <?= htmlspecialchars(partCategoryLabel($selectedPart['category']), ENT_QUOTES, 'UTF-8') ?>
                    </p>

                    <h2 class="part-section-title">Description</h2>
                    <p class="part-description"><?= nl2br(htmlspecialchars($description, ENT_QUOTES, 'UTF-8')) ?></p>

                    <?php if (!empty($detailRows)): ?>
                        <h2 class="part-section-title">Details</h2>
                        <dl class="part-details-list">
                            <?php foreach ($detailRows as $label => $value): ?>
                                <dt><?= $label ?></dt>
                                <dd><?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?></dd>
                            <?php endforeach; ?>
                        </dl>
                    <?php endif; ?>

                    <div class="part-facts">
                        <div class="part-fact">
                            <p class="part-fact-label">Stock</p>
                            <span class="stock-pill <?= $stockClass ?>"><?= htmlspecialchars($stockLabel, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>

                        <div class="part-fact">
                            <p class="part-fact-label">Unit</p>
                            <span class="part-fact-value"><?= htmlspecialchars(ucfirst($unitLabel), ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    </div>

                    <?php if ($quantity <= 0): ?>
                        <button type="button" class="part-action" disabled>Out of stock</button>
                    <?php elseif (can('shop.purchase')): ?>
                        <form action="../controllers/CartController.php" method="POST" class="cart-form">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="part_id" value="<?= (int) $selectedPart['part_id'] ?>">

                            <input
                                type="number"
                                name="quantity"
                                value="1"
                                min="1"
                                max="<?= $quantity ?>"
                                class="qty-input"
                                aria-label="Quantity"
                                required
                            >

                            <button type="submit" class="part-action">Add to cart</button>
                        </form>
                    <?php elseif (isLoggedIn()): ?>
                        <a href="../views/<?= dashboardForRole($_SESSION['role'] ?? '') ?>" class="part-action">Go to dashboard</a>
                    <?php else: ?>
                        <a href="../views/login.php" class="part-action">Log in to reserve</a>
                    <?php endif; ?>

                    <p class="part-note">
                        Reserve online and pick up at the shop. Availability is confirmed by the shop.
                    </p>
                </div>
            </div>

            <?php if (!empty($moreParts)): ?>
                <section class="more-parts" data-reveal>
                    <h2 class="more-parts-title">More parts</h2>

                    <div class="offerings-carousel">
                        <button class="offerings-arrow offerings-arrow-prev" type="button" aria-label="Previous" data-scroll="-1">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 4 7 12l8 8"/></svg>
                        </button>

                        <div class="offerings-track">
                            <?php foreach ($moreParts as $part): ?>
                                <a href="part-details.php?id=<?= urlencode($part['part_id']) ?>" class="offering-item">
                                    <div class="offering-visual">
                                        <img src="<?= partImagePath($part['image'] ?? '') ?>" alt="<?= displayText($part['part_name']) ?>" loading="lazy">
                                    </div>

                                    <span class="offering-index"><?= str_pad($part['number'], 2, '0', STR_PAD_LEFT) ?></span>
                                    <h3 class="offering-name"><?= displayText($part['part_name']) ?></h3>
                                    <span class="offering-price"><?= formatPrice($part['unit_price']) ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>

                        <button class="offerings-arrow offerings-arrow-next" type="button" aria-label="Next" data-scroll="1">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 4 8 8-8 8"/></svg>
                        </button>
                    </div>
                </section>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>

<?php include '../partials/public-footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/offerings-carousel.js"></script>


</body>
</html>
