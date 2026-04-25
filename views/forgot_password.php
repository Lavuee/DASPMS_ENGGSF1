<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password - Norily's Repair Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { background-color: var(--bg-light); display: flex; align-items: center; justify-content: center; height: 100vh; }
        .reset-card { background-color: var(--white); border-radius: 20px; box-shadow: 0 15px 35px rgba(0, 32, 63, 0.1); padding: 3rem 2.5rem; width: 100%; max-width: 450px; }
        .form-control { background-color: #f8fafc; border: 1px solid #e2e8f0; padding: 0.8rem 1rem; border-radius: 10px; }
        .form-control:focus { border-color: var(--primary-blue); box-shadow: 0 0 0 3px rgba(0, 32, 63, 0.1); }
        .btn-reset { background-color: var(--primary-blue); color: #fff; border: none; padding: 0.8rem; border-radius: 10px; font-weight: 600; font-size: 1.1rem; }
    </style>
</head>
<body>
    <div class="reset-card text-center">
        <h3 class="fw-bold mb-2" style="color: var(--text-main);">Reset Password</h3>
        <p class="text-muted mb-4" style="font-size: 0.95rem;">Enter your email address and we'll send you a link to reset your password.</p>

        <form action="login.php" method="POST">
            <div class="mb-4 text-start">
                <label class="fw-bold mb-1" style="font-size: 0.85rem; color: var(--text-muted);">EMAIL ADDRESS</label>
                <input type="email" name="email" class="form-control" placeholder="Enter your registered email" required>
            </div>

            <button type="submit" class="btn btn-reset w-100 mb-3">Send Reset Link</button>
            
            <a href="login.php" style="color: var(--text-muted); text-decoration: none; font-size: 0.9rem; font-weight: 500;">Back to Login</a>
        </form>
    </div>
</body>
</html>