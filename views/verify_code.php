<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth_page.php';

/*
    Email verification by code. The verification email carries a 6-digit code as
    well as the link; typing the code here works from any device, including when
    the email is read on a phone that cannot open a localhost link.
*/
$email = (string)($_SESSION['verify_email'] ?? '');

renderAuthPageStart('Verify Email', 'Enter your code', 'We emailed you a 6-digit code. Enter it below to verify your account. It may take a minute to arrive, so also check your Spam folder.');
?>
        <form action="../controllers/AuthController.php" method="POST" class="auth-form" data-auth-form novalidate>
            <?= csrfField() ?>
            <input type="hidden" name="action" value="verify_code">

            <div class="auth-field">
                <label class="auth-label" for="email">Email</label>
                <input type="email" id="email" name="email" class="auth-input" maxlength="255" autocomplete="email" required data-check="email" value="<?= authEscape($email) ?>">
            </div>

            <div class="auth-field">
                <label class="auth-label" for="code">6-digit code</label>
                <input type="text" id="code" name="code" class="auth-input" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="\d{6}" required data-check="code" data-required-message="Enter the 6-digit code from the email." <?= $email !== '' ? 'autofocus' : '' ?>>
            </div>

            <button type="submit" class="auth-submit" data-loading-text="Verifying…">Verify my email</button>
        </form>

        <form action="../controllers/AuthController.php" method="POST" class="auth-switch">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="resend_verification">
            <input type="hidden" name="email" value="<?= authEscape($email) ?>" data-copy-from="email">
            <span>Didn't get it?</span>
            <button type="submit" class="auth-text-link auth-link-button">Send a new code</button>
        </form>
<?php renderAuthPageEnd(); ?>
