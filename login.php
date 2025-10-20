<?php
session_start();
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header('Location: index.php');
    exit;
}
require_once 'db.php';
$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_identifier = $_POST['login_identifier'] ?? '';
    $password = $_POST['password'] ?? '';
    if (empty($login_identifier) || empty($password)) {
        $login_error = 'Username/Email and password are required.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username OR email = :email");
            $stmt->execute([':username' => $login_identifier, ':email' => $login_identifier]);
            $user = $stmt->fetch();
            if ($user && password_verify($password, $user['password_hash'])) {
                if ($user['is_email_verified'] != 1) {
                    $login_error = 'Your email is not verified. Please complete the email verification process.';
                } else {
                    $login_otp = random_int(100000, 999999);
                    $otp_expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                    $update_stmt = $pdo->prepare("UPDATE users SET login_otp = :otp, login_otp_expires_at = :expires WHERE id = :id");
                    $update_stmt->execute([':otp' => $login_otp, ':expires' => $otp_expiry, ':id' => $user['id']]);
                    $subject = "Your Login Verification Code";
                    $message = "Hello {$user['first_name']},\n\nYour 6-digit verification code to log in is: {$login_otp}\n\nThis code will expire in 10 minutes.\n\nRegards,\nFeza Logistics";
                    $headers = "From: no-reply@fezalogistics.com";
                    if (mail($user['email'], $subject, $message, $headers)) {
                        header("Location: verify_login.php?user_id=" . $user['id']);
                        exit;
                    } else {
                        $login_error = 'Could not send verification code. Please contact support.';
                    }
                }
            } else {
                $login_error = 'Invalid username, email, or password.';
            }
        } catch (PDOException $e) {
            $login_error = 'A database error occurred. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Feza Logistics</title>
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
            <h2>Welcome Back</h2>
            <p>Log in to access your financial dashboard and manage your clients and invoices seamlessly.</p>
        </div>
        <div class="auth-form-section">
            <div class="form-box">
                <h1>Login</h1>
                <p class="form-subtitle">Enter your credentials to continue.</p>
                <?php if (!empty($login_error)): ?>
                    <div class="error-message"><?php echo htmlspecialchars($login_error); ?></div>
                <?php endif; ?>
                <form action="login.php" method="post" class="auth-form">
                    <div class="form-group">
                        <label for="login_identifier" class="form-label">Username or Email</label>
                        <input type="text" id="login_identifier" name="login_identifier" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" id="password" name="password" class="form-control" required>
                    </div>
                    <div class="forgot-password-link">
                        <a href="forgot_password.php">Forgot Password?</a>
                    </div>
                    <button type="submit" class="auth-button">Continue</button>
                </form>
                <div class="bottom-link">
                    Don't have an account? <a href="register.php">Register Now</a>
                </div>
            </div>
        </div>
    </main>
    <footer class="auth-footer">
        All rights reserved 2025 by Joseph Devops; Tel: +250788827138
    </footer>
</body>
</html>