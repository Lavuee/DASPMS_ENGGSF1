<?php
session_start();
if (!isset($_SESSION['logged_in'])) {
    header("Location: login.php");
    exit;
}

require_once '../config/Database.php';
require_once '../models/Service.php';

$database = new Database();
$db = $database->getConnection();

$service = new Service($db);
$stmtServices = $service->readAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Catalog - DASPMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm">
    <div class="container-fluid">
        <span class="navbar-brand mb-0 h1">DASPMS - <?php echo $_SESSION['role']; ?></span>
        <div class="d-flex">
            <a href="customers.php" class="btn btn-outline-light btn-sm me-2">Customers</a>
            <a href="vehicles.php" class="btn btn-outline-light btn-sm me-2">Vehicles</a>
            <a href="inventory.php" class="btn btn-outline-light btn-sm me-2">Inventory</a>
            <a href="dashboard_<?php echo strtolower(explode(' ', $_SESSION['role'])[0]); ?>.php" class="btn btn-outline-light btn-sm me-2">Dashboard</a>
            <a href="../controllers/AuthController.php?action=logout" class="btn btn-outline-danger btn-sm">Logout</a>
        </div>
    </div>
</nav>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Repair Services Catalog</h2>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addServiceModal">
            + Add New Service
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
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th class="ps-4">Category</th>
                        <th>Service Name</th>
                        <th>Base Price</th>
                        <th>Warranty</th>
                        <th class="pe-4 text-end">Down Payment</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($stmtServices->rowCount() > 0): ?>
                        <?php while ($row = $stmtServices->fetch(PDO::FETCH_ASSOC)): ?>
                            <tr>
                                <td class="ps-4"><span class="badge bg-info text-dark"><?php echo htmlspecialchars($row['category']); ?></span></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['service_name']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($row['description']); ?></small>
                                </td>
                                <td>₱<?php echo number_format($row['base_price'], 2); ?></td>
                                <td>
                                    <?php echo $row['warranty_days'] > 0 ? htmlspecialchars($row['warranty_days']) . ' Days' : '<span class="text-muted">None</span>'; ?>
                                </td>
                                <td class="pe-4 text-end">
                                    <?php if($row['requires_down_payment'] == 1): ?>
                                        <span class="badge bg-warning text-dark">Required</span>
                                    <?php else: ?>
                                        <span class="text-muted">Optional</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">No services defined yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addServiceModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form action="../controllers/ServiceController.php" method="POST">
          <div class="modal-header bg-primary text-white">
            <h5 class="modal-title">Define Repair Service</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="action" value="add">
            
            <div class="mb-3">
                <label class="form-label">Category *</label>
                <input type="text" name="category" class="form-control" list="categoryOptions" placeholder="e.g., Electrical, Mechanical" required>
                <datalist id="categoryOptions">
                    <option value="Electrical Rewinding">
                    <option value="Mechanical Repair">
                    <option value="General Maintenance">
                </datalist>
            </div>

            <div class="mb-3">
                <label class="form-label">Service Name *</label>
                <input type="text" name="service_name" class="form-control" placeholder="e.g., Alternator Repair" required>
            </div>

            <div class="row mb-3">
                <div class="col">
                    <label class="form-label">Base Price (₱) *</label>
                    <input type="number" step="0.01" name="base_price" class="form-control" required>
                </div>
                <div class="col">
                    <label class="form-label">Warranty (Days) *</label>
                    <input type="number" name="warranty_days" class="form-control" value="0" min="0" required>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Description / Scope of Work</label>
                <textarea name="description" class="form-control" rows="2"></textarea>
            </div>

            <div class="form-check mt-4 p-3 bg-light rounded border">
                <input type="checkbox" class="form-check-input ms-1 mt-1" id="requires_down_payment" name="requires_down_payment" value="1">
                <label class="form-check-label ms-2 fw-bold text-danger" for="requires_down_payment">
                    Requires 50% Down Payment
                </label>
                <div class="form-text ms-2">Check this box to enforce a down payment when this service is billed.</div>
            </div>

          </div>
          <div class="modal-footer border-0 bg-light">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Service</button>
          </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>