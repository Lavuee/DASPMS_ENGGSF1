<?php
session_start();
if (!isset($_SESSION['logged_in'])) {
    header("Location: login.php");
    exit;
}

require_once '../config/Database.php';
require_once '../models/JobOrder.php';
require_once '../models/Customer.php';
require_once '../models/Vehicle.php';
require_once '../models/Service.php';
require_once '../models/Part.php';

$database = new Database();
$db = $database->getConnection();

$jobOrder = new JobOrder($db);
$stmtJO = $jobOrder->readAll();

// Fetch data for dropdowns
$stmtCustomers = (new Customer($db))->readAll();
$stmtVehicles = (new Vehicle($db))->readAllWithCustomer();
$stmtServices = (new Service($db))->readAll();
$stmtParts = (new Part($db))->readAll();

// Pre-fetch arrays for JavaScript injection
$servicesArray = $stmtServices->fetchAll(PDO::FETCH_ASSOC);
$partsArray = $stmtParts->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Job Orders - DASPMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-dark mb-4">
    <div class="container-fluid">
        <span class="navbar-brand mb-0 h1">DASPMS - <?php echo $_SESSION['role']; ?> Dashboard</span>
        <div>
            <a href="customers.php" class="btn btn-outline-light btn-sm me-2">Customers</a>
            <a href="vehicles.php" class="btn btn-outline-light btn-sm me-2">Vehicles</a>
            <a href="inventory.php" class="btn btn-outline-light btn-sm me-2">Inventory</a>
            <a href="services.php" class="btn btn-outline-light btn-sm me-2">Services</a>
            <a href="../controllers/AuthController.php?action=logout" class="btn btn-outline-danger btn-sm">Logout</a>
        </div>
    </div>
</nav>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Job Orders (Repair Services)</h2>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addJobModal">
            + New Job Order
        </button>
    </div>

    <?php if(isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
    <?php endif; ?>
    <?php if(isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>JO Number</th>
                        <th>Customer</th>
                        <th>Vehicle</th>
                        <th>Status</th>
                        <th>Est. Cost</th>
                        <th>Date Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $stmtJO->fetch(PDO::FETCH_ASSOC)): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['job_order_number']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['plate_number']); ?></td>
                            <td>
                                <?php 
                                    $badge = 'bg-secondary';
                                    if($row['status'] == 'In Progress') $badge = 'bg-primary';
                                    if($row['status'] == 'Completed') $badge = 'bg-success';
                                ?>
                                <span class="badge <?php echo $badge; ?>"><?php echo htmlspecialchars($row['status']); ?></span>
                            </td>
                            <td>₱<?php echo number_format($row['estimated_cost'], 2); ?></td>
                            <td><?php echo date('M d, Y', strtotime($row['date_created'])); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addJobModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form action="../controllers/JobOrderController.php" method="POST">
          <div class="modal-header">
            <h5 class="modal-title">Create New Job Order</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="action" value="create">
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label>Customer</label>
                    <select name="customer_id" class="form-select" required>
                        <option value="" disabled selected>Select Customer...</option>
                        <?php while ($cRow = $stmtCustomers->fetch(PDO::FETCH_ASSOC)): ?>
                            <option value="<?php echo $cRow['customer_id']; ?>">
                                <?php echo htmlspecialchars($cRow['last_name'] . ', ' . $cRow['first_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label>Vehicle</label>
                    <select name="vehicle_id" class="form-select" required>
                        <option value="" disabled selected>Select Vehicle...</option>
                        <?php 
                        // Reset vehicle statement pointer since we already used it if we were doing dynamic drops, but here we loop it
                        $stmtVehicles->execute(); 
                        while ($vRow = $stmtVehicles->fetch(PDO::FETCH_ASSOC)): ?>
                            <option value="<?php echo $vRow['vehicle_id']; ?>">
                                <?php echo htmlspecialchars($vRow['plate_number'] . ' (' . $vRow['make'] . ')'); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label>Problem Description / Notes</label>
                <textarea name="description" class="form-control" rows="3" required></textarea>
            </div>

            <hr>
            <h6>Assign Services</h6>
            <div id="service-container">
                </div>
            <button type="button" class="btn btn-sm btn-outline-primary mb-3" onclick="addServiceRow()">+ Add Service</button>

            <hr>
            <h6>Add Auto Parts (Deducts from Inventory)</h6>
            <div id="part-container">
                </div>
            <button type="button" class="btn btn-sm btn-outline-primary mb-3" onclick="addPartRow()">+ Add Part</button>

            <div class="mb-3 form-check mt-3 border-top pt-3">
                <input type="checkbox" class="form-check-input" id="requires_down_payment" name="requires_down_payment" value="1">
                <label class="form-check-label text-danger fw-bold" for="requires_down_payment">Require 50% Down Payment Before Start</label>
            </div>

          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Create Job Order</button>
          </div>
      </form>
    </div>
  </div>
</div>

<script>
// Pass PHP data to JS
const servicesData = <?php echo json_encode($servicesArray); ?>;
const partsData = <?php echo json_encode($partsArray); ?>;

function addServiceRow() {
    let options = '<option value="" disabled selected>Select Service...</option>';
    servicesData.forEach(s => {
        options += `<option value="${s.service_id}" data-price="${s.base_price}">${s.service_name}</option>`;
    });

    const row = `
        <div class="row mb-2">
            <div class="col-md-8">
                <select name="service_ids[]" class="form-select" onchange="updateServicePrice(this)" required>
                    ${options}
                </select>
            </div>
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text">₱</span>
                    <input type="number" step="0.01" name="service_prices[]" class="form-control" placeholder="Price" required>
                </div>
            </div>
        </div>
    `;
    document.getElementById('service-container').insertAdjacentHTML('beforeend', row);
}

function updateServicePrice(selectElem) {
    const price = selectElem.options[selectElem.selectedIndex].getAttribute('data-price');
    selectElem.parentElement.nextElementSibling.querySelector('input').value = price;
}

function addPartRow() {
    let options = '<option value="" disabled selected>Select Part...</option>';
    partsData.forEach(p => {
        options += `<option value="${p.part_id}" data-price="${p.unit_price}">[Stock: ${p.quantity_on_hand}] ${p.part_name}</option>`;
    });

    const row = `
        <div class="row mb-2">
            <div class="col-md-6">
                <select name="part_ids[]" class="form-select" onchange="updatePartPrice(this)" required>
                    ${options}
                </select>
            </div>
            <div class="col-md-3">
                <input type="number" name="part_qtys[]" class="form-control" placeholder="Qty" min="1" value="1" required>
            </div>
            <div class="col-md-3">
                <div class="input-group">
                    <span class="input-group-text">₱</span>
                    <input type="number" step="0.01" name="part_prices[]" class="form-control" placeholder="Price" required>
                </div>
            </div>
        </div>
    `;
    document.getElementById('part-container').insertAdjacentHTML('beforeend', row);
}

function updatePartPrice(selectElem) {
    const price = selectElem.options[selectElem.selectedIndex].getAttribute('data-price');
    selectElem.parentElement.nextElementSibling.nextElementSibling.querySelector('input').value = price;
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>