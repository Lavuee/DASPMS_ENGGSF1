<?php
/*
    Public site header: one compact, sticky row used on every public page, including home.
    Links come from includes/public_nav.php; $activePage marks the current page's link.
*/
$basePath = $basePath ?? '';
// Swap the logo by replacing this file, or set $siteLogo before including this partial.
$siteLogo = $siteLogo ?? 'assets/images/logo.png';
$activePage = $activePage ?? '';

$homeUrl = $basePath !== '' ? $basePath : './';
$logoUrl = htmlspecialchars($basePath . $siteLogo, ENT_QUOTES, 'UTF-8');
?>
<header class="pub-header pub-header-compact pub-header-sticky" id="pubHeader" data-pub-header>
    <div class="container pub-header-bar">
        <a href="<?= $homeUrl ?>" class="pub-brand">
            <img src="<?= $logoUrl ?>" alt="Norily's Vehicle Repair Shop logo" class="pub-brand-logo">
            <span class="pub-brand-text">
                <strong>Norily's</strong>
                <small>Vehicle Repair Shop</small>
            </span>
        </a>

        <button type="button" class="pub-menu-toggle" aria-expanded="false" aria-controls="pubHeader-menu">Menu</button>

        <nav class="pub-nav" id="pubHeader-menu" aria-label="Main">
            <?php include __DIR__ . '/../includes/public_nav.php'; ?>
        </nav>
    </div>
</header>

<script src="<?= $basePath ?>assets/js/public-nav.js?v=<?= filemtime(__DIR__ . '/../assets/js/public-nav.js') ?>" defer></script>
