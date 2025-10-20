<?php
require_once 'db.php';
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();
            if ($user) {
                // Generate a secure, unique token
                $token = bin2hex(random_bytes(32));
                $token_expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                
                // Store the token and expiry in the database
                $update_stmt = $pdo->prepare("UPDATE users SET password_reset_token = :token, password_reset_expires_at = :expires WHERE id = :id");
                $update_stmt->execute([':token' => $token, ':expires' => $token_expiry, ':id' => $user['id']]);

                // Send the reset link email
                $reset_link = "https://duns.fezalogistics.com/reset_password.php?token=" . $token;
                $subject = "Password Reset Request";
                $email_message = "Hello {$user['first_name']},\n\nYou requested a password reset. Click the link below to set a new password:\n\n{$reset_link}\n\nThis link will expire in 15 minutes. If you did not request this, please ignore this email.\n\nRegards,\nFeza Logistics";
                $headers = "From: no-reply@fezalogistics.com";

                mail($user['email'], $subject, $email_message, $headers);
            }
            // Always show a generic success message to prevent user enumeration
            $message = 'If an account with that email exists, we have sent a password reset link.';
        } catch (PDOException $e) {
            $error = 'A database error occurred. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Feza Logistics</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/design-system.css">
    <link rel="stylesheet" href="assets/css/application.css">
</head>
<body>
    <main class="auth-container">
        <div class="auth-panel">
            <img src="https://www.fezalogistics.com/wp-content/uploads/2025/06/SQUARE-SIZEXX-FEZA-LOGO.png" alt="Feza Logistics Logo" class="logo">
            <h2>Password Recovery</h2>
            <p>No problem. Enter your email address and we'll send you a secure link to reset your password.</p>
        </div>
        <div class="auth-form-section">
            <div class="form-box">
                <h1>Reset Password</h1>
                <p class="form-subtitle">Enter your email to receive a reset link.</p>
                
                <?php if (!empty($error)): ?>
                    <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php if (!empty($message)): ?>
                    <div class="success-message"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>
                
                <?php if (!$message): ?>
                <form action="forgot_password.php" method="post" class="auth-form">
                    <div class="form-group">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" id="email" name="email" class="form-control" required 
                               placeholder="Enter your registered email address">
                    </div>
                    <button type="submit" class="auth-button">Send Reset Link</button>
                </form>
                <?php endif; ?>
                
                <div class="bottom-link">
                    Remember your password? <a href="login.php">Back to Login</a>
                </div>
            </div>
        </div>
    </main>
    <footer class="auth-footer">
        All rights reserved 2025 by Joseph Devops; Tel: +250788827138
    </footer>
</body>
    <footer class="auth-footer">
        All rights reserved 2025 by Joseph Devops; Tel: +250788827138
    </footer>
</body>
</html>