<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] === 'Customer' || $_SESSION['role'] === 'Head Mechanic') { header("Location: login.php"); exit; }
require_once '../config/Database.php';

$db = (new Database())->getConnection();
$stmt = $db->prepare("SELECT * FROM part ORDER BY part_name ASC");
$stmt->execute();
$parts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalParts = count($parts);
$lowStockCount = 0;

foreach ($parts as $p) { 
    $qty = (int)$p['quantity_on_hand'];
    $threshold = (isset($p['low_stock_threshold']) && (int)$p['low_stock_threshold'] > 0) ? (int)$p['low_stock_threshold'] : 5;
    
    if ($qty <= $threshold) {
        $lowStockCount++; 
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inventory - Norily's Repair Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .category-badge { background-color: var(--sail); color: var(--text-muted); padding: 4px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 600;}
        .action-icon { color: var(--cloud); cursor: pointer; transition: color 0.2s; font-size: 1.1rem; margin-left: 10px;}
        .action-icon:hover { color: var(--danger); }
        .action-icon.edit:hover { color: var(--primary-blue); }
    </style>
</head>
<body>
<div class="app-wrapper">
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-1">Inventory</h2>
                <p class="text-muted"><?php echo $totalParts; ?> parts tracked • <?php echo $lowStockCount; ?> low stock</p>
            </div>
            <button class="btn btn-primary px-4 py-2 fw-bold" data-bs-toggle="modal" data-bs-target="#addPartModal"><i class="bi bi-plus-lg"></i> Add Part</button>
        </div>

        <?php if(isset($_SESSION['success_message'])): ?><div class="alert alert-success alert-dismissible fade show"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
        <?php if(isset($_SESSION['error_message'])): ?><div class="alert alert-danger alert-dismissible fade show"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

        <div class="custom-card p-0 overflow-hidden">
            <table class="table table-custom mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Part Name</th>
                        <th>Category</th>
                        <th>Stock</th>
                        <th>Unit Price</th>
                        <th>Cost Price</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($parts as $p): 
                        // Strict Integer Math for Badges
                        $qty = (int)$p['quantity_on_hand'];
                        $threshold = (isset($p['low_stock_threshold']) && (int)$p['low_stock_threshold'] > 0) ? (int)$p['low_stock_threshold'] : 5;
                        $isLowStock = $qty <= $threshold;
                    ?>
                    <tr>
                        <td class="ps-4 fw-bold"><?php echo htmlspecialchars($p['part_name']); ?></td>
                        <td><span class="category-badge"><?php echo htmlspecialchars($p['category']); ?></span></td>
                        <td class="fw-bold"><?php echo $qty; ?></td>
                        <td>₱<?php echo number_format($p['unit_price'], 2); ?></td>
                        <td class="text-muted">₱<?php echo number_format($p['unit_price'] * 0.7, 2); ?></td>
                        <td>
                            <?php if ($isLowStock): ?>
                                <span class="badge-soft-danger"><i class="bi bi-exclamation-triangle me-1"></i> Low Stock</span>
                            <?php else: ?>
                                <span class="badge-soft-success">In Stock</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end pe-4">
                            <i class="bi bi-pencil action-icon edit" onclick='openEditModal(<?php echo json_encode($p); ?>)'></i>
                            <i class="bi bi-trash action-icon" onclick="openDeleteModal(<?php echo $p['part_id']; ?>, '<?php echo addslashes($p['part_name']); ?>')"></i>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<div class="modal fade" id="addPartModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius: 12px; border: none;">
      <form action="../controllers/PartController.php" method="POST">
          <div class="modal-header border-0 pb-0">
            <h5 class="modal-title fw-bold">Add New Part</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body p-4">
            <input type="hidden" name="action" value="add">
            <div class="mb-3"><label class="form-label text-muted fw-bold">Part Name</label><input type="text" name="part_name" class="form-control" required></div>
            <div class="mb-3">
                <label class="form-label text-muted fw-bold">Category</label>
                <select name="category" class="form-select" required>
                    <option value="Battery">Battery</option>
                    <option value="Capacitor">Capacitor</option>
                    <option value="AVR">AVR</option>
                    <option value="Oil">Oil</option>
                    <option value="Switch">Switch</option>
                </select>
            </div>
            <div class="row mb-4 g-2">
                <div class="col"><label class="form-label text-muted fw-bold">Stock Qty</label><input type="number" name="quantity_on_hand" class="form-control" required></div>
                <div class="col"><label class="form-label text-muted fw-bold">Unit Price (₱)</label><input type="number" step="0.01" name="unit_price" class="form-control" required></div>
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">Save Part</button>
          </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="editPartModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius: 12px; border: none;">
      <form action="../controllers/PartController.php" method="POST">
          <div class="modal-header border-0 pb-0">
            <h5 class="modal-title fw-bold">Edit Part</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body p-4">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="part_id" id="edit_part_id">
            <div class="mb-3"><label class="form-label text-muted fw-bold">Part Name</label><input type="text" name="part_name" id="edit_part_name" class="form-control" required></div>
            <div class="row mb-4 g-2">
                <div class="col"><label class="form-label text-muted fw-bold">Stock Qty</label><input type="number" name="quantity_on_hand" id="edit_quantity" class="form-control" required></div>
                <div class="col"><label class="form-label text-muted fw-bold">Unit Price (₱)</label><input type="number" step="0.01" name="unit_price" id="edit_price" class="form-control" required></div>
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">Update Part</button>
          </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="deletePartModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius: 12px; border: none;">
      <form action="../controllers/PartController.php" method="POST">
          <div class="modal-body p-4 text-center">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="part_id" id="delete_part_id">
            <i class="bi bi-exclamation-circle text-danger" style="font-size: 3rem;"></i>
            <h4 class="fw-bold mt-3">Delete Part?</h4>
            <p class="text-muted">Are you sure you want to delete <strong id="delete_part_name"></strong>? This cannot be undone.</p>
            <div class="d-flex gap-2 mt-4">
                <button type="button" class="btn btn-light w-50 fw-bold" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger w-50 fw-bold">Delete</button>
            </div>
          </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function openEditModal(part) {
        document.getElementById('edit_part_id').value = part.part_id;
        document.getElementById('edit_part_name').value = part.part_name;
        document.getElementById('edit_quantity').value = part.quantity_on_hand;
        document.getElementById('edit_price').value = part.unit_price;
        new bootstrap.Modal(document.getElementById('editPartModal')).show();
    }
    function openDeleteModal(id, name) {
        document.getElementById('delete_part_id').value = id;
        document.getElementById('delete_part_name').innerText = name;
        new bootstrap.Modal(document.getElementById('deletePartModal')).show();
    }
</script>
</body>
</html>