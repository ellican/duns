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
    <style>
        /* Login Page Specific Styles */
        body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', 'Poppins', sans-serif;
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .auth-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            flex: 1;
            min-height: 100vh;
        }

        /* Left Panel - Branding */
        .auth-panel {
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 4rem;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .auth-panel::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: pulse 8s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.3; }
            50% { transform: scale(1.1); opacity: 0.5; }
        }

        .auth-panel .logo-container {
            position: relative;
            z-index: 1;
            text-align: center;
            margin-bottom: 3rem;
        }

        .auth-panel .logo {
            max-width: 180px;
            height: auto;
            margin-bottom: 2rem;
            filter: drop-shadow(0 4px 12px rgba(0,0,0,0.2));
        }

        .auth-panel h2 {
            position: relative;
            z-index: 1;
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0 0 1rem 0;
            text-align: center;
            line-height: 1.2;
        }

        .auth-panel p {
            position: relative;
            z-index: 1;
            font-size: 1.125rem;
            line-height: 1.6;
            text-align: center;
            opacity: 0.9;
            max-width: 450px;
        }

        .auth-panel .features {
            position: relative;
            z-index: 1;
            margin-top: 3rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .auth-panel .feature-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 1rem;
        }

        .auth-panel .feature-item svg {
            width: 24px;
            height: 24px;
            flex-shrink: 0;
        }

        /* Right Panel - Form */
        .auth-form-section {
            background: white;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 2rem;
        }

        .form-box {
            width: 100%;
            max-width: 450px;
            padding: 2rem;
        }

        .form-box h1 {
            font-size: 2rem;
            font-weight: 700;
            color: #1e293b;
            margin: 0 0 0.5rem 0;
        }

        .form-subtitle {
            color: #64748b;
            font-size: 1rem;
            margin-bottom: 2rem;
        }

        .error-message {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-weight: 500;
            color: #334155;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            transition: all 0.2s;
            box-sizing: border-box;
        }

        .form-control:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .forgot-password-link {
            text-align: right;
            margin-bottom: 1.5rem;
        }

        .forgot-password-link a {
            color: #2563eb;
            font-size: 0.875rem;
            text-decoration: none;
            font-weight: 500;
        }

        .forgot-password-link a:hover {
            text-decoration: underline;
        }

        .auth-button {
            width: 100%;
            padding: 0.875rem;
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: white;
            border: none;
            border-radius: 0.5rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .auth-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        .auth-button:active {
            transform: translateY(0);
        }

        .bottom-link {
            text-align: center;
            margin-top: 1.5rem;
            color: #64748b;
            font-size: 0.875rem;
        }

        .bottom-link a {
            color: #2563eb;
            font-weight: 600;
            text-decoration: none;
        }

        .bottom-link a:hover {
            text-decoration: underline;
        }

        .auth-footer {
            background: #1e293b;
            color: #94a3b8;
            text-align: center;
            padding: 1.5rem;
            font-size: 0.875rem;
        }

        /* Responsive Design */
        @media (max-width: 968px) {
            .auth-container {
                grid-template-columns: 1fr;
            }

            .auth-panel {
                min-height: 40vh;
                padding: 2rem;
            }

            .auth-panel h2 {
                font-size: 2rem;
            }

            .auth-panel .features {
                display: none;
            }
        }
    </style>
</head>
<body>
    <main class="auth-container">
        <div class="auth-panel">
            <div class="logo-container">
                <img src="https://www.fezalogistics.com/wp-content/uploads/2025/06/SQUARE-SIZEXX-FEZA-LOGO.png" alt="Feza Logistics Logo" class="logo">
            </div>
            <h2>Feza Logistics</h2>
            <p>Your trusted partner in seamless logistics and supply chain management. Access your dashboard to manage shipments, track deliveries, and optimize your operations.</p>
            <div class="features">
                <div class="feature-item">
                    <svg fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <span>Real-time shipment tracking</span>
                </div>
                <div class="feature-item">
                    <svg fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <span>Comprehensive client management</span>
                </div>
                <div class="feature-item">
                    <svg fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <span>Secure payment processing</span>
                </div>
            </div>
        </div>
        <div class="auth-form-section">
            <div class="form-box">
                <h1>Welcome Back</h1>
                <p class="form-subtitle">Please enter your credentials to continue.</p>
                <?php if (!empty($login_error)): ?>
                    <div class="error-message"><?php echo htmlspecialchars($login_error); ?></div>
                <?php endif; ?>
                <form action="login.php" method="post" class="auth-form">
                    <div class="form-group">
                        <label for="login_identifier" class="form-label">Username or Email</label>
                        <input type="text" id="login_identifier" name="login_identifier" class="form-control" required autofocus>
                    </div>
                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" id="password" name="password" class="form-control" required>
                    </div>
                    <div class="forgot-password-link">
                        <a href="forgot_password.php">Forgot Password?</a>
                    </div>
                    <button type="submit" class="auth-button">Sign In</button>
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