<?php
session_start();
// STRICT SECURITY: Kick out anyone who isn't the Owner
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Owner') {
    header("Location: login.php");
    exit;
}

require_once '../config/Database.php';
require_once '../models/User.php';

$database = new Database();
$db = $database->getConnection();

$user = new User($db);
$stmtUsers = $user->readAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Staff Management - DASPMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm">
    <div class="container-fluid">
        <span class="navbar-brand mb-0 h1">DASPMS - Staff Accounts</span>
        <div class="d-flex">
            <a href="dashboard_owner.php" class="btn btn-outline-light btn-sm me-2">Back to Dashboard</a>
            <a href="../controllers/AuthController.php?action=logout" class="btn btn-outline-danger btn-sm">Logout</a>
        </div>
    </div>
</nav>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Manage Employees</h2>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
            + Register Staff Account
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
                        <th class="ps-4">Name</th>
                        <th>Username</th>
                        <th>System Role</th>
                        <th class="pe-4 text-end">Date Added</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $stmtUsers->fetch(PDO::FETCH_ASSOC)): ?>
                        <tr>
                            <td class="ps-4 fw-bold"><?php echo htmlspecialchars($row['last_name'] . ', ' . $row['first_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['username']); ?></td>
                            <td>
                                <?php 
                                    $badge = 'bg-secondary';
                                    if($row['role'] == 'Owner') $badge = 'bg-danger';
                                    if($row['role'] == 'Cashier') $badge = 'bg-primary';
                                    if($row['role'] == 'Head Mechanic') $badge = 'bg-success';
                                ?>
                                <span class="badge <?php echo $badge; ?>"><?php echo htmlspecialchars($row['role']); ?></span>
                            </td>
                            <td class="pe-4 text-end text-muted"><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addUserModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form action="../controllers/UserController.php" method="POST">
          <div class="modal-header bg-primary text-white">
            <h5 class="modal-title">Register New Staff</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="action" value="add">
            
            <div class="row mb-3">
                <div class="col">
                    <label class="form-label">First Name *</label>
                    <input type="text" name="first_name" class="form-control" required>
                </div>
                <div class="col">
                    <label class="form-label">Last Name *</label>
                    <input type="text" name="last_name" class="form-control" required>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label text-primary fw-bold">Assign Role *</label>
                <select name="role" class="form-select" required>
                    <option value="" disabled selected>-- Select Role --</option>
                    <option value="Cashier">Cashier (POS & Billing Access)</option>
                    <option value="Head Mechanic">Head Mechanic (Workshop Access)</option>
                </select>
            </div>

            <hr>

            <div class="mb-3">
                <label class="form-label">Login Username *</label>
                <input type="text" name="username" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Temporary Password *</label>
                <input type="password" name="password" class="form-control" required>
            </div>

          </div>
          <div class="modal-footer border-0 bg-light">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Create Account</button>
          </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>