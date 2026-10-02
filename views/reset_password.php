<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth_page.php';
require_once __DIR__ . '/../includes/password_reset.php';

$token = trim($_GET['token'] ?? '');
$reset = findValidPasswordReset((new Database())->getConnection(), $token);

if (!$reset) {
    renderAuthPageStart('Reset Password', 'Link expired', 'This password reset link is invalid, already used, or has expired.');
    ?>
        <a href="forgot_password.php" class="btn btn-reset w-100 mb-3">Request a New Link</a>
    <?php
    renderAuthPageEnd();
    exit;
}

renderAuthPageStart('Reset Password', 'Choose a New Password', 'Use at least ' . PASSWORD_MIN_LENGTH . ' characters with at least one letter and one number.');
?>
        <form action="../controllers/AuthController.php" method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">

            <div class="mb-3 text-start">
                <label class="fw-bold mb-1 field-label" for="password">NEW PASSWORD</label>
                <input type="password" id="password" name="password" class="form-control" minlength="<?= PASSWORD_MIN_LENGTH ?>" maxlength="<?= PASSWORD_MAX_BYTES ?>" autocomplete="new-password" required>
            </div>

            <div class="mb-4 text-start">
                <label class="fw-bold mb-1 field-label" for="password_confirm">CONFIRM NEW PASSWORD</label>
                <input type="password" id="password_confirm" name="password_confirm" class="form-control" minlength="<?= PASSWORD_MIN_LENGTH ?>" maxlength="<?= PASSWORD_MAX_BYTES ?>" autocomplete="new-password" required>
            </div>

            <button type="submit" class="btn btn-reset w-100 mb-3">Reset Password</button>
        </form>
<?php renderAuthPageEnd(); ?>
