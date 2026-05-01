<?php
$basePath = $basePath ?? '';
?>

<footer class="footer">
    <div class="container">
        <div class="footer-grid">
            <!-- Shop Links -->
            <div class="footer-column">
                <h6>Shop</h6>

                <a href="<?= $basePath ?>public_pages/about.php">About Us</a>
                <a href="<?= $basePath ?>public_pages/contact.php">Contact</a>
                <a href="<?= $basePath ?>public_pages/location.php">Store Location</a>
            </div>

            <!-- Customer Support Links -->
            <div class="footer-column">
                <h6>Customer Support</h6>

                <a href="<?= $basePath ?>public_pages/how-it-works.php">How It Works</a>
                <a href="<?= $basePath ?>public_pages/faqs.php">FAQs</a>
                <a href="<?= $basePath ?>public_pages/terms.php">Terms & Conditions</a>
                <a href="<?= $basePath ?>public_pages/privacy.php">Privacy Policy</a>
            </div>

            <!-- Business Hours -->
            <div class="footer-column footer-contact">
                <h6>Business Hours</h6>

                <p><strong>Monday - Saturday:</strong> 8:00 AM - 5:00 PM</p>
                <p><strong>Sunday:</strong> Closed</p>
                <p><strong>Booking:</strong> Online service requests available anytime</p>
            </div>
        </div>

        <div class="footer-bottom">
            <p>© 2026 Norily's Vehicle Repair Shop | Digital Auto Service and Parts Management System</p>
        </div>
    </div>
</footer>