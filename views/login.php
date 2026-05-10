<?php
session_start();

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    $dashboardMap = [
        'Owner' => 'dashboard_owner.php',
        'Cashier' => 'dashboard_cashier.php',
        'Head Mechanic' => 'dashboard_mechanic.php',
        'Customer' => 'dashboard_customer.php'
    ];
    header("Location: " . ($dashboardMap[$_SESSION['role']] ?? 'login.php'));
    exit;
}

$showRegister = false;

if (isset($_GET['panel']) && $_GET['panel'] === 'register') {
    $showRegister = true;
}

if (isset($_SESSION['auth_panel']) && $_SESSION['auth_panel'] === 'register') {
    $showRegister = true;
    unset($_SESSION['auth_panel']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login / Register - Norily's Repair Shop</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            font-family: Arial, sans-serif;
            background:
                radial-gradient(circle at top left, rgba(245, 197, 24, 0.18), transparent 30%),
                linear-gradient(135deg, #f8f8f8 0%, #ffffff 50%, #f5f5f5 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            color: var(--black);
        }

        .auth-page {
            width: 100%;
            max-width: 980px;
        }

        .top-alert {
            border-radius: 14px;
            margin-bottom: 1rem;
            box-shadow: 0 8px 20px rgba(17, 17, 17, 0.08);
        }

        .auth-container {
            position: relative;
            width: 100%;
            min-height: 640px;
            background: var(--white);
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 25px 65px rgba(17, 17, 17, 0.14);
            border: 1px solid var(--border-light);
        }

        .form-container {
            position: absolute;
            top: 0;
            height: 100%;
            width: 50%;
            transition: all 0.6s ease-in-out;
            background: var(--white);
        }

        .sign-in-container {
            left: 0;
            z-index: 2;
        }

        .sign-up-container {
            left: 0;
            opacity: 0;
            z-index: 1;
        }

        .auth-container.right-panel-active .sign-in-container {
            transform: translateX(100%);
            opacity: 0;
        }

        .auth-container.right-panel-active .sign-up-container {
            transform: translateX(100%);
            opacity: 1;
            z-index: 5;
        }

        .auth-form {
            height: 100%;
            padding: 2.5rem 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .auth-logo-wrap {
            width: 82px;
            height: 82px;
            background: transparent;
            border-radius: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem auto;
            box-shadow: none;
            border: none;
        }

        .auth-logo {
            width: 74px;
            height: 74px;
            object-fit: contain;
        }

        .auth-title {
            font-size: 2rem;
            font-weight: 900;
            line-height: 1.1;
            margin-bottom: 0.4rem;
            color: var(--black);
            text-align: center;
        }

        .auth-subtitle {
            font-size: 0.96rem;
            color: var(--muted-gray);
            text-align: center;
            margin-bottom: 1.4rem;
        }

        .auth-label {
            font-size: 0.80rem;
            font-weight: 800;
            color: var(--dark-gray);
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-bottom: 0.4rem;
        }

        .custom-input-group {
            display: flex;
            align-items: center;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
            background: var(--white);
            transition: all 0.25s ease;
        }

        .custom-input-group:focus-within {
            border-color: var(--primary-yellow);
            box-shadow: 0 0 0 4px rgba(245, 197, 24, 0.18);
            background: var(--white);
        }

        .custom-input-icon {
            width: 48px;
            min-height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--muted-gray);
            background: var(--white);
            border-right: 1px solid #e5e7eb;
        }

        .custom-input {
            width: 100%;
            border: none;
            outline: none;
            box-shadow: none !important;
            background: var(--white);
            padding: 0.85rem 0.9rem;
            font-size: 0.95rem;
            color: var(--black);
        }

        .custom-input:-webkit-autofill,
        .custom-input:-webkit-autofill:hover,
        .custom-input:-webkit-autofill:focus,
        .custom-input:-webkit-autofill:active {
            -webkit-box-shadow: 0 0 0 1000px var(--white) inset !important;
            -webkit-text-fill-color: var(--black) !important;
            transition: background-color 9999s ease-in-out 0s;
        }

        .custom-input::placeholder {
            color: #9ca3af;
        }

        .toggle-password {
            width: 46px;
            min-height: 48px;
            border: none;
            background: var(--white);
            color: var(--muted-gray);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .toggle-password:hover {
            color: var(--black);
        }

        .auth-link {
            color: var(--black);
            text-decoration: none;
            font-size: 0.88rem;
            font-weight: 700;
        }

        .auth-link:hover {
            color: var(--yellow-dark);
        }

        .auth-btn {
            background: var(--primary-yellow);
            border: 2px solid var(--primary-yellow);
            color: var(--black);
            border-radius: 999px;
            padding: 0.85rem 1.25rem;
            font-size: 0.95rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            transition: all 0.25s ease;
        }

        .auth-btn:hover {
            background: var(--black);
            border-color: var(--black);
            color: var(--white);
            transform: translateY(-2px);
        }

        .auth-btn:disabled {
            opacity: 0.75;
            cursor: not-allowed;
            transform: none;
        }

        .overlay-container {
            position: absolute;
            top: 0;
            left: 50%;
            width: 50%;
            height: 100%;
            overflow: hidden;
            transition: transform 0.6s ease-in-out;
            z-index: 100;
        }

        .auth-container.right-panel-active .overlay-container {
            transform: translateX(-100%);
        }

        .overlay {
            position: relative;
            left: -100%;
            height: 100%;
            width: 200%;
            transform: translateX(0);
            transition: transform 0.6s ease-in-out;
            background: var(--primary-yellow, #f2b705);
            color: var(--black);
        }

        .auth-container.right-panel-active .overlay {
            transform: translateX(50%);
        }

        .overlay-panel {
            position: absolute;
            top: 0;
            width: 50%;
            height: 100%;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            transition: transform 0.6s ease-in-out;
        }

        .overlay-left {
            transform: translateX(-20%);
        }

        .auth-container.right-panel-active .overlay-left {
            transform: translateX(0);
        }

        .overlay-right {
            right: 0;
            transform: translateX(0);
        }

        .auth-container.right-panel-active .overlay-right {
            transform: translateX(20%);
        }

        .overlay-title {
            font-size: 2.35rem;
            font-weight: 900;
            line-height: 1.1;
            margin-bottom: 1rem;
        }

        .overlay-text {
            font-size: 1rem;
            line-height: 1.7;
            color: rgba(17, 17, 17, 0.78);
            max-width: 330px;
            margin-bottom: 1.6rem;
        }

        .overlay-btn {
            background: transparent;
            border: 2px solid var(--black);
            color: var(--black);
            border-radius: 999px;
            padding: 0.85rem 2rem;
            font-size: 0.95rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            transition: all 0.25s ease;
        }

        .overlay-btn:hover {
            background: var(--black);
            color: var(--white);
            transform: translateY(-2px);
        }

        .mobile-switch {
            display: none;
            text-align: center;
            margin-top: 1rem;
            font-size: 0.95rem;
        }

        .mobile-switch button {
            border: none;
            background: transparent;
            color: var(--black);
            font-weight: 800;
            padding: 0;
        }

        .auth-bottom-bar {
            display: flex;
            justify-content: center;
            margin-top: 1rem;
            padding-left: 0;
        }

        .back-home-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            background: transparent;
            color: var(--muted-gray);
            text-decoration: none;
            border: none;
            padding: 0.25rem 0;
            font-size: 0.95rem;
            font-weight: 500;
            box-shadow: none;
            transition: color 0.2s ease, transform 0.2s ease;
        }

        .back-home-btn i {
            font-size: 1rem;
        }

        .back-home-btn:hover {
            color: var(--black);
            transform: translateX(-3px);
        }

        .auth-footer-simple {
            margin-top: 1.15rem;
            padding-top: 1.25rem;
            border-top: 1px solid var(--border-light);
            text-align: center;
        }

        .auth-footer-simple p {
            margin: 0;
            color: var(--muted-gray);
            font-size: 0.95rem;
            line-height: 1.5;
        }

        @media (max-width: 991.98px) {
            body {
                padding: 1rem;
            }

            .auth-container {
                min-height: auto;
                border-radius: 20px;
            }

            .overlay-container {
                display: none;
            }

            .form-container {
                position: relative;
                width: 100%;
                height: auto;
                transform: none !important;
                opacity: 1 !important;
                display: none;
            }

            .form-container.active-mobile {
                display: block;
            }

            .auth-form {
                padding: 2rem 1.35rem;
            }

            .mobile-switch {
                display: block;
            }

            .auth-title {
                font-size: 1.7rem;
            }

            .auth-bottom-bar {
                margin-top: 0.85rem;
            }

            .auth-footer-simple {
                margin-top: 1rem;
                padding-top: 1rem;
            }

            .auth-footer-simple p {
                font-size: 0.82rem;
                padding: 0 0.75rem;
            }
        }
    </style>
</head>
<body>

    <div class="auth-page">

        <?php if(isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger top-alert">
                <i class="bi bi-exclamation-circle-fill me-2"></i>
                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

        <?php if(isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success top-alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>

        <div class="auth-container <?php echo $showRegister ? 'right-panel-active' : ''; ?>" id="authContainer">

            <!-- LOGIN -->
            <div class="form-container sign-in-container <?php echo !$showRegister ? 'active-mobile' : ''; ?>" id="loginPanel">
                <form action="../controllers/AuthController.php" method="POST" class="auth-form auth-submit-form">
                    <input type="hidden" name="action" value="login">

                    <div class="auth-logo-wrap">
                        <img src="../assets/images/logo.png" alt="Logo" class="auth-logo" onerror="this.outerHTML='<i class=\'bi bi-wrench-adjustable fs-2\'></i>'">
                    </div>

                    <h1 class="auth-title">Welcome Back</h1>
                    <p class="auth-subtitle">Sign in to your DASPMS account</p>

                    <div class="mb-3">
                        <label class="auth-label">Username or Email</label>
                        <div class="custom-input-group">
                            <span class="custom-input-icon"><i class="bi bi-person"></i></span>
                            <input type="text" name="login_id" class="custom-input" placeholder="Enter email or staff username" required>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="auth-label">Password</label>
                        <div class="custom-input-group">
                            <span class="custom-input-icon"><i class="bi bi-lock"></i></span>
                            <input type="password" name="password" class="custom-input password-field" placeholder="Enter password" required>
                            <button type="button" class="toggle-password" aria-label="Show password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="text-end mb-4">
                        <a href="forgot_password.php" class="auth-link">Forgot Password?</a>
                    </div>

                    <button type="submit" class="auth-btn w-100 mb-3" data-loading-text="Signing In...">
                        Sign In
                    </button>

                    <div class="mobile-switch">
                        <span class="text-muted">Don't have an account?</span>
                        <button type="button" id="mobileShowRegister">Register here</button>
                    </div>
                </form>
            </div>

            <!-- REGISTER -->
            <div class="form-container sign-up-container <?php echo $showRegister ? 'active-mobile' : ''; ?>" id="registerPanel">
                <form action="../controllers/AuthController.php" method="POST" class="auth-form auth-submit-form">
                    <input type="hidden" name="action" value="register">

                    <h1 class="auth-title">Create Account</h1>
                    <p class="auth-subtitle">Join Norily's Repair Shop to track your vehicles and service records online</p>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="auth-label">First Name</label>
                            <div class="custom-input-group">
                                <span class="custom-input-icon"><i class="bi bi-person"></i></span>
                                <input type="text" name="first_name" class="custom-input" placeholder="First name" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="auth-label">Last Name</label>
                            <div class="custom-input-group">
                                <span class="custom-input-icon"><i class="bi bi-person"></i></span>
                                <input type="text" name="last_name" class="custom-input" placeholder="Last name" required>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="auth-label">Email Address</label>
                            <div class="custom-input-group">
                                <span class="custom-input-icon"><i class="bi bi-envelope"></i></span>
                                <input type="email" name="email" class="custom-input" placeholder="Email address" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="auth-label">Phone Number</label>
                            <div class="custom-input-group">
                                <span class="custom-input-icon"><i class="bi bi-telephone"></i></span>
                                <input type="text" name="phone" class="custom-input" placeholder="11-digit phone number" maxlength="11" required>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-light border mb-3" style="font-size: 0.88rem; border-radius: 14px;">
                        <i class="bi bi-info-circle me-1"></i>
                        Your email address will be used for login. No separate username is required.
                    </div>

                    <div class="mb-4">
                        <label class="auth-label">Password</label>
                        <div class="custom-input-group">
                            <span class="custom-input-icon"><i class="bi bi-lock"></i></span>
                            <input type="password" name="password" class="custom-input password-field" placeholder="Create a password" required>
                            <button type="button" class="toggle-password" aria-label="Show password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="auth-btn w-100 mb-3" data-loading-text="Creating Account...">
                        Register Account
                    </button>

                    <div class="mobile-switch">
                        <span class="text-muted">Already have an account?</span>
                        <button type="button" id="mobileShowLogin">Sign In</button>
                    </div>
                </form>
            </div>

            <!-- OVERLAY -->
            <div class="overlay-container">
                <div class="overlay">

                    <div class="overlay-panel overlay-left">
                        <h2 class="overlay-title">Welcome Back!</h2>
                        <p class="overlay-text">
                            Already have an account? Sign in to continue tracking your vehicle service,
                            reservations, and order updates.
                        </p>
                        <button class="overlay-btn" id="signInBtn" type="button">Sign In</button>
                    </div>

                    <div class="overlay-panel overlay-right">
                        <h2 class="overlay-title">Hello, Customer!</h2>
                        <p class="overlay-text">
                            Create your account and start using DASPMS for service monitoring,
                            reservations, and repair shop updates.
                        </p>
                        <button class="overlay-btn" id="signUpBtn" type="button">Sign Up</button>
                    </div>

                </div>
            </div>

        </div>

        <div class="auth-bottom-bar">
            <a href="../index.php" class="back-home-btn">
                <i class="bi bi-arrow-left"></i>
                Back to Home
            </a>
        </div>

        <div class="auth-footer-simple">
            <p>
                © <?php echo date('Y'); ?> Norily's Vehicle Repair Shop |
                Digital Auto Service and Parts Management System
            </p>
        </div>

    </div>

    <script>
        const authContainer = document.getElementById('authContainer');
        const signUpBtn = document.getElementById('signUpBtn');
        const signInBtn = document.getElementById('signInBtn');
        const loginPanel = document.getElementById('loginPanel');
        const registerPanel = document.getElementById('registerPanel');
        const mobileShowRegister = document.getElementById('mobileShowRegister');
        const mobileShowLogin = document.getElementById('mobileShowLogin');

        function showRegister() {
            authContainer.classList.add('right-panel-active');
            loginPanel.classList.remove('active-mobile');
            registerPanel.classList.add('active-mobile');

            if (window.history.replaceState) {
                window.history.replaceState(null, '', 'login.php?panel=register');
            }
        }

        function showLogin() {
            authContainer.classList.remove('right-panel-active');
            registerPanel.classList.remove('active-mobile');
            loginPanel.classList.add('active-mobile');

            if (window.history.replaceState) {
                window.history.replaceState(null, '', 'login.php');
            }
        }

        if (signUpBtn) signUpBtn.addEventListener('click', showRegister);
        if (signInBtn) signInBtn.addEventListener('click', showLogin);
        if (mobileShowRegister) mobileShowRegister.addEventListener('click', showRegister);
        if (mobileShowLogin) mobileShowLogin.addEventListener('click', showLogin);

        const passwordToggleButtons = document.querySelectorAll('.toggle-password');

        passwordToggleButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                const passwordInput = this.parentElement.querySelector('.password-field');
                const icon = this.querySelector('i');

                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    icon.classList.remove('bi-eye');
                    icon.classList.add('bi-eye-slash');
                    this.setAttribute('aria-label', 'Hide password');
                } else {
                    passwordInput.type = 'password';
                    icon.classList.remove('bi-eye-slash');
                    icon.classList.add('bi-eye');
                    this.setAttribute('aria-label', 'Show password');
                }
            });
        });

        const submitForms = document.querySelectorAll('.auth-submit-form');

        submitForms.forEach(function(form) {
            form.addEventListener('submit', function() {
                const submitButton = form.querySelector('button[type="submit"]');

                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>' + submitButton.dataset.loadingText;
                }
            });
        });
    </script>

</body>
</html>