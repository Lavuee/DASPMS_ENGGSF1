<?php
require_once __DIR__ . '/../includes/auth.php';

requirePermission('job_orders.manage');

require_once '../config/Database.php';
require_once '../controllers/RestockAlert.php';
require_once '../includes/business_rules.php';
require_once '../includes/staff_ui.php';

$db = (new Database())->getConnection();
$userId = intval($_SESSION['user_id']);

$error = '';
$success = '';

// ==========================================
// FORM SUBMISSION LOGIC (ACID TRANSACTION)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();

        // 1. INSERT CUSTOMER
        $stmtCust = $db->prepare("
            INSERT INTO customer (first_name, last_name, contact_number, email, address, status) 
            VALUES (?, ?, ?, ?, ?, 'Active')
        ");
        $stmtCust->execute([
            trim($_POST['first_name']),
            trim($_POST['last_name']),
            trim($_POST['contact_number']),
            trim($_POST['email']) ?: null,
            trim($_POST['address']) ?: null
        ]);
        $customerId = $db->lastInsertId();

        // 2. INSERT VEHICLE
        $stmtVeh = $db->prepare("
            INSERT INTO vehicle (customer_id, plate_number, make, model, year, color, status) 
            VALUES (?, ?, ?, ?, ?, ?, 'Active')
        ");
        $stmtVeh->execute([
            $customerId,
            trim(strtoupper($_POST['plate_number'])),
            trim($_POST['make']),
            trim($_POST['model']),
            trim($_POST['year']),
            trim($_POST['color'])
        ]);
        $vehicleId = $db->lastInsertId();

        // 3. GENERATE JOB ORDER NUMBER & INSERT JOB ORDER
        $jobNumber = 'JO-' . strtoupper(substr(uniqid(), -5));
        
        $stmtJo = $db->prepare("
            INSERT INTO job_order (vehicle_id, customer_id, created_by, job_order_number, description, request_source, status, estimated_cost) 
            VALUES (?, ?, ?, ?, ?, 'Walk-in', 'Pending', 0)
        ");
        $stmtJo->execute([
            $vehicleId,
            $customerId,
            $userId,
            $jobNumber,
            trim($_POST['description'])
        ]);
        $jobOrderId = $db->lastInsertId();

        // Labour/services estimate; parts are billed separately via final_cost (see JobOrder::recalculateFinalCost).
        $servicesTotal = 0;
        $partsTotal = 0;
        $usedPartIds = [];

        // 4. INSERT SERVICES
        if (!empty($_POST['services'])) {
            $stmtSvc = $db->prepare("SELECT base_price FROM service WHERE service_id = ?");
            $stmtJoSvc = $db->prepare("
                INSERT INTO job_order_service (job_order_id, service_id, quantity, unit_price, subtotal) 
                VALUES (?, ?, 1, ?, ?)
            ");

            foreach ($_POST['services'] as $serviceId) {
                $stmtSvc->execute([$serviceId]);
                $price = $stmtSvc->fetchColumn();
                if ($price !== false) {
                    $stmtJoSvc->execute([$jobOrderId, $serviceId, $price, $price]);
                    $servicesTotal += $price;
                }
            }
        }

        // 5. INSERT PARTS & DEDUCT INVENTORY
        if (!empty($_POST['part_ids'])) {
            $stmtPartInfo = $db->prepare("SELECT unit_price, quantity_on_hand FROM part WHERE part_id = ? AND is_active = 1 FOR UPDATE");
            $stmtJoPart = $db->prepare("
                INSERT INTO job_order_part (job_order_id, part_id, quantity_used, unit_price_at_use, subtotal, used_by, used_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmtUpdateStock = $db->prepare("UPDATE part SET quantity_on_hand = quantity_on_hand - ? WHERE part_id = ? AND quantity_on_hand >= ?");

            foreach ($_POST['part_ids'] as $index => $partId) {
                $qty = intval($_POST['part_qtys'][$index]);
                if (empty($partId) || $qty <= 0) continue;

                $stmtPartInfo->execute([$partId]);
                $part = $stmtPartInfo->fetch(PDO::FETCH_ASSOC);

                if ($part && $part['quantity_on_hand'] >= $qty) {
                    $subtotal = $part['unit_price'] * $qty;
                    $stmtJoPart->execute([$jobOrderId, $partId, $qty, $part['unit_price'], $subtotal, $userId]);
                    $stmtUpdateStock->execute([$qty, $partId, $qty]);

                    if ($stmtUpdateStock->rowCount() === 0) {
                        throw new Exception("Not enough stock for one of the selected parts.");
                    }

                    $partsTotal += $subtotal;
                    $usedPartIds[] = intval($partId);
                } else {
                    throw new Exception("Not enough stock for one of the selected parts.");
                }
            }
        }

        // 6. UPDATE TOTAL
        $stmtUpdateJo = $db->prepare("UPDATE job_order SET estimated_cost = ?, final_cost = ? WHERE job_order_id = ?");
        $stmtUpdateJo->execute([$servicesTotal, $servicesTotal + $partsTotal, $jobOrderId]);

        $db->commit();
        RestockAlert::checkParts($db, $usedPartIds);

        $_SESSION['success_message'] = "Walk-in transaction created successfully! ($jobNumber)";
        header("Location: job_orders.php");
        exit;

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        $error = "Transaction failed: " . $e->getMessage();
    }
}

// FETCH DATA FOR UI
$services = $db->query("SELECT service_id, service_name, base_price FROM service WHERE is_active = 1 ORDER BY service_name")->fetchAll(PDO::FETCH_ASSOC);
$parts = $db->query("SELECT part_id, part_name, brand, unit_price, quantity_on_hand FROM part WHERE is_active = 1 AND quantity_on_hand > 0 ORDER BY part_name")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Transaction Form - Norily's Repair Shop</title>
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
            'Transaction Form',
            'Record a walk-in repair in one go: customer, vehicle, services and parts. It becomes a job order.',
            '<a href="job_orders.php" class="cp-btn cp-btn-line">Cancel</a>'
        );
        ?>

        <?php if ($error): ?>
            <div class="cp-flash cp-flash-bad" role="alert">
                <span><?= cpEscape($error) ?></span>
                <button type="button" class="cp-flash-close" aria-label="Dismiss" onclick="this.parentElement.remove()">&times;</button>
            </div>
        <?php endif; ?>

        <form action="" method="POST" id="unifiedForm" class="sp-form-layout"
              data-cp-confirm="This registers the customer and the vehicle, and creates a pending job order."
              data-cp-confirm-title="Submit this transaction?"
              data-cp-confirm-yes="Submit transaction"
              data-cp-confirm-no="Go back"
              data-cp-confirm-tone="dark">
            <?= csrfField() ?>

            <div class="sp-form">
                <!-- 01 Customer -->
                <section class="sp-fsec">
                    <div class="sp-fsec-label">
                        <span class="sp-sec-num">01</span>
                        <h2>Customer</h2>
                        <p>Saved as a walk-in customer.</p>
                    </div>
                    <div class="sp-fsec-body">
                        <div class="sp-fgrid">
                            <div class="sp-field">
                                <label for="ctFirst">First name <span class="sp-req">*</span></label>
                                <input type="text" name="first_name" id="ctFirst" class="cp-input" required>
                            </div>
                            <div class="sp-field">
                                <label for="ctLast">Last name <span class="sp-req">*</span></label>
                                <input type="text" name="last_name" id="ctLast" class="cp-input" required>
                            </div>
                            <div class="sp-field">
                                <label for="ctContact">Contact number <span class="sp-req">*</span></label>
                                <input type="text" name="contact_number" id="ctContact" class="cp-input" maxlength="11" placeholder="09XXXXXXXXX" inputmode="tel" required>
                            </div>
                            <div class="sp-field">
                                <label for="ctEmail">Email address</label>
                                <input type="email" name="email" id="ctEmail" class="cp-input" placeholder="Optional">
                            </div>
                            <div class="sp-field sp-span">
                                <label for="ctAddress">Home address</label>
                                <input type="text" name="address" id="ctAddress" class="cp-input">
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 02 Vehicle -->
                <section class="sp-fsec">
                    <div class="sp-fsec-label">
                        <span class="sp-sec-num">02</span>
                        <h2>Vehicle</h2>
                    </div>
                    <div class="sp-fsec-body">
                        <div class="sp-fgrid is-3">
                            <div class="sp-field">
                                <label for="ctPlate">Plate number <span class="sp-req">*</span></label>
                                <input type="text" name="plate_number" id="ctPlate" class="cp-input" placeholder="ABC 1234" required>
                            </div>
                            <div class="sp-field">
                                <label for="ctMake">Make or brand <span class="sp-req">*</span></label>
                                <input type="text" name="make" id="ctMake" class="cp-input" placeholder="e.g. Toyota" required>
                            </div>
                            <div class="sp-field">
                                <label for="ctModel">Model <span class="sp-req">*</span></label>
                                <input type="text" name="model" id="ctModel" class="cp-input" placeholder="e.g. Vios" required>
                            </div>
                            <div class="sp-field">
                                <label for="ctYear">Year <span class="sp-req">*</span></label>
                                <input type="number" name="year" id="ctYear" class="cp-input" min="1950" max="2026" required>
                            </div>
                            <div class="sp-field">
                                <label for="ctColor">Color <span class="sp-req">*</span></label>
                                <input type="text" name="color" id="ctColor" class="cp-input" required>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 03 Repair description -->
                <section class="sp-fsec">
                    <div class="sp-fsec-label">
                        <span class="sp-sec-num">03</span>
                        <h2>Repair description</h2>
                    </div>
                    <div class="sp-fsec-body">
                        <div class="sp-field">
                            <label for="ctDescription">What's wrong <span class="sp-req">*</span></label>
                            <textarea name="description" id="ctDescription" class="cp-input" rows="4" placeholder="Customer's concern and what the mechanic found" required></textarea>
                        </div>
                    </div>
                </section>

                <!-- 04 Services -->
                <section class="sp-fsec">
                    <div class="sp-fsec-label">
                        <span class="sp-sec-num">04</span>
                        <h2>Services</h2>
                        <p>Pick one or more.</p>
                    </div>
                    <div class="sp-fsec-body">
                        <?php if (!empty($services)): ?>
                            <div class="sp-picks">
                                <?php foreach ($services as $svc): ?>
                                    <?php $price = floatval($svc['base_price']); ?>
                                    <label class="sp-pick" for="svc_<?= intval($svc['service_id']) ?>">
                                        <input class="service-checkbox" type="checkbox" name="services[]"
                                               value="<?= intval($svc['service_id']) ?>" id="svc_<?= intval($svc['service_id']) ?>"
                                               data-price="<?= cpEscape($svc['base_price']) ?>">
                                        <span class="sp-pick-name"><?= cpEscape($svc['service_name']) ?></span>
                                        <span class="sp-pick-price sp-mono">
                                            <?= $price > 0 ? cpMoney($price) : cpMoney(MIN_SERVICE_FEE) . ' minimum' ?>
                                        </span>
                                        <span class="sp-pick-box" aria-hidden="true"></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="sp-lines-empty">No active services yet. Add them under Stock › Services.</p>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- 05 Parts -->
                <section class="sp-fsec">
                    <div class="sp-fsec-label">
                        <span class="sp-sec-num">05</span>
                        <h2>Parts</h2>
                        <p>Optional. Deducted from stock.</p>
                    </div>
                    <div class="sp-fsec-body">
                        <div class="sp-lines" id="partsContainer"></div>
                        <button type="button" class="sp-add" onclick="addPartRow()">+ Add part</button>
                    </div>
                </section>
            </div>

            <!-- Summary -->
            <aside class="sp-summary" aria-label="Summary">
                <p class="sp-summary-title">Summary</p>
                <div class="sp-summary-row"><span>Services</span><b id="servicesTotal">₱0.00</b></div>
                <div class="sp-summary-row"><span>Parts</span><b id="partsTotal">₱0.00</b></div>

                <div class="sp-summary-total">
                    <span>Subtotal</span>
                    <strong id="liveTotal">₱0.00</strong>
                </div>

                <button type="submit" class="cp-btn cp-btn-dark sp-submit">Submit transaction</button>

                <p class="sp-summary-note">
                    The job order starts as Pending. Services that need a down payment ask for
                    <?= intval(DOWN_PAYMENT_RATE * 100) ?>% before work starts.
                </p>
            </aside>
        </form>
    </main>
</div>

<template id="partRowTemplate">
    <div class="sp-line part-row">
        <div class="sp-field">
            <label>Part</label>
            <select name="part_ids[]" class="cp-input part-select" onchange="calculateTotal()">
                <option value="" data-price="0">Select part</option>
                <?php foreach ($parts as $p): ?>
                    <option value="<?= intval($p['part_id']) ?>" data-price="<?= cpEscape($p['unit_price']) ?>" data-stock="<?= intval($p['quantity_on_hand']) ?>">
                        <?= cpEscape($p['part_name']) ?> · <?= cpMoney($p['unit_price']) ?> · <?= intval($p['quantity_on_hand']) ?> left
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="sp-field">
            <label>Qty</label>
            <input type="number" name="part_qtys[]" class="cp-input part-qty" min="1" value="1" oninput="validateStock(this); calculateTotal()">
        </div>
        <div class="sp-field">
            <label>Subtotal</label>
            <input type="text" class="cp-input part-subtotal sp-mono" readonly value="—" tabindex="-1">
        </div>
        <button type="button" class="sp-line-x" aria-label="Remove part" onclick="this.closest('.part-row').remove(); calculateTotal()">&times;</button>
    </div>
</template>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let partRowId = 0;

    function peso(value) {
        return '₱' + Number(value).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function addPartRow() {
        const container = document.getElementById('partsContainer');
        const row = document.getElementById('partRowTemplate').content.firstElementChild.cloneNode(true);
        const id = ++partRowId;

        // Give each label its own input so screen readers and clicks work per row
        row.querySelectorAll('.sp-field').forEach((field, i) => {
            const control = field.querySelector('select, input');
            control.id = 'ctPart' + id + '_' + i;
            field.querySelector('label').htmlFor = control.id;
        });

        container.appendChild(row);
    }

    function validateStock(input) {
        const select = input.closest('.part-row').querySelector('.part-select');
        const selectedOption = select.options[select.selectedIndex];
        const maxStock = parseInt(selectedOption.getAttribute('data-stock')) || 0;

        if (select.value !== '' && parseInt(input.value) > maxStock) {
            cpFieldError(input, 'Only ' + maxStock + ' in stock.');
            input.value = maxStock;
        }
    }

    function calculateTotal() {
        let services = 0;
        let parts = 0;

        document.querySelectorAll('.service-checkbox:checked').forEach(cb => {
            services += parseFloat(cb.getAttribute('data-price')) || 0;
        });

        document.querySelectorAll('.part-row').forEach(row => {
            const select = row.querySelector('.part-select');
            const qtyInput = row.querySelector('.part-qty');
            const subtotalInput = row.querySelector('.part-subtotal');

            if (select.value !== '') {
                const price = parseFloat(select.options[select.selectedIndex].getAttribute('data-price')) || 0;
                const qty = parseInt(qtyInput.value) || 0;
                subtotalInput.value = peso(price * qty);
                parts += price * qty;
            } else {
                subtotalInput.value = '—';
            }
        });

        document.getElementById('servicesTotal').innerText = peso(services);
        document.getElementById('partsTotal').innerText = peso(parts);
        document.getElementById('liveTotal').innerText = peso(services + parts);
    }

    document.querySelectorAll('.service-checkbox').forEach(cb => {
        cb.addEventListener('change', calculateTotal);
    });
</script>

</body>
</html>
