<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Head Mechanic') {
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
    <title>Parts Availability - DASPMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-secondary mb-4 shadow-sm">
    <div class="container-fluid">
        <span class="navbar-brand mb-0 h1">DASPMS - Workshop</span>
        <div class="d-flex">
            <a href="dashboard_mechanic.php" class="btn btn-outline-light btn-sm me-2">Back to Workspace</a>
            <a href="../controllers/AuthController.php?action=logout" class="btn btn-outline-danger btn-sm">Logout</a>
        </div>
    </div>
</nav>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Parts Availability Check</h2>
        <span class="text-muted">Read-Only Workshop View</span>
    </div>

    <div class="alert alert-secondary">
        <strong>Note:</strong> If a part you need for a repair is out of stock, please notify the Cashier or Owner to process a Stock-In.
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th class="ps-4">Category</th>
                        <th>Part Name</th>
                        <th>Description</th>
                        <th class="pe-4 text-end">Current Stock</th>
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
                                <td><strong><?php echo htmlspecialchars($row['part_name']); ?></strong></td>
                                <td class="text-muted"><small><?php echo htmlspecialchars($row['description']); ?></small></td>
                                <td class="pe-4 text-end">
                                    <span class="fs-5 fw-bold <?php echo $isLowStock ? 'text-danger' : 'text-success'; ?>">
                                        <?php echo htmlspecialchars($row['quantity_on_hand']); ?>
                                    </span>
                                    <?php if($row['quantity_on_hand'] == 0): ?>
                                        <br><span class="badge bg-danger">OUT OF STOCK</span>
                                    <?php elseif($isLowStock): ?>
                                        <br><span class="badge bg-warning text-dark">Low Stock</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center py-4 text-muted">No auto parts in inventory.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>