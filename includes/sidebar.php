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

$cartCount = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cartCount += intval($item['qty'] ?? 0);
    }
}
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

        <?php if ($userRole === 'Customer'): ?>
            <a href="customer_parts.php" class="nav-link <?php echo ($currentPage == 'customer_parts.php') ? 'active' : ''; ?>">
                <i class="bi bi-box-seam"></i> Browse Parts
            </a>

            <a href="customer_cart.php" class="nav-link <?php echo ($currentPage == 'customer_cart.php') ? 'active' : ''; ?>">
                <i class="bi bi-cart3"></i> Cart
                <?php if ($cartCount > 0): ?>
                    (<?php echo $cartCount; ?>)
                <?php endif; ?>
            </a>
        <?php endif; ?>

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

            <a href="services.php" class="nav-link <?php echo ($currentPage == 'services.php') ? 'active' : ''; ?>">
                <i class="bi bi-wrench-adjustable-circle"></i> Services
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
            <div class="fw-bold text-truncate" style="font-size: 0.95rem; color: var(--white);">
                <?php echo htmlspecialchars($_SESSION['first_name']); ?>
            </div>

            <div style="font-size: 0.8rem; color: #9ca3af;">
                <?php echo htmlspecialchars($userRole); ?>
            </div>
        </div>

        <a
            href="#"
            onclick="showLogoutModal(event)"
            class="position-absolute end-0 me-3"
            style="color: #9ca3af; transition: 0.2s; cursor: pointer;"
            title="Sign Out"
            onmouseover="this.style.color='var(--dashboard-primary)'"
            onmouseout="this.style.color='#9ca3af'"
        >
            <i class="bi bi-box-arrow-right fs-4"></i>
        </a>
    </div>
</nav>

<div class="modal fade" id="customLogoutModal" tabindex="-1" style="z-index: 9999;">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div
            class="modal-content text-center p-3"
            style="
                border-radius: 18px;
                border: none;
                box-shadow: 0 18px 45px rgba(0,0,0,0.32);
                overflow: hidden;
            "
        >
            <div class="modal-body px-3 py-4">
                <div
                    class="mx-auto mb-3"
                    style="
                        width: 64px;
                        height: 64px;
                        border-radius: 18px;
                        background: rgba(245, 197, 24, 0.16);
                        color: var(--black);
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    "
                >
                    <i class="bi bi-box-arrow-right" style="font-size: 2.3rem;"></i>
                </div>

                <h4 class="fw-bold mb-2" style="color: var(--black);">
                    Sign Out?
                </h4>

                <p class="text-muted mb-0" style="font-size: 0.95rem; line-height: 1.5;">
                    Are you sure you want to log out of your account?
                </p>

                <div class="d-flex justify-content-center gap-2 mt-4">
                    <button
                        type="button"
                        class="btn fw-bold w-50"
                        onclick="hideLogoutModal()"
                        style="
                            background: #f8fafc;
                            border: 1px solid #e5e7eb;
                            color: var(--dashboard-text-main);
                            border-radius: 10px;
                            min-height: 42px;
                        "
                    >
                        Cancel
                    </button>

                    <a
                        href="../controllers/AuthController.php?action=logout"
                        class="btn fw-bold w-50"
                        style="
                            background: var(--dashboard-primary);
                            border: 1px solid var(--dashboard-primary);
                            color: var(--black);
                            border-radius: 10px;
                            min-height: 42px;
                            display: inline-flex;
                            align-items: center;
                            justify-content: center;
                            text-decoration: none;
                        "
                        onmouseover="this.style.background='var(--black)'; this.style.borderColor='var(--black)'; this.style.color='var(--white)'"
                        onmouseout="this.style.background='var(--dashboard-primary)'; this.style.borderColor='var(--dashboard-primary)'; this.style.color='var(--black)'"
                    >
                        Sign Out
                    </a>
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

    if (backdrop) {
        backdrop.remove();
    }
}
</script>