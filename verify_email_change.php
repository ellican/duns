<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['loggedin'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = 'info';

// Fetch the pending email to display to the user
$stmt = $pdo->prepare("SELECT new_email_pending FROM users WHERE id = :id");
$stmt->execute([':id' => $user_id]);
$pending_email = $stmt->fetchColumn();

if (!$pending_email) {
    // If there's no pending email, maybe they already verified. Send them back.
    header('Location: profile.php#email');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = $_POST['otp'] ?? '';

    if (empty($otp)) {
        $message = 'Please enter the verification code.';
        $message_type = 'error';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT email_change_otp, otp_expires_at FROM users WHERE id = :id");
            $stmt->execute([':id' => $user_id]);
            $user = $stmt->fetch();

            if ($user && $user['email_change_otp'] == $otp && strtotime($user['otp_expires_at']) > time()) {
                // OTP is correct and not expired. Finalize the email change.
                $update_stmt = $pdo->prepare(
                    "UPDATE users SET email = :new_email, new_email_pending = NULL, email_change_otp = NULL, otp_expires_at = NULL WHERE id = :id"
                );
                $update_stmt->execute([':new_email' => $pending_email, ':id' => $user_id]);

                // Update the session
                $_SESSION['email'] = $pending_email;

                // Set a success message and redirect back to the profile page
                $_SESSION['profile_message'] = 'Your email address has been updated successfully!';
                $_SESSION['profile_message_type'] = 'success';
                header('Location: profile.php#email');
                exit;

            } else {
                $message = 'Invalid or expired verification code. Please try again.';
                $message_type = 'error';
            }
        } catch (PDOException $e) {
            $message = 'A database error occurred.';
            $message_type = 'error';
        }
    }
}

// Check for and display messages from the session (used for the redirect)
if (isset($_SESSION['profile_message'])) {
    $message = $_SESSION['profile_message'];
    $message_type = $_SESSION['profile_message_type'];
    unset($_SESSION['profile_message'], $_SESSION['profile_message_type']);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email Change - Feza Logistics</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #0052cc; --primary-hover: #0041a3; --secondary-color: #f4f7f6; --text-color: #333;
            --border-color: #ddd; --white-color: #fff; --success-bg: #d1e7dd; --success-text: #0f5132;
            --error-bg: #f8d7da; --error-text: #842029; --info-bg: #cff4fc; --info-text: #055160;
        }
        body { font-family: 'Poppins', sans-serif; background-color: var(--secondary-color); display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; }
        .verify-box { max-width: 450px; width: 100%; background: var(--white-color); padding: 40px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); text-align: center; }
        h1 { font-size: 1.8rem; margin-top: 0; color: var(--text-color); }
        p { color: #555; line-height: 1.6; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; color: #555; }
        input[type="text"] {
            width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 5px; box-sizing: border-box; font-size: 1.5rem; text-align: center; letter-spacing: 0.5em;
        }
        .btn { display: block; width: 100%; padding: 12px; background-color: var(--primary-color); color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 1rem; font-weight: 600; }
        .message { padding: 15px; margin-top: 20px; border-radius: 5px; border: 1px solid transparent; }
        .message.success { background-color: var(--success-bg); color: var(--success-text); border-color: #b6d7c3; }
        .message.error { background-color: var(--error-bg); color: var(--error-text); border-color: #f5c2c7; }
        .message.info { background-color: var(--info-bg); color: var(--info-text); border-color: #b6effb; }
        .back-link { display: inline-block; margin-top: 20px; color: var(--primary-color); text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
    <div class="verify-box">
        <h1>Confirm Your New Email</h1>
        <p>A verification code has been sent to <strong><?php echo htmlspecialchars($pending_email); ?></strong>. Please enter the code below to finalize the change.</p>
        
        <form action="verify_email_change.php" method="POST">
            <div class="form-group">
                <label for="otp">6-Digit Verification Code</label>
                <input type="text" id="otp" name="otp" required maxlength="6" pattern="\d{6}">
            </div>
            <button type="submit" class="btn">Verify and Update Email</button>
        </form>

        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <a href="profile.php#email" class="back-link">Cancel and go back</a>
    </div>
</body>
</html>