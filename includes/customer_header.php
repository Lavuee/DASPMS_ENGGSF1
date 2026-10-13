<?php
/*
    Customer portal top header. Included by includes/sidebar.php in place of the
    sidebar when the signed-in role is Customer (staff roles keep the sidebar).
    Expects $currentPage, $dashLink, $userInitial and $cartCount from sidebar.php.
    Styles: assets/css/customer-portal.css   Behaviour: assets/js/customer-portal.js
*/
$cpFirstName = htmlspecialchars($_SESSION['first_name'] ?? '', ENT_QUOTES, 'UTF-8');

$cpLinks = [
    ['href' => $dashLink, 'label' => 'Dashboard', 'pages' => [$dashLink]],
];

if (can('shop.purchase')) {
    $cpLinks[] = ['href' => 'customer_parts.php', 'label' => 'Browse Parts', 'pages' => ['customer_parts.php', 'customer_part_details.php']];
    $cpLinks[] = ['href' => 'customer_services.php', 'label' => 'Services Offered', 'pages' => ['customer_services.php', 'customer_service_details.php']];
    $cpLinks[] = ['href' => 'customer_service_requests.php', 'label' => 'My Service Requests', 'pages' => ['customer_service_requests.php', 'customer_request_service.php']];
}
?>
<a class="cp-skip" href="#cpMain">Skip to content</a>

<header class="cp-header" id="cpHeader">
    <div class="cp-header-bar">
        <a href="<?= $dashLink ?>" class="cp-brand">
            <img src="../assets/images/logo.png" alt="Norily's logo" class="cp-brand-logo">
            <span class="cp-brand-text">
                <strong>Norily's</strong>
                <small>Customer portal</small>
            </span>
        </a>

        <nav class="cp-nav" aria-label="Customer portal">
            <?php foreach ($cpLinks as $link): ?>
                <?php $isActive = in_array($currentPage, $link['pages'], true); ?>
                <a href="<?= $link['href'] ?>" class="cp-nav-link<?= $isActive ? ' is-active' : '' ?>"<?= $isActive ? ' aria-current="page"' : '' ?>><?= $link['label'] ?></a>
            <?php endforeach; ?>
        </nav>

        <div class="cp-tools">
            <?php if (can('shop.purchase')): ?>
                <a href="customer_parts.php#search" class="cp-icon-btn" aria-label="Search parts" title="Search parts">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                </a>

                <a href="customer_cart.php" class="cp-cart<?= $currentPage === 'customer_cart.php' ? ' is-active' : '' ?>" aria-label="Cart, <?= intval($cartCount) ?> item<?= $cartCount == 1 ? '' : 's' ?>">
                    <span class="cp-cart-label">Cart</span>
                    <span class="cp-cart-count"><?= intval($cartCount) ?></span>
                </a>
            <?php endif; ?>

            <div class="cp-user" data-cp-user>
                <button type="button" class="cp-user-btn" aria-expanded="false" aria-haspopup="true" aria-controls="cpUserMenu">
                    <span class="cp-avatar"><?= $userInitial ?></span>
                    <span class="cp-user-name"><?= $cpFirstName ?></span>
                    <svg class="cp-chev" width="10" height="10" viewBox="0 0 10 10" aria-hidden="true"><path d="M1 3l4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.4"/></svg>
                </button>
                <div class="cp-user-menu" id="cpUserMenu" hidden>
                    <p class="cp-user-menu-head">
                        <strong><?= $cpFirstName ?></strong>
                        <span>Customer</span>
                    </p>
                    <a href="<?= $dashLink ?>">My garage</a>
                    <?php if (can('shop.purchase')): ?>
                        <a href="customer_cart.php">My cart</a>
                        <a href="customer_service_requests.php">My service requests</a>
                    <?php endif; ?>
                    <button type="button" class="cp-user-logout" onclick="showLogoutModal(event)">Log out</button>
                </div>
            </div>

            <button type="button" class="cp-menu-btn" aria-expanded="false" aria-controls="cpSheet">Menu</button>
        </div>
    </div>
</header>

<!-- Fullscreen menu for tablets and phones (1180px and below) -->
<div class="cp-sheet" id="cpSheet" role="dialog" aria-modal="true" aria-label="Menu" hidden>
    <div class="cp-sheet-top">
        <span class="cp-label">Customer portal</span>
        <button type="button" class="cp-sheet-close" aria-label="Close menu">&times;</button>
    </div>
    <nav class="cp-sheet-nav" aria-label="Customer portal">
        <?php foreach ($cpLinks as $i => $link): ?>
            <?php $isActive = in_array($currentPage, $link['pages'], true); ?>
            <a href="<?= $link['href'] ?>" class="<?= $isActive ? 'is-active' : '' ?>"<?= $isActive ? ' aria-current="page"' : '' ?>>
                <span class="cp-sheet-num"><?= sprintf('%02d', $i + 1) ?></span><?= $link['label'] ?>
            </a>
        <?php endforeach; ?>
        <?php if (can('shop.purchase')): ?>
            <a href="customer_cart.php" class="<?= $currentPage === 'customer_cart.php' ? 'is-active' : '' ?>">
                <span class="cp-sheet-num"><?= sprintf('%02d', count($cpLinks) + 1) ?></span>Cart (<?= intval($cartCount) ?>)
            </a>
        <?php endif; ?>
    </nav>
    <div class="cp-sheet-foot">
        <span class="cp-sheet-user"><span class="cp-avatar"><?= $userInitial ?></span><?= $cpFirstName ?></span>
        <button type="button" class="cp-btn cp-btn-line" onclick="showLogoutModal(event)">Log out</button>
    </div>
</div>

<div class="modal fade cp-modal" id="customLogoutModal" tabindex="-1" style="z-index: 9999;" aria-labelledby="cpLogoutTitle">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="cp-modal-head">
                <div>
                    <p class="cp-label">Account</p>
                    <h2 class="cp-modal-title" id="cpLogoutTitle">Log out?</h2>
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
// Logout modal (same open/close behaviour as the staff sidebar)
function showLogoutModal(e) {
    if (e) e.preventDefault();
    if (window.cpCloseMenus) window.cpCloseMenus();
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
