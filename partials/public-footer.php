<?php
$basePath = $basePath ?? '';
$siteLogo = $siteLogo ?? 'assets/images/logo.png';

// Shop contact and social links. Replace '#' with the real page URLs.
$shopPhone = '0912-345-6789';
$socialLinks = [
    'facebook' => '#',
    'messenger' => '#',
];
?>

<footer class="footer">
    <div class="container">
        <div class="footer-grid" data-reveal-group>
            <div class="footer-brand">
                <a href="<?= $basePath !== '' ? $basePath : './' ?>" class="footer-logo">
                    <img src="<?= htmlspecialchars($basePath . $siteLogo, ENT_QUOTES, 'UTF-8') ?>" alt="Norily's Vehicle Repair Shop Logo">
                    <span>Norily's</span>
                </a>

                <p class="footer-tagline">
                    Accurate repairs and fair prices<br>
                    for Rosario since 1998.
                </p>

                <p class="footer-meta">
                    Vila Rosario, La Union<br>
                    Mon &ndash; Sat &middot; 8:00 AM &ndash; 5:00 PM
                </p>

                <a href="tel:<?= preg_replace('/\D/', '', $shopPhone) ?>" class="footer-phone"><?= $shopPhone ?></a>
            </div>

            <div class="footer-column">
                <h6>Main</h6>

                <a href="<?= $basePath ?>public_pages/services.php" class="link-grow">Services</a>
                <a href="<?= $basePath ?>public_pages/parts.php" class="link-grow">Parts</a>
                <a href="<?= $basePath ?>public_pages/how-it-works.php" class="link-grow">How it works</a>
                <a href="<?= $basePath ?>public_pages/about.php" class="link-grow">About</a>
                <a href="<?= $basePath ?>public_pages/location.php" class="link-grow">Store location</a>
            </div>

            <div class="footer-column">
                <h6>Support</h6>

                <a href="<?= $basePath ?>public_pages/contact.php" class="link-grow">Contact us</a>
                <a href="<?= $basePath ?>public_pages/faqs.php" class="link-grow">FAQs</a>
                <a href="<?= $basePath ?>public_pages/terms.php" class="link-grow">Terms &amp; conditions</a>
                <a href="<?= $basePath ?>public_pages/privacy.php" class="link-grow">Privacy policy</a>
            </div>

            <div class="footer-column">
                <h6>Socials</h6>

                <div class="footer-socials">
                    <a href="<?= htmlspecialchars($socialLinks['facebook'], ENT_QUOTES, 'UTF-8') ?>" aria-label="Facebook" target="_blank" rel="noopener">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" stroke="none" d="M13.5 21v-7.5h2.5l.4-3h-2.9V8.6c0-.9.3-1.5 1.5-1.5h1.5V4.4c-.3 0-1.2-.1-2.2-.1-2.2 0-3.7 1.3-3.7 3.8v2.4H8v3h2.6V21h2.9Z"/></svg>
                    </a>

                    <a href="<?= htmlspecialchars($socialLinks['messenger'], ENT_QUOTES, 'UTF-8') ?>" aria-label="Messenger" target="_blank" rel="noopener">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3.5c-4.8 0-8.5 3.5-8.5 8 0 2.4 1.1 4.6 2.9 6v2.9l2.7-1.5c.9.3 1.9.4 2.9.4 4.8 0 8.5-3.5 8.5-7.9S16.8 3.5 12 3.5Z"/><path d="m7.5 13.8 3-3.2 2 2 3-3.2"/></svg>
                    </a>

                    <a href="tel:<?= preg_replace('/\D/', '', $shopPhone) ?>" aria-label="Call the shop">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.6 3.5h2.6l1.4 3.8-1.9 1.3a10.5 10.5 0 0 0 4.7 4.7l1.3-1.9 3.8 1.4v2.6c0 1.2-1 2.2-2.2 2.1A15 15 0 0 1 4.5 5.7c-.1-1.2.9-2.2 2.1-2.2Z"/></svg>
                    </a>

                    <a href="<?= $basePath ?>public_pages/location.php" aria-label="Store location">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s-6.5-5.6-6.5-11a6.5 6.5 0 0 1 13 0c0 5.4-6.5 11-6.5 11Z"/><circle cx="12" cy="10" r="2.3"/></svg>
                    </a>
                </div>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; <?= date('Y') ?> Norily's Vehicle Repair Shop. All rights reserved.</p>
        </div>
    </div>
</footer>
