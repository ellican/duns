<?php
session_start();
require_once 'db.php';

$user_id = $_GET['user_id'] ?? null;
$verification_error = '';

if (!$user_id) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = $_POST['otp'] ?? '';

    if (empty($otp)) {
        $verification_error = 'Please enter the verification code.';
    } else {
        try {
            // Prepare to find the user and check the OTP
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
            $stmt->execute([':id' => $user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Check if OTP matches and is not expired
                if ($user['login_otp'] == $otp && strtotime($user['login_otp_expires_at']) > time()) {
                    // --- THIS IS THE CRITICAL FIX ---
                    // OTP is correct. Now, create the full session.
                    
                    session_regenerate_id(true); // Prevent session fixation

                    // Store ALL user details in the session
                    $_SESSION['loggedin'] = true;
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['first_name'] = $user['first_name'];
                    $_SESSION['last_name'] = $user['last_name'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['phone_number'] = $user['phone_number'];

                    // Clear the OTP from the database now that it's used
                    $update_stmt = $pdo->prepare("UPDATE users SET login_otp = NULL, login_otp_expires_at = NULL WHERE id = :id");
                    $update_stmt->execute([':id' => $user['id']]);

                    // --- SECURITY TRACKING: Capture login details ---
                    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                    
                    // Extract device info from User-Agent
                    $device = 'Unknown Device';
                    if (preg_match('/\((.*?)\)/', $user_agent, $matches)) {
                        $device = $matches[1];
                    }
                    
                    // Get approximate location from IP (using a free IP geolocation service)
                    $location = 'Unknown Location';
                    $country_code = 'XX';
                    try {
                        $geo_data = @file_get_contents("http://ip-api.com/json/{$ip_address}");
                        if ($geo_data) {
                            $geo = json_decode($geo_data, true);
                            if ($geo && $geo['status'] === 'success') {
                                $location = ($geo['city'] ?? '') . ', ' . ($geo['country'] ?? '');
                                $country_code = $geo['countryCode'] ?? 'XX';
                            }
                        }
                    } catch (Exception $e) {
                        // If geolocation fails, continue with unknown location
                    }

                    // Store login attempt in database
                    try {
                        $login_stmt = $pdo->prepare("INSERT INTO login_attempts (user_id, device, ip_address, location, country_code) VALUES (:user_id, :device, :ip_address, :location, :country_code)");
                        $login_stmt->execute([
                            ':user_id' => $user['id'],
                            ':device' => substr($device, 0, 255),
                            ':ip_address' => $ip_address,
                            ':location' => substr($location, 0, 255),
                            ':country_code' => $country_code
                        ]);
                    } catch (PDOException $e) {
                        // Log error but don't prevent login
                        error_log("Failed to log login attempt: " . $e->getMessage());
                    }

                    // Send security notification email
                    $email_subject = "New Login to Your Feza Logistics Account";
                    $email_body = "
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <style>
                            body { font-family: 'Arial', sans-serif; line-height: 1.6; color: #333; }
                            .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f8f9fa; }
                            .header { background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%); color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
                            .content { background: white; padding: 30px; border-radius: 0 0 8px 8px; }
                            .info-box { background: #eff6ff; border-left: 4px solid #2563eb; padding: 15px; margin: 20px 0; }
                            .info-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e5e7eb; }
                            .info-label { font-weight: bold; color: #1e40af; }
                            .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 12px; }
                            .flag { font-size: 24px; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <h2>🔐 Security Alert</h2>
                            </div>
                            <div class='content'>
                                <p>Hello <strong>{$user['first_name']}</strong>,</p>
                                <p>We detected a new login to your Feza Logistics account. If this was you, you can safely ignore this email.</p>
                                
                                <div class='info-box'>
                                    <h3 style='margin-top: 0; color: #1e40af;'>Login Details:</h3>
                                    <div class='info-row'>
                                        <span class='info-label'>Time:</span>
                                        <span>" . date('F j, Y, g:i a') . "</span>
                                    </div>
                                    <div class='info-row'>
                                        <span class='info-label'>IP Address:</span>
                                        <span>{$ip_address}</span>
                                    </div>
                                    <div class='info-row'>
                                        <span class='info-label'>Device:</span>
                                        <span>{$device}</span>
                                    </div>
                                    <div class='info-row'>
                                        <span class='info-label'>Location:</span>
                                        <span>{$location}</span>
                                    </div>
                                </div>

                                <p><strong>If this wasn't you:</strong></p>
                                <ul>
                                    <li>Change your password immediately</li>
                                    <li>Contact our support team</li>
                                    <li>Review your recent account activity</li>
                                </ul>

                                <p>Thank you for using Feza Logistics.</p>
                            </div>
                            <div class='footer'>
                                <p>This is an automated security notification from Feza Logistics.<br>
                                Please do not reply to this email.</p>
                            </div>
                        </div>
                    </body>
                    </html>
                    ";

                    $headers = "MIME-Version: 1.0\r\n";
                    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
                    $headers .= "From: Feza Logistics Security <security@fezalogistics.com>\r\n";
                    
                    // Send email (in production, consider using a proper email service)
                    @mail($user['email'], $email_subject, $email_body, $headers);

                    // Redirect to the main page
                    header('Location: index.php');
                    exit;

                } else {
                    $verification_error = 'Invalid or expired verification code.';
                }
            } else {
                $verification_error = 'User not found.';
            }
        } catch (PDOException $e) {
            $verification_error = 'A database error occurred. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Login - Feza Logistics</title>
    <!-- Use the same CSS as your login page for consistency -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #0052cc; --primary-hover: #0041a3; --secondary-color: #f4f7f6; --text-color: #333; --light-text-color: #777; --border-color: #ddd; --error-bg: #f8d7da; --error-text: #721c24;
        }
        body { font-family: 'Poppins', sans-serif; margin: 0; display: flex; justify-content: center; align-items: center; min-height: 100vh; background-color: var(--secondary-color); }
        .form-box { width: 100%; max-width: 400px; background: #fff; padding: 40px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .form-box h1 { color: var(--text-color); margin-bottom: 10px; font-size: 2rem; text-align: center; }
        .form-box .form-subtitle { color: var(--light-text-color); margin-bottom: 30px; text-align: center; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-color); }
        .form-group input { width: 100%; padding: 12px 15px; border: 1px solid var(--border-color); border-radius: 5px; box-sizing: border-box; font-size: 1rem; text-align: center; letter-spacing: 0.5em; }
        .auth-button { width: 100%; padding: 14px; background-color: var(--primary-color); color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 1.1rem; font-weight: 700; }
        .error-message { color: var(--error-text); background-color: var(--error-bg); border: 1px solid var(--error-text); padding: 10px; border-radius: 5px; margin-bottom: 20px; text-align: center; }
        .bottom-link { margin-top: 25px; text-align: center; }
        .bottom-link a { color: var(--primary-color); text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
    <div class="form-box">
        <h1>Verify Your Identity</h1>
        <p class="form-subtitle">A 6-digit code has been sent to your email.</p>
        <?php if (!empty($verification_error)): ?>
            <div class="error-message"><?php echo htmlspecialchars($verification_error); ?></div>
        <?php endif; ?>
        <form action="verify_login.php?user_id=<?php echo htmlspecialchars($user_id); ?>" method="post">
            <div class="form-group">
                <label for="otp">Verification Code</label>
                <input type="text" id="otp" name="otp" required maxlength="6" pattern="\d{6}" title="Enter the 6-digit code.">
            </div>
            <button type="submit" class="auth-button">Verify & Login</button>
        </form>
        <div class="bottom-link">
            <a href="login.php">Back to Login</a>
        </div>
    </div>
</body>
</html>