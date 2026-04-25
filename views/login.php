<?php
session_start();
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    $dashboardMap = ['Owner' => 'dashboard_owner.php', 'Cashier' => 'dashboard_cashier.php', 'Head Mechanic' => 'dashboard_mechanic.php', 'Customer' => 'dashboard_customer.php'];
    header("Location: " . ($dashboardMap[$_SESSION['role']] ?? 'login.php'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - Norily's Repair Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { background-color: var(--bg-light); height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-card { background-color: var(--white); border-radius: 20px; box-shadow: 0 15px 35px rgba(0, 32, 63, 0.1); padding: 3rem 2.5rem; width: 100%; max-width: 420px; border: 1px solid rgba(0,0,0,0.05); }
        .login-logo-container { width: 80px; height: 80px; background-color: var(--primary-blue); border-radius: 16px; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem auto; box-shadow: 0 8px 20px rgba(0, 32, 63, 0.25); }
        .login-logo { width: 55px; height: 55px; object-fit: contain; }
        .form-control { background-color: #f8fafc; border: 1px solid #e2e8f0; padding: 0.8rem 1.2rem; border-radius: 10px; color: var(--text-main); }
        .form-control:focus { border-color: var(--primary-blue); box-shadow: 0 0 0 3px rgba(0, 32, 63, 0.1); }
        .btn-login { background-color: var(--primary-blue); color: #fff; border: none; padding: 0.8rem; border-radius: 10px; font-weight: 600; font-size: 1.1rem; transition: all 0.3s ease; }
        .btn-login:hover { background-color: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0, 32, 63, 0.2); }
    </style>
</head>
<body>

    <div class="login-card text-center">
        <div class="login-logo-container">
            <img src="../assets/img/logo.png" alt="Logo" class="login-logo" onerror="this.outerHTML='<i class=\'bi bi-wrench-adjustable text-white fs-1\'></i>'">
        </div>
        
        <h3 class="fw-bold mb-1" style="color: var(--text-main);">Welcome Back</h3>
        <p class="text-muted mb-4" style="font-size: 0.95rem;">Sign in to your account</p>

        <?php if(isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger p-2" style="border-radius: 10px; font-size: 0.9rem;">
                <i class="bi bi-exclamation-circle-fill me-2"></i><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>
        <?php if(isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success p-2" style="border-radius: 10px; font-size: 0.9rem;">
                <i class="bi bi-check-circle-fill me-2"></i><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>

        <form action="../controllers/AuthController.php" method="POST">
            <input type="hidden" name="action" value="login">
            
            <div class="mb-3 text-start">
                <label class="fw-bold mb-1" style="font-size: 0.85rem; color: var(--text-muted);">USERNAME OR EMAIL</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-person text-muted"></i></span>
                    <input type="text" name="login_id" class="form-control border-start-0 ps-0" placeholder="Enter username or email" required>
                </div>
            </div>
            
            <div class="mb-2 text-start">
                <label class="fw-bold mb-1" style="font-size: 0.85rem; color: var(--text-muted);">PASSWORD</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-lock text-muted"></i></span>
                    <input type="password" name="password" class="form-control border-start-0 ps-0" placeholder="Enter password" required>
                </div>
            </div>

            <div class="text-end mb-4">
                <a href="forgot_password.php" style="font-size: 0.85rem; color: var(--primary-blue); font-weight: 600; text-decoration: none;">Forgot Password?</a>
            </div>
            
            <button type="submit" class="btn btn-login w-100 mb-4">Sign In</button>
            
            <div class="text-center" style="font-size: 0.95rem;">
                <span class="text-muted">Don't have an account?</span> 
                <a href="register.php" class="fw-bold" style="color: var(--primary-blue); text-decoration: none;">Register here</a>
            </div>
        </form>
    </div>

</body>
</html>