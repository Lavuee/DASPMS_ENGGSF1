<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requirePermission('shop.purchase');

require_once '../config/Database.php';

$db = (new Database())->getConnection();

$part_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$part = null;
$relatedParts = [];
$dbError = '';

try {
    if ($part_id) {
        $stmt = $db->prepare("
            SELECT 
                part_id,
                category,
                brand,
                part_name,
                description,
                specification,
                compatibility,
                unit,
                full_description,
                unit_price,
                quantity_on_hand,
                low_stock_threshold,
                image
            FROM part
            WHERE part_id = ?
              AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$part_id]);
        $part = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($part) {
            $relatedStmt = $db->prepare("
                SELECT
                    part_id,
                    category,
                    brand,
                    part_name,
                    description,
                    unit,
                    unit_price,
                    quantity_on_hand,
                    image
                FROM part
                WHERE is_active = 1
                  AND quantity_on_hand > 0
                  AND part_id != ?
                  AND category = ?
                ORDER BY part_name ASC
                LIMIT 3
            ");
            $relatedStmt->execute([
                intval($part['part_id']),
                $part['category']
            ]);
            $relatedParts = $relatedStmt->fetchAll(PDO::FETCH_ASSOC);

            // Same-category parts first, then other parts, so "More parts" is never nearly empty.
            if (count($relatedParts) < 6) {
                $excludeIds = array_merge([intval($part['part_id'])], array_map('intval', array_column($relatedParts, 'part_id')));
                $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));

                $fallbackStmt = $db->prepare("
                    SELECT
                        part_id,
                        category,
                        brand,
                        part_name,
                        description,
                        unit,
                        unit_price,
                        quantity_on_hand,
                        image
                    FROM part
                    WHERE is_active = 1
                      AND quantity_on_hand > 0
                      AND part_id NOT IN ($placeholders)
                    ORDER BY category ASC, part_name ASC
                    LIMIT " . (6 - count($relatedParts)) . "
                ");
                $fallbackStmt->execute($excludeIds);
                $relatedParts = array_merge($relatedParts, $fallbackStmt->fetchAll(PDO::FETCH_ASSOC));
            }
        }
    }
} catch (Exception $e) {
    $dbError = "Unable to load part details.";
}

function partImagePath($image)
{
    if (!$image) {
        return '../assets/images/parts/default.png';
    }

    return '../assets/images/parts/' . htmlspecialchars($image);
}

function displayMultiline($value, $fallback = 'No information available.')
{
    $value = trim(html_entity_decode((string)($value ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    if ($value === '') {
        $value = $fallback;
    }

    return nl2br(htmlspecialchars($value));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?php echo $part ? displayText($part['part_name'] ?? '') . ' - ' : ''; ?>Part Details - Norily's Customer Portal</title>
<link rel="icon" type="image/png" href="../assets/images/logo.png">
<?php require_once __DIR__ . '/../includes/customer_ui.php'; cpHeadAssets(); ?>
</head>

<body class="cp-body">
<?php include '../includes/sidebar.php'; ?>

<main class="cp-main" id="cpMain">

    <ol class="cp-crumb">
        <li><a href="customer_parts.php">Browse parts</a></li>
        <?php if ($part): ?>
            <li><?php echo htmlspecialchars(partCategoryLabel($part['category'] ?? '')); ?></li>
            <li aria-current="page"><?php echo displayText($part['part_name'] ?? ''); ?></li>
        <?php else: ?>
            <li aria-current="page">Part details</li>
        <?php endif; ?>
    </ol>

    <?php cpFlash(); ?>

    <?php if ($dbError): ?>
        <div class="cp-empty">
            <strong>Something went wrong</strong>
            <?php echo htmlspecialchars($dbError); ?>
            <div><a href="customer_parts.php" class="cp-btn cp-btn-line">Back to parts</a></div>
        </div>

    <?php elseif (!$part): ?>
        <div class="cp-empty">
            <strong>Part not found</strong>
            Part not found or unavailable.
            <div><a href="customer_parts.php" class="cp-btn cp-btn-line">Back to parts</a></div>
        </div>

    <?php else: ?>
        <?php
            // Main photo first, then the extra angles saved for this part (same as the public part page).
            require_once __DIR__ . '/../models/PartImage.php';
            $galleryImages = array_values(array_filter(array_merge(
                [$part['image'] ?? ''],
                array_column((new PartImage($db))->listForItem(intval($part['part_id'])), 'image')
            )));

            $quantity = intval($part['quantity_on_hand']);
            $unitLabel = displayText($part['unit'] ?? '', 'piece');
            $brandText = plainText($part['brand'] ?? '', 'Unspecified');
        ?>
        <div class="cpd-grid">

            <div class="cpd-left">
            <div class="cpd-media" data-gallery>
                <?php if (count($galleryImages) > 1): ?>
                    <div class="cpd-thumbs" role="group" aria-label="Part photos">
                        <?php foreach ($galleryImages as $index => $image): ?>
                            <button
                                type="button"
                                class="cpd-thumb<?php echo $index === 0 ? ' active' : ''; ?>"
                                data-full="<?php echo partImagePath($image); ?>"
                                aria-label="Show photo <?php echo $index + 1; ?>"
                                aria-pressed="<?php echo $index === 0 ? 'true' : 'false'; ?>"
                            >
                                <img src="<?php echo partImagePath($image); ?>" alt="" loading="lazy">
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="cpd-stage">
                    <?php if (!empty($galleryImages)): ?>
                        <img
                            data-gallery-main
                            src="<?php echo partImagePath($galleryImages[0]); ?>"
                            alt="<?php echo displayText($part['part_name'] ?? 'Part image'); ?>"
                            data-letter="<?php echo htmlspecialchars(mb_strtoupper(mb_substr(plainText($part['part_name'] ?? '?'), 0, 1))); ?>"
                            onerror="cpLetterFallback(this)"
                        >
                    <?php else: ?>
                        <?php echo cpThumb('', $part['part_name'] ?? 'Part'); ?>
                    <?php endif; ?>
                </div>
            </div>

            </div>

            <div class="cpd-panel">
                <div class="cpd-panel-head">
                    <h1 class="cpd-title"><?php echo displayText($part['part_name'] ?? ''); ?></h1>
                    <div class="cpd-price">₱<?php echo number_format(floatval($part['unit_price']), 2); ?> <small>/ <?php echo $unitLabel; ?></small></div>
                </div>

                <p class="cpd-code"><?php echo htmlspecialchars(partCategoryLabel($part['category'] ?? '')); ?> · Part #<?php echo intval($part['part_id']); ?></p>

                <div class="offerings-tabs cpd-tabs" role="tablist" aria-label="Part details" data-tablist>
                    <button class="offerings-tab active" type="button" role="tab" id="part-description-tab" aria-selected="true" aria-controls="part-description-panel" data-offering-tab="description">Description</button>
                    <button class="offerings-tab" type="button" role="tab" id="part-details-tab" aria-selected="false" aria-controls="part-details-panel" tabindex="-1" data-offering-tab="details">Details</button>
                    <button class="offerings-tab" type="button" role="tab" id="part-pickup-tab" aria-selected="false" aria-controls="part-pickup-panel" tabindex="-1" data-offering-tab="pickup">Pickup &amp; payment</button>
                    <span class="offerings-tab-indicator" aria-hidden="true"></span>
                </div>

                <div class="cpd-tabpanels">
                    <div class="offerings-panel cpd-tabpanel active" id="part-description-panel" role="tabpanel" aria-labelledby="part-description-tab" tabindex="0">
                        <p class="cpd-text"><?php echo displayMultiline($part['full_description'] ?: $part['description'], 'No description available.'); ?></p>
                    </div>
                    <div class="offerings-panel cpd-tabpanel" id="part-details-panel" role="tabpanel" aria-labelledby="part-details-tab" tabindex="0">
                        <dl class="cpd-list">
                            <dt>Brand</dt>
                            <dd><?php echo htmlspecialchars($brandText); ?></dd>

                            <dt>Specification</dt>
                            <dd><?php echo displayMultiline($part['specification'] ?? '', 'No specification provided.'); ?></dd>

                            <dt>Fits</dt>
                            <dd><?php echo displayMultiline($part['compatibility'] ?? '', 'No compatibility information provided.'); ?></dd>

                            <dt>Category</dt>
                            <dd><?php echo displayText($part['category'] ?? ''); ?></dd>

                            <dt>Availability</dt>
                            <dd><?php echo $quantity; ?> <?php echo displayText($part['unit'] ?? '', 'unit'); ?>(s) available</dd>
                        </dl>
                    </div>
                    <div class="offerings-panel cpd-tabpanel" id="part-pickup-panel" role="tabpanel" aria-labelledby="part-pickup-tab" tabindex="0">
                        <ol class="cpd-steps">
                            <li><span>01</span><span>This part is reserved online and claimed through shop pickup after confirmation.</span></li>
                            <li><span>02</span><span>The shop will still confirm item availability and your preferred pickup schedule.</span></li>
                            <li><span>03</span><span>Payment can be Cash on Pickup, GCash Down Payment, or GCash Full Payment during checkout.</span></li>
                            <li><span>04</span><span>GCash payments go through PayMongo and are confirmed automatically. Any remaining balance is settled at the shop.</span></li>
                        </ol>
                    </div>
                </div>

                <div class="cpd-facts">
                    <div class="cpd-fact">
                        <p class="cpd-fact-label">Stock</p>
                        <?php echo cpStockPill($quantity, $part['low_stock_threshold'] ?? 3); ?>
                    </div>

                    <div class="cpd-fact">
                        <p class="cpd-fact-label">Unit</p>
                        <span class="cpd-fact-value"><?php echo htmlspecialchars(ucfirst(plainText($part['unit'] ?? '', 'piece'))); ?></span>
                    </div>
                </div>

                <?php
                // Pieces already in the cart count against the stock that can still be added
                $inCartQty = intval($_SESSION['cart'][intval($part['part_id'])]['qty'] ?? 0);
                $canAddQty = max($quantity - $inCartQty, 0);
                ?>

                <?php if ($quantity > 0): ?>
                    <form action="../controllers/CartController.php" method="POST" class="cpd-cart-form" id="addToCartForm"<?= $canAddQty <= 0 ? ' hidden' : '' ?>>
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="part_id" value="<?php echo intval($part['part_id']); ?>">

                        <div class="cpd-qty">
                            <button type="button" id="decreaseQty" aria-label="Decrease quantity">−</button>
                            <input
                                type="number"
                                name="quantity"
                                id="quantityInput"
                                min="1"
                                max="<?php echo max($canAddQty, 1); ?>"
                                value="1"
                                inputmode="numeric"
                                aria-label="Quantity"
                                required
                            >
                            <button type="button" id="increaseQty" aria-label="Increase quantity">+</button>
                        </div>

                        <button type="submit" class="cpd-action">Add to cart</button>
                    </form>

                    <p class="cpd-cart-full" id="cartFull"<?= $canAddQty > 0 ? ' hidden' : '' ?>>All <?php echo $quantity; ?> in stock are already in your cart.</p>

                    <div class="cpd-subline">
                        <span>Maximum: <span class="cp-mono" id="cartMax"><?php echo $canAddQty; ?></span><?php if ($inCartQty > 0): ?> <span id="cartInNote">(<?php echo $inCartQty; ?> in cart)</span><?php else: ?> <span id="cartInNote"></span><?php endif; ?></span>
                        <a href="customer_cart.php">View cart (<span id="cartSubCount"><?php echo intval($cartCount ?? 0); ?></span>)</a>
                    </div>
                <?php else: ?>
                    <button type="button" class="cpd-action" disabled>Out of stock</button>
                <?php endif; ?>

            </div>
        </div>

        <?php if (!empty($relatedParts)): ?>
            <section class="cpd-more" aria-labelledby="moreParts">
                <h2 class="cpd-more-title" id="moreParts">More parts</h2>

                <div class="offerings-carousel">
                    <button class="offerings-arrow offerings-arrow-prev" type="button" aria-label="Previous" data-scroll="-1">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 4 7 12l8 8"/></svg>
                    </button>

                    <div class="offerings-track">
                        <?php foreach ($relatedParts as $related): ?>
                            <?php $relatedImage = !empty($related['image']) ? '../assets/images/parts/' . $related['image'] : ''; ?>
                            <a href="customer_part_details.php?id=<?php echo intval($related['part_id']); ?>" class="offering-item">
                                <div class="offering-visual">
                                    <?php echo cpThumb($relatedImage, $related['part_name'] ?? 'Part'); ?>
                                </div>

                                <h3 class="offering-name">
                                    <?php echo displayText($related['part_name'] ?? ''); ?>
                                    <?php if (cpHasValue($related['brand'] ?? '')): ?> · <?php echo displayText($related['brand']); ?><?php endif; ?>
                                </h3>
                                <span class="offering-price cp-mono">₱<?php echo number_format(floatval($related['unit_price']), 2); ?> / <?php echo displayText($related['unit'] ?? '', 'piece'); ?></span>
                                <span class="cp-hint mt-1"><?php echo htmlspecialchars(partCategoryLabel($related['category'] ?? '')); ?> · <?php echo intval($related['quantity_on_hand']); ?> left</span>
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

</main>


<script>
const qtyInput = document.getElementById('quantityInput');
const decreaseBtn = document.getElementById('decreaseQty');
const increaseBtn = document.getElementById('increaseQty');

if (qtyInput && decreaseBtn && increaseBtn) {
    const min = parseInt(qtyInput.min) || 1;
    const max = parseInt(qtyInput.max) || 1;

    function clampQty() {
        let value = parseInt(qtyInput.value);

        if (isNaN(value) || value < min) value = min;
        if (value > max) value = max;

        qtyInput.value = value;

        decreaseBtn.disabled = value <= min;
        increaseBtn.disabled = value >= max;
    }

    decreaseBtn.addEventListener('click', function () {
        qtyInput.value = (parseInt(qtyInput.value) || min) - 1;
        clampQty();
    });

    increaseBtn.addEventListener('click', function () {
        qtyInput.value = (parseInt(qtyInput.value) || min) + 1;
        clampQty();
    });

    qtyInput.addEventListener('input', clampQty);
    clampQty();
}

/* Add to cart without leaving the page: the cart count in the header goes up and a short note appears.
   If anything unexpected comes back (e.g. the session expired), the form is sent the normal way. */
(function () {
    const form = document.getElementById('addToCartForm');
    if (!form || !window.fetch) return;

    const button = form.querySelector('.cpd-action');
    function setCartCount(count) {
        document.querySelectorAll('.cp-cart-count').forEach(function (el) {
            el.textContent = count > 99 ? '99+' : count;
            el.hidden = count <= 0;
        });
        document.querySelectorAll('.cp-cart').forEach(function (el) {
            el.setAttribute('aria-label', 'Cart, ' + count + ' item' + (count === 1 ? '' : 's'));
        });
        document.querySelectorAll('.cp-sheet a[href="customer_cart.php"]').forEach(function (el) {
            el.lastChild.textContent = 'Cart (' + count + ')';
        });
        const sub = document.getElementById('cartSubCount');
        if (sub) sub.textContent = count;
    }

    form.addEventListener('submit', function (e) {
        if (form.dataset.plainSubmit === '1') return;
        e.preventDefault();
        button.disabled = true;
        button.textContent = 'Adding...';

        // form.getAttribute, not form.action: the hidden field named "action" hides the property
        fetch(form.getAttribute('action'), { method: 'POST', body: new FormData(form), headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' })
            .then(function (res) {
                const type = res.headers.get('Content-Type') || '';
                if (type.indexOf('application/json') === -1) throw new Error('not json');
                return res.json();
            })
            .then(function (data) {
                setCartCount(data.cartCount);
                if (data.ok) {
                    cpToast(true, 'Added to cart', data.item);
                } else {
                    cpToast(false, data.message);
                    return;
                }

                // What is left to add after this
                const left = data.canAddMore;
                document.getElementById('cartMax').textContent = left;
                document.getElementById('cartInNote').textContent = '(' + data.inCart + ' in cart)';
                if (left > 0) {
                    qtyInput.max = left;
                    qtyInput.value = 1;
                    qtyInput.dispatchEvent(new Event('input'));
                } else {
                    form.hidden = true;
                    document.getElementById('cartFull').hidden = false;
                }
            })
            .catch(function () {
                // Fall back to the normal form post (goes to the cart page, as before)
                form.dataset.plainSubmit = '1';
                form.submit();
            })
            .finally(function () {
                button.disabled = false;
                button.textContent = 'Add to cart';
            });
    });
})();
</script>

<script src="../assets/js/offerings-carousel.js?v=<?= filemtime(__DIR__ . '/../assets/js/offerings-carousel.js') ?>"></script>
<script>if (typeof updateOfferingArrows === "function") updateOfferingArrows();</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>