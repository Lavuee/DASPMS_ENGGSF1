<?php
/*
    Staff top header (Owner, Cashier, Head Mechanic). Included by includes/sidebar.php in place of
    the old sidebar. Menu items follow the same permissions the sidebar used (includes/rbac.php);
    counts and alerts are read-only queries on existing data.
    Expects $currentPage, $dashLink, $userInitial and $userRole from sidebar.php.
    Styles: assets/css/customer-portal.css (shared components) + assets/css/staff-portal.css.
    Behaviour: assets/js/staff-portal.js
*/
require_once __DIR__ . '/business_rules.php';

$spFirstName = htmlspecialchars($_SESSION['first_name'] ?? '', ENT_QUOTES, 'UTF-8');
$spRoleLabel = $userRole === 'Owner' ? 'Owner portal' : ($userRole === 'Cashier' ? 'Cashier portal' : 'Workshop portal');

// ---- Live counts (read-only). Any failure just hides the numbers. ----
$spCounts = [
    'low_stock' => 0,
    'out_of_stock' => 0,
    'pending_requests' => 0,
    'pending_web_orders' => 0,
    'active_jobs' => 0,
    'pending_jobs' => 0,
    'unpaid_bills' => 0,
    'overdue_bills' => 0,
];

try {
    $spDb = (new Database())->getConnection();
    $spCount = function ($sql) use ($spDb) {
        return intval($spDb->query($sql)->fetchColumn());
    };

    if (can('inventory.manage') || can('inventory.view')) {
        $spCounts['low_stock'] = $spCount("SELECT COUNT(*) FROM part WHERE is_active = 1 AND quantity_on_hand <= low_stock_threshold");
        $spCounts['out_of_stock'] = $spCount("SELECT COUNT(*) FROM part WHERE is_active = 1 AND quantity_on_hand <= 0");
    }

    if (can('service_requests.review')) {
        $spCounts['pending_requests'] = $spCount("SELECT COUNT(*) FROM service_request WHERE status = 'Pending'");
    }

    if (can('online_orders.manage')) {
        $spCounts['pending_web_orders'] = $spCount("SELECT COUNT(*) FROM part_order WHERE status = 'Pending'");
    }

    if (can('job_orders.view')) {
        $spCounts['active_jobs'] = $spCount("SELECT COUNT(*) FROM job_order WHERE status IN ('Pending', 'In Progress', 'Ready for Pickup')");
        $spCounts['pending_jobs'] = $spCount("SELECT COUNT(*) FROM job_order WHERE status = 'Pending'");
    }

    if (can('billing.manage')) {
        $spCounts['unpaid_bills'] = $spCount("SELECT COUNT(*) FROM invoice i LEFT JOIN job_order jo ON jo.job_order_id = i.job_order_id WHERE i.is_voided = 0 AND i.payment_status <> 'Paid' AND (i.job_order_id IS NULL OR jo.status = 'Completed')"); // a down-payment invoice counts once its job is completed
        $spCounts['overdue_bills'] = countOverdueServiceInvoices($spDb);
    }
} catch (Exception $e) {
    error_log('Staff header counts: ' . $e->getMessage());
}

// ---- Menu: groups -> items, each item only when the role may open that page ----
$spInventoryPage = can('inventory.manage') ? 'inventory.php' : 'inventory_view.php';

$spGroups = [
    'counter' => [
        'label' => 'Counter',
        'items' => [
            ['href' => 'pos.php', 'label' => 'POS Terminal', 'note' => 'Sell parts over the counter', 'show' => can('pos.use')],
            ['href' => 'create_transaction.php', 'label' => 'Transaction Form', 'note' => 'Walk-in customer, vehicle and job in one form', 'show' => can('pos.use') && can('job_orders.manage')],
            ['href' => 'billing.php', 'label' => 'Billing & Invoices', 'note' => 'Invoices and payments due', 'show' => can('billing.manage'), 'count' => $spCounts['unpaid_bills'], 'alert' => $spCounts['overdue_bills'] > 0],
            ['href' => 'invoice_history.php', 'label' => 'Invoice History', 'note' => 'Past invoices, print or download', 'show' => can('billing.manage')],
        ],
    ],
    'workshop' => [
        'label' => 'Workshop',
        'items' => [
            ['href' => 'job_orders.php', 'label' => can('job_orders.manage') ? 'Job Orders' : 'Active Jobs', 'note' => 'Repairs and their status', 'show' => can('job_orders.view'), 'count' => $spCounts['active_jobs'], 'pages' => ['job_orders.php', 'create_job_order.php']],
            ['href' => 'service_requests.php', 'label' => 'Service Requests', 'note' => 'Online requests waiting for review', 'show' => can('service_requests.review'), 'count' => $spCounts['pending_requests']],
            ['href' => 'online_orders.php', 'label' => 'Web Orders', 'note' => 'Parts reserved through the website', 'show' => can('online_orders.manage'), 'count' => $spCounts['pending_web_orders']],
        ],
    ],
    'records' => [
        'label' => 'Records',
        'items' => [
            ['href' => 'customers.php', 'label' => 'Customers', 'note' => 'Contact details and history', 'show' => can('customers.manage')],
            ['href' => 'vehicles.php', 'label' => 'Vehicles', 'note' => 'Registered vehicles and owners', 'show' => can('vehicles.manage')],
        ],
    ],
    'stock' => [
        'label' => 'Stock',
        'items' => [
            ['href' => $spInventoryPage, 'label' => can('inventory.manage') ? 'Parts Inventory' : 'Parts Availability', 'note' => 'Stock levels and low-stock items', 'show' => can('inventory.manage') || can('inventory.view'), 'count' => $spCounts['low_stock'], 'alert' => $spCounts['low_stock'] > 0],
            ['href' => 'suppliers.php', 'label' => 'Suppliers', 'note' => 'Who you restock from', 'show' => can('suppliers.manage')],
            ['href' => 'services.php', 'label' => 'Services', 'note' => 'Service list, rates and warranty', 'show' => can('services.manage')],
        ],
    ],
    'owner' => [
        'label' => 'Owner',
        'items' => [
            ['href' => 'reports.php', 'label' => 'Reports', 'note' => 'Sales, income and stock summaries', 'show' => can('reports.view')],
            ['href' => 'users.php', 'label' => 'Staff Accounts', 'note' => 'Logins and roles', 'show' => can('users.manage')],
            ['href' => 'activity_log.php', 'label' => 'Activity Log', 'note' => 'Cancellations, voids, payments, archiving', 'show' => can('activity_log.view')],
        ],
    ],
];

foreach ($spGroups as $key => $group) {
    $spGroups[$key]['items'] = array_values(array_filter($group['items'], function ($item) {
        return !empty($item['show']);
    }));

    if (empty($spGroups[$key]['items'])) {
        unset($spGroups[$key]);
        continue;
    }

    $spGroups[$key]['active'] = false;
    $spGroups[$key]['count'] = 0;
    $spGroups[$key]['alert'] = false;

    foreach ($spGroups[$key]['items'] as $item) {
        if (in_array($currentPage, $item['pages'] ?? [$item['href']], true)) {
            $spGroups[$key]['active'] = true;
        }
        $spGroups[$key]['count'] += intval($item['count'] ?? 0);
        $spGroups[$key]['alert'] = $spGroups[$key]['alert'] || !empty($item['alert']);
    }
}

// Pages the quick search can jump to (only the ones this role may open)
$spSearchPages = [['label' => 'Dashboard', 'group' => 'Dashboard', 'href' => $dashLink]];
foreach ($spGroups as $group) {
    foreach ($group['items'] as $item) {
        $spSearchPages[] = ['label' => $item['label'], 'group' => $group['label'], 'note' => $item['note'], 'href' => $item['href']];
    }
}

// ---- Alerts (bell): only what this role can act on ----
$spAlerts = [];

if ($spCounts['overdue_bills'] > 0) {
    $spAlerts[] = ['tone' => 'bad', 'text' => $spCounts['overdue_bills'] . ' repair bill' . ($spCounts['overdue_bills'] === 1 ? ' is' : 's are') . ' past the 7-day credit period', 'href' => 'billing.php'];
}
if ($spCounts['out_of_stock'] > 0) {
    $spAlerts[] = ['tone' => 'bad', 'text' => $spCounts['out_of_stock'] . ' part' . ($spCounts['out_of_stock'] === 1 ? ' is' : 's are') . ' out of stock', 'href' => $spInventoryPage];
}
if ($spCounts['low_stock'] - $spCounts['out_of_stock'] > 0) {
    $low = $spCounts['low_stock'] - $spCounts['out_of_stock'];
    $spAlerts[] = ['tone' => 'pend', 'text' => $low . ' part' . ($low === 1 ? ' is' : 's are') . ' running low', 'href' => $spInventoryPage];
}
if ($spCounts['pending_requests'] > 0) {
    $spAlerts[] = ['tone' => 'prog', 'text' => $spCounts['pending_requests'] . ' service request' . ($spCounts['pending_requests'] === 1 ? '' : 's') . ' waiting for review', 'href' => 'service_requests.php'];
}
if ($spCounts['pending_web_orders'] > 0) {
    $spAlerts[] = ['tone' => 'prog', 'text' => $spCounts['pending_web_orders'] . ' web order' . ($spCounts['pending_web_orders'] === 1 ? '' : 's') . ' waiting for confirmation', 'href' => 'online_orders.php'];
}
if (!can('job_orders.manage') && $spCounts['pending_jobs'] > 0) {
    $spAlerts[] = ['tone' => 'prog', 'text' => $spCounts['pending_jobs'] . ' job order' . ($spCounts['pending_jobs'] === 1 ? '' : 's') . ' waiting to start', 'href' => 'job_orders.php'];
}

$spAssetBase = __DIR__ . '/../assets';
?>
<link rel="stylesheet" href="../assets/css/customer-portal.css?v=<?= filemtime($spAssetBase . '/css/customer-portal.css') ?>">
<link rel="stylesheet" href="../assets/css/staff-portal.css?v=<?= filemtime($spAssetBase . '/css/staff-portal.css') ?>">
<script src="../assets/js/customer-portal.js?v=<?= filemtime($spAssetBase . '/js/customer-portal.js') ?>" defer></script>
<script src="../assets/js/staff-portal.js?v=<?= filemtime($spAssetBase . '/js/staff-portal.js') ?>" defer></script>

<a class="cp-skip" href="#spMain">Skip to content</a>

<header class="cp-header sp-header" id="spHeader">
    <div class="cp-header-bar sp-header-bar">
        <a href="<?= $dashLink ?>" class="cp-brand">
            <img src="../assets/images/logo.png" alt="Norily's logo" class="cp-brand-logo">
            <span class="cp-brand-text">
                <strong>Norily's</strong>
                <small><?= $spRoleLabel ?></small>
            </span>
        </a>

        <nav class="sp-nav" aria-label="Staff portal">
            <a href="<?= $dashLink ?>" class="sp-nav-link<?= $currentPage === $dashLink ? ' is-active' : '' ?>"<?= $currentPage === $dashLink ? ' aria-current="page"' : '' ?>>Dashboard</a>

            <?php foreach ($spGroups as $key => $group): ?>
                <div class="sp-menu" data-sp-menu>
                    <button type="button" class="sp-nav-link sp-menu-btn<?= $group['active'] ? ' is-active' : '' ?>" aria-expanded="false" aria-controls="spPanel-<?= $key ?>">
                        <?= $group['label'] ?>
                        <?php if ($group['count'] > 0): ?>
                            <span class="sp-nav-count<?= $group['alert'] ? ' is-alert' : '' ?>"><?= $group['count'] ?></span>
                        <?php endif; ?>
                        <svg class="cp-chev" width="9" height="9" viewBox="0 0 10 10" aria-hidden="true"><path d="M1 3l4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.4"/></svg>
                    </button>
                    <div class="sp-panel" id="spPanel-<?= $key ?>" hidden>
                        <p class="cp-label sp-panel-label"><?= $group['label'] ?></p>
                        <?php foreach ($group['items'] as $item): ?>
                            <?php $isActive = in_array($currentPage, $item['pages'] ?? [$item['href']], true); ?>
                            <a href="<?= $item['href'] ?>" class="sp-panel-link<?= $isActive ? ' is-active' : '' ?>"<?= $isActive ? ' aria-current="page"' : '' ?>>
                                <span class="sp-panel-text">
                                    <strong><?= htmlspecialchars($item['label']) ?></strong>
                                    <small><?= htmlspecialchars($item['note']) ?></small>
                                </span>
                                <?php if (!empty($item['count'])): ?>
                                    <span class="sp-count<?= !empty($item['alert']) ? ' is-alert' : '' ?>"><?= intval($item['count']) ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </nav>

        <div class="cp-tools">
            <button type="button" class="sp-tool-btn sp-search-btn" data-sp-search-open aria-haspopup="dialog" aria-controls="spSearch" aria-label="Search">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
            </button>

            <div class="sp-alerts" data-sp-alerts>
                <button type="button" class="sp-tool-btn sp-bell" aria-expanded="false" aria-controls="spAlertsPanel" aria-label="Alerts, <?= count($spAlerts) ?> new">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 8a6 6 0 1 1 12 0c0 7 3 8 3 8H3s3-1 3-8"/><path d="M10 20a2 2 0 0 0 4 0"/></svg>
                    <span class="sp-bell-label">Alerts</span>
                    <?php if (!empty($spAlerts)): ?>
                        <span class="sp-bell-dot"><?= count($spAlerts) ?></span>
                    <?php endif; ?>
                </button>
                <div class="sp-panel sp-alerts-panel" id="spAlertsPanel" hidden>
                    <p class="cp-label sp-panel-label">Alerts</p>
                    <?php if (empty($spAlerts)): ?>
                        <p class="sp-empty-alerts">Nothing needs attention right now.</p>
                    <?php else: ?>
                        <?php foreach ($spAlerts as $alert): ?>
                            <a href="<?= $alert['href'] ?>" class="sp-alert-link">
                                <span class="sp-alert-dot is-<?= $alert['tone'] ?>" aria-hidden="true"></span>
                                <span><?= htmlspecialchars($alert['text']) ?></span>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (can('pos.use')): ?>
                <a href="pos.php" class="cp-btn cp-btn-dark sp-new-sale">+ New sale</a>
            <?php endif; ?>

            <div class="cp-user" data-cp-user>
                <button type="button" class="cp-user-btn" aria-expanded="false" aria-haspopup="true" aria-controls="spUserMenu">
                    <span class="cp-avatar"><?= $userInitial ?></span>
                    <span class="cp-user-name"><?= $spFirstName ?></span>
                    <svg class="cp-chev" width="10" height="10" viewBox="0 0 10 10" aria-hidden="true"><path d="M1 3l4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.4"/></svg>
                </button>
                <div class="cp-user-menu" id="spUserMenu" hidden>
                    <p class="cp-user-menu-head">
                        <strong><?= $spFirstName ?></strong>
                        <span><?= htmlspecialchars($userRole) ?></span>
                    </p>
                    <a href="<?= $dashLink ?>">Dashboard</a>
                    <button type="button" class="cp-user-logout" onclick="showLogoutModal(event)">Log out</button>
                </div>
            </div>

            <button type="button" class="cp-menu-btn sp-menu-toggle" aria-expanded="false" aria-controls="spSheet">Menu</button>
        </div>
    </div>
</header>

<!-- Quick search: pages, plus records from controllers/StaffSearchController.php -->
<div class="sp-search" id="spSearch" role="dialog" aria-modal="true" aria-label="Search" hidden>
    <div class="sp-search-backdrop" data-sp-search-close></div>
    <div class="sp-search-box">
        <div class="sp-search-field">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
            <input type="search" id="spSearchInput" class="sp-search-input" placeholder="Search pages, job orders, customers, plates, parts..." autocomplete="off" aria-controls="spSearchResults">
            <button type="button" class="sp-search-esc" data-sp-search-close aria-label="Close search">Esc</button>
        </div>
        <div class="sp-search-results" id="spSearchResults" role="listbox" aria-label="Results"></div>
        <p class="sp-search-hint">Type at least 2 letters. Use ↑ ↓ and Enter to open a result.</p>
    </div>
    <script type="application/json" id="spSearchPages"><?= json_encode($spSearchPages, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
</div>

<!-- Fullscreen menu for tablets and phones -->
<div class="cp-sheet" id="spSheet" data-desktop-from="1280" role="dialog" aria-modal="true" aria-label="Menu" hidden>
    <div class="cp-sheet-top">
        <span class="cp-label"><?= $spRoleLabel ?></span>
        <button type="button" class="cp-sheet-close" aria-label="Close menu">&times;</button>
    </div>
    <nav class="sp-sheet-nav" aria-label="Staff portal">
        <a href="<?= $dashLink ?>" class="sp-sheet-link<?= $currentPage === $dashLink ? ' is-active' : '' ?>">Dashboard</a>
        <?php foreach ($spGroups as $group): ?>
            <p class="cp-label sp-sheet-group"><?= $group['label'] ?></p>
            <?php foreach ($group['items'] as $item): ?>
                <?php $isActive = in_array($currentPage, $item['pages'] ?? [$item['href']], true); ?>
                <a href="<?= $item['href'] ?>" class="sp-sheet-link<?= $isActive ? ' is-active' : '' ?>">
                    <?= htmlspecialchars($item['label']) ?>
                    <?php if (!empty($item['count'])): ?>
                        <span class="sp-count<?= !empty($item['alert']) ? ' is-alert' : '' ?>"><?= intval($item['count']) ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>
    <div class="cp-sheet-foot">
        <span class="cp-sheet-user"><span class="cp-avatar"><?= $userInitial ?></span><?= $spFirstName ?> · <?= htmlspecialchars($userRole) ?></span>
        <button type="button" class="cp-btn cp-btn-line" onclick="showLogoutModal(event)">Log out</button>
    </div>
</div>

<div class="modal fade cp-modal" id="customLogoutModal" tabindex="-1" style="z-index: 9999;" aria-labelledby="spLogoutTitle">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="cp-modal-head">
                <div>
                    <p class="cp-label">Account</p>
                    <h2 class="cp-modal-title" id="spLogoutTitle">Log out?</h2>
                </div>
                <button type="button" class="cp-modal-x" aria-label="Close" onclick="hideLogoutModal()">&times;</button>
            </div>
            <div class="cp-modal-body">
                <p class="cp-muted mb-0">Are you sure you want to log out of your account?</p>
            </div>
            <div class="cp-modal-foot">
                <button type="button" class="cp-btn cp-btn-line" onclick="hideLogoutModal()">Cancel</button>
                <form action="../controllers/AuthController.php" method="POST" class="m-0">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="cp-btn cp-btn-dark">Log out</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Logout modal (same open/close behaviour as before)
function showLogoutModal(e) {
    if (e) e.preventDefault();
    if (window.spCloseMenus) window.spCloseMenus();
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
        backdrop.addEventListener('click', hideLogoutModal);
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
<?php if (!empty($_SESSION['access_denied_message'])): ?>
<div id="accessDeniedAlert" class="cp-flash cp-flash-bad cp-flash-float" role="alert">
    <span><?= htmlspecialchars($_SESSION['access_denied_message'], ENT_QUOTES, 'UTF-8') ?></span>
    <button type="button" class="cp-flash-close" aria-label="Close" onclick="this.parentElement.remove()">&times;</button>
</div>
<script>
setTimeout(() => document.getElementById('accessDeniedAlert')?.remove(), 5000);
</script>
<?php unset($_SESSION['access_denied_message']); endif; ?>
