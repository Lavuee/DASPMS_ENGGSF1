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
        <a href="resend_verification.php" class="btn btn-reset w-100 mb-3">Send a New Link</a>
    <?php
    renderAuthPageEnd();
    exit;
}

$firstName = html_entity_decode((string)$user['first_name'], ENT_QUOTES, 'UTF-8');
renderAuthPageStart('Verify Email', 'Confirm Your Email', 'Hi ' . $firstName . ', click below to verify ' . $user['email'] . '.');
?>
        <form action="../controllers/AuthController.php" method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="confirm_email">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="btn btn-reset w-100 mb-3">Verify My Email</button>
        </form>
<?php renderAuthPageEnd(); ?>
