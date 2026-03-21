<?php
session_start();
if (!isset($_SESSION['logged_in'])) {
    header("Location: login.php");
    exit;
}

require_once '../config/Database.php';
require_once '../models/Vehicle.php';
require_once '../models/Customer.php'; // Required to populate the owner dropdown

$database = new Database();
$db = $database->getConnection();

// Fetch Vehicles
$vehicle = new Vehicle($db);
$stmtVehicles = $vehicle->readAllWithCustomer();

// Fetch Customers for the dropdown
$customer = new Customer($db);
$stmtCustomers = $customer->readAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Management - DASPMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm">
    <div class="container-fluid">
        <span class="navbar-brand mb-0 h1">DASPMS - <?php echo $_SESSION['role']; ?></span>
        <div class="d-flex">
            <a href="customers.php" class="btn btn-outline-light btn-sm me-2">Customers</a>
            <a href="dashboard_<?php echo strtolower(explode(' ', $_SESSION['role'])[0]); ?>.php" class="btn btn-outline-light btn-sm me-2">Dashboard</a>
            <a href="../controllers/AuthController.php?action=logout" class="btn btn-outline-danger btn-sm">Logout</a>
        </div>
    </div>
</nav>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Registered Vehicles</h2>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addVehicleModal">
            + Register New Vehicle
        </button>
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
            <table class="table table-striped table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th class="ps-4">Plate Number</th>
                        <th>Make & Model</th>
                        <th>Year</th>
                        <th>Color</th>
                        <th>Owner (Customer)</th>
                        <th class="pe-4 text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($stmtVehicles->rowCount() > 0): ?>
                        <?php while ($row = $stmtVehicles->fetch(PDO::FETCH_ASSOC)): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-primary"><?php echo htmlspecialchars($row['plate_number']); ?></td>
                                <td><?php echo htmlspecialchars($row['make'] . ' ' . $row['model']); ?></td>
                                <td><?php echo htmlspecialchars($row['year']); ?></td>
                                <td><?php echo htmlspecialchars($row['color']); ?></td>
                                <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                <td class="pe-4 text-end">
                                    <button class="btn btn-sm btn-outline-secondary" disabled>Service History</button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">No vehicles registered yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addVehicleModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form action="../controllers/VehicleController.php" method="POST">
          <div class="modal-header bg-primary text-white">
            <h5 class="modal-title">Register Vehicle</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="action" value="add">
            
            <div class="mb-3">
                <label class="form-label text-primary fw-bold">Select Owner (Customer) *</label>
                <select name="customer_id" class="form-select" required>
                    <option value="" disabled selected>-- Choose a Customer --</option>
                    <?php while ($custRow = $stmtCustomers->fetch(PDO::FETCH_ASSOC)): ?>
                        <option value="<?php echo $custRow['customer_id']; ?>">
                            <?php echo htmlspecialchars($custRow['last_name'] . ', ' . $custRow['first_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="row mb-3">
                <div class="col">
                    <label class="form-label">Plate Number *</label>
                    <input type="text" name="plate_number" class="form-control" placeholder="ABC-1234" required>
                </div>
                <div class="col">
                    <label class="form-label">Year *</label>
                    <input type="number" name="year" class="form-control" min="1950" max="<?php echo date('Y') + 1; ?>" required>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col">
                    <label class="form-label">Make (Brand) *</label>
                    <input type="text" name="make" class="form-control" placeholder="e.g. Toyota" required>
                </div>
                <div class="col">
                    <label class="form-label">Model *</label>
                    <input type="text" name="model" class="form-control" placeholder="e.g. Hilux" required>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Color *</label>
                <input type="text" name="color" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Additional Notes</label>
                <textarea name="notes" class="form-control" rows="2" placeholder="Any specific issues or identifying marks..."></textarea>
            </div>
          </div>
          <div class="modal-footer border-0 bg-light">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Vehicle</button>
          </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>