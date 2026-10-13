<?php
require_once __DIR__ . '/../includes/auth.php';

requirePermission('pos.use');

require_once '../config/Database.php';
require_once '../models/Part.php';
require_once '../models/Customer.php';
require_once '../models/POS.php';
require_once '../includes/staff_ui.php';

$database = new Database();
$db = $database->getConnection();

$stmtParts = (new Part($db))->readAll();
$partsData = $stmtParts->fetchAll(PDO::FETCH_ASSOC);

$stmtCustomers = (new Customer($db))->readAll();
$stmtHistory = (new POS($db))->readRecent();
$recentSales = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);

// Items of the recent sales for the ITEMS column (read-only)
$saleItems = [];
if (!empty($recentSales)) {
    $ids = array_map('intval', array_column($recentSales, 'pos_id'));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmtItems = $db->prepare("
        SELECT pi.pos_id, p.part_name, pi.quantity_sold
        FROM pos_item pi
        JOIN part p ON p.part_id = pi.part_id
        WHERE pi.pos_id IN ($placeholders)
        ORDER BY pi.pos_item_id
    ");
    $stmtItems->execute($ids);
    foreach ($stmtItems->fetchAll(PDO::FETCH_ASSOC) as $item) {
        $saleItems[$item['pos_id']][] = $item['part_name'] . ' × ' . intval($item['quantity_sold']);
    }
}

$paymentMethods = ['Cash', 'GCash', 'Bank Transfer', 'Cheque'];
$canVoid = can('pos.void'); // Owner only
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>POS Terminal - Norily's Repair Shop</title>
<link rel="icon" type="image/png" href="../assets/images/logo.png">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>

<body>
<div class="app-wrapper">
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content sp-page" id="spMain">

        <?php
        spPageHead(
            spPortalLabel() . ' · Counter',
            'POS Terminal',
            'Sell parts over the counter. Stock goes down as soon as the sale is paid.'
        );
        cpFlash();
        ?>

        <form action="../controllers/POSController.php" method="POST" id="posForm" class="sp-form-layout">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="checkout">

            <div class="sp-form">
                <!-- 01 Customer -->
                <section class="sp-fsec">
                    <div class="sp-fsec-label">
                        <span class="sp-sec-num">01</span>
                        <h2>Customer</h2>
                        <p>Walk-in guest or a registered customer.</p>
                    </div>

                    <div class="sp-fsec-body">
                        <div class="sp-field">
                            <span class="sp-field-label" id="customerTypeLabel">Customer type</span>
                            <div class="sp-seg" role="radiogroup" aria-labelledby="customerTypeLabel">
                                <label><input type="radio" name="customer_type" value="Walk-in" checked><span>Walk-in guest</span></label>
                                <label><input type="radio" name="customer_type" value="Registered"><span>Registered customer</span></label>
                            </div>
                        </div>

                        <div id="registeredCustomerBox" hidden>
                            <div class="sp-field">
                                <label for="customerId">Registered customer <span class="sp-req">*</span></label>
                                <select name="customer_id" class="cp-input" id="customerId">
                                    <option value="">Select registered customer</option>
                                    <?php while ($cRow = $stmtCustomers->fetch(PDO::FETCH_ASSOC)): ?>
                                        <option value="<?php echo intval($cRow['customer_id']); ?>">
                                            <?php echo htmlspecialchars($cRow['last_name'] . ', ' . $cRow['first_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>

                        <div id="walkinFields" class="sp-fgrid">
                            <div class="sp-field">
                                <label for="walkinCustomerName">Walk-in name</label>
                                <input type="text" name="walkin_customer_name" id="walkinCustomerName" class="cp-input" placeholder="Optional" maxlength="150">
                            </div>
                            <div class="sp-field">
                                <label for="walkinContactNumber">Contact number</label>
                                <input type="text" name="walkin_contact_number" id="walkinContactNumber" class="cp-input" placeholder="09XXXXXXXXX" maxlength="30" inputmode="tel">
                            </div>
                            <div class="sp-field sp-span">
                                <label for="walkinAddress">Address or note</label>
                                <input type="text" name="walkin_address" id="walkinAddress" class="cp-input" maxlength="255">
                                <p class="cp-hint">Walk-in details are saved with this sale only. They are not added to the Customers list.</p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 02 Items -->
                <section class="sp-fsec">
                    <div class="sp-fsec-label">
                        <span class="sp-sec-num">02</span>
                        <h2>Items</h2>
                        <p>Add each part and its quantity.</p>
                    </div>

                    <div class="sp-fsec-body">
                        <div class="sp-lines" id="cart-container"></div>
                        <button type="button" class="sp-add" onclick="addCartRow()">+ Add item</button>
                    </div>
                </section>
            </div>

            <!-- Payment -->
            <aside class="sp-summary" aria-label="Payment">
                <p class="sp-summary-title">Payment</p>
                <div class="sp-summary-row"><span>Items</span><b id="itemCountDisplay">0</b></div>

                <div class="sp-summary-total">
                    <span>Total</span>
                    <strong id="grandTotalDisplay">₱0.00</strong>
                </div>

                <div class="sp-field">
                    <span class="sp-field-label" id="paymentMethodLabel">Payment method</span>
                    <div class="sp-seg is-grid" role="radiogroup" aria-labelledby="paymentMethodLabel">
                        <?php foreach ($paymentMethods as $i => $method): ?>
                            <label><input type="radio" name="payment_method" value="<?= cpEscape($method) ?>"<?= $i === 0 ? ' checked' : '' ?>><span><?= cpEscape($method) ?></span></label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="sp-field">
                    <label for="referenceNumber">Reference no. <span class="sp-req" id="referenceStar" hidden>*</span></label>
                    <input type="text" name="reference_number" id="referenceNumber" class="cp-input" placeholder="Optional for Cash">
                    <p class="cp-hint" id="referenceNote">Required for GCash, Bank Transfer and Cheque.</p>
                </div>

                <button type="submit" class="cp-btn cp-btn-dark sp-submit">Process payment</button>
            </aside>
        </form>

        <!-- 03 Recent sales -->
        <section class="sp-sec" aria-label="Recent sales">
            <?php spSectionHead('03', 'Recent sales', can('billing.manage') ? '<a href="invoice_history.php" class="sp-arrow-link">Invoice history ↗</a>' : ''); ?>

            <table class="cp-table sp-table">
                <thead>
                    <tr>
                        <th scope="col">Ref</th>
                        <th scope="col">Customer</th>
                        <th scope="col">Items</th>
                        <th scope="col">Method</th>
                        <th scope="col">Status</th>
                        <th scope="col">Amount</th>
                        <th scope="col"><span class="visually-hidden">Receipt</span></th>
                    </tr>
                </thead>
                <tbody data-cp-paginate="10" data-cp-noun="sale">
                    <?php foreach ($recentSales as $row): ?>
                        <?php
                        $customerDisplay = 'Walk-in';
                        if (!empty($row['customer_id'])) {
                            $customerDisplay = trim(($row['customer_first_name'] ?? '') . ' ' . ($row['customer_last_name'] ?? ''));
                            $customerDisplay = $customerDisplay !== '' ? $customerDisplay : 'Registered Customer';
                        } elseif (!empty($row['walkin_customer_name'])) {
                            $customerDisplay = $row['walkin_customer_name'];
                        }
                        $receiptNo = 'POS-' . str_pad($row['pos_id'], 5, '0', STR_PAD_LEFT);
                        ?>
                        <tr>
                            <td data-label="Ref">
                                <span class="sp-ref"><?= cpEscape($receiptNo) ?></span>
                                <span class="cp-cell-sub"><?= date('M d · g:i A', strtotime($row['transaction_date'])) ?></span>
                            </td>
                            <td data-label="Customer"><?= cpEscape($customerDisplay) ?></td>
                            <td data-label="Items"><?= cpEscape(implode(', ', $saleItems[$row['pos_id']] ?? []) ?: '—') ?></td>
                            <td data-label="Method">
                                <div>
                                    <?= cpEscape($row['payment_method']) ?>
                                    <?php if (!empty($row['reference_number'])): ?>
                                        <span class="cp-cell-sub">ref <?= cpEscape($row['reference_number']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td data-label="Status">
                                <div>
                                    <?= cpBadge($row['status']) ?>
                                    <?php if (!empty($row['void_reason'])): ?>
                                        <span class="cp-cell-sub">Voided: <?= cpEscape(html_entity_decode($row['void_reason'], ENT_QUOTES, 'UTF-8')) ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td data-label="Amount" class="sp-mono"><?= cpMoney($row['total_amount']) ?></td>
                            <td class="cp-num">
                                <div class="sp-row-actions">
                                    <a href="print_pos_receipt.php?pos_id=<?= intval($row['pos_id']) ?>" target="_blank"
                                       class="cp-btn cp-btn-line cp-btn-sm"
                                       data-sp-doc data-sp-doc-kind="Sales receipt" data-sp-doc-ref="<?= cpEscape($receiptNo) ?>">Receipt</a>
                                    <?php if ($canVoid && $row['status'] === 'Completed'): ?>
                                        <button type="button" class="cp-btn cp-btn-line cp-btn-sm sp-btn-cancel" data-sp-open="#voidSaleDialog"
                                                data-pos-id="<?= intval($row['pos_id']) ?>" data-ref="<?= cpEscape($receiptNo) ?>"
                                                data-amount="<?= cpEscape(cpMoney($row['total_amount'])) ?>">Void</button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recentSales)): ?>
                        <tr class="sp-empty-row"><td colspan="7">No sales recorded yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    </main>
</div>

<?php if ($canVoid): ?>
    <!-- Void a sale (Owner only): reason required, stock goes back -->
    <div class="sp-dialog" id="voidSaleDialog" role="dialog" aria-modal="true" aria-labelledby="voidSaleTitle" hidden>
        <div class="sp-dialog-box">
            <form action="../controllers/POSController.php" method="POST" id="voidSaleForm">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="void">
                <input type="hidden" name="pos_id" id="voidPosId" value="">

                <div class="cp-modal-head">
                    <div>
                        <p class="cp-label sp-mono" id="voidSaleRef"></p>
                        <h2 class="cp-modal-title" id="voidSaleTitle">Void sale</h2>
                    </div>
                    <button type="button" class="cp-modal-x" data-sp-close aria-label="Close">&times;</button>
                </div>

                <div class="sp-dialog-body">
                    <p class="sp-callout is-bad" id="voidSaleNote"></p>
                    <div class="sp-field">
                        <label for="voidReason">Reason for voiding <span class="sp-req">*</span></label>
                        <textarea name="void_reason" id="voidReason" class="cp-input" rows="3" maxlength="500" placeholder="e.g. wrong item rung up" required></textarea>
                    </div>
                </div>

                <div class="cp-modal-foot">
                    <button type="button" class="cp-btn cp-btn-line" data-sp-close>Keep sale</button>
                    <button type="submit" class="cp-btn cp-btn-dark">Void sale</button>
                </div>
            </form>
        </div>
    </div>
    <script>
    document.getElementById('voidSaleDialog').addEventListener('sp:open', function (event) {
        const btn = event.detail.trigger;
        if (!btn) return;
        document.getElementById('voidPosId').value = btn.dataset.posId;
        document.getElementById('voidSaleRef').textContent = btn.dataset.ref;
        document.getElementById('voidSaleNote').textContent = 'The ' + btn.dataset.amount + ' sale is marked Cancelled and keeps its number. Every part sold goes back into stock. This is recorded in the activity log.';
        document.getElementById('voidReason').value = '';
    });
    document.getElementById('voidSaleForm').addEventListener('submit', function (e) {
        const reason = document.getElementById('voidReason');
        reason.value = reason.value.trim();
        if (reason.value === '') { e.preventDefault(); reason.focus(); }
    });
    </script>
<?php endif; ?>

<script>
const partsData = <?php echo json_encode($partsData); ?>;

function peso(value) {
    return '₱' + Number(value).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function escapeText(value) {
    return String(value).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

let cartRowId = 0;

function addCartRow() {
    let options = '<option value="" disabled selected>Select part</option>';

    partsData.forEach(p => {
        const stock = parseInt(p.quantity_on_hand);
        const lowStock = parseInt(p.low_stock_threshold || 4);

        if (stock > 0) {
            const stockLabel = stock <= lowStock ? `${stock} left · low` : `${stock} left`;
            options += `<option value="${p.part_id}" data-price="${p.unit_price}" data-stock="${stock}">${escapeText(p.part_name)} · ${peso(p.unit_price)} · ${stockLabel}</option>`;
        }
    });

    const id = ++cartRowId;
    const rowHTML = `
        <div class="sp-line cart-row">
            <div class="sp-field">
                <label for="cartPart${id}">Part</label>
                <select name="part_ids[]" id="cartPart${id}" class="cp-input part-select" onchange="onPartChange(this)" required>${options}</select>
            </div>
            <div class="sp-field">
                <label for="cartQty${id}">Qty</label>
                <input type="number" name="part_qtys[]" id="cartQty${id}" class="cp-input qty-input" min="1" value="1"
                       onchange="calculateTotal()" oninput="calculateTotal()" required>
            </div>
            <div class="sp-field">
                <label for="cartSub${id}">Subtotal</label>
                <input type="text" id="cartSub${id}" class="cp-input subtotal-display sp-mono" value="—" readonly tabindex="-1">
                <input type="hidden" name="part_prices[]" class="price-input">
            </div>
            <button type="button" class="sp-line-x" aria-label="Remove item" onclick="this.closest('.cart-row').remove(); calculateTotal();">&times;</button>
        </div>
    `;

    document.getElementById('cart-container').insertAdjacentHTML('beforeend', rowHTML);
    calculateTotal();
}

function onPartChange(select) {
    const row = select.closest('.cart-row');
    const selected = select.options[select.selectedIndex];
    const stock = parseInt(selected.getAttribute('data-stock') || 0);

    const qtyInput = row.querySelector('.qty-input');
    qtyInput.max = stock;

    if (parseInt(qtyInput.value) > stock) {
        qtyInput.value = stock;
    }

    calculateTotal();
}

function calculateTotal() {
    let grandTotal = 0;
    let itemCount = 0;

    document.querySelectorAll('.cart-row').forEach(row => {
        const select = row.querySelector('.part-select');
        const qtyInput = row.querySelector('.qty-input');
        const subtotal = row.querySelector('.subtotal-display');

        if (select.selectedIndex > 0) {
            const selected = select.options[select.selectedIndex];
            const price = parseFloat(selected.getAttribute('data-price'));
            const stock = parseInt(selected.getAttribute('data-stock'));
            let qty = parseInt(qtyInput.value);

            if (isNaN(qty) || qty < 1) {
                qty = 1;
                qtyInput.value = 1;
            }

            if (qty > stock) {
                qty = stock;
                qtyInput.value = stock;
                cpFieldError(qtyInput, 'Only ' + stock + ' in stock.');
            }

            row.querySelector('.price-input').value = price.toFixed(2);
            subtotal.value = peso(price * qty);
            grandTotal += price * qty;
            itemCount += qty;
        } else {
            subtotal.value = '—';
        }
    });

    document.getElementById('grandTotalDisplay').innerText = peso(grandTotal);
    document.getElementById('itemCountDisplay').innerText = itemCount;
}

function checkedValue(name) {
    const input = document.querySelector(`input[name="${name}"]:checked`);
    return input ? input.value : '';
}

function toggleCustomerFields() {
    const registeredBox = document.getElementById('registeredCustomerBox');
    const walkinFields = document.getElementById('walkinFields');
    const customerId = document.getElementById('customerId');

    if (checkedValue('customer_type') === 'Registered') {
        registeredBox.hidden = false;
        walkinFields.hidden = true;
        customerId.required = true;
    } else {
        registeredBox.hidden = true;
        walkinFields.hidden = false;
        customerId.required = false;
        customerId.value = '';
    }
}

function toggleReferenceField() {
    const reference = document.getElementById('referenceNumber');
    const isCash = checkedValue('payment_method') === 'Cash';

    reference.placeholder = isCash ? 'Optional for Cash' : 'Required';
    reference.required = !isCash;
    document.getElementById('referenceStar').hidden = isCash;
}

document.getElementById('posForm').addEventListener('submit', function (e) {
    const cartRows = document.querySelectorAll('.cart-row');
    const method = checkedValue('payment_method');
    const reference = document.getElementById('referenceNumber').value.trim();
    const customerId = document.getElementById('customerId').value;

    if (cartRows.length === 0) {
        e.preventDefault();
        cpNotice(document.querySelector('#posForm .sp-submit'), 'Add at least one item before processing the payment.');
        return;
    }

    if (checkedValue('customer_type') === 'Registered' && customerId === '') {
        e.preventDefault();
        cpFieldError(document.getElementById('customerId'), 'Select a registered customer.');
        return;
    }

    if (['GCash', 'Bank Transfer', 'Cheque'].includes(method) && reference === '') {
        e.preventDefault();
        cpFieldError(document.getElementById('referenceNumber'), 'Reference number is required for ' + method + ' payments.');
    }
});

document.querySelectorAll('input[name="customer_type"]').forEach(input => input.addEventListener('change', toggleCustomerFields));
document.querySelectorAll('input[name="payment_method"]').forEach(input => input.addEventListener('change', toggleReferenceField));

toggleCustomerFields();
toggleReferenceField();
addCartRow();
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
