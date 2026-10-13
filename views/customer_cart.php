<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/paymongo.php';

requirePermission('shop.purchase');

$onlinePaymentAvailable = paymongoIsConfigured();
$onlineMinimum = paymongoMinimumAmount();

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
<title>My Cart - Norily's Customer Portal</title>
<link rel="icon" type="image/png" href="../assets/images/logo.png">
<?php require_once __DIR__ . '/../includes/customer_ui.php'; cpHeadAssets(); ?>
</head>

<body class="cp-body">
<?php include '../includes/sidebar.php'; ?>

<main class="cp-main" id="cpMain">

    <?php
        cpPageHead(
            'My cart',
            'Review selected parts before submitting your reservation. Only checked items are reserved.',
            !empty($cart) ? '<a href="customer_parts.php" class="cp-btn cp-btn-line">← Continue browsing</a>' : ''
        );
    ?>

    <?php cpFlash(); ?>

    <?php if (empty($cart)): ?>
        <div class="cp-empty">
            <strong>Your cart is empty</strong>
            Browse available parts and add items for reservation.
            <div><a href="customer_parts.php" class="cp-btn cp-btn-dark">Browse parts</a></div>
        </div>
    <?php else: ?>
        <div class="cp-cart-layout">

            <div>
                <!-- 01 Items -->
                <section aria-labelledby="secItems">
                    <div class="cp-sec-head">
                        <h2 id="secItems"><span class="cp-sec-num">01</span>Items</h2>
                        <label class="cp-selectall cp-label">
                            <input type="checkbox" id="selectAllItems" class="cp-check">
                            Select all
                        </label>
                    </div>

                    <div>
                        <?php foreach ($cart as $part_id => $item): ?>
                            <?php
                                $subtotal = floatval($item['price']) * intval($item['qty']);
                                $itemImage = !empty($item['image']) ? '../assets/images/parts/' . $item['image'] : '';
                            ?>
                            <div class="cp-cart-line">
                                <input
                                    type="checkbox"
                                    name="selected_items[]"
                                    value="<?php echo intval($part_id); ?>"
                                    form="reserveForm"
                                    class="cp-check cart-check"
                                    data-subtotal="<?php echo htmlspecialchars($subtotal); ?>"
                                    aria-label="Reserve <?php echo htmlspecialchars($item['name']); ?>"
                                >

                                <a href="customer_part_details.php?id=<?php echo intval($part_id); ?>" class="cp-cart-thumb">
                                    <?php echo cpThumb($itemImage, $item['name']); ?>
                                </a>

                                <div class="min-w-0">
                                    <a href="customer_part_details.php?id=<?php echo intval($part_id); ?>" class="cp-cell-title text-decoration-none"><?php echo htmlspecialchars($item['name']); ?></a>
                                    <span class="cp-cell-sub"><span class="cp-mono">₱<?php echo number_format(floatval($item['price']), 2); ?></span> each · Stock <span class="cp-mono"><?php echo intval($item['stock']); ?></span></span>

                                    <form
                                        action="../controllers/CartController.php"
                                        method="POST"
                                        class="mt-2"
                                        data-cp-confirm="Remove this item from your cart?"
                                        data-cp-confirm-title="Remove item?"
                                        data-cp-confirm-yes="Remove"
                                        data-cp-confirm-no="Keep it"
                                    >
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="remove">
                                        <input type="hidden" name="part_id" value="<?php echo intval($part_id); ?>">
                                        <button type="submit" class="cp-link cp-link-danger">Remove</button>
                                    </form>
                                </div>

                                <form action="../controllers/CartController.php" method="POST" class="d-flex align-items-center gap-2">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="part_id" value="<?php echo intval($part_id); ?>">

                                    <div class="cp-stepper">
                                        <label class="visually-hidden" for="qty<?php echo intval($part_id); ?>">Quantity</label>
                                        <input
                                            type="number"
                                            name="quantity"
                                            id="qty<?php echo intval($part_id); ?>"
                                            min="1"
                                            max="<?php echo intval($item['stock']); ?>"
                                            value="<?php echo intval($item['qty']); ?>"
                                            inputmode="numeric"
                                            required
                                        >
                                    </div>
                                    <button type="submit" class="cp-link">Update</button>
                                </form>

                                <div class="text-end">
                                    <span class="cp-cell-title cp-mono fw-normal">₱<?php echo number_format($subtotal, 2); ?></span>
                                    <span class="cp-cell-sub">Subtotal</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <form action="../controllers/CheckoutController.php" method="POST" id="reserveForm">
                    <?= csrfField() ?>

                    <!-- 02 Pickup -->
                    <section class="cp-sec" aria-labelledby="secPickup">
                        <div class="cp-sec-head">
                            <h2 id="secPickup"><span class="cp-sec-num">02</span>Pickup</h2>
                        </div>

                        <div class="cp-form-grid">
                            <div class="cp-field">
                                <label for="pickupDate">Preferred pickup date *</label>
                                <input type="date" name="preferred_pickup_date" id="pickupDate" class="cp-input cp-mono" min="<?php echo date('Y-m-d'); ?>" required>
                            </div>

                            <div class="cp-field">
                                <label for="pickupTime">Preferred pickup time *</label>
                                <input type="time" name="preferred_pickup_time" id="pickupTime" class="cp-input cp-mono" required>
                            </div>

                            <div class="cp-field cp-span">
                                <label for="pickupNotes">Pickup notes</label>
                                <textarea name="pickup_notes" id="pickupNotes" class="cp-input" maxlength="500" rows="3" placeholder="Example: I will pick up the item after class/work."></textarea>
                                <p class="cp-hint">The shop will still confirm item availability and pickup schedule before release.</p>
                            </div>
                        </div>
                    </section>

                    <!-- 03 Payment -->
                    <section class="cp-sec" aria-labelledby="secPayment">
                        <div class="cp-sec-head">
                            <h2 id="secPayment"><span class="cp-sec-num">03</span>Payment</h2>
                        </div>

                        <div class="cp-radios" role="radiogroup" aria-labelledby="secPayment">
                            <label class="cp-radio">
                                <input type="radio" name="payment_method" value="Cash on Pickup" checked required>
                                <span>
                                    <strong>Cash on Pickup</strong>
                                    <span>Pay the full amount at the shop when you claim your parts.</span>
                                </span>
                            </label>
                            <?php if ($onlinePaymentAvailable): ?>
                                <label class="cp-radio">
                                    <input type="radio" name="payment_method" value="GCash Down Payment">
                                    <span>
                                        <strong>GCash Down Payment <span class="cp-faint fw-normal">(pay online)</span></strong>
                                        <span>Pay part now through PayMongo; settle the rest at the shop.</span>
                                    </span>
                                </label>
                                <label class="cp-radio">
                                    <input type="radio" name="payment_method" value="GCash Full Payment">
                                    <span>
                                        <strong>GCash Full Payment <span class="cp-faint fw-normal">(pay online)</span></strong>
                                        <span>Pay the whole selected total now through PayMongo.</span>
                                    </span>
                                </label>
                            <?php endif; ?>
                        </div>

                        <div id="gcashFields" class="cp-pay-card mt-3">
                            <p>After you reserve, you will be taken to PayMongo's secure checkout page to pay with GCash. Your payment is confirmed automatically once PayMongo receives it.</p>

                            <div id="gcashDownpaymentBox" class="cp-field">
                                <label for="gcashPaymentAmount">GCash down payment amount *</label>
                                <input
                                    type="number"
                                    name="gcash_payment_amount"
                                    id="gcashPaymentAmount"
                                    class="cp-input cp-mono"
                                    min="<?= htmlspecialchars(number_format($onlineMinimum, 2, '.', '')) ?>"
                                    step="0.01"
                                    placeholder="Enter amount to pay now through GCash"
                                >
                                <p class="cp-hint">Minimum ₱<?= number_format($onlineMinimum, 2) ?>. Down payment must be less than the selected total. The remaining balance will be paid at the shop.</p>
                            </div>

                            <div id="gcashFullNote">
                                <p class="mb-0">Full payment amount will match the selected total: <strong class="cp-mono" id="gcashFullAmountDisplay">₱0.00</strong>.</p>
                            </div>
                        </div>

                        <div class="cp-field mt-4">
                            <label for="paymentNotes">Payment notes</label>
                            <textarea name="payment_notes" id="paymentNotes" class="cp-input" maxlength="500" rows="3" placeholder="Optional note about your payment or reservation."></textarea>
                            <p class="cp-hint">
                                Cash on Pickup means you will pay at the shop.
                                <?php if ($onlinePaymentAvailable): ?>
                                GCash options take you to PayMongo to pay part or all of the selected total online.
                                <?php else: ?>
                                Online GCash payment is currently unavailable.
                                <?php endif; ?>
                            </p>
                        </div>
                    </section>
                </form>
            </div>

            <aside class="cp-summary" aria-labelledby="summaryTitle">
                <p class="cp-label" id="summaryTitle">Order summary</p>

                <div class="mt-3">
                    <div class="cp-summary-row"><span>Selected items</span><span id="selectedItemCount">0</span></div>
                    <div class="cp-summary-row"><span>Total items in cart</span><span><?php echo count($cart); ?></span></div>
                    <div class="cp-summary-row"><span>Reservation type</span><span>Pickup</span></div>
                    <div class="cp-summary-row"><span>Payment</span><span id="paymentSummaryDisplay">At Shop</span></div>
                </div>

                <div class="cp-summary-total">
                    <span>Selected total</span>
                    <strong id="selectedTotalDisplay">₱0.00</strong>
                </div>

                <button type="submit" class="cp-btn cp-btn-dark cp-btn-block" id="reserveBtn" form="reserveForm" disabled>
                    Reserve selected items
                </button>

                <p class="cp-hint mt-3">
                    <strong>Note:</strong> Only checked items will be included in the reservation request.
                    GCash payments are confirmed automatically by PayMongo.
                </p>

                <form
                    action="../controllers/CartController.php"
                    method="POST"
                    class="mt-3 pt-3 border-top"
                    data-cp-confirm="Clear all items from your cart?"
                    data-cp-confirm-title="Clear cart?"
                    data-cp-confirm-yes="Clear cart"
                    data-cp-confirm-no="Keep items"
                >
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="clear">
                    <button type="submit" class="cp-link cp-link-danger">Clear cart</button>
                </form>
            </aside>

        </div>
    <?php endif; ?>

</main>

<script>
const selectAllItems = document.getElementById('selectAllItems');
const itemChecks = document.querySelectorAll('.cart-check');
const selectedItemCount = document.getElementById('selectedItemCount');
const selectedTotalDisplay = document.getElementById('selectedTotalDisplay');
const reserveForm = document.getElementById('reserveForm');
const reserveBtn = document.getElementById('reserveBtn');

// Payment method is a set of radio rows; this reads whichever one is checked.
const paymentRadios = document.querySelectorAll('input[name="payment_method"]');
const paymentMethod = paymentRadios.length ? {
    get value() {
        const checked = document.querySelector('input[name="payment_method"]:checked');
        return checked ? checked.value : 'Cash on Pickup';
    }
} : null;
const paymentSummaryDisplay = document.getElementById('paymentSummaryDisplay');

const onlineMinimum = <?= json_encode((float) $onlineMinimum) ?>;
const gcashFields = document.getElementById('gcashFields');
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

    if (gcashDownpaymentBox) {
        gcashDownpaymentBox.style.display = method === 'GCash Down Payment' ? 'flex' : 'none';
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

paymentRadios.forEach(radio => {
    radio.addEventListener('change', function () {
        togglePaymentFields();
    });
});

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
            cpNotice(document.getElementById('reserveBtn'), 'Select at least one item to reserve.');
            return;
        }

        if (paymentMethod && paymentMethod.value === 'GCash Down Payment') {
            const amount = parseFloat(gcashPaymentAmount.value || 0);

            if (amount < onlineMinimum || amount >= selectedTotal) {
                e.preventDefault();
                cpFieldError(gcashPaymentAmount, 'Down payment must be at least ' + formatCurrency(onlineMinimum) + ' and less than the selected total.');
                return;
            }
        }

        if (paymentMethod && paymentMethod.value === 'GCash Full Payment') {
            if (selectedTotal < onlineMinimum) {
                e.preventDefault();
                cpNotice(document.getElementById('reserveBtn'), 'Online GCash payments must be at least ' + formatCurrency(onlineMinimum) + '.');
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