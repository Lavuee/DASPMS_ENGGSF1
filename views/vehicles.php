<?php
session_start();
if (!isset($_SESSION['logged_in'])) { header("Location: login.php"); exit; }
require_once '../config/Database.php';

$db = (new Database())->getConnection();

$stmt = $db->prepare("SELECT v.*, c.first_name, c.last_name FROM vehicle v JOIN customer c ON v.customer_id = c.customer_id ORDER BY v.plate_number ASC");
$stmt->execute();
$vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
$vehicleCount = count($vehicles);

$stmtCustomers = $db->prepare("SELECT customer_id, first_name, last_name FROM customer ORDER BY first_name ASC");
$stmtCustomers->execute();
$customersList = $stmtCustomers->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Vehicles - Norily's Repair Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="app-wrapper">
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-1">Vehicles</h2>
                <p class="text-muted"><?php echo $vehicleCount; ?> registered vehicles</p>
            </div>
            <button class="btn btn-primary px-4 py-2 fw-bold" data-bs-toggle="modal" data-bs-target="#addVehicleModal"><i class="bi bi-plus-lg"></i> Add Vehicle</button>
        </div>

        <?php if(isset($_SESSION['success_message'])): ?><div class="alert alert-success alert-dismissible fade show"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
        <?php if(isset($_SESSION['error_message'])): ?><div class="alert alert-danger alert-dismissible fade show"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

        <div class="custom-search mb-4 shadow-sm">
            <i class="bi bi-search"></i>
            <input type="text" placeholder="Search by plate number, owner, or make...">
        </div>

        <div class="row g-4">
            <?php foreach ($vehicles as $v): ?>
            <div class="col-md-4">
                <div class="custom-card">
                    <div class="avatar-box"><i class="bi bi-car-front-fill"></i></div>
                    <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($v['plate_number']); ?></h5>
                    <p class="text-muted mb-3" style="font-size: 0.9rem;">
                        <?php echo htmlspecialchars($v['make'] . ' ' . $v['model'] . ' • ' . $v['year']); ?>
                    </p>
                    <div style="font-size: 0.9rem; font-weight: 500; color: var(--text-main);">
                        <?php echo htmlspecialchars($v['first_name'] . ' ' . $v['last_name']); ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </main>
</div>

<div class="modal fade" id="addVehicleModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius: 12px; border: none; box-shadow: 0 10px 25px rgba(0,0,0,0.1);">
      <form action="../controllers/VehicleController.php" method="POST">
          <div class="modal-header border-0 pb-0">
            <h5 class="modal-title fw-bold">Register Vehicle</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body p-4">
            <input type="hidden" name="action" value="add">
            
            <div class="mb-3">
                <label class="form-label text-muted fw-bold">Select Owner</label>
                <select name="customer_id" class="form-select" required>
                    <option value="" disabled selected>-- Choose Customer --</option>
                    <?php foreach ($customersList as $c): ?>
                        <option value="<?php echo $c['customer_id']; ?>">
                            <?php echo htmlspecialchars($c['first_name'] . ' ' . $c['last_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="mb-3">
                <label class="form-label text-muted fw-bold">Plate Number</label>
                <input type="text" name="plate_number" class="form-control text-uppercase" required>
            </div>
            <div class="row mb-3 g-2">
                <div class="col">
                    <label class="form-label text-muted fw-bold">Make</label>
                    <input type="text" name="make" class="form-control" placeholder="e.g. Toyota" required>
                </div>
                <div class="col">
                    <label class="form-label text-muted fw-bold">Model</label>
                    <input type="text" name="model" class="form-control" placeholder="e.g. Vios" required>
                </div>
            </div>
            <div class="row mb-4 g-2">
                <div class="col">
                    <label class="form-label text-muted fw-bold">Year</label>
                    <input type="number" name="year" class="form-control" required>
                </div>
                <div class="col">
                    <label class="form-label text-muted fw-bold">Color</label>
                    <input type="text" name="color" class="form-control" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">Save Vehicle</button>
          </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>