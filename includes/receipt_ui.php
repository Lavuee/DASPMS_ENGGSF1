<?php
/*
    Pieces of the narrow digital receipt (assets/css/receipt.css), shared by
    views/print_invoice.php, views/print_pos_receipt.php and the print/PDF copy of views/reports.php.
    Layout only, no data access.
*/

function rcEscape($value) {
    return htmlspecialchars(html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8');
}

function rcMoney($amount) {
    return '₱' . number_format(floatval($amount), 2);
}

// Status pill; $tone is amber, green, gray or red
function rcBadge($label, $tone) {
    return '<span class="rc-badge rc-' . $tone . '">' . rcEscape($label) . '</span>';
}

// Shop header used by all three receipts. $numberHtml is the mono number or the report title.
function rcHeader($kind, $numberHtml) {
    ?>
    <header class="rc-head">
        <span class="rc-logo" aria-hidden="true">N</span>
        <p class="rc-shop">Norily's Vehicle Repair Shop</p>
        <p class="rc-sub">Vila Rosario, La Union<br>Digital Auto Service and Parts Management System</p>
        <p class="rc-kind"><?= rcEscape($kind) ?></p>
        <?= $numberHtml ?>
    </header>
    <?php
}

// Torn paper edge: a row of half circles in the page colour. Built from small round spans
// (not SVG or a CSS mask) because the PDF tool draws those reliably, so the edge also shows in the PDF.
function rcTear() {
    return '<div class="rc-tear" aria-hidden="true">' . str_repeat('<span></span>', 32) . '</div>';
}
