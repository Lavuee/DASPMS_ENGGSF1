<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Customer') {
    header("Location: login.php");
    exit;
}

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
                part_name,
                description,
                full_description,
                unit_price,
                quantity_on_hand,
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
                    part_name,
                    description,
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

            if (count($relatedParts) === 0) {
                $fallbackStmt = $db->prepare("
                    SELECT
                        part_id,
                        category,
                        part_name,
                        description,
                        unit_price,
                        quantity_on_hand,
                        image
                    FROM part
                    WHERE is_active = 1
                    AND quantity_on_hand > 0
                    AND part_id != ?
                    ORDER BY category ASC, part_name ASC
                    LIMIT 3
                ");
                $fallbackStmt->execute([intval($part['part_id'])]);
                $relatedParts = $fallbackStmt->fetchAll(PDO::FETCH_ASSOC);
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Part Details - Norily's Repair Shop</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/style.css">

<style>
    .main-content {
        overflow-x: hidden;
    }

    .part-detail-page {
        width: 100%;
        max-width: 100%;
    }

    .detail-topbar {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 1.6rem;
    }

    .detail-topbar h2 {
        font-size: 1.8rem;
        font-weight: 900;
        color: var(--dashboard-text-main);
        margin-bottom: 0.25rem;
        line-height: 1.1;
    }

    .detail-topbar p {
        color: var(--dashboard-text-muted);
        margin-bottom: 0;
        font-size: 0.92rem;
        font-weight: 500;
    }

    .back-btn {
        min-height: 42px;
        border-radius: 12px;
        padding: 0.6rem 1rem;
        font-size: 0.88rem;
        font-weight: 800;
        white-space: nowrap;
        border: 1px solid rgba(17, 24, 39, 0.18);
        background: transparent;
        color: var(--dashboard-text-main);
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        transition: 0.2s ease;
    }

    .back-btn:hover {
        background: rgba(255, 255, 255, 0.65);
        color: var(--black);
        border-color: rgba(17, 24, 39, 0.25);
    }

    .detail-alert {
        border-radius: 14px;
        margin-bottom: 1rem;
        font-size: 0.9rem;
    }

    .detail-layout {
        display: grid;
        grid-template-columns: minmax(0, 1.05fr) minmax(340px, 0.95fr);
        gap: 2.6rem;
        align-items: center;
    }

    .product-media-panel {
        background: transparent;
        border: none;
        padding: 0;
        min-width: 0;
    }

    .product-media-stage {
        min-height: 455px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0;
        background: transparent;
        border: none;
        box-shadow: none;
    }

    .product-media-stage img {
        max-width: 100%;
        max-height: 390px;
        object-fit: contain;
        display: block;
    }

    .product-info-panel {
        background: transparent;
        border: none;
        border-radius: 0;
        padding: 0;
        min-width: 0;
    }

    .product-category {
        color: var(--dashboard-text-muted);
        font-size: 0.78rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 0.55rem;
    }

    .product-title {
        font-size: 2rem;
        font-weight: 900;
        line-height: 1.08;
        color: var(--dashboard-text-main);
        margin-bottom: 0.55rem;
    }

    .product-price {
        font-size: 1.65rem;
        font-weight: 900;
        color: var(--black);
        margin-bottom: 1rem;
    }

    .product-stock-row {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        flex-wrap: wrap;
        margin-bottom: 1.1rem;
    }

    .stock-badge-available {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        background: rgba(245, 197, 24, 0.22);
        color: var(--black);
        border-radius: 999px;
        padding: 0.45rem 0.8rem;
        font-size: 0.82rem;
        font-weight: 900;
    }

    .stock-badge-available i {
        color: #047857;
    }

    .stock-badge-out {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        background: #fee2e2;
        color: #b91c1c;
        border-radius: 999px;
        padding: 0.45rem 0.8rem;
        font-size: 0.82rem;
        font-weight: 900;
    }

    .product-divider {
        border: 0;
        border-top: 1px solid rgba(17, 24, 39, 0.10);
        margin: 1.15rem 0;
    }

    .product-description {
        color: var(--dashboard-text-muted);
        font-size: 0.92rem;
        line-height: 1.75;
        margin-bottom: 1.25rem;
    }

    .purchase-box {
        padding-top: 0.1rem;
    }

    .purchase-row {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1rem;
        align-items: end;
        padding: 1rem 0;
        border-top: 1px solid rgba(17, 24, 39, 0.10);
        border-bottom: 1px solid rgba(17, 24, 39, 0.10);
        margin-bottom: 1rem;
    }

    .quantity-label {
        color: var(--black);
        font-weight: 900;
        font-size: 0.9rem;
        margin-bottom: 0.55rem;
    }

    .quantity-control-wrap {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 0.4rem;
    }

    .quantity-control {
        display: inline-flex;
        align-items: center;
        border: 1px solid rgba(17, 24, 39, 0.16);
        border-radius: 10px;
        overflow: hidden;
        background: rgba(255, 255, 255, 0.62);
    }

    .qty-btn {
        width: 40px;
        height: 40px;
        border: none;
        background: transparent;
        color: var(--dashboard-text-main);
        font-size: 0.95rem;
        font-weight: 900;
        transition: background 0.2s ease;
    }

    .qty-btn:hover {
        background: rgba(245, 197, 24, 0.18);
        color: var(--black);
    }

    .qty-btn:disabled {
        opacity: 0.4;
        cursor: not-allowed;
    }

    .qty-input {
        width: 62px;
        height: 40px;
        border: none;
        border-left: 1px solid rgba(17, 24, 39, 0.10);
        border-right: 1px solid rgba(17, 24, 39, 0.10);
        text-align: center;
        font-weight: 900;
        font-size: 0.92rem;
        outline: none;
        box-shadow: none;
        background: transparent;
        color: var(--black);
    }

    .max-note {
        color: var(--dashboard-text-muted);
        font-size: 0.78rem;
        margin: 0;
    }

    .action-buttons {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.75rem;
        margin-top: 1rem;
    }

    .add-cart-btn,
    .view-cart-btn,
    .out-stock-btn {
        min-height: 48px;
        border-radius: 0;
        font-size: 0.92rem;
        font-weight: 900;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        transition: 0.2s ease;
    }

    .add-cart-btn {
        background: var(--black);
        border: 1px solid var(--black);
        color: var(--white);
    }

    .add-cart-btn:hover {
        background: var(--dashboard-primary);
        border-color: var(--dashboard-primary);
        color: var(--black);
    }

    .view-cart-btn {
        background: transparent;
        border: 1px solid rgba(17, 24, 39, 0.38);
        color: var(--dashboard-text-main);
    }

    .view-cart-btn:hover {
        background: rgba(245, 197, 24, 0.16);
        border-color: var(--dashboard-primary);
        color: var(--black);
    }

    .out-stock-btn {
        background: #e5e7eb;
        border: 1px solid #e5e7eb;
        color: #6b7280;
    }

    .product-meta-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.8rem;
        margin-top: 1.25rem;
    }

    .meta-box {
        border: 1px solid rgba(17, 24, 39, 0.07);
        border-radius: 14px;
        padding: 0.85rem 0.95rem;
        background: rgba(255, 255, 255, 0.24);
    }

    .meta-label {
        display: block;
        color: var(--dashboard-text-muted);
        font-size: 0.7rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.45px;
        margin-bottom: 0.25rem;
    }

    .meta-value {
        color: var(--black);
        font-size: 0.88rem;
        font-weight: 900;
        line-height: 1.45;
    }

    .related-parts-section {
        margin-top: 4rem;
        padding-top: 2.4rem;
        border-top: 1px solid rgba(17, 24, 39, 0.10);
    }

    .related-parts-title {
        color: var(--dashboard-text-main);
        font-size: 1.35rem;
        font-weight: 900;
        letter-spacing: 2px;
        text-transform: uppercase;
        text-align: center;
        margin-bottom: 2rem;
    }

    .related-parts-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 1.8rem;
        max-width: 980px;
        margin: 0 auto;
    }

    .related-part-card {
        text-align: center;
        text-decoration: none;
        color: inherit;
        display: block;
        transition: 0.2s ease;
    }

    .related-part-card:hover {
        transform: translateY(-3px);
        color: inherit;
    }

    .related-part-image {
        width: 100%;
        aspect-ratio: 1 / 0.72;
        background: rgba(255, 255, 255, 0.38);
        border: 1px solid rgba(17, 24, 39, 0.04);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1.2rem;
        margin-bottom: 0.85rem;
        position: relative;
        overflow: hidden;
    }

    .related-part-image img {
        max-width: 100%;
        max-height: 145px;
        object-fit: contain;
        transition: 0.2s ease;
    }

    .related-part-card:hover .related-part-image img {
        transform: scale(1.04);
    }

    .related-stock {
        position: absolute;
        top: 0.65rem;
        right: 0.65rem;
        background: rgba(245, 197, 24, 0.92);
        color: var(--black);
        border-radius: 999px;
        padding: 0.28rem 0.55rem;
        font-size: 0.68rem;
        font-weight: 900;
        line-height: 1;
    }

    .related-part-name {
        color: var(--dashboard-text-main);
        font-size: 0.95rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 0.35rem;
        line-height: 1.3;
    }

    .related-part-category {
        color: var(--dashboard-primary);
        font-size: 0.7rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 1.3px;
        margin-bottom: 0.45rem;
    }

    .related-part-price {
        color: #047857;
        font-size: 0.85rem;
        font-weight: 900;
    }

    .empty-state-card {
        background: rgba(255, 255, 255, 0.3);
        border: 1px solid rgba(17, 24, 39, 0.06);
        border-radius: 18px;
        padding: 2.5rem 1.5rem;
        text-align: center;
        color: var(--dashboard-text-muted);
    }

    .empty-state-card i {
        display: block;
        font-size: 1.8rem;
        color: var(--dashboard-primary);
        margin-bottom: 0.8rem;
    }

    @media (max-width: 1199.98px) {
        .detail-layout {
            grid-template-columns: 1fr;
            gap: 1.75rem;
            align-items: start;
        }

        .product-media-stage {
            min-height: 340px;
            justify-content: flex-start;
        }

        .product-media-stage img {
            max-height: 320px;
        }
    }

    @media (max-width: 991.98px) {
        .related-parts-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 767.98px) {
        .detail-topbar {
            flex-direction: column;
            align-items: stretch;
        }

        .back-btn {
            width: 100%;
            justify-content: center;
        }

        .detail-topbar h2 {
            font-size: 1.55rem;
        }

        .product-media-stage {
            min-height: 260px;
            justify-content: center;
        }

        .product-media-stage img {
            max-height: 245px;
        }

        .product-title {
            font-size: 1.55rem;
        }

        .product-price {
            font-size: 1.35rem;
        }

        .action-buttons {
            grid-template-columns: 1fr;
        }

        .product-meta-grid {
            grid-template-columns: 1fr;
        }

        .related-parts-section {
            margin-top: 3rem;
            padding-top: 2rem;
        }

        .related-parts-grid {
            grid-template-columns: 1fr;
        }

        .related-parts-title {
            font-size: 1.1rem;
        }
    }
</style>
</head>

<body>

<div class="app-wrapper">
<?php include '../includes/sidebar.php'; ?>

<main class="main-content">
    <div class="part-detail-page">

        <div class="detail-topbar">
            <div>
                <h2>Part Details</h2>
                <p>Review part information before adding it to your cart.</p>
            </div>

            <a href="customer_parts.php" class="btn back-btn">
                <i class="bi bi-arrow-left"></i>
                Back to Parts
            </a>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show detail-alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show detail-alert">
                <i class="bi bi-exclamation-circle-fill me-2"></i>
                <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($dbError): ?>
            <div class="empty-state-card">
                <i class="bi bi-exclamation-circle"></i>
                <div class="fw-bold mb-2">Something went wrong</div>
                <div><?php echo htmlspecialchars($dbError); ?></div>
            </div>

        <?php elseif (!$part): ?>
            <div class="empty-state-card">
                <i class="bi bi-box-seam"></i>
                <div class="fw-bold mb-2">Part not found</div>
                <div>Part not found or unavailable.</div>
            </div>

        <?php else: ?>
            <div class="detail-layout">

                <div class="product-media-panel">
                    <div class="product-media-stage">
                        <img
                            src="<?php echo partImagePath($part['image'] ?? ''); ?>"
                            alt="<?php echo htmlspecialchars($part['part_name']); ?>"
                        >
                    </div>
                </div>

                <div class="product-info-panel">
                    <div class="product-category">
                        <?php echo htmlspecialchars($part['category']); ?>
                    </div>

                    <h1 class="product-title">
                        <?php echo htmlspecialchars($part['part_name']); ?>
                    </h1>

                    <div class="product-price">
                        ₱<?php echo number_format(floatval($part['unit_price']), 2); ?>
                    </div>

                    <div class="product-stock-row">
                        <?php if (intval($part['quantity_on_hand']) > 0): ?>
                            <span class="stock-badge-available">
                                <i class="bi bi-check-circle-fill"></i>
                                In Stock: <?php echo intval($part['quantity_on_hand']); ?>
                            </span>
                        <?php else: ?>
                            <span class="stock-badge-out">
                                <i class="bi bi-x-circle-fill"></i>
                                Out of Stock
                            </span>
                        <?php endif; ?>
                    </div>

                    <hr class="product-divider">

                    <div class="product-description">
                        <?php echo nl2br(htmlspecialchars($part['full_description'] ?: $part['description'] ?: 'No description available.')); ?>
                    </div>

                    <?php if (intval($part['quantity_on_hand']) > 0): ?>
                        <form action="../controllers/CartController.php" method="POST" class="purchase-box">
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="part_id" value="<?php echo intval($part['part_id']); ?>">

                            <div class="purchase-row">
                                <div class="quantity-control-wrap">
                                    <p class="quantity-label">Quantity</p>

                                    <div class="quantity-control">
                                        <button type="button" class="qty-btn" id="decreaseQty">
                                            <i class="bi bi-dash"></i>
                                        </button>

                                        <input
                                            type="number"
                                            name="quantity"
                                            id="quantityInput"
                                            class="qty-input"
                                            min="1"
                                            max="<?php echo intval($part['quantity_on_hand']); ?>"
                                            value="1"
                                            required
                                        >

                                        <button type="button" class="qty-btn" id="increaseQty">
                                            <i class="bi bi-plus"></i>
                                        </button>
                                    </div>

                                    <p class="max-note">
                                        Maximum: <?php echo intval($part['quantity_on_hand']); ?>
                                    </p>
                                </div>
                            </div>

                            <div class="action-buttons">
                                <button type="submit" class="btn add-cart-btn">
                                    <i class="bi bi-cart-plus"></i>
                                    Add to Cart
                                </button>

                                <a href="customer_cart.php" class="btn view-cart-btn">
                                    <i class="bi bi-cart3"></i>
                                    View Cart
                                </a>
                            </div>
                        </form>
                    <?php else: ?>
                        <button class="btn out-stock-btn w-100 mt-3" disabled>
                            <i class="bi bi-slash-circle"></i>
                            Out of Stock
                        </button>
                    <?php endif; ?>

                    <div class="product-meta-grid">
                        <div class="meta-box">
                            <span class="meta-label">Category</span>
                            <div class="meta-value">
                                <?php echo htmlspecialchars($part['category']); ?>
                            </div>
                        </div>

                        <div class="meta-box">
                            <span class="meta-label">Availability</span>
                            <div class="meta-value">
                                <?php echo intval($part['quantity_on_hand']); ?> unit(s) available
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <?php if (!empty($relatedParts)): ?>
                <section class="related-parts-section">
                    <h2 class="related-parts-title">Related Parts</h2>

                    <div class="related-parts-grid">
                        <?php foreach ($relatedParts as $related): ?>
                            <a
                                href="customer_part_details.php?id=<?php echo intval($related['part_id']); ?>"
                                class="related-part-card"
                            >
                                <div class="related-part-image">
                                    <span class="related-stock">
                                        <?php echo intval($related['quantity_on_hand']); ?> left
                                    </span>

                                    <img
                                        src="<?php echo partImagePath($related['image'] ?? ''); ?>"
                                        alt="<?php echo htmlspecialchars($related['part_name']); ?>"
                                    >
                                </div>

                                <div class="related-part-name">
                                    <?php echo htmlspecialchars($related['part_name']); ?>
                                </div>

                                <div class="related-part-category">
                                    <?php echo htmlspecialchars($related['category']); ?>
                                </div>

                                <div class="related-part-price">
                                    ₱<?php echo number_format(floatval($related['unit_price']), 2); ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        <?php endif; ?>

    </div>
</main>
</div>

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
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>