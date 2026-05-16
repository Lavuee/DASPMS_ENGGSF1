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

/*
    Financial modules such as Billing, Payment, and Invoice History
    should only be accessible to roles assigned to payment/billing work.
    Head Mechanic should not see these modules.
*/
$canAccessFinancialModules = ($userRole === 'Owner' || $userRole === 'Cashier');

$cartCount = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cartCount += intval($item['qty'] ?? 0);
    }
}
?>

<style>
/* =========================================
   Base Sidebar & PC Scrollbar Fix
   ========================================= */
.sidebar {
    display: flex;
    flex-direction: column;
    height: 100vh;
    width: 260px;
    transition: width 0.4s cubic-bezier(0.25, 1, 0.5, 1);
    background-color: #111827;
    z-index: 1040;
}

.sidebar-nav {
    flex-grow: 1;
    overflow-y: auto;
    overflow-x: hidden;
    /* Firefox */
    scrollbar-width: thin;
    scrollbar-color: #4b5563 transparent; 
}

/* Chrome, Edge, and Safari Custom Scrollbar */
.sidebar-nav::-webkit-scrollbar {
    width: 6px;
}
.sidebar-nav::-webkit-scrollbar-track {
    background: transparent; 
}
.sidebar-nav::-webkit-scrollbar-thumb {
    background-color: #4b5563;
    border-radius: 10px;
}
.sidebar-nav::-webkit-scrollbar-thumb:hover {
    background-color: #6b7280; 
}

/* =========================================
   Header & Logo Layout
   ========================================= */
.sidebar-header {
    display: flex;
    align-items: center;
    padding: 24px 20px;
    position: relative;
    min-height: 85px;
    transition: all 0.3s ease;
}

.brand-box {
    display: flex;
    align-items: center;
    gap: 14px; /* Perfect spacing between logo and text */
}

.sidebar-logo-img {
    width: 38px;
    height: 38px;
    object-fit: contain;
    flex-shrink: 0;
}

.brand-text {
    display: flex;
    flex-direction: column;
    justify-content: center;
    white-space: nowrap;
}

.brand-text h5 {
    margin: 0 0 2px 0;
    font-size: 1.15rem;
    line-height: 1;
}

.brand-text small {
    margin: 0;
    font-size: 0.75rem;
    line-height: 1;
}

/* =========================================
   PC Collapse Animation System
   ========================================= */
.sidebar-text {
    transition: opacity 0.25s ease, transform 0.3s ease;
    opacity: 1;
}

.sidebar.collapsed {
    width: 80px;
}

.sidebar.collapsed .sidebar-text {
    opacity: 0;
    pointer-events: none;
    position: absolute;
    transform: translateX(10px);
}

/* Desktop Collapse Button */
.desktop-collapse-btn {
    position: absolute;
    right: 16px;
    top: 50%;
    transform: translateY(-50%);
    background: transparent;
    border: none;
    color: #9ca3af;
    cursor: pointer;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 6px;
    border-radius: 8px;
    transition: background 0.2s, color 0.2s;
    z-index: 2;
}

.desktop-collapse-btn:hover {
    background: rgba(255, 255, 255, 0.1);
    color: var(--white);
}

.desktop-collapse-btn i {
    font-size: 1.1rem;
    transition: transform 0.4s cubic-bezier(0.25, 1, 0.5, 1);
}

@media (min-width: 769px) {
    .desktop-collapse-btn {
        display: flex;
    }
}

/* Header behavior when collapsed */
.sidebar.collapsed .sidebar-header {
    flex-direction: column;
    padding: 20px 0;
    justify-content: flex-start;
}

.sidebar.collapsed .brand-box {
    gap: 0;
    justify-content: center;
}

/* Move button perfectly below the logo when collapsed */
.sidebar.collapsed .desktop-collapse-btn {
    position: static;
    transform: none;
    margin-top: 14px;
}

.sidebar.collapsed .desktop-collapse-btn i {
    transform: rotate(180deg);
}

/* Nav Link overrides for collapse */
.sidebar.collapsed .nav-link {
    justify-content: center;
    padding: 12px 0;
}

.sidebar.collapsed .nav-link i {
    margin-right: 0;
    font-size: 1.2rem;
}

/* Footer overrides for collapse */
.sidebar.collapsed .sidebar-footer {
    justify-content: center;
    padding: 20px 10px;
}

.sidebar.collapsed .sidebar-footer .user-avatar {
    margin-right: 0;
}

/* =========================================
   Mobile Top Bar & Sidebar System
   ========================================= */
.mobile-top-bar {
    display: none; 
}
.sidebar-overlay {
    display: none;
}

@media (max-width: 768px) {
    body {
        padding-top: 60px;
    }

    .mobile-top-bar {
        display: flex;
        align-items: center;
        justify-content: flex-start; 
        gap: 16px; 
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        height: 60px;
        background-color: #111827;
        padding: 0 16px;
        z-index: 1020;
        box-shadow: 0 4px 15px rgba(0,0,0,0.15);
    }

    .mobile-top-bar-brand {
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: bold;
        font-size: 1.1rem;
        color: var(--white, #ffffff);
    }

    .mobile-top-bar-brand img {
        width: 32px;
        height: 32px;
        object-fit: contain;
    }

    .mobile-sidebar-btn {
        background: transparent;
        color: var(--white, #ffffff);
        border: none;
        padding: 5px;
        font-size: 1.8rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: transform 0.2s, color 0.2s;
    }

    .mobile-sidebar-btn:active {
        transform: scale(0.9);
        color: var(--dashboard-primary, #f5c518);
    }

    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        height: 100vh;
        z-index: 1040;
        transform: translateX(-100%);
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 4px 0 25px rgba(0,0,0,0.4);
    }

    .sidebar.mobile-open {
        transform: translateX(0);
    }

    .sidebar-overlay {
        display: block;
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        z-index: 1030;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.3s ease, visibility 0.3s ease;
    }

    .sidebar-overlay.mobile-open {
        opacity: 1;
        visibility: visible;
    }
    
    .desktop-collapse-btn {
        display: none !important; 
    }
}
</style>

<div class="mobile-top-bar">
    <button class="mobile-sidebar-btn" onclick="toggleMobileSidebar()" aria-label="Toggle Sidebar">
        <i class="bi bi-list"></i>
    </button>
    <div class="mobile-top-bar-brand">
        <img src="../assets/img/logo.png" alt="Logo">
        <span style="color: var(--white);">Norily's</span>
    </div>
</div>

<div class="sidebar-overlay" id="mobileOverlay" onclick="toggleMobileSidebar()"></div>

<nav class="sidebar" id="mainSidebar">
    
    <div class="sidebar-header">
        <div class="brand-box">
            <img src="../assets/img/logo.png" alt="Norily's Logo" class="sidebar-logo-img">
            <div class="brand-text sidebar-text">
                <h5 class="fw-bold" style="color: var(--white); letter-spacing: 0.5px;">Norily's</h5>
                <small style="color: #9ca3af;">Repair Shop</small>
            </div>
        </div>
        <button class="desktop-collapse-btn" onclick="toggleDesktopSidebar()" title="Toggle Sidebar">
            <i class="bi bi-chevron-left"></i>
        </button>
    </div>

    <div class="sidebar-nav">
        <a href="<?php echo $dashLink; ?>" class="nav-link <?php echo ($currentPage == $dashLink) ? 'active' : ''; ?>">
            <i class="bi bi-grid-1x2"></i> <span class="sidebar-text">Dashboard</span>
        </a>
        
        <?php if ($userRole === 'Customer'): ?>
            <a href="customer_parts.php" class="nav-link <?php echo ($currentPage == 'customer_parts.php' || $currentPage == 'customer_part_details.php') ? 'active' : ''; ?>">
                <i class="bi bi-box-seam"></i> <span class="sidebar-text">Browse Parts</span>
            </a>

            <a href="customer_cart.php" class="nav-link <?php echo ($currentPage == 'customer_cart.php') ? 'active' : ''; ?>">
                <i class="bi bi-cart3"></i> 
                <span class="sidebar-text">
                    Cart
                    <?php if ($cartCount > 0): ?>
                        (<?php echo $cartCount; ?>)
                    <?php endif; ?>
                </span>
            </a>

            <a href="customer_services.php" class="nav-link <?php echo ($currentPage == 'customer_services.php' || $currentPage == 'customer_service_details.php') ? 'active' : ''; ?>">
                <i class="bi bi-wrench-adjustable-circle"></i> <span class="sidebar-text">Services Offered</span>
            </a>

            <a href="customer_service_requests.php" class="nav-link <?php echo ($currentPage == 'customer_service_requests.php' || $currentPage == 'customer_request_service.php') ? 'active' : ''; ?>">
                <i class="bi bi-calendar-check"></i> <span class="sidebar-text">My Service Requests</span>
            </a>
        <?php endif; ?>

        <?php if ($userRole === 'Owner' || $userRole === 'Cashier'): ?>
            <a href="pos.php" class="nav-link <?php echo ($currentPage == 'pos.php') ? 'active' : ''; ?>">
                <i class="bi bi-calculator"></i> <span class="sidebar-text">POS Terminal</span>
            </a>
            <?php if ($userRole === 'Owner' || $userRole === 'Cashier'): ?>
                <a href="create_transaction.php" class="nav-link <?php echo ($currentPage == 'create_transaction.php') ? 'active' : ''; ?>">
                    <i class="bi bi-plus-circle-fill"></i>
                    <span class="sidebar-text">Transaction Form</span>
                </a>
            <?php endif; ?>

            <?php if ($userRole === 'Owner'): ?>
                <a href="customers.php" class="nav-link <?php echo ($currentPage == 'customers.php') ? 'active' : ''; ?>">
                    <i class="bi bi-people"></i> <span class="sidebar-text">Customers</span>
                </a>

                <a href="vehicles.php" class="nav-link <?php echo ($currentPage == 'vehicles.php') ? 'active' : ''; ?>">
                    <i class="bi bi-car-front"></i> <span class="sidebar-text">Vehicles</span>
                </a>
            <?php endif; ?>

            <a href="job_orders.php" class="nav-link <?php echo ($currentPage == 'job_orders.php') ? 'active' : ''; ?>">
                <i class="bi bi-tools"></i> <span class="sidebar-text">Job Orders</span>
            </a>

            <a href="service_requests.php" class="nav-link <?php echo ($currentPage == 'service_requests.php') ? 'active' : ''; ?>">
                <i class="bi bi-calendar-check"></i> <span class="sidebar-text">Service Requests</span>
            </a>

            <a href="online_orders.php" class="nav-link <?php echo ($currentPage == 'online_orders.php') ? 'active' : ''; ?>">
                <i class="bi bi-globe"></i> <span class="sidebar-text">Web Orders</span>
            </a>

            <?php if ($canAccessFinancialModules): ?>
                <a href="billing.php" class="nav-link <?php echo ($currentPage == 'billing.php') ? 'active' : ''; ?>">
                    <i class="bi bi-receipt"></i> <span class="sidebar-text">Billing & Invoices</span>
                </a>

                <a href="invoice_history.php" class="nav-link <?php echo ($currentPage == 'invoice_history.php') ? 'active' : ''; ?>">
                    <i class="bi bi-clock-history"></i> <span class="sidebar-text">Invoice History</span>
                </a>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($userRole === 'Head Mechanic'): ?>
            <a href="job_orders.php" class="nav-link <?php echo ($currentPage == 'job_orders.php') ? 'active' : ''; ?>">
                <i class="bi bi-tools"></i> <span class="sidebar-text">Active Jobs</span>
            </a>

            <a href="inventory_view.php" class="nav-link <?php echo ($currentPage == 'inventory_view.php') ? 'active' : ''; ?>">
                <i class="bi bi-box-seam"></i> <span class="sidebar-text">Parts Availability</span>
            </a>
        <?php endif; ?>

        <?php if ($userRole === 'Owner' || $userRole === 'Cashier'): ?>
            <a href="inventory.php" class="nav-link <?php echo ($currentPage == 'inventory.php') ? 'active' : ''; ?>">
                <i class="bi bi-box-seam"></i> <span class="sidebar-text">Inventory</span>
            </a>

            <a href="suppliers.php" class="nav-link <?php echo ($currentPage == 'suppliers.php') ? 'active' : ''; ?>">
                <i class="bi bi-truck"></i> <span class="sidebar-text">Suppliers</span>
            </a>

            <a href="services.php" class="nav-link <?php echo ($currentPage == 'services.php') ? 'active' : ''; ?>">
                <i class="bi bi-wrench-adjustable-circle"></i> <span class="sidebar-text">Services</span>
            </a>
        <?php endif; ?>

        <?php if ($userRole === 'Owner'): ?>
            <a href="reports.php" class="nav-link <?php echo ($currentPage == 'reports.php') ? 'active' : ''; ?>">
                <i class="bi bi-bar-chart"></i> <span class="sidebar-text">Reports</span>
            </a>

            <a href="users.php" class="nav-link <?php echo ($currentPage == 'users.php') ? 'active' : ''; ?>">
                <i class="bi bi-person-badge"></i> <span class="sidebar-text">Staff Accounts</span>
            </a>
        <?php endif; ?>
    </div>

    <div class="sidebar-footer position-relative" style="display: flex; align-items: center; cursor: pointer;" onclick="showLogoutModal(event)">
        <div class="user-avatar"><?php echo $userInitial; ?></div>

        <div class="sidebar-text overflow-hidden" style="flex-grow: 1;">
            <div class="fw-bold text-truncate" style="font-size: 0.95rem; color: var(--white);">
                <?php echo htmlspecialchars($_SESSION['first_name']); ?>
            </div>
            <div style="font-size: 0.8rem; color: #9ca3af;">
                <?php echo htmlspecialchars($userRole); ?>
            </div>
        </div>

        <div class="sidebar-text position-absolute end-0 me-3" style="color: #9ca3af; transition: 0.2s;" title="Sign Out">
            <i class="bi bi-box-arrow-right fs-4"></i>
        </div>
    </div>
</nav>

<div class="modal fade" id="customLogoutModal" tabindex="-1" style="z-index: 9999;">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content text-center p-3" style="border-radius: 18px; border: none; box-shadow: 0 18px 45px rgba(0,0,0,0.32); overflow: hidden;">
            <div class="modal-body px-3 py-4">
                <div class="mx-auto mb-3" style="width: 64px; height: 64px; border-radius: 18px; background: rgba(245, 197, 24, 0.16); color: var(--black); display: flex; align-items: center; justify-content: center;">
                    <i class="bi bi-box-arrow-right" style="font-size: 2.3rem;"></i>
                </div>
                <h4 class="fw-bold mb-2" style="color: var(--black);">Sign Out?</h4>
                <p class="text-muted mb-0" style="font-size: 0.95rem; line-height: 1.5;">Are you sure you want to log out of your account?</p>
                <div class="d-flex justify-content-center gap-2 mt-4">
                    <button type="button" class="btn fw-bold w-50" onclick="hideLogoutModal()" style="background: #f8fafc; border: 1px solid #e5e7eb; color: var(--dashboard-text-main); border-radius: 10px; min-height: 42px;">Cancel</button>
                    <a href="../controllers/AuthController.php?action=logout" class="btn fw-bold w-50" style="background: var(--dashboard-primary); border: 1px solid var(--dashboard-primary); color: var(--black); border-radius: 10px; min-height: 42px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none;" onmouseover="this.style.background='var(--black)'; this.style.borderColor='var(--black)'; this.style.color='var(--white)'" onmouseout="this.style.background='var(--dashboard-primary)'; this.style.borderColor='var(--dashboard-primary)'; this.style.color='var(--black)'">Sign Out</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// --- Initialization for PC Collapse State ---
document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('mainSidebar');
    if (localStorage.getItem('sidebarCollapsed') === 'true' && window.innerWidth > 768) {
        sidebar.classList.add('collapsed');
    }
});

// --- PC Sidebar Logic ---
function toggleDesktopSidebar() {
    const sidebar = document.getElementById('mainSidebar');
    sidebar.classList.toggle('collapsed');
    localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
}

// --- Mobile Sidebar Logic ---
function toggleMobileSidebar() {
    const sidebar = document.getElementById('mainSidebar');
    const overlay = document.getElementById('mobileOverlay');
    
    sidebar.classList.toggle('mobile-open');
    overlay.classList.toggle('mobile-open');
    
    if (sidebar.classList.contains('mobile-open')) {
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = '';
    }
}

// --- Logout Modal Logic ---
function showLogoutModal(e) {
    if(e) e.preventDefault();
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