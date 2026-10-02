<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth_page.php';

renderAuthPageStart('Resend Verification', 'Verify Your Email', 'Enter the email you registered with and we will send a new verification link.');
?>
        <form action="../controllers/AuthController.php" method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="resend_verification">

            <div class="mb-4 text-start">
                <label class="fw-bold mb-1 field-label" for="email">EMAIL ADDRESS</label>
                <input type="email" id="email" name="email" class="form-control" placeholder="Enter your registered email" maxlength="255" required>
            </div>

            <button type="submit" class="btn btn-reset w-100 mb-3">Send Verification Link</button>
        </form>
<?php renderAuthPageEnd(); ?>
