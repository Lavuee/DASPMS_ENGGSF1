<?php
session_start();
if (!isset($_SESSION['logged_in'])) {
    header("Location: login.php");
    exit;
}

require_once '../config/Database.php';
require_once '../models/Part.php';

$database = new Database();
$db = $database->getConnection();

$part = new Part($db);
$stmtParts = $part->readAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - DASPMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm">
    <div class="container-fluid">
        <span class="navbar-brand mb-0 h1">DASPMS - <?php echo $_SESSION['role']; ?></span>
        <div class="d-flex">
            <a href="customers.php" class="btn btn-outline-light btn-sm me-2">Customers</a>
            <a href="vehicles.php" class="btn btn-outline-light btn-sm me-2">Vehicles</a>
            <a href="dashboard_<?php echo strtolower(explode(' ', $_SESSION['role'])[0]); ?>.php" class="btn btn-outline-light btn-sm me-2">Dashboard</a>
            <a href="../controllers/AuthController.php?action=logout" class="btn btn-outline-danger btn-sm">Logout</a>
        </div>
    </div>
</nav>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Auto Parts Inventory</h2>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPartModal">
            + Register New Part
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
                        <th>Part Name & Desc.</th>
                        <th>Unit Price</th>
                        <th>Stock Level</th>
                        <th>Supplier Ref.</th>
                        <th class="pe-4 text-end">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($stmtParts->rowCount() > 0): ?>
                        <?php while ($row = $stmtParts->fetch(PDO::FETCH_ASSOC)): 
                            // Determine if low stock
                            $isLowStock = $row['quantity_on_hand'] <= $row['low_stock_threshold'];
                        ?>
                            <tr class="<?php echo $isLowStock ? 'table-warning' : ''; ?>">
                                <td class="ps-4"><span class="badge bg-secondary"><?php echo htmlspecialchars($row['category']); ?></span></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['part_name']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($row['description']); ?></small>
                                </td>
                                <td>₱<?php echo number_format($row['unit_price'], 2); ?></td>
                                <td>
                                    <span class="fs-5 fw-bold <?php echo $isLowStock ? 'text-danger' : 'text-success'; ?>">
                                        <?php echo htmlspecialchars($row['quantity_on_hand']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($row['supplier_reference']); ?></td>
                                <td class="pe-4 text-end">
                                    <?php if($isLowStock): ?>
                                        <span class="badge bg-danger">Low Stock Alert</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">In Stock</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">No auto parts in inventory.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addPartModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form action="../controllers/PartController.php" method="POST">
          <div class="modal-header bg-primary text-white">
            <h5 class="modal-title">Register Auto Part</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="action" value="add">
            
            <div class="mb-3">
                <label class="form-label">Category *</label>
                <select name="category" class="form-select" required>
                    <option value="" disabled selected>-- Select Category --</option>
                    <option value="Capacitor">Capacitor</option>
                    <option value="Switch">Switch</option>
                    <option value="AVR">AVR</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Part Name *</label>
                <input type="text" name="part_name" class="form-control" required>
            </div>

            <div class="row mb-3">
                <div class="col">
                    <label class="form-label">Selling Price (₱) *</label>
                    <input type="number" step="0.01" name="unit_price" class="form-control" required>
                </div>
                <div class="col">
                    <label class="form-label">Initial Stock Qty *</label>
                    <input type="number" name="quantity_on_hand" class="form-control" value="0" min="0" required>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col">
                    <label class="form-label">Low Stock Threshold *</label>
                    <input type="number" name="low_stock_threshold" class="form-control" value="3" min="1" required>
                    <div class="form-text">System alerts when stock hits this number.</div>
                </div>
                <div class="col">
                    <label class="form-label">Supplier Ref.</label>
                    <input type="text" name="supplier_reference" class="form-control" placeholder="Optional">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="2" placeholder="Item specifics..."></textarea>
            </div>
          </div>
          <div class="modal-footer border-0 bg-light">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Part</button>
          </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>