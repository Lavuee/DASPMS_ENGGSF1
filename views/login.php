<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth_page.php';

if (isLoggedIn()) {
    header("Location: " . dashboardForRole($_SESSION['role'] ?? ''));
    exit;
}

// One page, two modes. register.php (and register errors from the server) land here with ?panel=register.
$signupMode = ($_GET['panel'] ?? '') === 'register';

$css = '../assets/css/style.css?v=' . filemtime(__DIR__ . '/../assets/css/style.css');
$authCss = '../assets/css/auth.css?v=' . filemtime(__DIR__ . '/../assets/css/auth.css');
$authJs = '../assets/js/auth.js?v=' . filemtime(__DIR__ . '/../assets/js/auth.js');

// Logo + "Back to home" (top of each form half; on phones, above the card)
function authTopRow($extraClass = '') {
    ?>
    <div class="auth-top <?= $extraClass ?>">
        <a href="../" class="auth-brand">
            <img src="../assets/images/logo.png" alt="Norily's Vehicle Repair Shop logo" class="auth-brand-logo">
            <span class="auth-brand-text">
                <strong>Norily's</strong>
                <small>Vehicle Repair Shop</small>
            </span>
        </a>
        <a href="../" class="auth-back"><span aria-hidden="true">&larr;</span> Back to home</a>
    </div>
    <?php
}

// Small links (bottom of each form half; on phones, below the card)
function authBottomRow($extraClass = '') {
    ?>
    <div class="auth-bottom <?= $extraClass ?>">
        <span>&copy; <?= date('Y') ?> Norily's</span>
        <a href="../public_pages/terms.php">Terms</a>
        <a href="../public_pages/privacy.php">Privacy</a>
        <a href="../public_pages/contact.php">Help</a>
    </div>
    <?php
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $signupMode ? 'Create Account' : 'Log In' ?> | Norily's Vehicle Repair Shop</title>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= $css ?>">
    <link rel="stylesheet" href="<?= $authCss ?>">
    <script src="<?= $authJs ?>" defer></script>
</head>
<body class="auth-body auth-body-slide">

<?php authTopRow('auth-page-top'); ?>

<main class="auth-slide<?= $signupMode ? ' is-signup' : '' ?>" data-auth-slide>

    <!-- Column 1: Sign up -->
    <section class="auth-half auth-half-signup" aria-labelledby="signupHeading"<?= $signupMode ? '' : ' inert aria-hidden="true"' ?>>
        <?php authTopRow(); ?>

        <div class="auth-half-body">
            <div class="auth-form-wrap">
                <p class="auth-kicker">Join Norily's</p>
                <h1 class="auth-title" id="signupHeading">Create account</h1>
                <p class="auth-intro">Reserve parts, request services and follow your repair.</p>

                <?php if ($signupMode) { authMessages(); } ?>

                <!-- Same fields and action as before; the server still checks everything again. -->
                <form action="../controllers/AuthController.php" method="POST" class="auth-form auth-form-signup" data-auth-form novalidate>
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="register">

                    <div class="auth-row">
                        <div class="auth-field">
                            <label for="first_name" class="auth-label">First name</label>
                            <input type="text" id="first_name" name="first_name" class="auth-input" maxlength="20" autocomplete="given-name" required data-required-message="Enter your first name.">
                        </div>

                        <div class="auth-field">
                            <label for="last_name" class="auth-label">Last name</label>
                            <input type="text" id="last_name" name="last_name" class="auth-input" maxlength="20" autocomplete="family-name" required data-required-message="Enter your last name.">
                        </div>
                    </div>

                    <div class="auth-row">
                        <div class="auth-field">
                            <label for="email" class="auth-label">Email</label>
                            <input type="email" id="email" name="email" class="auth-input" maxlength="255" autocomplete="email" required aria-describedby="emailHint" data-check="email">
                            <p class="auth-hint" id="emailHint">You'll use this to log in.</p>
                        </div>

                        <div class="auth-field">
                            <label for="phone" class="auth-label">Contact number</label>
                            <input type="tel" id="phone" name="phone" class="auth-input" inputmode="numeric" maxlength="11" placeholder="09XXXXXXXXX" autocomplete="tel" required aria-describedby="phoneHint" data-check="phone">
                            <p class="auth-hint" id="phoneHint">11 digits, starting with 09.</p>
                        </div>
                    </div>

                    <div class="auth-field">
                        <label for="signup_password" class="auth-label">Password</label>
                        <div class="auth-password">
                            <input type="password" id="signup_password" name="password" class="auth-input" maxlength="72" autocomplete="new-password" required aria-describedby="passwordRules" data-check="password">
                            <button type="button" class="auth-show" data-password-toggle aria-controls="signup_password" aria-pressed="false" aria-label="Show password">Show</button>
                        </div>
                        <ul class="auth-rules" id="passwordRules" data-password-rules="signup_password">
                            <li data-rule="length">At least 8 characters</li>
                            <li data-rule="letter">A letter</li>
                            <li data-rule="number">A number</li>
                        </ul>
                    </div>

                    <button type="submit" class="auth-submit" data-loading-text="Creating account…">Create account</button>

                    <p class="auth-terms">
                        By creating an account you agree to the
                        <a href="../public_pages/terms.php" class="auth-text-link">Terms</a> and
                        <a href="../public_pages/privacy.php" class="auth-text-link">Privacy Policy</a>.
                    </p>
                </form>

                <div class="auth-switch">
                    Already have an account? <a href="login.php" class="auth-text-link auth-strong-link" data-auth-mode="login">Log in <span aria-hidden="true">&rarr;</span></a>
                </div>
            </div>
        </div>

        <?php authBottomRow(); ?>
    </section>

    <!-- Column 2: Log in -->
    <section class="auth-half auth-half-login" aria-labelledby="loginHeading"<?= $signupMode ? ' inert aria-hidden="true"' : '' ?>>
        <?php authTopRow(); ?>

        <div class="auth-half-body">
            <div class="auth-form-wrap">
                <p class="auth-kicker">Welcome back to</p>
                <h1 class="auth-title" id="loginHeading">Norily's</h1>
                <p class="auth-intro">Log in to your customer or staff account.</p>

                <?php if (!$signupMode) { authMessages(); } ?>

                <form action="../controllers/AuthController.php" method="POST" class="auth-form" data-auth-form novalidate>
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="login">

                    <div class="auth-field">
                        <label for="login_id" class="auth-label">Email or staff username</label>
                        <input type="text" id="login_id" name="login_id" class="auth-input" autocomplete="username" required data-required-message="Enter your email or staff username.">
                    </div>

                    <div class="auth-field">
                        <div class="auth-label-row">
                            <label for="password" class="auth-label">Password</label>
                            <a href="forgot_password.php" class="auth-text-link auth-small-link">Forgot password?</a>
                        </div>
                        <div class="auth-password">
                            <input type="password" id="password" name="password" class="auth-input" autocomplete="current-password" required data-required-message="Enter your password.">
                            <button type="button" class="auth-show" data-password-toggle aria-controls="password" aria-pressed="false" aria-label="Show password">Show</button>
                        </div>
                    </div>

                    <button type="submit" class="auth-submit" data-loading-text="Logging in…">Log in</button>
                </form>

                <div class="auth-switch">
                    No account yet? <a href="register.php" class="auth-text-link auth-strong-link" data-auth-mode="signup">Create one <span aria-hidden="true">&rarr;</span></a>
                </div>
            </div>
        </div>

        <?php authBottomRow(); ?>
    </section>

    <!-- Photo panel: covers the form not in use and slides between the two halves -->
    <div class="auth-photo" aria-hidden="true">
        <picture class="auth-photo-pic auth-photo-login">
            <source srcset="../assets/images/auth/auth-login-photo.webp" type="image/webp">
            <img src="../assets/images/auth/auth-login-photo.jpg" alt="">
        </picture>
        <picture class="auth-photo-pic auth-photo-signup">
            <source srcset="../assets/images/auth/auth-signup-photo.webp" type="image/webp">
            <img src="../assets/images/auth/auth-signup-photo.jpg" alt="">
        </picture>
    </div>
</main>

<?php authBottomRow('auth-page-bottom'); ?>

</body>
</html>
