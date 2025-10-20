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
    <title>Sign In - Financial Management</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Poppins', sans-serif;
            height: 100vh;
            overflow: hidden;
        }

        .login-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            height: 100vh;
        }

        /* Left Panel - Branding with City Background */
        .branding-panel {
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.95) 0%, rgba(30, 41, 59, 0.9) 100%),
                        url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 800"><defs><linearGradient id="night" x1="0%" y1="0%" x2="0%" y2="100%"><stop offset="0%" style="stop-color:%23001a33;stop-opacity:1"/><stop offset="100%" style="stop-color:%23003366;stop-opacity:1"/></linearGradient></defs><rect fill="url(%23night)" width="1200" height="800"/><g opacity="0.3"><rect x="50" y="400" width="60" height="200" fill="%231e293b"/><rect x="150" y="350" width="80" height="250" fill="%23334155"/><rect x="270" y="300" width="70" height="300" fill="%231e293b"/><rect x="380" y="250" width="90" height="350" fill="%23475569"/><rect x="510" y="280" width="75" height="320" fill="%23334155"/><rect x="625" y="320" width="85" height="280" fill="%231e293b"/><rect x="750" y="270" width="95" height="330" fill="%23475569"/><rect x="885" y="340" width="70" height="260" fill="%23334155"/><rect x="995" y="290" width="80" height="310" fill="%231e293b"/><circle cx="100" cy="380" r="3" fill="%23fbbf24" opacity="0.8"/><circle cx="180" cy="330" r="2" fill="%23fbbf24" opacity="0.6"/><circle cx="305" cy="280" r="3" fill="%23fbbf24" opacity="0.9"/><circle cx="425" cy="230" r="2" fill="%23fbbf24" opacity="0.7"/></g></svg>');
            background-size: cover;
            background-position: center;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 4rem;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .branding-panel::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 30% 50%, rgba(59, 130, 246, 0.15) 0%, transparent 50%);
            pointer-events: none;
        }

        .branding-content {
            position: relative;
            z-index: 1;
            max-width: 500px;
            text-align: center;
        }

        .logo-section {
            margin-bottom: 3rem;
        }

        .logo-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            box-shadow: 0 8px 32px rgba(59, 130, 246, 0.3);
        }

        .logo-icon svg {
            width: 48px;
            height: 48px;
            color: white;
        }

        .brand-title {
            font-size: 2.75rem;
            font-weight: 700;
            margin-bottom: 1rem;
            line-height: 1.2;
            background: linear-gradient(135deg, #ffffff 0%, #e0e7ff 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .brand-subtitle {
            font-size: 1.25rem;
            font-weight: 300;
            line-height: 1.6;
            color: #cbd5e1;
            margin-bottom: 3rem;
        }

        .features-list {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
            text-align: left;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 1rem;
            color: #e2e8f0;
        }

        .feature-icon {
            width: 40px;
            height: 40px;
            background: rgba(59, 130, 246, 0.2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .feature-icon svg {
            width: 20px;
            height: 20px;
            color: #3b82f6;
        }

        /* Right Panel - Login Form */
        .form-panel {
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .form-wrapper {
            width: 100%;
            max-width: 420px;
        }

        .form-header {
            margin-bottom: 2.5rem;
        }

        .form-title {
            font-size: 2rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 0.5rem;
        }

        .form-description {
            font-size: 1rem;
            color: #64748b;
        }

        .error-message {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
            padding: 1rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }

        .error-icon {
            flex-shrink: 0;
            width: 20px;
            height: 20px;
            color: #dc2626;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 0.5rem;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 20px;
            color: #94a3b8;
            pointer-events: none;
        }

        .form-input {
            width: 100%;
            padding: 0.875rem 1rem 0.875rem 3rem;
            font-size: 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 0.75rem;
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
        }

        .form-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }

        .form-input::placeholder {
            color: #cbd5e1;
        }

        .forgot-link {
            text-align: right;
            margin-bottom: 1.5rem;
        }

        .forgot-link a {
            color: #3b82f6;
            font-size: 0.875rem;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }

        .forgot-link a:hover {
            color: #2563eb;
            text-decoration: underline;
        }

        .submit-button {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            border: none;
            border-radius: 0.75rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .submit-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
        }

        .submit-button:active {
            transform: translateY(0);
        }

        .signup-link {
            text-align: center;
            margin-top: 2rem;
            font-size: 0.875rem;
            color: #64748b;
        }

        .signup-link a {
            color: #3b82f6;
            font-weight: 600;
            text-decoration: none;
        }

        .signup-link a:hover {
            text-decoration: underline;
        }

        /* Responsive Design */
        @media (max-width: 968px) {
            .login-container {
                grid-template-columns: 1fr;
            }

            .branding-panel {
                display: none;
            }

            .form-panel {
                padding: 1.5rem;
            }
        }

        @media (max-width: 640px) {
            .brand-title {
                font-size: 2rem;
            }

            .form-title {
                font-size: 1.5rem;
            }

            .branding-panel {
                padding: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Left Panel - Branding -->
        <div class="branding-panel">
            <div class="branding-content">
                <div class="logo-section">
                    <div class="logo-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
                <h1 class="brand-title">Sign In to<br>Financial Management</h1>
                <p class="brand-subtitle">State of the Art Financial Experience At Your Fingertips</p>
                
                <div class="features-list">
                    <div class="feature-item">
                        <div class="feature-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <span>Real-time financial insights and analytics</span>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <span>Secure client and transaction management</span>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <span>AI-powered financial assistant</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Panel - Login Form -->
        <div class="form-panel">
            <div class="form-wrapper">
                <div class="form-header">
                    <h2 class="form-title">Welcome Back</h2>
                    <p class="form-description">Please sign in to your account to continue</p>
                </div>

                <?php if (!empty($login_error)): ?>
                    <div class="error-message">
                        <svg class="error-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span><?php echo htmlspecialchars($login_error); ?></span>
                    </div>
                <?php endif; ?>

                <form action="login.php" method="post">
                    <div class="form-group">
                        <label for="login_identifier" class="form-label">Username</label>
                        <div class="input-wrapper">
                            <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            <input 
                                type="text" 
                                id="login_identifier" 
                                name="login_identifier" 
                                class="form-input" 
                                placeholder="Enter your username or email"
                                required 
                                autofocus>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-wrapper">
                            <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                class="form-input" 
                                placeholder="Enter your password"
                                required>
                        </div>
                    </div>

                    <div class="forgot-link">
                        <a href="forgot_p!as$s$wor$d.php">Reset Password?</a>
                    </div>

                    <button type="submit" class="submit-button">Sign In</button>
                </form>

                <div class="signup-link">
                    Don't have an account? <a href="regi#s%^&ter.php">Create Account</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>