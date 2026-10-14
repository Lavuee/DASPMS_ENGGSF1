<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth_page.php';

renderAuthPageStart('Forgot Password', 'Forgot password', "Enter your account email and we'll send you a link to set a new password.");
?>
        <form action="../controllers/AuthController.php" method="POST" class="auth-form" data-auth-form novalidate>
            <?= csrfField() ?>
            <input type="hidden" name="action" value="request_password_reset">

            <div class="auth-field">
                <label class="auth-label" for="email">Email</label>
                <input type="email" id="email" name="email" class="auth-input" maxlength="255" autocomplete="email" required data-check="email">
            </div>

            <button type="submit" class="auth-submit" data-loading-text="Sending…">Send reset link</button>
        </form>
<?php renderAuthPageEnd(); ?>
