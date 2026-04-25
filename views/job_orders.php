<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] === 'Customer') { header("Location: login.php"); exit; }
require_once '../config/Database.php';

$db = (new Database())->getConnection();

// Fetch Job Orders with details
$query = "SELECT jo.*, c.first_name, c.last_name, v.plate_number 
          FROM job_order jo 
          JOIN customer c ON jo.customer_id = c.customer_id 
          JOIN vehicle v ON jo.vehicle_id = v.vehicle_id 
          ORDER BY 
            CASE jo.status 
                WHEN 'Pending' THEN 1 
                WHEN 'In Progress' THEN 2 
                WHEN 'Ready for Pickup' THEN 3
                WHEN 'Completed' THEN 4
            END, jo.date_created DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$jobOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count statuses for tabs
$counts = ['All' => count($jobOrders), 'Pending' => 0, 'In Progress' => 0, 'Completed' => 0];
foreach ($jobOrders as $jo) {
    if ($jo['status'] == 'Pending') $counts['Pending']++;
    if ($jo['status'] == 'In Progress') $counts['In Progress']++;
    if ($jo['status'] == 'Completed' || $jo['status'] == 'Ready for Pickup') $counts['Completed']++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Job Orders - Norily's Repair Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .tabs { display: flex; gap: 20px; border-bottom: 1px solid #e2e8f0; margin-bottom: 1.5rem; padding-bottom: 10px; }
        .tab-item { color: var(--text-muted); font-weight: 500; cursor: pointer; padding: 5px 10px; border-radius: 20px; transition: all 0.2s;}
        .tab-item.active { background-color: #f1f5f9; color: var(--text-main); font-weight: 600; }
        .tab-item:hover { color: var(--primary-blue); }
    </style>
</head>
<body>

<div class="app-wrapper">
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-1">Job Orders</h2>
                <p class="text-muted"><?php echo $counts['All']; ?> total orders</p>
            </div>
            <?php if ($_SESSION['role'] !== 'Head Mechanic'): ?>
                <a href="create_job_order.php" class="btn btn-primary px-4 py-2 fw-bold"><i class="bi bi-plus-lg"></i> New Job Order</a>
            <?php endif; ?>
        </div>

        <?php if(isset($_SESSION['success_message'])): ?><div class="alert alert-success alert-dismissible fade show"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
        <?php if(isset($_SESSION['error_message'])): ?><div class="alert alert-danger alert-dismissible fade show"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

        <div class="tabs" id="joTabs">
            <div class="tab-item active" data-filter="All">All (<?php echo $counts['All']; ?>)</div>
            <div class="tab-item" data-filter="Pending">Pending (<?php echo $counts['Pending']; ?>)</div>
            <div class="tab-item" data-filter="In Progress">In Progress (<?php echo $counts['In Progress']; ?>)</div>
            <div class="tab-item" data-filter="Done">Done (<?php echo $counts['Completed']; ?>)</div>
        </div>

        <div class="custom-search mb-4 shadow-sm">
            <i class="bi bi-search"></i>
            <input type="text" id="searchInput" placeholder="Search by job number, customer, or description...">
        </div>

        <div class="row g-3" id="jobList">
            <?php if (count($jobOrders) > 0): ?>
                <?php foreach ($jobOrders as $jo): 
                    $badgeClass = 'badge-soft-warning';
                    $filterGroup = 'Pending';
                    if ($jo['status'] == 'In Progress') { $badgeClass = 'badge-soft-primary'; $filterGroup = 'In Progress'; }
                    if ($jo['status'] == 'Completed' || $jo['status'] == 'Ready for Pickup') { $badgeClass = 'badge-soft-success'; $filterGroup = 'Done'; }
                ?>
                <div class="col-12 jo-card" data-status="<?php echo $filterGroup; ?>">
                    <div class="custom-card p-4 d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-box mb-0"><i class="bi bi-wrench"></i></div>
                            <div>
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <h6 class="fw-bold mb-0 jo-search-target"><?php echo htmlspecialchars($jo['job_order_number'] . ' - ' . $jo['description']); ?></h6>
                                    <span class="<?php echo $badgeClass; ?> ms-2" style="font-size: 0.75rem;"><?php echo htmlspecialchars($jo['status']); ?></span>
                                </div>
                                <div class="text-muted mb-1 jo-search-target" style="font-size: 0.85rem;">
                                    <?php echo htmlspecialchars($jo['first_name'] . ' ' . $jo['last_name']); ?> • <?php echo htmlspecialchars($jo['plate_number']); ?> • <?php echo date('M d, Y', strtotime($jo['date_created'])); ?>
                                </div>
                                <div class="text-muted fw-bold" style="font-size: 0.85rem;">
                                    Est: ₱<?php echo number_format($jo['estimated_cost']); ?> 
                                    <?php if(isset($jo['final_cost']) && $jo['final_cost'] > 0): ?>
                                        • Final: ₱<?php echo number_format($jo['final_cost']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <?php if ($jo['status'] == 'Pending'): ?>
                                <form action="../controllers/JobOrderController.php" method="POST">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="job_order_id" value="<?php echo $jo['job_order_id']; ?>">
                                    <input type="hidden" name="status" value="In Progress">
                                    <button type="submit" class="btn btn-primary fw-bold px-3 py-2" style="font-size: 0.9rem;">Start Work</button>
                                </form>
                            <?php elseif ($jo['status'] == 'In Progress'): ?>
                                <form action="../controllers/JobOrderController.php" method="POST">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="job_order_id" value="<?php echo $jo['job_order_id']; ?>">
                                    <input type="hidden" name="status" value="Completed">
                                    <button type="submit" class="btn btn-success fw-bold px-3 py-2" style="font-size: 0.9rem;">Complete Job</button>
                                </form>
                            <?php elseif ($jo['status'] == 'Completed' || $jo['status'] == 'Ready for Pickup'): ?>
                                <button class="btn btn-outline-success px-3 py-2 fw-bold" style="font-size: 0.9rem;" disabled><i class="bi bi-check-circle"></i> Done</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center py-5 text-muted">No active job orders.</div>
            <?php endif; ?>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Tab Filtering Logic
    const tabs = document.querySelectorAll('.tab-item');
    const cards = document.querySelectorAll('.jo-card');

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            // Update active tab styling
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');

            // Filter cards
            const filter = tab.getAttribute('data-filter');
            cards.forEach(card => {
                if (filter === 'All' || card.getAttribute('data-status') === filter) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    });

    // Search Bar Logic
    document.getElementById('searchInput').addEventListener('input', function(e) {
        const term = e.target.value.toLowerCase();
        cards.forEach(card => {
            const text = card.innerText.toLowerCase();
            card.style.display = text.includes(term) ? 'block' : 'none';
        });
    });
</script>
</body>
</html>