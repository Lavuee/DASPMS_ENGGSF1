<?php
session_start();

$basePath = '../';

require_once '../config/Database.php';

$database = new Database();
$db = $database->getConnection();

$selectedId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$selectedPart = null;
$relatedParts = [];
$dbError = '';

try {
    if ($selectedId) {
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
            WHERE part_id = :part_id
              AND is_active = 1
            LIMIT 1
        ");

        $stmt->bindParam(':part_id', $selectedId, PDO::PARAM_INT);
        $stmt->execute();
        $selectedPart = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($selectedPart) {
            $relatedStmt = $db->prepare("
                SELECT
                    part_id,
                    category,
                    part_name,
                    image
                FROM part
                WHERE is_active = 1
                  AND part_id != :part_id
                  AND category = :category
                ORDER BY part_name ASC
                LIMIT 3
            ");

            $relatedStmt->bindParam(':part_id', $selectedId, PDO::PARAM_INT);
            $relatedStmt->bindParam(':category', $selectedPart['category']);
            $relatedStmt->execute();
            $relatedParts = $relatedStmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($relatedParts) === 0) {
                $fallbackStmt = $db->prepare("
                    SELECT
                        part_id,
                        category,
                        part_name,
                        image
                    FROM part
                    WHERE is_active = 1
                      AND part_id != :part_id
                    ORDER BY category ASC, part_name ASC
                    LIMIT 3
                ");

                $fallbackStmt->bindParam(':part_id', $selectedId, PDO::PARAM_INT);
                $fallbackStmt->execute();
                $relatedParts = $fallbackStmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    }
} catch (Exception $e) {
    $dbError = 'Unable to load part details at the moment.';
}

function partImagePath($image)
{
    $fallback = 'default.png';

    if (!$image) {
        return '../assets/images/parts/' . $fallback;
    }

    return '../assets/images/parts/' . htmlspecialchars($image);
}

function formatPrice($price)
{
    return '₱' . number_format((float) $price, 2);
}

function stockStatus($quantity)
{
    $quantity = (int) $quantity;

    if ($quantity <= 0) {
        return 'Out of stock';
    }

    if ($quantity <= 3) {
        return 'Low stock';
    }

    return 'Available';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?= $selectedPart ? htmlspecialchars($selectedPart['part_name']) . ' | Parts' : 'Part Not Found | Parts' ?>
    </title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .part-detail-page {
            background: var(--white);
            border-top: 1px solid var(--border-light);
        }

        .part-detail-wrapper {
            max-width: 1180px;
            margin: 0 auto;
            padding: 3.5rem 1rem 5rem;
        }

        .back-link {
            display: inline-flex;
            color: var(--muted-gray);
            text-decoration: none;
            font-size: 0.95rem;
            margin-bottom: 2.5rem;
            transition: color 0.2s ease;
        }

        .back-link:hover {
            color: var(--yellow-dark);
        }

        .part-detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
        }

        .part-detail-image {
            min-height: 360px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .part-detail-image img {
            max-width: 100%;
            max-height: 330px;
            object-fit: contain;
            display: block;
        }

        .part-detail-info {
            max-width: 520px;
        }

        .breadcrumb-text {
            color: var(--muted-gray);
            font-size: 0.85rem;
            margin-bottom: 1rem;
        }

        .breadcrumb-text a {
            color: var(--yellow-dark);
            text-decoration: none;
            font-weight: 800;
        }

        .part-detail-title {
            color: var(--black);
            font-size: clamp(1.8rem, 4vw, 2.8rem);
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 3px;
            line-height: 1.1;
            margin-bottom: 0.9rem;
        }

        .part-detail-type {
            display: block;
            color: var(--yellow-dark);
            font-size: 0.78rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 1rem;
        }

        .part-detail-price {
            color: var(--black);
            font-size: 1.1rem;
            font-weight: 900;
            margin-bottom: 0.65rem;
        }

        .part-detail-status {
            color: var(--black);
            font-size: 0.95rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
        }

        .part-detail-description {
            color: var(--muted-gray);
            font-size: 1rem;
            line-height: 1.8;
            margin-bottom: 2rem;
        }

        .detail-actions {
            display: flex;
            gap: 0.8rem;
            flex-wrap: wrap;
            margin-bottom: 2rem;
            align-items: center;
        }

        .detail-primary-btn,
        .detail-secondary-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.65rem 1.2rem;
            font-size: 0.78rem;
            font-weight: 900;
            text-transform: uppercase;
            text-decoration: none;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .detail-primary-btn {
            background: var(--primary-yellow);
            color: var(--black);
            border: 1px solid var(--primary-yellow);
        }

        .detail-secondary-btn {
            background: var(--black);
            color: var(--white);
            border: 1px solid var(--black);
        }

        .detail-primary-btn:hover,
        .detail-secondary-btn:hover {
            background: var(--yellow-dark);
            color: var(--black);
            border-color: var(--yellow-dark);
        }

        .detail-note {
            border-top: 1px solid var(--border-light);
            padding-top: 1rem;
            color: var(--muted-gray);
            font-size: 0.9rem;
            line-height: 1.7;
        }

        .qty-input {
            width: 80px;
            border: 1px solid var(--border-light);
            padding: 0.6rem;
            font-weight: 700;
            text-align: center;
        }

        .cart-form {
            display: flex;
            gap: 0.6rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .related-section {
            margin-top: 5rem;
            padding-top: 3rem;
            border-top: 1px solid var(--border-light);
        }

        .related-title {
            color: var(--black);
            font-size: clamp(1rem, 2vw, 1.5rem);
            font-weight: 900;
            letter-spacing: 2.5px;
            text-transform: uppercase;
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .related-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
        }

        .related-card {
            text-align: center;
        }

        .related-card a {
            color: inherit;
            text-decoration: none;
        }

        .related-card img {
            max-width: 88%;
            height: 150px;
            object-fit: contain;
            margin-bottom: 1rem;
            transition: transform 0.2s ease;
        }

        .related-card:hover img {
            transform: scale(1.03);
        }

        .related-card h3 {
            color: var(--black);
            font-size: 0.82rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0.4rem;
        }

        .related-card span {
            color: var(--yellow-dark);
            font-size: 0.65rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }

        .not-found-box {
            max-width: 720px;
            margin: 0 auto;
            text-align: center;
        }

        .not-found-box h1 {
            color: var(--black);
            font-size: 2rem;
            font-weight: 900;
            margin-bottom: 1rem;
        }

        .not-found-box p {
            color: var(--muted-gray);
            line-height: 1.7;
            margin-bottom: 2rem;
        }

        @media (max-width: 991.98px) {
            .part-detail-grid {
                grid-template-columns: 1fr;
                gap: 2.5rem;
            }

            .part-detail-info {
                max-width: 100%;
                text-align: center;
            }

            .detail-actions {
                justify-content: center;
            }

            .related-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 575.98px) {
            .part-detail-wrapper {
                padding: 2.5rem 1rem 4rem;
            }

            .part-detail-image {
                min-height: 240px;
            }

            .part-detail-image img {
                max-height: 220px;
            }

            .related-grid {
                grid-template-columns: 1fr;
            }

            .detail-primary-btn,
            .detail-secondary-btn,
            .cart-form {
                width: 100%;
            }

            .qty-input {
                width: 100%;
            }
        }
    </style>
</head>
<body>

<?php include '../partials/public-navbar.php'; ?>

<main class="part-detail-page">
    <section class="part-detail-wrapper">
        <a href="parts.php" class="back-link">← Back to Parts</a>

        <?php if ($dbError): ?>
            <div class="not-found-box">
                <h1>Unable to Load Part</h1>
                <p><?= htmlspecialchars($dbError) ?></p>

                <a href="parts.php" class="detail-primary-btn">
                    Browse Parts
                </a>
            </div>

        <?php elseif (!$selectedPart): ?>
            <div class="not-found-box">
                <h1>Part Not Found</h1>
                <p>
                    The part you are looking for does not exist, is inactive, or may have been removed.
                    Please return to the parts page and select another item.
                </p>

                <a href="parts.php" class="detail-primary-btn">
                    Browse Parts
                </a>
            </div>

        <?php else: ?>
            <div class="part-detail-grid">
                <div class="part-detail-image">
                    <img
                        src="<?= partImagePath($selectedPart['image'] ?? '') ?>"
                        alt="<?= htmlspecialchars($selectedPart['part_name']) ?>"
                    >
                </div>

                <div class="part-detail-info">
                    <p class="breadcrumb-text">
                        <a href="parts.php">Parts</a> › <?= htmlspecialchars($selectedPart['category']) ?>
                    </p>

                    <h1 class="part-detail-title">
                        <?= htmlspecialchars($selectedPart['part_name']) ?>
                    </h1>

                    <span class="part-detail-type">
                        <?= htmlspecialchars($selectedPart['category']) ?>
                    </span>

                    <p class="part-detail-price">
                        <?= formatPrice($selectedPart['unit_price']) ?>
                    </p>

                    <p class="part-detail-status">
                        Availability: <?= htmlspecialchars(stockStatus($selectedPart['quantity_on_hand'])) ?>
                        <?php if ((int) $selectedPart['quantity_on_hand'] > 0): ?>
                            — <?= (int) $selectedPart['quantity_on_hand'] ?> in stock
                        <?php endif; ?>
                    </p>

                    <p class="part-detail-description">
                        <?= nl2br(htmlspecialchars($selectedPart['full_description'] ?: $selectedPart['description'] ?: 'No detailed description available.')) ?>
                    </p>

                    <div class="detail-actions">
                        <?php if ((int) $selectedPart['quantity_on_hand'] <= 0): ?>

                            <button type="button" class="detail-primary-btn" disabled>
                                Out of Stock
                            </button>

                        <?php elseif (isset($_SESSION['logged_in']) && $_SESSION['role'] === 'Customer'): ?>

                            <form action="../controllers/CartController.php" method="POST" class="cart-form">
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="part_id" value="<?= (int) $selectedPart['part_id'] ?>">

                                <input
                                    type="number"
                                    name="quantity"
                                    value="1"
                                    min="1"
                                    max="<?= (int) $selectedPart['quantity_on_hand'] ?>"
                                    class="qty-input"
                                    required
                                >

                                <button type="submit" class="detail-primary-btn">
                                    Add to Cart
                                </button>
                            </form>

                        <?php else: ?>

                            <a href="../views/login.php" class="detail-primary-btn">
                                Login to Add to Cart
                            </a>

                        <?php endif; ?>

                        <a href="parts.php" class="detail-secondary-btn">
                            Browse More Parts
                        </a>
                    </div>

                    <p class="detail-note">
                        Guests can view part information, but adding items to cart requires login or account registration.
                        Reserved parts are subject to shop confirmation and pickup payment.
                    </p>
                </div>
            </div>

            <?php if (!empty($relatedParts)): ?>
                <div class="related-section">
                    <h2 class="related-title">Related Parts</h2>

                    <div class="related-grid">
                        <?php foreach ($relatedParts as $related): ?>
                            <article class="related-card">
                                <a href="part-details.php?id=<?= urlencode($related['part_id']) ?>">
                                    <img
                                        src="<?= partImagePath($related['image'] ?? '') ?>"
                                        alt="<?= htmlspecialchars($related['part_name']) ?>"
                                    >

                                    <h3><?= htmlspecialchars($related['part_name']) ?></h3>
                                    <span><?= htmlspecialchars($related['category']) ?></span>
                                </a>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</main>

<?php include '../partials/public-footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>