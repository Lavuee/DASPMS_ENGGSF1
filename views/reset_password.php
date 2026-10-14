<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth_page.php';
require_once __DIR__ . '/../includes/password_reset.php';

$token = trim($_GET['token'] ?? '');
$reset = findValidPasswordReset((new Database())->getConnection(), $token);

if (!$reset) {
    renderAuthPageStart('Reset Password', 'Link expired', 'This password reset link is invalid, already used, or has expired.');
    ?>
        <a href="forgot_password.php" class="auth-submit">Request a new link</a>
    <?php
    renderAuthPageEnd();
    exit;
}

renderAuthPageStart('Reset Password', 'Choose a new password', 'Use at least ' . PASSWORD_MIN_LENGTH . ' characters with at least one letter and one number.');
?>
        <form action="../controllers/AuthController.php" method="POST" class="auth-form" data-auth-form novalidate>
            <?= csrfField() ?>
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">

            <div class="auth-field">
                <label class="auth-label" for="password">New password</label>
                <div class="auth-password">
                    <input type="password" id="password" name="password" class="auth-input" maxlength="<?= PASSWORD_MAX_BYTES ?>" autocomplete="new-password" required aria-describedby="passwordRules" data-check="password">
                    <button type="button" class="auth-show" data-password-toggle aria-controls="password" aria-pressed="false" aria-label="Show password">Show</button>
                </div>
                <ul class="auth-rules" id="passwordRules" data-password-rules="password">
                    <li data-rule="length">At least <?= PASSWORD_MIN_LENGTH ?> characters</li>
                    <li data-rule="letter">A letter</li>
                    <li data-rule="number">A number</li>
                </ul>
            </div>

            <div class="auth-field">
                <label class="auth-label" for="password_confirm">Confirm new password</label>
                <div class="auth-password">
                    <input type="password" id="password_confirm" name="password_confirm" class="auth-input" maxlength="<?= PASSWORD_MAX_BYTES ?>" autocomplete="new-password" required data-check="match" data-match="password">
                    <button type="button" class="auth-show" data-password-toggle aria-controls="password_confirm" aria-pressed="false" aria-label="Show password">Show</button>
                </div>
            </div>

            <button type="submit" class="auth-submit" data-loading-text="Saving…">Reset password</button>
        </form>
<?php renderAuthPageEnd(); ?>
