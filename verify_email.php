<?php
require_once 'db.php';
$email = $_GET['email'] ?? '';
$errors = [];
$success_message = '';
if (empty($email)) {
    header("Location: register.php");
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = $_POST['otp'] ?? '';
    if (empty($otp)) {
        $errors[] = 'Please enter the verification code.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();
            if ($user) {
                if ($user['is_email_verified']) {
                    $success_message = 'This email has already been verified. You can now log in.';
                } elseif ($user['email_verification_otp'] == $otp && strtotime($user['email_otp_expires_at']) > time()) {
                    $update_stmt = $pdo->prepare("UPDATE users SET is_email_verified = 1, email_verification_otp = NULL, email_otp_expires_at = NULL WHERE id = :id");
                    $update_stmt->execute([':id' => $user['id']]);
                    $success_message = 'Email verified successfully! You can now proceed to login.';
                } else {
                    $errors[] = 'Invalid or expired verification code. Please try again.';
                }
            } else {
                $errors[] = 'User not found.';
            }
        } catch (PDOException $e) {
            $errors[] = 'Database error. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email - Feza Logistics</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary-color: #0052cc; --primary-hover: #0041a3; --secondary-color: #f4f7f6; --text-color: #333; --light-text-color: #777; --border-color: #ddd; --error-bg: #f8d7da; --error-text: #721c24; --success-bg: #d4edda; --success-text: #155724; /* --- FOOTER COLOR CHANGE --- */ --footer-bg: #ffffff; --footer-text: #555555; }
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
        .form-box .form-subtitle strong { color: var(--text-color); }
        .form-group input { width: 100%; padding: 12px 15px; border: 1px solid var(--border-color); border-radius: 5px; box-sizing: border-box; font-size: 1.5rem; transition: border-color 0.3s; text-align: center; letter-spacing: 0.5em; }
        .form-group input:focus { outline: none; border-color: var(--primary-color); }
        .auth-button { width: 100%; padding: 14px; background-color: var(--primary-color); color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 1.1rem; font-weight: 700; transition: background-color 0.3s; margin-top: 20px; }
        .auth-button:hover { background-color: var(--primary-hover); }
        .message-area { margin-bottom: 20px; }
        .message { padding: 15px; border-radius: 5px; text-align: center; }
        .error-message { color: var(--error-text); background-color: var(--error-bg); }
        .success-message { color: var(--success-text); background-color: var(--success-bg); }
        .bottom-link { margin-top: 25px; }
        .bottom-link a { color: var(--primary-color); text-decoration: none; font-weight: 600; }
        .auth-footer { text-align: center; padding: 20px; background-color: var(--footer-bg); color: var(--footer-text); font-size: 0.9rem; flex-shrink: 0; border-top: 1px solid var(--border-color); }
        @media (max-width: 992px) { .auth-panel { display: none; } .auth-form-section { padding: 30px; } }
    </style>
</head>
<body>
    <main class="auth-container">
        <div class="auth-panel">
            <img src="https://www.fezalogistics.com/wp-content/uploads/2025/06/SQUARE-SIZEXX-FEZA-LOGO.png" alt="Feza Logistics Logo" class="logo">
            <h2>One Last Step</h2>
            <p>Confirm your email to secure your account and unlock all features.</p>
        </div>
        <div class="auth-form-section">
            <div class="form-box">
                <h1>Verify Your Email</h1>
                <p class="form-subtitle">An 8-digit code has been sent to<br><strong><?php echo htmlspecialchars($email); ?></strong></p>
                <div class="message-area">
                    <?php if (!empty($errors)): ?><div class="message error-message"><?php echo htmlspecialchars($errors[0]); ?></div><?php endif; ?>
                    <?php if ($success_message): ?><div class="message success-message"><?php echo htmlspecialchars($success_message); ?></div><?php endif; ?>
                </div>
                <?php if (!$success_message): ?>
                <form action="verify_email.php?email=<?php echo urlencode($email); ?>" method="post">
                    <div class="form-group"><input type="text" name="otp" maxlength="8" required></div>
                    <button type="submit" class="auth-button">Verify Account</button>
                </form>
                <?php endif; ?>
                <?php if ($success_message): ?>
                <div class="bottom-link"><a href="login.php">Proceed to Login</a></div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    <footer class="auth-footer">
        All rights reserved 2025 by Joseph Devops; Tel: +250788827138
    </footer>
</body>
</html>