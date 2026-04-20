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

$stmtCustomers = (new Customer($db))->readAll();
$stmtVehicles = (new Vehicle($db))->readAllWithCustomer();
$stmtServices = (new Service($db))->readAll();
$stmtParts = (new Part($db))->readAll();

$servicesData = $stmtServices->fetchAll(PDO::FETCH_ASSOC);
$partsData = $stmtParts->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Orders - DASPMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm">
    <div class="container-fluid">
        <span class="navbar-brand mb-0 h1">DASPMS - <?php echo $_SESSION['role']; ?></span>
        <div class="d-flex">
            <a href="dashboard_<?php echo strtolower(explode(' ', $_SESSION['role'])[0]); ?>.php" class="btn btn-outline-light btn-sm me-2">Dashboard</a>
            <a href="../controllers/AuthController.php?action=logout" class="btn btn-outline-danger btn-sm">Logout</a>
        </div>
    </div>
</nav>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Active Job Orders (Repairs)</h2>
        <?php if ($_SESSION['role'] !== 'Head Mechanic'): ?>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addJobModal">
                + Create New Job Order
            </button>
        <?php endif; ?>
    </div>

    <?php if(isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if(isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th class="ps-4">Tracking No.</th>
                        <th>Customer</th>
                        <th>Vehicle</th>
                        <th>Status</th>
                        <th>Est. Cost</th>
                        <th class="pe-4 text-end">Date Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($stmtJO->rowCount() > 0): ?>
                        <?php while ($row = $stmtJO->fetch(PDO::FETCH_ASSOC)): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-primary"><?php echo htmlspecialchars($row['job_order_number']); ?></td>
                                <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['plate_number'] . ' (' . $row['make'] . ')'); ?></td>
                                <td>
                                    <?php 
                                        $badge = 'bg-secondary';
                                        if($row['status'] == 'In Progress') $badge = 'bg-primary';
                                        if($row['status'] == 'Completed') $badge = 'bg-success';
                                    ?>
                                    <span class="badge <?php echo $badge; ?>"><?php echo htmlspecialchars($row['status']); ?></span>
                                </td>
                                <td>₱<?php echo number_format($row['estimated_cost'], 2); ?></td>
                                <td class="pe-4 text-end text-muted"><?php echo date('M d, Y h:i A', strtotime($row['date_created'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">No active job orders.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($_SESSION['role'] !== 'Head Mechanic'): ?>
<div class="modal fade" id="addJobModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form action="../controllers/JobOrderController.php" method="POST">
          <div class="modal-header bg-primary text-white">
            <h5 class="modal-title">Create Repair Ticket</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="action" value="create">
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label text-primary fw-bold">Customer *</label>
                    <select name="customer_id" class="form-select" required>
                        <option value="" disabled selected>-- Select Customer --</option>
                        <?php while ($cRow = $stmtCustomers->fetch(PDO::FETCH_ASSOC)): ?>
                            <option value="<?php echo $cRow['customer_id']; ?>">
                                <?php echo htmlspecialchars($cRow['last_name'] . ', ' . $cRow['first_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label text-primary fw-bold">Vehicle *</label>
                    <select name="vehicle_id" class="form-select" required>
                        <option value="" disabled selected>-- Select Vehicle --</option>
                        <?php 
                        $stmtVehicles->execute(); // Reset pointer
                        while ($vRow = $stmtVehicles->fetch(PDO::FETCH_ASSOC)): ?>
                            <option value="<?php echo $vRow['vehicle_id']; ?>">
                                <?php echo htmlspecialchars($vRow['plate_number'] . ' - ' . $vRow['make'] . ' ' . $vRow['model']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label">Problem Description / Notes *</label>
                <textarea name="description" class="form-control" rows="2" placeholder="Describe the issue reported by the customer..." required></textarea>
            </div>

            <h6 class="border-bottom pb-2">Assigned Labor & Services</h6>
            <div id="service-container"></div>
            <button type="button" class="btn btn-sm btn-outline-primary mb-4" onclick="addServiceRow()">+ Add Service</button>

            <h6 class="border-bottom pb-2">Auto Parts Required <small class="text-danger">(Deducts from stock!)</small></h6>
            <div id="part-container"></div>
            <button type="button" class="btn btn-sm btn-outline-primary mb-4" onclick="addPartRow()">+ Add Part</button>

            <div class="form-check p-3 bg-light rounded border border-warning">
                <input type="checkbox" class="form-check-input ms-1 mt-1" id="requires_down_payment" name="requires_down_payment" value="1">
                <label class="form-check-label ms-2 fw-bold text-danger" for="requires_down_payment">Require 50% Down Payment to Start Work</label>
            </div>

          </div>
          <div class="modal-footer border-0 bg-light">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary px-4">Create Job Order</button>
          </div>
      </form>
    </div>
  </div>
</div>

<script>
const servicesData = <?php echo json_encode($servicesData); ?>;
const partsData = <?php echo json_encode($partsData); ?>;

function addServiceRow() {
    let options = '<option value="" disabled selected>-- Select a Service --</option>';
    servicesData.forEach(s => {
        options += `<option value="${s.service_id}" data-price="${s.base_price}">${s.service_name}</option>`;
    });

    const rowHTML = `
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
    document.getElementById('service-container').insertAdjacentHTML('beforeend', rowHTML);
}

function updateServicePrice(selectElem) {
    const price = selectElem.options[selectElem.selectedIndex].getAttribute('data-price');
    selectElem.parentElement.nextElementSibling.querySelector('input').value = price;
}

function addPartRow() {
    let options = '<option value="" disabled selected>-- Select a Part --</option>';
    partsData.forEach(p => {
        options += `<option value="${p.part_id}" data-price="${p.unit_price}">[Stock: ${p.quantity_on_hand}] ${p.part_name}</option>`;
    });

    const rowHTML = `
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
    document.getElementById('part-container').insertAdjacentHTML('beforeend', rowHTML);
}

function updatePartPrice(selectElem) {
    const price = selectElem.options[selectElem.selectedIndex].getAttribute('data-price');
    selectElem.parentElement.nextElementSibling.nextElementSibling.querySelector('input').value = price;
}
</script>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>