<?php
session_start();
require_once '../config/Database.php';

if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = trim($_GET['token']);
    
    try {
        $db = (new Database())->getConnection();
        
        // Find the user with this specific token who is not yet verified
        $stmt = $db->prepare("SELECT user_id FROM user WHERE verification_token = ? AND is_verified = 0 LIMIT 1");
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Update the user to verified and clear the token
            $update = $db->prepare("UPDATE user SET is_verified = 1, verification_token = NULL WHERE user_id = ?");
            $update->execute([$user['user_id']]);
            
            $_SESSION['success_message'] = "Your email has been successfully verified! You can now log in.";
        } else {
            $_SESSION['error_message'] = "Invalid or expired verification link. Your account may already be verified.";
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = "System error during verification. Please try again later.";
    }
} else {
    $_SESSION['error_message'] = "No verification token provided.";
}

// Redirect back to the login screen so the user can see the alert message
header("Location: login.php");
exit;
?>