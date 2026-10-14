<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth_page.php';

renderAuthPageStart('Resend Verification', 'Verify your email', 'Enter the email you registered with and we will send a new verification code and link.');
?>
        <form action="../controllers/AuthController.php" method="POST" class="auth-form" data-auth-form novalidate>
            <?= csrfField() ?>
            <input type="hidden" name="action" value="resend_verification">

            <div class="auth-field">
                <label class="auth-label" for="email">Email</label>
                <input type="email" id="email" name="email" class="auth-input" maxlength="255" autocomplete="email" required data-check="email">
            </div>

            <button type="submit" class="auth-submit" data-loading-text="Sending…">Send verification code</button>
        </form>
<?php renderAuthPageEnd(); ?>
