<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] === 'Head Mechanic' || $_SESSION['role'] === 'Customer') { header("Location: login.php"); exit; }
require_once '../config/Database.php';

$db = (new Database())->getConnection();

$stmtV = $db->prepare("SELECT v.*, c.first_name, c.last_name FROM vehicle v JOIN customer c ON v.customer_id = c.customer_id ORDER BY v.plate_number ASC");
$stmtV->execute();
$vehicles = $stmtV->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>New Job Order - Norily's Repair Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="app-wrapper">
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="mb-4 d-flex align-items-center gap-3">
            <a href="job_orders.php" class="btn btn-light border"><i class="bi bi-arrow-left"></i> Back</a>
            <div>
                <h2 class="fw-bold mb-0">Create New Job Order</h2>
                <p class="text-muted mb-0">Register a new vehicle repair task</p>
            </div>
        </div>

        <div class="custom-card" style="max-width: 800px;">
            <form action="../controllers/JobOrderController.php" method="POST">
                <input type="hidden" name="action" value="create">
                
                <div class="mb-4">
                    <label class="form-label text-muted fw-bold">Select Vehicle & Customer *</label>
                    <select name="vehicle_id" class="form-select form-select-lg" required>
                        <option value="" disabled selected>-- Search by Plate Number --</option>
                        <?php foreach ($vehicles as $v): ?>
                            <option value="<?php echo $v['vehicle_id']; ?>">
                                <?php echo htmlspecialchars($v['plate_number'] . ' - ' . $v['make'] . ' ' . $v['model'] . ' (' . $v['first_name'] . ' ' . $v['last_name'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="form-label text-muted fw-bold">Repair Description *</label>
                    <textarea name="description" class="form-control" rows="4" placeholder="Describe the issues, symptoms, and requested repairs..." required></textarea>
                </div>

                <div class="mb-5">
                    <label class="form-label text-muted fw-bold">Estimated Cost (₱) *</label>
                    <input type="number" step="0.01" name="estimated_cost" class="form-control form-control-lg" required>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-3 fw-bold fs-5">Save & Create Job Order</button>
            </form>
        </div>
    </main>
</div>
</body>
</html>