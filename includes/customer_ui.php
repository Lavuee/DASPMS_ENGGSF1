<?php
/*
    Small view helpers for the customer portal pages (layout only, no data access).
    Styles live in assets/css/customer-portal.css.
*/
require_once __DIR__ . '/helpers.php';

function cpEscape($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/*
    Maps a status from the database to a badge colour class.
      pend  amber  - waiting on something
      prog  blue   - being worked on
      ready solid green - ready for pickup
      ok    light green - in stock / active / verified
      done  gray   - finished
      bad   red    - cancelled, rejected, unpaid, out of stock, failed
*/
function cpStatusClass($status) {
    $map = [
        'Pending' => 'pend',
        'Partial' => 'pend',
        'Awaiting Payment' => 'pend',
        'Pending Verification' => 'pend',
        'Low stock' => 'pend',
        'In Progress' => 'prog',
        'Approved' => 'prog',
        'Converted' => 'prog',
        'Ready for Pickup' => 'ready solid',
        'In stock' => 'ok',
        'Active' => 'ok',
        'Verified' => 'ok',
        'Completed' => 'done',
        'Paid' => 'done',
        'Not Required' => 'done',
        'No Invoice' => 'done',
        'Inactive' => 'done',
        'Archived' => 'done',
        'Cancelled' => 'bad',
        'Rejected' => 'bad',
        'Not Paid' => 'bad',
        'Out of stock' => 'bad',
        'Failed' => 'bad',
        'Expired' => 'bad',
    ];

    return $map[trim((string) $status)] ?? 'done';
}

// <span class="st ...">Label</span>; the label defaults to the status itself.
function cpBadge($status, $label = null) {
    $label = $label ?? $status;

    return '<span class="st ' . cpStatusClass($status) . '">' . cpEscape($label !== '' ? $label : 'N/A') . '</span>';
}

// Stock badge from a quantity and the part's low_stock_threshold (same rule as the inventory pages).
function cpStockBadge($quantity, $lowThreshold = 3) {
    $quantity = intval($quantity);

    if ($quantity <= 0) {
        return cpBadge('Out of stock');
    }

    if ($quantity <= intval($lowThreshold)) {
        return cpBadge('Low stock', 'Low stock · ' . $quantity . ' left');
    }

    return cpBadge('In stock', 'In stock · ' . $quantity);
}

// Part / service image, or a letter tile when there is no image (or it fails to load).
function cpThumb($src, $name, $class = '') {
    $letter = cpEscape(mb_strtoupper(mb_substr(trim(plainText($name, '?')), 0, 1)));

    if (empty($src)) {
        return '<span class="cp-letter ' . $class . '" aria-hidden="true">' . $letter . '</span>';
    }

    // On a broken image, swap in the letter tile (data-letter holds the letter).
    return '<img src="' . cpEscape($src) . '" alt="' . cpEscape(plainText($name, '')) . '" class="' . $class . '" loading="lazy"'
        . ' data-letter="' . $letter . '" onerror="cpLetterFallback(this)">';
}

// False for empty or placeholder text such as "N/A" (so brand lines can be skipped).
function cpHasValue($value) {
    return !in_array(strtoupper(plainText($value ?? '')), ['', 'N/A', 'NA', 'NONE', '-'], true);
}

// Same stock rule as cpStockBadge(), drawn as the outline pill used on the details pages.
function cpStockPill($quantity, $lowThreshold = 3) {
    return str_replace('class="st ', 'class="cpd-pill ', cpStockBadge($quantity, $lowThreshold));
}

function cpMoney($amount) {
    return '₱' . number_format(floatval($amount), 2);
}

// Session success / error messages as flat bars (shown once, then cleared).
function cpFlash() {
    foreach (['success_message' => 'ok', 'error_message' => 'bad'] as $key => $tone) {
        if (empty($_SESSION[$key])) {
            continue;
        }
        ?>
        <div class="cp-flash cp-flash-<?= $tone ?>" role="<?= $tone === 'bad' ? 'alert' : 'status' ?>">
            <span><?= cpEscape($_SESSION[$key]) ?></span>
            <button type="button" class="cp-flash-close" aria-label="Dismiss" onclick="this.parentElement.remove()">&times;</button>
        </div>
        <?php
        unset($_SESSION[$key]);
    }
}

// One pop-up message queued by a controller ($_SESSION['cp_toast'] = [ok, title, detail]); shown by cpToast in customer-portal.js
function cpToastQueued() {
    if (empty($_SESSION['cp_toast'])) {
        return;
    }

    [$ok, $title, $detail] = array_pad((array) $_SESSION['cp_toast'], 3, '');
    unset($_SESSION['cp_toast']);
    ?>
    <div data-cp-toast data-ok="<?= $ok ? '1' : '0' ?>" data-title="<?= cpEscape($title) ?>" data-detail="<?= cpEscape($detail) ?>" hidden></div>
    <?php
}

// Standard page header: small label, big title, one line of description, buttons on the right.
function cpPageHead($title, $lead, $actionsHtml = '') {
    ?>
    <header class="cp-pagehead">
        <div class="cp-pagehead-text">
            <p class="cp-label">Customer portal</p>
            <h1 class="cp-title"><?= cpEscape($title) ?></h1>
            <p class="cp-lead"><?= cpEscape($lead) ?></p>
        </div>
        <?php if ($actionsHtml !== ''): ?>
            <div class="cp-pagehead-actions"><?= $actionsHtml ?></div>
        <?php endif; ?>
    </header>
    <?php
}

// Numbered section heading: "01  MY VEHICLES ............ + ADD VEHICLE"
function cpSectionHead($number, $title, $linkHtml = '') {
    ?>
    <div class="cp-sec-head">
        <h2><span class="cp-sec-num"><?= cpEscape($number) ?></span><?= cpEscape($title) ?></h2>
        <?php if ($linkHtml !== ''): ?>
            <div class="cp-sec-link"><?= $linkHtml ?></div>
        <?php endif; ?>
    </div>
    <?php
}

// <head> tags shared by every customer page (after the page's own <title>).
function cpHeadAssets() {
    $base = __DIR__ . '/../assets';
    ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime($base . '/css/style.css') ?>">
    <link rel="stylesheet" href="../assets/css/customer-portal.css?v=<?= filemtime($base . '/css/customer-portal.css') ?>">
    <script>function cpLetterFallback(img){var s=document.createElement('span');s.className='cp-letter';s.setAttribute('aria-hidden','true');s.textContent=img.getAttribute('data-letter')||'';img.replaceWith(s);}</script>
    <script>document.documentElement.classList.add('js');window.__motionFallback=setTimeout(function(){document.documentElement.classList.add('reveal-all');},3000);</script>
    <script src="../assets/js/motion.js?v=<?= filemtime($base . '/js/motion.js') ?>" defer></script>
    <script src="../assets/js/customer-portal.js?v=<?= filemtime($base . '/js/customer-portal.js') ?>" defer></script>
    <?php
}
