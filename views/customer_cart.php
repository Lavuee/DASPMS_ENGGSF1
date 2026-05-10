<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Customer') {
    header("Location: login.php");
    exit;
}

$cart = $_SESSION['cart'] ?? [];
$grandTotal = 0;

foreach ($cart as $item) {
    $grandTotal += floatval($item['price']) * intval($item['qty']);
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
<title>My Cart - Norily's Repair Shop</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/style.css">

<style>
    .main-content {
        overflow-x: hidden;
    }

    .cart-page {
        width: 100%;
        max-width: 100%;
    }

    .cart-topbar {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 1.6rem;
    }

    .cart-topbar h2 {
        font-size: 1.8rem;
        font-weight: 900;
        color: var(--dashboard-text-main);
        margin-bottom: 0.25rem;
        line-height: 1.1;
    }

    .cart-topbar p {
        color: var(--dashboard-text-muted);
        margin-bottom: 0;
        font-size: 0.92rem;
        font-weight: 500;
    }

    .continue-btn {
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
        text-decoration: none;
    }

    .continue-btn:hover {
        background: rgba(255, 255, 255, 0.65);
        color: var(--black);
        border-color: rgba(17, 24, 39, 0.25);
    }

    .cart-alert {
        border-radius: 14px;
        margin-bottom: 1rem;
        font-size: 0.9rem;
    }

    .cart-layout {
        display: block;
        width: 100%;
    }

    .cart-items-panel {
        background: transparent;
        border: none;
        box-shadow: none;
        min-width: 0;
    }

    .cart-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding-bottom: 0.85rem;
        border-bottom: 1px solid rgba(17, 24, 39, 0.10);
    }

    .select-all-wrap {
        display: inline-flex;
        align-items: center;
        gap: 0.55rem;
        font-size: 0.86rem;
        font-weight: 900;
        color: var(--dashboard-text-main);
        cursor: pointer;
        user-select: none;
    }

    .cart-check,
    .select-all-check {
        width: 17px;
        height: 17px;
        border: 1px solid rgba(17, 24, 39, 0.24);
        cursor: pointer;
        accent-color: var(--dashboard-primary);
    }

    .cart-toolbar-note {
        color: var(--dashboard-text-muted);
        font-size: 0.78rem;
        font-weight: 600;
    }

    .cart-list {
        display: flex;
        flex-direction: column;
        gap: 0;
    }

    .cart-item {
        display: grid;
        grid-template-columns: 26px 36px 72px minmax(0, 1fr) 110px 125px;
        gap: 1rem;
        align-items: center;
        padding: 1.15rem 0;
        border-bottom: 1px solid rgba(17, 24, 39, 0.10);
    }

    .cart-select-wrap {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .remove-mini-form {
        margin: 0;
    }

    .remove-mini-btn {
        width: 30px;
        height: 30px;
        border: none;
        background: transparent;
        color: var(--dashboard-text-muted);
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: 0.2s ease;
    }

    .remove-mini-btn:hover {
        background: #fee2e2;
        color: #b91c1c;
    }

    .cart-image {
        width: 72px;
        height: 72px;
        background: rgba(255, 255, 255, 0.45);
        border: 1px solid rgba(17, 24, 39, 0.05);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .cart-image img {
        max-width: 62px;
        max-height: 62px;
        object-fit: contain;
    }

    .cart-name {
        color: var(--dashboard-text-main);
        font-size: 0.95rem;
        font-weight: 900;
        margin-bottom: 0.25rem;
        line-height: 1.25;
    }

    .cart-meta {
        color: var(--dashboard-text-muted);
        font-size: 0.8rem;
        line-height: 1.45;
    }

    .cart-price {
        color: var(--black);
        font-size: 0.95rem;
        font-weight: 900;
        white-space: nowrap;
    }

    .cart-subtotal-note {
        color: var(--dashboard-text-muted);
        font-size: 0.74rem;
        margin-top: 0.15rem;
    }

    .qty-form {
        margin: 0;
    }

    .qty-control {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 0.45rem;
    }

    .qty-input {
        width: 70px;
        height: 38px;
        border: 1px solid rgba(17, 24, 39, 0.16);
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.62);
        color: var(--black);
        text-align: center;
        font-size: 0.9rem;
        font-weight: 900;
        box-shadow: none;
    }

    .qty-input:focus {
        border-color: var(--dashboard-primary);
        box-shadow: 0 0 0 4px rgba(245, 197, 24, 0.18);
    }

    .update-btn {
        min-height: 38px;
        border-radius: 10px;
        background: var(--dashboard-primary);
        border: 1px solid var(--dashboard-primary);
        color: var(--black);
        font-size: 0.82rem;
        font-weight: 900;
        padding: 0.45rem 0.7rem;
        transition: 0.2s ease;
    }

    .update-btn:hover {
        background: var(--black);
        border-color: var(--black);
        color: var(--white);
    }

    .summary-panel {
        position: static;
        background: transparent;
        border-left: none;
        border-top: 1px solid rgba(17, 24, 39, 0.16);
        padding-left: 0;
        padding-top: 1.2rem;
        margin-top: 1.25rem;
        min-width: 0;
    }

    .summary-title {
        color: var(--dashboard-text-main);
        font-size: 1.15rem;
        font-weight: 900;
        margin-bottom: 0.85rem;
    }

    .checkout-grid {
        display: grid;
        grid-template-columns: 0.85fr 1.1fr 1.15fr;
        border-top: 1px solid rgba(17, 24, 39, 0.10);
        border-bottom: 1px solid rgba(17, 24, 39, 0.10);
        margin-bottom: 1rem;
    }

    .checkout-section {
        padding: 1.05rem 1.25rem;
        min-width: 0;
    }

    .checkout-section:first-child {
        padding-left: 0;
    }

    .checkout-section:last-child {
        padding-right: 0;
    }

    .checkout-section + .checkout-section {
        border-left: 1px solid rgba(17, 24, 39, 0.12);
    }

    .checkout-section-title {
        color: var(--dashboard-text-main);
        font-size: 0.95rem;
        font-weight: 900;
        margin-bottom: 0.9rem;
    }

    .summary-line {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        color: var(--dashboard-text-muted);
        font-size: 0.86rem;
        margin-bottom: 0.75rem;
    }

    .summary-line strong {
        color: var(--black);
        font-weight: 900;
        text-align: right;
    }

    .summary-divider {
        border: 0;
        border-top: 1px solid rgba(17, 24, 39, 0.16);
        margin: 0.95rem 0;
    }

    .summary-total {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 0;
    }

    .summary-total span {
        color: var(--dashboard-text-main);
        font-size: 0.95rem;
        font-weight: 900;
    }

    .summary-total strong {
        color: var(--black);
        font-size: 1.15rem;
        font-weight: 900;
        text-align: right;
    }

    .pickup-schedule-box,
    .payment-method-box {
        border: none;
        padding: 0;
        margin-bottom: 0;
    }

    .pickup-label,
    .payment-label {
        display: block;
        color: var(--dashboard-text-muted);
        font-size: 0.72rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.35px;
        margin-bottom: 0.35rem;
    }

    .pickup-control,
    .payment-control {
        width: 100%;
        min-height: 42px;
        border: 1px solid rgba(17, 24, 39, 0.16);
        border-radius: 12px;
        background: rgba(255, 255, 255, 0.62);
        color: var(--dashboard-text-main);
        font-size: 0.88rem;
        font-weight: 700;
        padding: 0.55rem 0.75rem;
        box-shadow: none;
    }

    .pickup-control:focus,
    .payment-control:focus {
        border-color: var(--dashboard-primary);
        box-shadow: 0 0 0 4px rgba(245, 197, 24, 0.18);
        outline: none;
    }

    .pickup-textarea,
    .payment-textarea {
        min-height: 78px;
        resize: vertical;
        line-height: 1.5;
    }

    .pickup-helper,
    .payment-helper,
    .payment-mini-note {
        color: var(--dashboard-text-muted);
        font-size: 0.75rem;
        line-height: 1.5;
        margin-top: 0.55rem;
    }

    .gcash-fields {
        display: none;
        margin-top: 1rem;
    }

    .gcash-downpayment-box {
        display: none;
        margin-top: 1rem;
    }

    .gcash-full-note {
        display: none;
        margin-top: 1rem;
    }

    .reservation-note {
        background: rgba(245, 197, 24, 0.16);
        border: 1px solid rgba(245, 197, 24, 0.35);
        color: var(--dashboard-text-main);
        border-radius: 14px;
        padding: 0.85rem 1rem;
        font-size: 0.82rem;
        line-height: 1.55;
        margin-bottom: 0.9rem;
    }

    .checkout-button-row {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 0.75rem;
        flex-wrap: wrap;
        margin-top: 0.95rem;
    }

    .clear-cart-form {
        margin: 0;
    }

    .reserve-btn,
    .clear-btn,
    .browse-empty-btn {
        min-height: 44px;
        border-radius: 0;
        font-size: 0.9rem;
        font-weight: 900;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.45rem;
        transition: 0.2s ease;
    }

    .reserve-btn,
    .clear-btn {
        width: 230px;
        min-width: 230px;
        max-width: 230px;
    }

    .reserve-btn {
        background: var(--black);
        border: 1px solid var(--black);
        color: var(--white);
    }

    .reserve-btn:hover {
        background: var(--dashboard-primary);
        border-color: var(--dashboard-primary);
        color: var(--black);
    }

    .reserve-btn:disabled {
        opacity: 0.55;
        cursor: not-allowed;
    }

    .clear-btn {
        background: transparent;
        border: 1px solid rgba(17, 24, 39, 0.38);
        color: var(--dashboard-text-main);
    }

    .clear-btn:hover {
        background: #fee2e2;
        border-color: #fecaca;
        color: #b91c1c;
    }

    .empty-cart {
        background: rgba(255, 255, 255, 0.28);
        border: 1px solid rgba(17, 24, 39, 0.06);
        border-radius: 18px;
        padding: 3.4rem 1.5rem;
        text-align: center;
        color: var(--dashboard-text-muted);
        min-height: 340px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }

    .empty-cart i.empty-icon {
        display: block;
        font-size: 2.5rem;
        color: var(--dashboard-primary);
        margin-bottom: 0.9rem;
    }

    .empty-cart h4 {
        color: var(--dashboard-text-main);
        font-size: 1.25rem;
        font-weight: 900;
        margin-bottom: 0.45rem;
    }

    .empty-cart p {
        font-size: 0.9rem;
        margin-bottom: 1rem;
    }

    .browse-empty-btn {
        background: var(--black);
        border: 1px solid var(--black);
        color: var(--white);
        padding: 0.7rem 1.2rem;
        text-decoration: none;
    }

    .browse-empty-btn:hover {
        background: var(--dashboard-primary);
        border-color: var(--dashboard-primary);
        color: var(--black);
    }

    @media (max-width: 1199.98px) {
        .checkout-grid {
            grid-template-columns: 1fr;
            border-top: 1px solid rgba(17, 24, 39, 0.10);
            border-bottom: 1px solid rgba(17, 24, 39, 0.10);
        }

        .checkout-section,
        .checkout-section:first-child,
        .checkout-section:last-child {
            padding: 1rem 0;
        }

        .checkout-section + .checkout-section {
            border-left: none;
            border-top: 1px solid rgba(17, 24, 39, 0.10);
        }
    }

    @media (max-width: 991.98px) {
        .cart-item {
            grid-template-columns: 24px 30px 68px minmax(0, 1fr);
        }

        .qty-form,
        .cart-price-wrap {
            grid-column: 4 / -1;
        }

        .qty-control {
            justify-content: flex-start;
        }
    }

    @media (max-width: 767.98px) {
        .cart-topbar {
            flex-direction: column;
            align-items: stretch;
        }

        .continue-btn {
            width: 100%;
            justify-content: center;
        }

        .cart-topbar h2 {
            font-size: 1.55rem;
        }

        .cart-toolbar {
            flex-direction: column;
            align-items: flex-start;
        }

        .cart-item {
            grid-template-columns: 24px 28px 62px minmax(0, 1fr);
            gap: 0.75rem;
        }

        .cart-image {
            width: 62px;
            height: 62px;
        }

        .cart-image img {
            max-width: 54px;
            max-height: 54px;
        }

        .qty-control {
            flex-wrap: wrap;
        }

        .qty-input {
            width: 100%;
            max-width: 90px;
        }

        .checkout-button-row {
            flex-direction: column;
        }

        .reserve-btn,
        .clear-btn {
            width: 100%;
            min-width: 0;
            max-width: 320px;
        }

        .clear-cart-form {
            width: 100%;
            max-width: 320px;
        }

        .clear-cart-form .clear-btn {
            max-width: 100%;
        }
    }
</style>
</head>

<body>

<div class="app-wrapper">
<?php include '../includes/sidebar.php'; ?>

<main class="main-content">
    <div class="cart-page">

        <div class="cart-topbar">
            <div>
                <h2>My Cart</h2>
                <p>Review selected parts before submitting your reservation.</p>
            </div>

            <?php if (!empty($cart)): ?>
                <a href="customer_parts.php" class="btn continue-btn">
                    <i class="bi bi-arrow-left"></i>
                    Continue Browsing
                </a>
            <?php endif; ?>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show cart-alert" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show cart-alert" role="alert">
                <i class="bi bi-exclamation-circle-fill me-2"></i>
                <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (empty($cart)): ?>
            <div class="empty-cart">
                <i class="bi bi-cart-x empty-icon"></i>
                <h4>Your cart is empty</h4>
                <p>Browse available parts and add items for reservation.</p>

                <a href="customer_parts.php" class="btn browse-empty-btn">
                    <i class="bi bi-box-seam"></i>
                    Browse Parts
                </a>
            </div>
        <?php else: ?>
            <div class="cart-layout">

                <div class="cart-items-panel">
                    <div class="cart-toolbar">
                        <label class="select-all-wrap">
                            <input type="checkbox" id="selectAllItems" class="select-all-check">
                            Select all items
                        </label>

                        <div class="cart-toolbar-note">
                            Choose only the items you want to reserve.
                        </div>
                    </div>

                    <div class="cart-list">
                        <?php foreach ($cart as $part_id => $item): ?>
                            <?php
                                $subtotal = floatval($item['price']) * intval($item['qty']);
                            ?>

                            <div class="cart-item">
                                <div class="cart-select-wrap">
                                    <input
                                        type="checkbox"
                                        name="selected_items[]"
                                        value="<?php echo intval($part_id); ?>"
                                        form="reserveForm"
                                        class="cart-check"
                                        data-subtotal="<?php echo htmlspecialchars($subtotal); ?>"
                                    >
                                </div>

                                <form
                                    action="../controllers/CartController.php"
                                    method="POST"
                                    class="remove-mini-form"
                                    onsubmit="return confirm('Remove this item from your cart?');"
                                >
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="part_id" value="<?php echo intval($part_id); ?>">

                                    <button type="submit" class="remove-mini-btn" title="Remove item">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </form>

                                <div class="cart-image">
                                    <img
                                        src="<?php echo partImagePath($item['image'] ?? ''); ?>"
                                        alt="<?php echo htmlspecialchars($item['name']); ?>"
                                    >
                                </div>

                                <div>
                                    <div class="cart-name">
                                        <?php echo htmlspecialchars($item['name']); ?>
                                    </div>

                                    <div class="cart-meta">
                                        ₱<?php echo number_format(floatval($item['price']), 2); ?> each
                                        <br>
                                        Stock: <?php echo intval($item['stock']); ?>
                                    </div>
                                </div>

                                <form action="../controllers/CartController.php" method="POST" class="qty-form">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="part_id" value="<?php echo intval($part_id); ?>">

                                    <div class="qty-control">
                                        <input
                                            type="number"
                                            name="quantity"
                                            class="form-control qty-input"
                                            min="1"
                                            max="<?php echo intval($item['stock']); ?>"
                                            value="<?php echo intval($item['qty']); ?>"
                                            required
                                        >

                                        <button type="submit" class="btn update-btn">
                                            Update
                                        </button>
                                    </div>
                                </form>

                                <div class="cart-price-wrap text-end">
                                    <div class="cart-price">
                                        ₱<?php echo number_format($subtotal, 2); ?>
                                    </div>
                                    <div class="cart-subtotal-note">
                                        Subtotal
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <section class="summary-panel">
                    <h5 class="summary-title">Checkout Details</h5>

                    <form action="../controllers/CheckoutController.php" method="POST" class="mb-2" id="reserveForm">
                        <div class="checkout-grid">

                            <div class="checkout-section">
                                <h6 class="checkout-section-title">Cart Totals</h6>

                                <div class="summary-line">
                                    <span>Selected Items</span>
                                    <strong id="selectedItemCount">0</strong>
                                </div>

                                <div class="summary-line">
                                    <span>Total Items in Cart</span>
                                    <strong><?php echo count($cart); ?></strong>
                                </div>

                                <div class="summary-line">
                                    <span>Reservation Type</span>
                                    <strong>Pickup</strong>
                                </div>

                                <div class="summary-line">
                                    <span>Payment</span>
                                    <strong id="paymentSummaryDisplay">At Shop</strong>
                                </div>

                                <hr class="summary-divider">

                                <div class="summary-total">
                                    <span>Selected Total</span>
                                    <strong id="selectedTotalDisplay">₱0.00</strong>
                                </div>
                            </div>

                            <div class="checkout-section">
                                <h6 class="checkout-section-title">Preferred Pickup</h6>

                                <div class="pickup-schedule-box">
                                    <label class="pickup-label">Preferred Pickup Date</label>
                                    <input
                                        type="date"
                                        name="preferred_pickup_date"
                                        class="pickup-control"
                                        min="<?php echo date('Y-m-d'); ?>"
                                        required
                                    >

                                    <label class="pickup-label mt-3">Preferred Pickup Time</label>
                                    <input
                                        type="time"
                                        name="preferred_pickup_time"
                                        class="pickup-control"
                                        required
                                    >

                                    <label class="pickup-label mt-3">Pickup Notes</label>
                                    <textarea
                                        name="pickup_notes"
                                        class="pickup-control pickup-textarea"
                                        maxlength="500"
                                        placeholder="Example: I will pick up the item after class/work."
                                    ></textarea>

                                    <div class="pickup-helper">
                                        The shop will still confirm item availability and pickup schedule before release.
                                    </div>
                                </div>
                            </div>

                            <div class="checkout-section">
                                <h6 class="checkout-section-title">Payment Method</h6>

                                <div class="payment-method-box">
                                    <label class="payment-label">Payment Method</label>
                                    <select
                                        name="payment_method"
                                        id="paymentMethod"
                                        class="payment-control"
                                        required
                                    >
                                        <option value="Cash on Pickup" selected>Cash on Pickup</option>
                                        <option value="GCash Down Payment">GCash Down Payment</option>
                                        <option value="GCash Full Payment">GCash Full Payment</option>
                                    </select>

                                    <div id="gcashFields" class="gcash-fields">
                                        <label class="payment-label">GCash Reference Number</label>
                                        <input
                                            type="text"
                                            name="gcash_reference"
                                            id="gcashReference"
                                            class="payment-control"
                                            maxlength="100"
                                            placeholder="Enter GCash reference number"
                                        >

                                        <div id="gcashDownpaymentBox" class="gcash-downpayment-box">
                                            <label class="payment-label mt-3">GCash Down Payment Amount</label>
                                            <input
                                                type="number"
                                                name="gcash_payment_amount"
                                                id="gcashPaymentAmount"
                                                class="payment-control"
                                                min="1"
                                                step="0.01"
                                                placeholder="Enter amount sent through GCash"
                                            >

                                            <div class="payment-mini-note">
                                                Down payment must be less than the selected total. The remaining balance will be paid at the shop.
                                            </div>
                                        </div>

                                        <div id="gcashFullNote" class="gcash-full-note">
                                            <div class="payment-helper">
                                                Full payment amount will match the selected total:
                                                <strong id="gcashFullAmountDisplay">₱0.00</strong>.
                                            </div>
                                        </div>
                                    </div>

                                    <label class="payment-label mt-3">Payment Notes</label>
                                    <textarea
                                        name="payment_notes"
                                        class="payment-control payment-textarea"
                                        maxlength="500"
                                        placeholder="Optional note about your payment or reservation."
                                    ></textarea>

                                    <div class="payment-helper">
                                        Cash on Pickup means you will pay at the shop. GCash Down Payment records a partial payment.
                                        GCash Full Payment records the full selected total after admin verification.
                                    </div>
                                </div>
                            </div>

                        </div>

                        <div class="reservation-note">
                            <strong>Note:</strong> Only checked items will be included in the reservation request.
                            GCash payments are manually verified by the shop before approval.
                        </div>
                    </form>

                    <div class="checkout-button-row">
                        <button
                            type="submit"
                            class="btn reserve-btn"
                            id="reserveBtn"
                            form="reserveForm"
                            disabled
                        >
                            <i class="bi bi-check2-circle"></i>
                            Reserve Selected Items
                        </button>

                        <form
                            action="../controllers/CartController.php"
                            method="POST"
                            class="clear-cart-form"
                            onsubmit="return confirm('Clear all items from your cart?');"
                        >
                            <input type="hidden" name="action" value="clear">

                            <button type="submit" class="btn clear-btn">
                                <i class="bi bi-trash3"></i>
                                Clear Cart
                            </button>
                        </form>
                    </div>
                </section>

            </div>
        <?php endif; ?>

    </div>
</main>
</div>

<script>
const cartAlerts = document.querySelectorAll('.cart-alert');

cartAlerts.forEach(alertBox => {
    setTimeout(() => {
        if (!document.body.contains(alertBox)) {
            return;
        }

        alertBox.classList.remove('show');

        setTimeout(() => {
            if (alertBox.parentNode) {
                alertBox.remove();
            }
        }, 200);
    }, 3000);
});

const selectAllItems = document.getElementById('selectAllItems');
const itemChecks = document.querySelectorAll('.cart-check');
const selectedItemCount = document.getElementById('selectedItemCount');
const selectedTotalDisplay = document.getElementById('selectedTotalDisplay');
const reserveForm = document.getElementById('reserveForm');
const reserveBtn = document.getElementById('reserveBtn');

const paymentMethod = document.getElementById('paymentMethod');
const paymentSummaryDisplay = document.getElementById('paymentSummaryDisplay');

const gcashFields = document.getElementById('gcashFields');
const gcashReference = document.getElementById('gcashReference');
const gcashDownpaymentBox = document.getElementById('gcashDownpaymentBox');
const gcashPaymentAmount = document.getElementById('gcashPaymentAmount');
const gcashFullNote = document.getElementById('gcashFullNote');
const gcashFullAmountDisplay = document.getElementById('gcashFullAmountDisplay');

let selectedTotal = 0;

function formatCurrency(amount) {
    return '₱' + amount.toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function updateSelectedSummary() {
    let count = 0;
    let total = 0;

    itemChecks.forEach(check => {
        if (check.checked) {
            count++;
            total += parseFloat(check.dataset.subtotal || 0);
        }
    });

    selectedTotal = total;

    if (selectedItemCount) {
        selectedItemCount.textContent = count;
    }

    if (selectedTotalDisplay) {
        selectedTotalDisplay.textContent = formatCurrency(total);
    }

    if (gcashFullAmountDisplay) {
        gcashFullAmountDisplay.textContent = formatCurrency(total);
    }

    if (reserveBtn) {
        reserveBtn.disabled = count === 0;
    }

    if (gcashPaymentAmount) {
        if (total > 0) {
            gcashPaymentAmount.max = Math.max(total - 0.01, 0).toFixed(2);
        } else {
            gcashPaymentAmount.removeAttribute('max');
        }
    }

    if (selectAllItems) {
        selectAllItems.checked = count === itemChecks.length && itemChecks.length > 0;
        selectAllItems.indeterminate = count > 0 && count < itemChecks.length;
    }
}

function togglePaymentFields() {
    if (!paymentMethod) {
        return;
    }

    const method = paymentMethod.value;
    const isGcash = method === 'GCash Down Payment' || method === 'GCash Full Payment';

    if (paymentSummaryDisplay) {
        paymentSummaryDisplay.textContent = method === 'Cash on Pickup' ? 'At Shop' : method;
    }

    if (gcashFields) {
        gcashFields.style.display = isGcash ? 'block' : 'none';
    }

    if (gcashReference) {
        gcashReference.required = isGcash;

        if (!isGcash) {
            gcashReference.value = '';
        }
    }

    if (gcashDownpaymentBox) {
        gcashDownpaymentBox.style.display = method === 'GCash Down Payment' ? 'block' : 'none';
    }

    if (gcashFullNote) {
        gcashFullNote.style.display = method === 'GCash Full Payment' ? 'block' : 'none';
    }

    if (gcashPaymentAmount) {
        gcashPaymentAmount.required = method === 'GCash Down Payment';

        if (method !== 'GCash Down Payment') {
            gcashPaymentAmount.value = '';
        }
    }
}

if (paymentMethod) {
    paymentMethod.addEventListener('change', function () {
        togglePaymentFields();
    });
}

if (selectAllItems) {
    selectAllItems.addEventListener('change', function () {
        itemChecks.forEach(check => {
            check.checked = this.checked;
        });

        updateSelectedSummary();
        togglePaymentFields();
    });
}

itemChecks.forEach(check => {
    check.addEventListener('change', function () {
        updateSelectedSummary();
        togglePaymentFields();
    });
});

if (reserveForm) {
    reserveForm.addEventListener('submit', function (e) {
        const selected = document.querySelectorAll('.cart-check:checked');

        if (selected.length === 0) {
            e.preventDefault();
            alert('Please select at least one item to reserve.');
            return;
        }

        if (paymentMethod && paymentMethod.value === 'GCash Down Payment') {
            const amount = parseFloat(gcashPaymentAmount.value || 0);

            if (amount <= 0 || amount >= selectedTotal) {
                e.preventDefault();
                alert('Down payment must be greater than zero and less than the selected total.');
                return;
            }
        }

        if (paymentMethod && paymentMethod.value === 'GCash Full Payment') {
            if (selectedTotal <= 0) {
                e.preventDefault();
                alert('Please select valid items before using full payment.');
                return;
            }
        }
    });
}

updateSelectedSummary();
togglePaymentFields();
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>