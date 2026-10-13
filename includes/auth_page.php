<?php
/*
    Shared layout for the account pages: log in, sign up, forgot/reset password and
    email verification. Gives every page the same frame (top bar, card, small footer)
    and shows the flash messages the AuthController sets in the session.

    Small pages (forgot/reset password, resend/confirm verification) use
    renderAuthPageStart() / renderAuthPageEnd(), which wrap their form in a 520px card.
    Log in and sign up build their own two-column card between authPageOpen() and authPageClose().
*/

function authEscape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// <head>, top bar and the opening of <main>.
function authPageOpen($title, $bodyClass = '') {
    $css = '../assets/css/style.css?v=' . filemtime(__DIR__ . '/../assets/css/style.css');
    $authCss = '../assets/css/auth.css?v=' . filemtime(__DIR__ . '/../assets/css/auth.css');
    $authJs = '../assets/js/auth.js?v=' . filemtime(__DIR__ . '/../assets/js/auth.js');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= authEscape($title) ?> | Norily's Vehicle Repair Shop</title>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= $css ?>">
    <link rel="stylesheet" href="<?= $authCss ?>">
    <script src="<?= $authJs ?>" defer></script>
</head>
<body class="auth-body <?= authEscape($bodyClass) ?>">
    <header class="auth-topbar">
        <a href="../" class="auth-brand">
            <img src="../assets/images/logo.png" alt="Norily's Vehicle Repair Shop logo" class="auth-brand-logo">
            <span class="auth-brand-text">
                <strong>Norily's</strong>
                <small>Vehicle Repair Shop</small>
            </span>
        </a>

        <a href="../" class="auth-back"><span aria-hidden="true">&larr;</span> Back to home</a>
    </header>

    <main class="auth-main">
    <?php
}

// Closes <main>, then the small centered footer.
function authPageClose() {
    ?>
    </main>

    <footer class="auth-footer">
        <span>&copy; <?= date('Y') ?> Norily's</span>
        <a href="../public_pages/terms.php">Terms</a>
        <a href="../public_pages/privacy.php">Privacy</a>
        <a href="../public_pages/contact.php">Help</a>
    </footer>
</body>
</html>
    <?php
}

/*
    Shows (and clears) the flash messages set by the AuthController.
    The "Resend verification email" link appears only when the server asks for it.
*/
function authMessages() {
    if (!empty($_SESSION['error_message'])) {
        ?>
        <div class="auth-alert auth-alert-error" role="alert">
            <?= authEscape($_SESSION['error_message']) ?>
            <?php if (!empty($_SESSION['show_resend_verification'])): ?>
                <a href="resend_verification.php" class="auth-alert-link">Resend verification email</a>
            <?php endif; ?>
        </div>
        <?php
    }

    if (!empty($_SESSION['success_message'])) {
        ?>
        <div class="auth-alert auth-alert-success" role="status">
            <?= authEscape($_SESSION['success_message']) ?>
        </div>
        <?php
    }

    unset($_SESSION['error_message'], $_SESSION['success_message'], $_SESSION['show_resend_verification']);
}

// Small pages: one 520px card with a label, a big heading and a short intro line.
function renderAuthPageStart($title, $heading, $intro, $kicker = 'Account help') {
    authPageOpen($title, 'auth-body-small');
    ?>
        <section class="auth-card auth-card-small">
            <p class="auth-kicker"><?= authEscape($kicker) ?></p>
            <h1 class="auth-title"><?= authEscape($heading) ?></h1>
            <p class="auth-intro"><?= authEscape($intro) ?></p>

            <?php authMessages(); ?>
    <?php
}

function renderAuthPageEnd($backLink = true) {
    if ($backLink) {
        ?>
            <div class="auth-switch">
                <a href="login.php" class="auth-text-link"><span aria-hidden="true">&larr;</span> Back to log in</a>
            </div>
        <?php
    }
    ?>
        </section>
    <?php
    authPageClose();
}
