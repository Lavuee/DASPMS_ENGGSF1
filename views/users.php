<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Owner') { header("Location: login.php"); exit; }
require_once '../config/Database.php';

$db = (new Database())->getConnection();
// Only fetch actual staff members (ignore Customers)
$stmt = $db->prepare("SELECT * FROM user WHERE role IN ('Owner', 'Cashier', 'Head Mechanic') ORDER BY role ASC, last_name ASC");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Staff Management - Norily's Repair Shop</title>
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
                <h2 class="fw-bold mb-1">Staff Accounts</h2>
                <p class="text-muted">Manage system access for employees</p>
            </div>
            <button class="btn btn-primary px-4 py-2 fw-bold" data-bs-toggle="modal" data-bs-target="#addUserModal"><i class="bi bi-person-plus"></i> Register Staff</button>
        </div>

        <?php if(isset($_SESSION['success_message'])): ?><div class="alert alert-success alert-dismissible fade show"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
        <?php if(isset($_SESSION['error_message'])): ?><div class="alert alert-danger alert-dismissible fade show"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

        <div class="row g-4">
            <?php foreach ($users as $u): 
                $initial = strtoupper(substr($u['first_name'], 0, 1));
                $badge = 'badge-soft-primary';
                if($u['role'] == 'Owner') $badge = 'badge-soft-danger';
                if($u['role'] == 'Head Mechanic') $badge = 'badge-soft-success';
            ?>
            <div class="col-md-4">
                <div class="custom-card text-center py-4">
                    <div class="avatar-box mx-auto mb-3" style="width: 64px; height: 64px; font-size: 1.5rem;"><?php echo $initial; ?></div>
                    <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?></h5>
                    <p class="text-muted mb-3">@<?php echo htmlspecialchars($u['username']); ?></p>
                    <span class="<?php echo $badge; ?> px-3 py-1"><?php echo htmlspecialchars($u['role']); ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </main>
</div>

<div class="modal fade" id="addUserModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius: 12px; border: none; box-shadow: 0 10px 25px rgba(0,0,0,0.1);">
      <form action="../controllers/UserController.php" method="POST">
          <div class="modal-header border-0 pb-0">
            <h5 class="modal-title fw-bold">Register New Staff</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body p-4">
            <input type="hidden" name="action" value="add">
            <div class="row mb-3 g-2">
                <div class="col"><label class="form-label text-muted fw-bold">First Name</label><input type="text" name="first_name" class="form-control" required></div>
                <div class="col"><label class="form-label text-muted fw-bold">Last Name</label><input type="text" name="last_name" class="form-control" required></div>
            </div>
            <div class="mb-3">
                <label class="form-label text-muted fw-bold">Assign Role</label>
                <select name="role" class="form-select" required>
                    <option value="Cashier">Cashier</option>
                    <option value="Head Mechanic">Head Mechanic</option>
                </select>
            </div>
            <hr class="my-4">
            <div class="mb-3"><label class="form-label text-muted fw-bold">Login Username</label><input type="text" name="username" class="form-control" required></div>
            <div class="mb-4"><label class="form-label text-muted fw-bold">Temporary Password</label><input type="password" name="password" class="form-control" required></div>
            <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">Create Account</button>
          </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>