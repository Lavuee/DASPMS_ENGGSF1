<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$userInitial = strtoupper(substr($_SESSION['first_name'], 0, 1));
$userRole = $_SESSION['role'];

$dashboardMap = [
    'Owner' => 'dashboard_owner.php',
    'Cashier' => 'dashboard_cashier.php',
    'Head Mechanic' => 'dashboard_mechanic.php',
    'Customer' => 'dashboard_customer.php'
];
$dashLink = $dashboardMap[$userRole] ?? 'login.php';
?>

<nav class="sidebar">
    <div class="sidebar-header">
        <img src="../assets/img/logo.png" alt="Norily's Logo" class="sidebar-logo-img">
        <div>
            <h5 class="mb-0 fw-bold" style="color: var(--white); letter-spacing: 0.5px;">Norily's</h5>
            <small style="font-size: 0.75rem; color: #9ca3af;">Repair Shop</small>
        </div>
    </div>

    <div class="sidebar-nav">
        <a href="<?php echo $dashLink; ?>" class="nav-link <?php echo ($currentPage == $dashLink) ? 'active' : ''; ?>">
            <i class="bi bi-grid-1x2"></i> Dashboard
        </a>

        <?php if ($userRole === 'Owner' || $userRole === 'Cashier'): ?>
            <a href="pos.php" class="nav-link <?php echo ($currentPage == 'pos.php') ? 'active' : ''; ?>">
                <i class="bi bi-calculator"></i> POS Terminal
            </a>
            <?php if ($userRole === 'Owner'): ?>
                <a href="customers.php" class="nav-link <?php echo ($currentPage == 'customers.php') ? 'active' : ''; ?>">
                    <i class="bi bi-people"></i> Customers
                </a>
                <a href="vehicles.php" class="nav-link <?php echo ($currentPage == 'vehicles.php') ? 'active' : ''; ?>">
                    <i class="bi bi-car-front"></i> Vehicles
                </a>
            <?php endif; ?>
            <a href="job_orders.php" class="nav-link <?php echo ($currentPage == 'job_orders.php') ? 'active' : ''; ?>">
                <i class="bi bi-tools"></i> Job Orders
            </a>
            <a href="online_orders.php" class="nav-link <?php echo ($currentPage == 'online_orders.php') ? 'active' : ''; ?>">
                <i class="bi bi-globe"></i> Web Orders
            </a>
            <a href="billing.php" class="nav-link <?php echo ($currentPage == 'billing.php') ? 'active' : ''; ?>">
                <i class="bi bi-receipt"></i> Billing & Invoices
            </a>
        <?php endif; ?>

        <?php if ($userRole === 'Head Mechanic'): ?>
            <a href="job_orders.php" class="nav-link <?php echo ($currentPage == 'job_orders.php') ? 'active' : ''; ?>">
                <i class="bi bi-tools"></i> Active Jobs
            </a>
            <a href="inventory_view.php" class="nav-link <?php echo ($currentPage == 'inventory_view.php') ? 'active' : ''; ?>">
                <i class="bi bi-box-seam"></i> Parts Availability
            </a>
        <?php endif; ?>

        <?php if ($userRole === 'Owner' || $userRole === 'Cashier'): ?>
            <a href="inventory.php" class="nav-link <?php echo ($currentPage == 'inventory.php') ? 'active' : ''; ?>">
                <i class="bi bi-box-seam"></i> Inventory
            </a>
        <?php endif; ?>

        <?php if ($userRole === 'Owner'): ?>
            <a href="reports.php" class="nav-link <?php echo ($currentPage == 'reports.php') ? 'active' : ''; ?>">
                <i class="bi bi-bar-chart"></i> Reports
            </a>
            <a href="users.php" class="nav-link <?php echo ($currentPage == 'users.php') ? 'active' : ''; ?>">
                <i class="bi bi-person-badge"></i> Staff Accounts
            </a>
        <?php endif; ?>
    </div>

    <div class="sidebar-footer position-relative">
        <div class="user-avatar"><?php echo $userInitial; ?></div>
        <div class="overflow-hidden">
            <div class="fw-bold text-truncate" style="font-size: 0.95rem; color: var(--white);"><?php echo htmlspecialchars($_SESSION['first_name']); ?></div>
            <div style="font-size: 0.8rem; color: #9ca3af;"><?php echo htmlspecialchars($userRole); ?></div>
        </div>
        <a href="#" onclick="showLogoutModal(event)" class="position-absolute end-0 me-3" style="color: #9ca3af; transition: 0.2s; cursor: pointer;" title="Sign Out" onmouseover="this.style.color='var(--danger)'" onmouseout="this.style.color='#9ca3af'">
            <i class="bi bi-box-arrow-right fs-4"></i>
        </a>
    </div>
</nav>

<div class="modal fade" id="customLogoutModal" tabindex="-1" style="z-index: 9999;">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content text-center p-3" style="border-radius: 16px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
      <div class="modal-body">
        <i class="bi bi-box-arrow-right text-danger mb-3" style="font-size: 3rem;"></i>
        <h4 class="fw-bold mb-2">Sign Out?</h4>
        <p class="text-muted" style="font-size: 0.95rem;">Are you sure you want to log out of your account?</p>
        <div class="d-flex justify-content-center gap-2 mt-4">
            <button type="button" class="btn btn-light fw-bold w-50" onclick="hideLogoutModal()">Cancel</button>
            <a href="../controllers/AuthController.php?action=logout" class="btn btn-danger fw-bold w-50">Sign Out</a>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
    function showLogoutModal(e) {
        e.preventDefault();
        const modal = document.getElementById('customLogoutModal');
        
        if (modal.parentNode !== document.body) {
            document.body.appendChild(modal);
        }
        
        modal.style.display = 'block';
        setTimeout(() => modal.classList.add('show'), 10);
        
        if (!document.getElementById('customBackdrop')) {
            const backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop fade show';
            backdrop.id = 'customBackdrop';
            backdrop.style.zIndex = '9998';
            document.body.appendChild(backdrop);
        }
    }

    function hideLogoutModal() {
        const modal = document.getElementById('customLogoutModal');
        modal.classList.remove('show');
        setTimeout(() => modal.style.display = 'none', 150);
        
        const backdrop = document.getElementById('customBackdrop');
        if (backdrop) backdrop.remove();
    }
</script>