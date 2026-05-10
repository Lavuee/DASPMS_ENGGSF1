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
                brand,
                part_name,
                description,
                specification,
                compatibility,
                unit,
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

            if (count($relatedParts) === 0) {
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

function displayText($value, $fallback = 'N/A')
{
    $value = trim(html_entity_decode((string)($value ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    if ($value === '') {
        return htmlspecialchars($fallback);
    }

    return htmlspecialchars($value);
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
        margin-bottom: 1.05rem;
    }

    .detail-topbar h2 {
        font-size: 1.65rem;
        font-weight: 900;
        color: var(--dashboard-text-main);
        margin-bottom: 0.2rem;
        line-height: 1.1;
    }

    .detail-topbar p {
        color: var(--dashboard-text-muted);
        margin-bottom: 0;
        font-size: 0.88rem;
        font-weight: 500;
    }

    .back-btn {
        min-height: 40px;
        border-radius: 12px;
        padding: 0.55rem 0.9rem;
        font-size: 0.84rem;
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
        grid-template-columns: minmax(0, 1.05fr) minmax(360px, 0.95fr);
        gap: 2.35rem;
        align-items: start;
        min-height: 0;
    }

    .product-media-panel {
        min-width: 0;
        align-self: start;
        position: sticky;
        top: 1.2rem;
    }

    .product-media-stage {
        min-height: 465px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0.9rem;
        background:
            radial-gradient(circle at center, rgba(255, 255, 255, 0.50) 0%, rgba(255, 255, 255, 0.20) 42%, rgba(255, 255, 255, 0) 72%);
        border: none;
        box-shadow: none;
        overflow: visible;
    }

    .product-media-stage img {
        max-width: 100%;
        max-height: 425px;
        object-fit: contain;
        display: block;
        filter: drop-shadow(0 20px 22px rgba(17, 24, 39, 0.12));
        transition: 0.25s ease;
    }

    .product-media-stage:hover img {
        transform: translateY(-2px) scale(1.01);
        filter: drop-shadow(0 24px 26px rgba(17, 24, 39, 0.15));
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
        font-size: 0.72rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.9px;
        margin-bottom: 0.45rem;
    }

    .product-title {
        font-size: 2rem;
        font-weight: 900;
        line-height: 1.05;
        color: var(--dashboard-text-main);
        margin-bottom: 0.3rem;
    }

    .product-brand-line {
        color: var(--dashboard-text-muted);
        font-size: 0.86rem;
        font-weight: 700;
        margin-bottom: 0.78rem;
    }

    .product-price-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 0.75rem;
    }

    .product-price {
        font-size: 1.42rem;
        font-weight: 900;
        color: var(--black);
        line-height: 1;
        margin-bottom: 0.22rem;
    }

    .product-unit-line {
        color: var(--dashboard-text-muted);
        font-size: 0.76rem;
        font-weight: 700;
    }

    .stock-badge-available,
    .stock-badge-out {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        border-radius: 999px;
        padding: 0.38rem 0.68rem;
        font-size: 0.76rem;
        font-weight: 900;
        white-space: nowrap;
    }

    .stock-badge-available {
        background: rgba(245, 197, 24, 0.22);
        color: var(--black);
    }

    .stock-badge-available i {
        color: #047857;
    }

    .stock-badge-out {
        background: #fee2e2;
        color: #b91c1c;
    }

    .detail-tabs {
        display: flex;
        align-items: center;
        gap: 1.05rem;
        border-bottom: 1px solid rgba(17, 24, 39, 0.13);
        margin-top: 0.72rem;
        margin-bottom: 0.8rem;
        overflow-x: auto;
        scrollbar-width: none;
    }

    .detail-tabs::-webkit-scrollbar {
        display: none;
    }

    .detail-tab-btn {
        border: none;
        background: transparent;
        color: var(--dashboard-text-muted);
        font-size: 0.76rem;
        font-weight: 900;
        padding: 0 0 0.55rem 0;
        white-space: nowrap;
        position: relative;
        transition: 0.2s ease;
    }

    .detail-tab-btn::after {
        content: '';
        position: absolute;
        left: 0;
        right: 0;
        bottom: -1px;
        height: 2px;
        background: transparent;
        transition: 0.2s ease;
    }

    .detail-tab-btn:hover,
    .detail-tab-btn.active {
        color: var(--dashboard-text-main);
    }

    .detail-tab-btn.active::after {
        background: var(--dashboard-primary);
    }

    .tab-panels {
        min-height: 125px;
        max-height: 125px;
        overflow-y: auto;
        margin-bottom: 0.82rem;
        padding-right: 0.25rem;
        scrollbar-width: thin;
    }

    .tab-panels::-webkit-scrollbar {
        width: 5px;
    }

    .tab-panels::-webkit-scrollbar-thumb {
        background: rgba(17, 24, 39, 0.18);
        border-radius: 999px;
    }

    .tab-panel {
        display: none;
        color: var(--dashboard-text-muted);
        font-size: 0.84rem;
        line-height: 1.62;
    }

    .tab-panel.active {
        display: block;
    }

    .spec-strip {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.52rem;
    }

    .spec-mini {
        border: 1px solid rgba(17, 24, 39, 0.08);
        border-radius: 11px;
        padding: 0.55rem 0.65rem;
        background: rgba(255, 255, 255, 0.24);
    }

    .spec-mini.full {
        grid-column: 1 / -1;
    }

    .spec-label,
    .meta-label {
        display: block;
        color: var(--dashboard-text-muted);
        font-size: 0.62rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.38px;
        margin-bottom: 0.2rem;
    }

    .spec-value,
    .meta-value {
        color: var(--black);
        font-size: 0.78rem;
        font-weight: 850;
        line-height: 1.4;
    }

    .reservation-info-list {
        margin: 0;
        padding-left: 1.05rem;
        color: var(--dashboard-text-muted);
    }

    .reservation-info-list li {
        color: var(--dashboard-text-muted);
        font-size: 0.78rem;
        line-height: 1.55;
        margin-bottom: 0.42rem;
        padding-left: 0.15rem;
    }

    .reservation-info-list li::marker {
        color: var(--dashboard-text-main);
        font-size: 0.85rem;
    }

    .purchase-box {
        padding-top: 0;
    }

    .purchase-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 0.72rem 0;
        border-top: 1px solid rgba(17, 24, 39, 0.10);
        border-bottom: 1px solid rgba(17, 24, 39, 0.10);
        margin-bottom: 0.75rem;
        flex-wrap: wrap;
    }

    .quantity-label {
        color: var(--black);
        font-weight: 900;
        font-size: 0.82rem;
        margin-bottom: 0.42rem;
    }

    .quantity-control-wrap {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 0.28rem;
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
        width: 34px;
        height: 34px;
        border: none;
        background: transparent;
        color: var(--dashboard-text-main);
        font-size: 0.84rem;
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
        width: 54px;
        height: 34px;
        border: none;
        border-left: 1px solid rgba(17, 24, 39, 0.10);
        border-right: 1px solid rgba(17, 24, 39, 0.10);
        text-align: center;
        font-weight: 900;
        font-size: 0.84rem;
        outline: none;
        box-shadow: none;
        background: transparent;
        color: var(--black);
    }

    .max-note {
        color: var(--dashboard-text-muted);
        font-size: 0.7rem;
        margin: 0;
    }

    .action-buttons {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.65rem;
    }

    .add-cart-btn,
    .view-cart-btn,
    .out-stock-btn {
        min-height: 42px;
        border-radius: 0;
        font-size: 0.84rem;
        font-weight: 900;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.45rem;
        transition: 0.2s ease;
    }

    .add-cart-btn {
        background: var(--dashboard-primary);
        border: 1px solid var(--dashboard-primary);
        color: var(--black);
    }

    .add-cart-btn:hover {
        background: var(--black);
        border-color: var(--black);
        color: var(--white);
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
        gap: 0.65rem;
        margin-top: 0.75rem;
    }

    .meta-box {
        border: 1px solid rgba(17, 24, 39, 0.07);
        border-radius: 13px;
        padding: 0.62rem 0.72rem;
        background: rgba(255, 255, 255, 0.24);
    }

    .related-parts-section {
        margin-top: 3.4rem;
        padding-top: 2.15rem;
        border-top: 1px solid rgba(17, 24, 39, 0.10);
    }

    .related-parts-title {
        color: var(--dashboard-text-main);
        font-size: 1.38rem;
        font-weight: 900;
        letter-spacing: 2.2px;
        text-transform: uppercase;
        text-align: center;
        margin-bottom: 2.1rem;
    }

    .related-parts-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 2.4rem;
        max-width: 1120px;
        margin: 0 auto;
    }

    .related-part-card {
        text-align: center;
        text-decoration: none;
        color: inherit;
        display: block;
        transition: 0.2s ease;
        min-width: 0;
    }

    .related-part-card:hover {
        color: inherit;
        transform: translateY(-3px);
    }

    .related-part-image {
        width: 100%;
        min-height: 230px;
        background: transparent;
        border: none;
        border-radius: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0.8rem 0.4rem 1rem 0.4rem;
        margin-bottom: 0.9rem;
        position: relative;
        overflow: visible;
    }

    .related-part-image img {
        max-width: 100%;
        max-height: 185px;
        object-fit: contain;
        filter: drop-shadow(0 18px 18px rgba(17, 24, 39, 0.10));
        transition: 0.2s ease;
    }

    .related-part-card:hover .related-part-image img {
        transform: scale(1.04);
        filter: drop-shadow(0 22px 20px rgba(17, 24, 39, 0.13));
    }

    .related-stock {
        position: absolute;
        top: 0.15rem;
        right: 1.25rem;
        background: rgba(245, 197, 24, 0.95);
        color: var(--black);
        border-radius: 999px;
        padding: 0.28rem 0.58rem;
        font-size: 0.66rem;
        font-weight: 900;
        line-height: 1;
    }

    .related-part-name {
        color: var(--dashboard-text-main);
        font-size: 1.02rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.85px;
        margin-bottom: 0.42rem;
        line-height: 1.28;
    }

    .related-part-category {
        color: var(--dashboard-primary);
        font-size: 0.7rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 1.15px;
        margin-bottom: 0.42rem;
    }

    .related-part-brand {
        color: var(--dashboard-text-muted);
        font-size: 0.75rem;
        font-weight: 800;
        margin-bottom: 0.35rem;
    }

    .related-part-price {
        color: #047857;
        font-size: 0.9rem;
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
            gap: 1.4rem;
            align-items: start;
            min-height: 0;
        }

        .product-media-panel {
            position: static;
        }

        .product-media-stage {
            min-height: 315px;
            justify-content: center;
        }

        .product-media-stage img {
            max-height: 300px;
        }

        .tab-panels {
            max-height: none;
            min-height: 115px;
            overflow-y: visible;
        }

        .related-parts-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            max-width: 760px;
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
            font-size: 1.45rem;
        }

        .product-media-stage {
            min-height: 240px;
            padding: 0.45rem;
        }

        .product-media-stage img {
            max-height: 225px;
        }

        .product-title {
            font-size: 1.55rem;
        }

        .product-price {
            font-size: 1.22rem;
        }

        .product-price-row {
            align-items: flex-start;
        }

        .spec-strip,
        .product-meta-grid,
        .action-buttons {
            grid-template-columns: 1fr;
        }

        .detail-tabs {
            gap: 0.85rem;
        }

        .related-parts-section {
            margin-top: 2.4rem;
            padding-top: 1.6rem;
        }

        .related-parts-title {
            font-size: 1.12rem;
            margin-bottom: 1.35rem;
        }

        .related-parts-grid {
            grid-template-columns: 1fr;
            gap: 1.6rem;
            max-width: 360px;
        }

        .related-part-image {
            min-height: 205px;
            padding: 0.6rem 0.25rem 0.8rem 0.25rem;
        }

        .related-part-image img {
            max-height: 160px;
        }

        .related-stock {
            right: 0.65rem;
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
                            alt="<?php echo displayText($part['part_name'] ?? 'Part image'); ?>"
                        >
                    </div>
                </div>

                <div class="product-info-panel">
                    <div class="product-category">
                        <?php echo displayText($part['category'] ?? ''); ?>
                    </div>

                    <h1 class="product-title">
                        <?php echo displayText($part['part_name'] ?? ''); ?>
                    </h1>

                    <div class="product-brand-line">
                        Brand: <?php echo displayText($part['brand'] ?? '', 'Unspecified'); ?>
                    </div>

                    <div class="product-price-row">
                        <div>
                            <div class="product-price">
                                ₱<?php echo number_format(floatval($part['unit_price']), 2); ?>
                            </div>

                            <div class="product-unit-line">
                                Price per <?php echo displayText($part['unit'] ?? '', 'piece'); ?>
                            </div>
                        </div>

                        <div>
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
                    </div>

                    <div class="detail-tabs" role="tablist" aria-label="Part detail sections">
                        <button type="button" class="detail-tab-btn active" data-tab="overview">Overview</button>
                        <button type="button" class="detail-tab-btn" data-tab="specifications">Specifications</button>
                        <button type="button" class="detail-tab-btn" data-tab="compatibility">Compatibility</button>
                        <button type="button" class="detail-tab-btn" data-tab="pickup">Pickup & Payment</button>
                    </div>

                    <div class="tab-panels">
                        <div class="tab-panel active" id="tab-overview">
                            <?php echo displayMultiline($part['full_description'] ?: $part['description'], 'No description available.'); ?>
                        </div>

                        <div class="tab-panel" id="tab-specifications">
                            <div class="spec-strip">
                                <div class="spec-mini">
                                    <span class="spec-label">Brand</span>
                                    <div class="spec-value">
                                        <?php echo displayText($part['brand'] ?? '', 'Unspecified'); ?>
                                    </div>
                                </div>

                                <div class="spec-mini">
                                    <span class="spec-label">Unit</span>
                                    <div class="spec-value">
                                        <?php echo displayText($part['unit'] ?? '', 'piece'); ?>
                                    </div>
                                </div>

                                <div class="spec-mini">
                                    <span class="spec-label">Category</span>
                                    <div class="spec-value">
                                        <?php echo displayText($part['category'] ?? ''); ?>
                                    </div>
                                </div>

                                <div class="spec-mini">
                                    <span class="spec-label">Availability</span>
                                    <div class="spec-value">
                                        <?php echo intval($part['quantity_on_hand']); ?>
                                        <?php echo displayText($part['unit'] ?? '', 'unit'); ?>(s) available
                                    </div>
                                </div>

                                <div class="spec-mini full">
                                    <span class="spec-label">Technical Specification</span>
                                    <div class="spec-value">
                                        <?php echo displayMultiline($part['specification'] ?? '', 'No specification provided.'); ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="tab-panel" id="tab-compatibility">
                            <?php echo displayMultiline($part['compatibility'] ?? '', 'No compatibility information provided.'); ?>
                        </div>

                        <div class="tab-panel" id="tab-pickup">
                            <ul class="reservation-info-list">
                                <li>This part is reserved online and claimed through shop pickup after confirmation.</li>
                                <li>The shop will still confirm item availability and your preferred pickup schedule.</li>
                                <li>Payment can be Cash on Pickup, GCash Down Payment, or GCash Full Payment during checkout.</li>
                                <li>GCash payments are manually verified by shop staff before approval.</li>
                            </ul>
                        </div>
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
                                <?php echo displayText($part['category'] ?? ''); ?>
                            </div>
                        </div>

                        <div class="meta-box">
                            <span class="meta-label">Availability</span>
                            <div class="meta-value">
                                <?php echo intval($part['quantity_on_hand']); ?>
                                <?php echo displayText($part['unit'] ?? '', 'unit'); ?>(s) available
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
                                        alt="<?php echo displayText($related['part_name'] ?? 'Part image'); ?>"
                                    >
                                </div>

                                <div class="related-part-name">
                                    <?php echo displayText($related['part_name'] ?? ''); ?>
                                </div>

                                <div class="related-part-category">
                                    <?php echo displayText($related['category'] ?? ''); ?>
                                </div>

                                <div class="related-part-brand">
                                    Brand: <?php echo displayText($related['brand'] ?? '', 'Unspecified'); ?>
                                </div>

                                <div class="related-part-price">
                                    ₱<?php echo number_format(floatval($related['unit_price']), 2); ?>
                                    / <?php echo displayText($related['unit'] ?? '', 'piece'); ?>
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
const detailTabButtons = document.querySelectorAll('.detail-tab-btn');
const detailTabPanels = document.querySelectorAll('.tab-panel');

detailTabButtons.forEach(button => {
    button.addEventListener('click', function () {
        const target = this.dataset.tab;

        detailTabButtons.forEach(btn => btn.classList.remove('active'));
        detailTabPanels.forEach(panel => panel.classList.remove('active'));

        this.classList.add('active');

        const activePanel = document.getElementById('tab-' + target);
        if (activePanel) {
            activePanel.classList.add('active');
        }
    });
});

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