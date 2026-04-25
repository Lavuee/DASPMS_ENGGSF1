<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register - Norily's Repair Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { background-color: var(--bg-light); display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 2rem 0; }
        .reg-card { background-color: var(--white); border-radius: 20px; box-shadow: 0 15px 35px rgba(0, 32, 63, 0.1); padding: 3rem 2.5rem; width: 100%; max-width: 550px; border: 1px solid rgba(0,0,0,0.05); }
        .form-control { background-color: #f8fafc; border: 1px solid #e2e8f0; padding: 0.8rem 1rem; border-radius: 10px; }
        .form-control:focus { border-color: var(--primary-blue); box-shadow: 0 0 0 3px rgba(0, 32, 63, 0.1); }
        .btn-register { background-color: var(--primary-blue); color: #fff; border: none; padding: 0.8rem; border-radius: 10px; font-weight: 600; font-size: 1.1rem; transition: all 0.3s ease; }
        .btn-register:hover { background-color: var(--primary-hover); transform: translateY(-2px); }
    </style>
</head>
<body>
    <div class="reg-card">
        <div class="text-center mb-4">
            <h3 class="fw-bold mb-1" style="color: var(--text-main);">Create an Account</h3>
            <p class="text-muted" style="font-size: 0.95rem;">Join Norily's Repair Shop to track your vehicles online</p>
        </div>

        <?php if(isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger p-2"><i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
        <?php endif; ?>

        <form action="../controllers/AuthController.php" method="POST">
            <input type="hidden" name="action" value="register">
            
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="fw-bold mb-1" style="font-size: 0.85rem; color: var(--text-muted);">FIRST NAME</label>
                    <input type="text" name="first_name" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="fw-bold mb-1" style="font-size: 0.85rem; color: var(--text-muted);">LAST NAME</label>
                    <input type="text" name="last_name" class="form-control" required>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="fw-bold mb-1" style="font-size: 0.85rem; color: var(--text-muted);">EMAIL ADDRESS</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="fw-bold mb-1" style="font-size: 0.85rem; color: var(--text-muted);">PHONE NUMBER</label>
                    <input type="text" name="phone" class="form-control" required>
                </div>
            </div>

            <div class="mb-3">
                <label class="fw-bold mb-1" style="font-size: 0.85rem; color: var(--text-muted);">CHOOSE A USERNAME</label>
                <input type="text" name="username" class="form-control" required>
            </div>

            <div class="mb-4">
                <label class="fw-bold mb-1" style="font-size: 0.85rem; color: var(--text-muted);">PASSWORD</label>
                <input type="password" name="password" class="form-control" required>
            </div>

            <button type="submit" class="btn btn-register w-100 mb-4">Register Account</button>
            
            <div class="text-center" style="font-size: 0.95rem;">
                <span class="text-muted">Already have an account?</span> 
                <a href="login.php" class="fw-bold" style="color: var(--primary-blue); text-decoration: none;">Sign In</a>
            </div>
        </form>
    </div>
</body>
</html>