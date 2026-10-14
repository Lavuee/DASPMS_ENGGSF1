<?php
/*
    Small view helpers for the staff pages (Owner, Cashier, Head Mechanic). Layout only, no data access.
    Builds on the customer portal helpers (badges, money, flash messages) in includes/customer_ui.php.
    Styles: assets/css/customer-portal.css (shared) + assets/css/staff-portal.css.
*/
require_once __DIR__ . '/customer_ui.php';

// "Owner portal", "Cashier portal" or "Workshop portal" for the logged-in role.
function spPortalLabel() {
    $role = $_SESSION['role'] ?? '';

    if ($role === 'Owner') return 'Owner portal';
    if ($role === 'Cashier') return 'Cashier portal';

    return 'Workshop portal';
}

// Page header: small label ("Owner portal · Counter"), big title, one line of description, buttons on the right.
function spPageHead($eyebrow, $title, $lead, $actionsHtml = '') {
    ?>
    <header class="sp-pagehead">
        <div class="sp-pagehead-text">
            <p class="cp-label"><?= cpEscape($eyebrow) ?></p>
            <h1 class="sp-title"><?= cpEscape($title) ?></h1>
            <?php if ($lead !== ''): ?>
                <p class="sp-lead"><?= cpEscape($lead) ?></p>
            <?php endif; ?>
        </div>
        <?php if ($actionsHtml !== ''): ?>
            <div class="sp-pagehead-actions"><?= $actionsHtml ?></div>
        <?php endif; ?>
    </header>
    <?php
}

// Numbered section heading: "01  TODAY AT A GLANCE ............ LINK ↗"
function spSectionHead($number, $title, $linkHtml = '') {
    ?>
    <div class="sp-sec-head">
        <h2><span class="sp-sec-num"><?= cpEscape($number) ?></span><?= cpEscape($title) ?></h2>
        <?php if ($linkHtml !== ''): ?>
            <div class="sp-sec-link"><?= $linkHtml ?></div>
        <?php endif; ?>
    </div>
    <?php
}

// Whole pesos for the big dashboard figures: ₱18,450
function spPeso($amount) {
    return '₱' . number_format(floatval($amount), 0);
}

