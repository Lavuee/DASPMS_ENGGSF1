<?php
$basePath = $basePath ?? '';
?>

<header class="site-header">
    <div class="container">
        <div class="brand-area">
            <a href="<?= $basePath ?>index.php" class="brand-link">
                <img src="<?= $basePath ?>assets/images/logo.png" alt="Norily's Vehicle Repair Shop Logo" class="brand-logo">
                <span class="brand-name">Norily's Vehicle Repair Shop</span>
            </a>
        </div>

        <nav class="main-nav">
            <a href="<?= $basePath ?>index.php">Home</a>
            <a href="<?= $basePath ?>public_pages/parts.php">Parts</a>
            <a href="<?= $basePath ?>public_pages/services.php">Services</a>
            <a href="<?= $basePath ?>views/login.php">Login</a>
        </nav>
    </div>
</header>