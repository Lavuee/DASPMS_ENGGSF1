<?php
session_start();
if (!isset($_SESSION['logged_in'])) { header("Location: login.php"); exit; }
require_once '../config/Database.php';

$db = (new Database())->getConnection();

// Fetch all customers
$stmt = $db->prepare("SELECT * FROM customer ORDER BY first_name ASC");
$stmt->execute();
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
$customerCount = count($customers);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customers - Norily's Repair Shop</title>
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
                <h2 class="fw-bold mb-1">Customers</h2>
                <p class="text-muted"><?php echo $customerCount; ?> registered customers</p>
            </div>
            <button class="btn btn-primary px-4 py-2 fw-bold"><i class="bi bi-plus-lg"></i> Add Customer</button>
        </div>

        <div class="custom-search mb-4 shadow-sm">
            <i class="bi bi-search"></i>
            <input type="text" placeholder="Search by name or phone number...">
        </div>

        <div class="row g-4">
            <?php foreach ($customers as $c): 
                $initial = strtoupper(substr($c['first_name'], 0, 1));
            ?>
            <div class="col-md-4">
                <div class="custom-card">
                    <div class="avatar-box"><?php echo $initial; ?></div>
                    <h5 class="fw-bold mb-3"><?php echo htmlspecialchars($c['first_name'] . ' ' . $c['last_name']); ?></h5>
                    
                    <div class="text-muted mb-2" style="font-size: 0.9rem;">
                        <i class="bi bi-telephone me-2"></i> <?php echo htmlspecialchars($c['contact_number']); ?>
                    </div>
                    <div class="text-muted mb-3" style="font-size: 0.9rem;">
                        <i class="bi bi-geo-alt me-2"></i> <?php echo htmlspecialchars($c['address']); ?>
                    </div>
                    
                    <?php if ($c['credit_balance'] > 0): ?>
                        <div class="text-warning fw-bold" style="font-size: 0.9rem;">
                            <i class="bi bi-credit-card me-2"></i> ₱<?php echo number_format($c['credit_balance'], 2); ?> balance
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>