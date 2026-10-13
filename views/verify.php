<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth_page.php';
require_once __DIR__ . '/../includes/email_verification.php';

/*
    Opening the emailed link only shows a confirmation button; the account is
    verified by the POST (AuthController action=confirm_email). This stops email
    security scanners that prefetch links from verifying accounts by themselves.
*/
$token = trim($_GET['token'] ?? '');
$user = findUserByVerificationToken((new Database())->getConnection(), $token);

if (!$user) {
    renderAuthPageStart('Verify Email', 'Link expired', 'This verification link is invalid, already used, or has expired.');
    ?>
        <a href="resend_verification.php" class="auth-submit">Send a new link</a>
    <?php
    renderAuthPageEnd();
    exit;
}

$firstName = html_entity_decode((string)$user['first_name'], ENT_QUOTES, 'UTF-8');
renderAuthPageStart('Verify Email', 'Confirm your email', 'Hi ' . $firstName . ', click below to verify ' . $user['email'] . '.');
?>
        <form action="../controllers/AuthController.php" method="POST" class="auth-form" data-auth-form>
            <?= csrfField() ?>
            <input type="hidden" name="action" value="confirm_email">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="auth-submit" data-loading-text="Verifying…">Verify my email</button>
        </form>
<?php renderAuthPageEnd(); ?>
