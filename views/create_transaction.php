<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] === 'Customer') {
    header("Location: login.php");
    exit;
}

require_once '../config/Database.php';

$db = (new Database())->getConnection();
$userId = $_SESSION['user_id'] ?? 1;

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

        $totalEstimatedCost = 0;

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
                    $totalEstimatedCost += $price;
                }
            }
        }

        // 5. INSERT PARTS & DEDUCT INVENTORY
        if (!empty($_POST['part_ids'])) {
            $stmtPartInfo = $db->prepare("SELECT unit_price, quantity_on_hand FROM part WHERE part_id = ?");
            $stmtJoPart = $db->prepare("
                INSERT INTO job_order_part (job_order_id, part_id, quantity_used, unit_price_at_use, subtotal) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmtUpdateStock = $db->prepare("UPDATE part SET quantity_on_hand = quantity_on_hand - ? WHERE part_id = ?");

            foreach ($_POST['part_ids'] as $index => $partId) {
                $qty = intval($_POST['part_qtys'][$index]);
                if (empty($partId) || $qty <= 0) continue;

                $stmtPartInfo->execute([$partId]);
                $part = $stmtPartInfo->fetch(PDO::FETCH_ASSOC);

                if ($part && $part['quantity_on_hand'] >= $qty) {
                    $subtotal = $part['unit_price'] * $qty;
                    $stmtJoPart->execute([$jobOrderId, $partId, $qty, $part['unit_price'], $subtotal]);
                    $stmtUpdateStock->execute([$qty, $partId]);
                    $totalEstimatedCost += $subtotal;
                } else {
                    throw new Exception("Not enough stock for one of the selected parts.");
                }
            }
        }

        // 6. UPDATE TOTAL
        $stmtUpdateJo = $db->prepare("UPDATE job_order SET estimated_cost = ? WHERE job_order_id = ?");
        $stmtUpdateJo->execute([$totalEstimatedCost, $jobOrderId]);

        $db->commit();
        $_SESSION['success_message'] = "Walk-in transaction created successfully! ($jobNumber)";
        header("Location: job_orders.php");
        exit;

    } catch (Exception $e) {
        $db->rollBack();
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
    <title>New Walk-in - Norily's Repair Shop</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* Container Layout */
        .step-container {
            max-width: 850px;
            margin: 0 auto;
            padding-bottom: 3rem;
        }
        
        .dashboard-header {
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #edf0f4;
        }

        /* Step Cards */
        .section-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.02);
            position: relative;
        }
        
        .step-badge {
            position: absolute;
            top: -12px;
            left: 2rem;
            background: var(--dashboard-primary);
            color: #000;
            font-weight: 900;
            font-size: 0.85rem;
            padding: 0.35rem 1rem;
            border-radius: 999px;
            box-shadow: 0 4px 6px rgba(245, 197, 24, 0.3);
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 900;
            color: var(--dashboard-text-main);
            margin-bottom: 1.25rem;
            margin-top: 0.5rem;
        }

        .form-label {
            font-size: 0.82rem;
            font-weight: 800;
            color: var(--dashboard-text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-control, .form-select {
            border-radius: 12px;
            border: 1px solid #cfd6df;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            background: #f8fafc;
        }

        .form-control:focus, .form-select:focus {
            background: #fff;
            border-color: var(--dashboard-primary);
            box-shadow: 0 0 0 4px rgba(245, 197, 24, 0.15);
        }

        /* Custom Animated Checkbox Cards */
        .custom-service-card input[type="checkbox"] {
            display: none; /* Hide default input */
        }
        
        .custom-service-card label {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            padding: 1rem 1.25rem;
            border: 2px solid #edf0f4;
            border-radius: 14px;
            background: #fff;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            margin-bottom: 0.75rem;
        }
        
        .custom-service-card label:hover {
            border-color: #d1d5db;
            transform: translateY(-2px);
        }

        /* Active/Checked State */
        .custom-service-card input[type="checkbox"]:checked + label {
            border-color: var(--dashboard-primary);
            background: rgba(245, 197, 24, 0.08);
            box-shadow: 0 4px 12px rgba(245, 197, 24, 0.15);
        }

        .check-circle {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            border: 2px solid #cfd6df;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            transition: all 0.2s ease;
            background: #fff;
        }

        .custom-service-card input[type="checkbox"]:checked + label .check-circle {
            background: var(--dashboard-primary);
            border-color: var(--dashboard-primary);
        }

        .custom-service-card input[type="checkbox"]:checked + label .check-circle::after {
            content: "\F26A"; /* Bootstrap Icon check-lg */
            font-family: "bootstrap-icons";
            color: #000;
            font-size: 0.9rem;
            font-weight: 900;
        }

        .service-name {
            font-size: 1rem;
            font-weight: 800;
            color: var(--dashboard-text-main);
        }

        .service-price {
            font-weight: 900;
            font-size: 0.95rem;
            color: var(--dashboard-text-muted);
        }
        
        .custom-service-card input[type="checkbox"]:checked + label .service-price {
            color: #000; /* Darken price when selected */
        }

        /* Parts Layout */
        .part-row {
            display: grid;
            grid-template-columns: minmax(0, 2fr) 100px 120px auto;
            gap: 1rem;
            align-items: end;
            margin-bottom: 1rem;
            background: #fcfcfc;
            padding: 1rem;
            border-radius: 12px;
            border: 1px dashed #d1d5db;
        }

        /* Action Footer */
        .action-footer {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 8px 24px rgba(0,0,0,0.04);
            position: sticky;
            bottom: 2rem;
            z-index: 10;
        }

        .total-display {
            font-size: 1.5rem;
            font-weight: 900;
            color: var(--dashboard-text-main);
        }

        .total-display span {
            color: var(--dashboard-primary);
            font-size: 1.8rem;
        }

        .btn-submit {
            background: #000;
            color: var(--dashboard-primary);
            font-weight: 900;
            padding: 0.8rem 2.5rem;
            border-radius: 999px;
            border: none;
            font-size: 1.1rem;
            transition: 0.3s;
        }

        .btn-submit:hover {
            transform: scale(1.02);
            box-shadow: 0 8px 15px rgba(0,0,0,0.2);
        }
        
        @media (max-width: 768px) {
            .part-row {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }
            .action-footer {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
                padding: 1.5rem;
            }
            .btn-submit {
                width: 100%;
            }
        }
    </style>
</head>
<body>

<div class="app-wrapper">
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid">
            
            <div class="step-container">
                <div class="dashboard-header d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="fw-bold mb-0">Transaction Form</h2>
                        <p class="text-muted mb-0">Complete all steps to seamlessly register a new job order.</p>
                    </div>
                    <a href="javascript:history.back()" class="btn btn-outline-secondary rounded-pill fw-bold">
                        <i class="bi bi-x-lg"></i> Cancel
                    </a>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger rounded-4"><i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form action="" method="POST" id="unifiedForm">
                    
                    <div class="section-card">
                        <div class="step-badge">STEP 1</div>
                        <h4 class="section-title"><i class="bi bi-person-bounding-box me-2"></i>Customer Information</h4>
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label">First Name *</label>
                                <input type="text" name="first_name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Last Name *</label>
                                <input type="text" name="last_name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Contact Number *</label>
                                <input type="text" name="contact_number" class="form-control" maxlength="11" placeholder="09xxxxxxxxx" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email Address (Optional)</label>
                                <input type="email" name="email" class="form-control">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Home Address</label>
                                <input type="text" name="address" class="form-control">
                            </div>
                        </div>
                    </div>

                    <div class="section-card">
                        <div class="step-badge">STEP 2</div>
                        <h4 class="section-title"><i class="bi bi-car-front-fill me-2"></i>Vehicle Registration</h4>
                        <div class="row g-4">
                            <div class="col-md-4">
                                <label class="form-label">Plate Number *</label>
                                <input type="text" name="plate_number" class="form-control" placeholder="ABC 1234" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Make/Brand *</label>
                                <input type="text" name="make" class="form-control" placeholder="e.g. Toyota" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Model *</label>
                                <input type="text" name="model" class="form-control" placeholder="e.g. Vios" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Year *</label>
                                <input type="number" name="year" class="form-control" min="1950" max="2026" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Color *</label>
                                <input type="text" name="color" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="section-card">
                        <div class="step-badge">STEP 3</div>
                        <h4 class="section-title"><i class="bi bi-clipboard-pulse me-2"></i>Repair Description</h4>
                        <textarea name="description" class="form-control" rows="3" placeholder="Describe the problem, symptoms, or reason for visit..." required></textarea>
                    </div>

                    <div class="section-card">
                        <div class="step-badge">STEP 4</div>
                        <h4 class="section-title"><i class="bi bi-wrench-adjustable me-2"></i>Select Services</h4>
                        <div class="row">
                            <?php foreach ($services as $svc): ?>
                                <div class="col-md-6">
                                    <div class="custom-service-card">
                                        <input class="service-checkbox" type="checkbox" name="services[]" value="<?php echo $svc['service_id']; ?>" id="svc_<?php echo $svc['service_id']; ?>" data-price="<?php echo $svc['base_price']; ?>">
                                        <label for="svc_<?php echo $svc['service_id']; ?>">
                                            <div class="d-flex align-items-center">
                                                <div class="check-circle"></div>
                                                <span class="service-name"><?php echo htmlspecialchars($svc['service_name']); ?></span>
                                            </div>
                                            <span class="service-price">₱<?php echo number_format($svc['base_price'], 2); ?></span>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="section-card">
                        <div class="step-badge">STEP 5</div>
                        <h4 class="section-title"><i class="bi bi-box-seam me-2"></i>Add Parts (Optional)</h4>
                        <div id="partsContainer">
                            </div>
                        <button type="button" class="btn btn-outline-dark rounded-pill fw-bold mt-2" onclick="addPartRow()">
                            <i class="bi bi-plus-lg me-1"></i> Add Part
                        </button>
                    </div>

                    <div class="action-footer">
                        <div class="total-display">
                            Estimated Total: <span id="liveTotal">₱0.00</span>
                        </div>
                        <button type="submit" class="btn-submit" onclick="return confirm('Save this walk-in transaction? This will automatically register the customer, vehicle, and job order.');">
                            <i class="bi bi-check2-circle me-2"></i> Submit Transaction
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </main>
</div>

<script type="template" id="partRowTemplate">
    <div class="part-row">
        <div>
            <label class="form-label">Select Part from Inventory</label>
            <select name="part_ids[]" class="form-select part-select" onchange="calculateTotal()">
                <option value="" data-price="0">-- Search & Select Part --</option>
                <?php foreach ($parts as $p): ?>
                    <option value="<?php echo $p['part_id']; ?>" data-price="<?php echo $p['unit_price']; ?>" data-stock="<?php echo $p['quantity_on_hand']; ?>">
                        <?php echo htmlspecialchars($p['part_name']); ?> (Stock: <?php echo $p['quantity_on_hand']; ?>) - ₱<?php echo number_format($p['unit_price'], 2); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label">Qty</label>
            <input type="number" name="part_qtys[]" class="form-control part-qty text-center" min="1" value="1" oninput="validateStock(this); calculateTotal()">
        </div>
        <div>
            <label class="form-label">Subtotal</label>
            <input type="text" class="form-control part-subtotal text-end fw-bold" readonly value="₱0.00" style="background:#f1f5f9;">
        </div>
        <div class="text-end">
            <button type="button" class="btn btn-outline-danger rounded-pill px-3" style="margin-top: 31px;" onclick="this.closest('.part-row').remove(); calculateTotal()"><i class="bi bi-trash"></i></button>
        </div>
    </div>
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function addPartRow() {
        const container = document.getElementById('partsContainer');
        const template = document.getElementById('partRowTemplate').innerHTML;
        container.insertAdjacentHTML('beforeend', template);
    }

    function validateStock(input) {
        const select = input.closest('.part-row').querySelector('.part-select');
        const selectedOption = select.options[select.selectedIndex];
        const maxStock = parseInt(selectedOption.getAttribute('data-stock')) || 0;
        
        if (parseInt(input.value) > maxStock) {
            alert('Cannot exceed available stock limit of ' + maxStock);
            input.value = maxStock;
        }
    }

    function calculateTotal() {
        let total = 0;

        // Add Checked Services
        document.querySelectorAll('.service-checkbox:checked').forEach(cb => {
            total += parseFloat(cb.getAttribute('data-price')) || 0;
        });

        // Add Parts
        document.querySelectorAll('.part-row').forEach(row => {
            const select = row.querySelector('.part-select');
            const qtyInput = row.querySelector('.part-qty');
            const subtotalInput = row.querySelector('.part-subtotal');
            
            if (select.value !== "") {
                const price = parseFloat(select.options[select.selectedIndex].getAttribute('data-price')) || 0;
                const qty = parseInt(qtyInput.value) || 0;
                const sub = price * qty;
                subtotalInput.value = '₱' + sub.toFixed(2);
                total += sub;
            } else {
                subtotalInput.value = '₱0.00';
            }
        });

        // Update live sticky footer
        document.getElementById('liveTotal').innerText = '₱' + total.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }

    // Attach listener to service checkboxes dynamically
    document.querySelectorAll('.service-checkbox').forEach(cb => {
        cb.addEventListener('change', calculateTotal);
    });

    // Add one empty part row by default on load
    addPartRow();
</script>

</body>
</html>