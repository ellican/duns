<?php
require_once 'db.php';
$token = $_GET['token'] ?? '';
$error = '';
$message = '';

if (empty($token)) {
    header("Location: login.php");
    exit;
}

try {
    // Check if the token is valid and not expired
    $stmt = $pdo->prepare("SELECT * FROM users WHERE password_reset_token = :token AND password_reset_expires_at > NOW()");
    $stmt->execute([':token' => $token]);
    $user = $stmt->fetch();

    if (!$user) {
        $error = "This password reset link is invalid or has expired. Please request a new one.";
    }
} catch (PDOException $e) {
    $error = "Database error. Please try again later.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    $password_regex = "/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/";

    if (empty($password) || empty($password_confirm)) {
        $error = 'Please enter and confirm your new password.';
    } elseif ($password !== $password_confirm) {
        $error = 'Passwords do not match.';
    } elseif (!preg_match($password_regex, $password)) {
        $error = 'Password must be at least 8 characters long and include an uppercase letter, a lowercase letter, a number, and a special character.';
    } else {
        // All checks passed, update the password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $update_stmt = $pdo->prepare("UPDATE users SET password_hash = :hash, password_reset_token = NULL, password_reset_expires_at = NULL WHERE id = :id");
        $update_stmt->execute([':hash' => $password_hash, ':id' => $user['id']]);
        $message = "Your password has been reset successfully. You can now log in with your new password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Feza Logistics</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary-color: #28a745; --primary-hover: #218838; --secondary-color: #f4f7f6; --text-color: #333; --light-text-color: #777; --border-color: #ddd; --error-bg: #f8d7da; --error-text: #721c24; --success-bg: #d4edda; --success-text: #155724; --footer-bg: #ffffff; --footer-text: #555555; }
        body { font-family: 'Poppins', sans-serif; margin: 0; display: flex; flex-direction: column; min-height: 100vh; background-color: var(--secondary-color); }
        main.auth-container { flex-grow: 1; display: flex; width: 100%; }
        .auth-panel { flex: 1; background: linear-gradient(135deg, #0052cc, #007bff); color: white; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 50px; text-align: center; }
        .auth-panel .logo { max-width: 120px; margin-bottom: 30px; background: #fff; border-radius:10px; padding:10px; }
        .auth-panel h2 { font-size: 2rem; margin-bottom: 15px; }
        .auth-panel p { font-size: 1.1rem; line-height: 1.6; max-width: 350px; }
        .auth-form-section { flex: 1; display: flex; align-items: center; justify-content: center; padding: 50px; background: #fff; }
        .form-box { width: 100%; max-width: 400px; text-align: center; }
        .form-box h1 { color: var(--text-color); margin-bottom: 10px; font-size: 2.2rem; }
        .form-box .form-subtitle { color: var(--light-text-color); margin-bottom: 30px; }
        .form-group label { display: block; text-align: left; margin-bottom: 8px; font-weight: 600; color: var(--text-color); }
        .form-group input { width: 100%; padding: 12px 15px; border: 1px solid var(--border-color); border-radius: 5px; box-sizing: border-box; font-size: 1rem; }
        .auth-button { width: 100%; padding: 14px; background-color: var(--primary-color); color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 1.1rem; font-weight: 700; }
        .message-area { margin-bottom: 20px; }
        .message { padding: 15px; border-radius: 5px; }
        .error-message { color: var(--error-text); background-color: var(--error-bg); }
        .success-message { color: var(--success-text); background-color: var(--success-bg); }
        .bottom-link { margin-top: 25px; }
        .bottom-link a { color: #0052cc; text-decoration: none; font-weight: 600; }
        .auth-footer { text-align: center; padding: 20px; background-color: var(--footer-bg); color: var(--footer-text); font-size: 0.9rem; flex-shrink: 0; border-top: 1px solid var(--border-color); }
        @media (max-width: 992px) { .auth-panel { display: none; } }
    </style>
</head>
<body>
    <main class="auth-container">
        <div class="auth-panel">
            <img src="https://www.fezalogistics.com/wp-content/uploads/2025/06/SQUARE-SIZEXX-FEZA-LOGO.png" alt="Feza Logistics Logo" class="logo">
            <h2>Set a New Password</h2>
            <p>Choose a strong, new password to secure your account.</p>
        </div>
        <div class="auth-form-section">
            <div class="form-box">
                <h1>Create New Password</h1>
                <div class="message-area">
                    <?php if ($error): ?><div class="message error-message"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
                    <?php if ($message): ?><div class="message success-message"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
                </div>

                <?php if ($user && !$message): ?>
                <p class="form-subtitle">Please enter your new password below.</p>
                <form action="reset_password.php?token=<?php echo htmlspecialchars($token); ?>" method="post">
                    <div class="form-group">
                        <label for="password">New Password</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <div class="form-group">
                        <label for="password_confirm">Confirm New Password</label>
                        <input type="password" id="password_confirm" name="password_confirm" required>
                    </div>
                    <button type="submit" class="auth-button">Reset Password</button>
                </form>
                <?php endif; ?>
                
                <div class="bottom-link">
                    <a href="login.php">Back to Login</a>
                </div>
            </div>
        </div>
    </main>
    <footer class="auth-footer">
        All rights reserved 2025 by Joseph Devops; Tel: +250788827138
    </footer>
</body>
</html>