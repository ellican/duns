<?php
session_start();

// --- Authenticate User ---
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

require_once 'db.php';

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = ''; // 'success', 'error', 'info'

// Get user info for display
$username = $_SESSION['username'] ?? 'User';
$email = $_SESSION['email'] ?? '';
$first_name = $_SESSION['first_name'] ?? $username;
$initials = strtoupper(substr($first_name, 0, 2));

// --- The PHP logic for updating profile, password, and email remains exactly the same ---
// --- No changes are needed in the PHP block below ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'update_profile') {
            $first_name = $_POST['first_name'] ?? ''; $last_name = $_POST['last_name'] ?? ''; $username = $_POST['username'] ?? ''; $phone_number = $_POST['phone_number'] ?? '';
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username AND id != :user_id");
            $stmt->execute([':username' => $username, ':user_id' => $user_id]);
            if ($stmt->fetch()) {
                $message = 'Username is already taken. Please choose another.'; $message_type = 'error';
            } else {
                $update_stmt = $pdo->prepare("UPDATE users SET first_name = :first_name, last_name = :last_name, username = :username, phone_number = :phone_number WHERE id = :user_id");
                $update_stmt->execute([':first_name' => $first_name, ':last_name' => $last_name, ':username' => $username, ':phone_number' => $phone_number, ':user_id' => $user_id]);
                $_SESSION['first_name'] = $first_name; $_SESSION['last_name'] = $last_name; $_SESSION['username'] = $username; $_SESSION['phone_number'] = $phone_number;
                $message = 'Profile information updated successfully!'; $message_type = 'success';
            }
        }
        if ($action === 'change_password') {
            $current_password = $_POST['current_password'] ?? ''; $new_password = $_POST['new_password'] ?? ''; $confirm_password = $_POST['confirm_password'] ?? '';
            $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = :user_id");
            $stmt->execute([':user_id' => $user_id]);
            $user = $stmt->fetch();
            if (!$user || !password_verify($current_password, $user['password_hash'])) {
                $message = 'Your current password is not correct.'; $message_type = 'error';
            } elseif (empty($new_password) || strlen($new_password) < 8) {
                $message = 'New password must be at least 8 characters long.'; $message_type = 'error';
            } elseif ($new_password !== $confirm_password) {
                $message = 'New passwords do not match.'; $message_type = 'error';
            } else {
                $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $update_stmt = $pdo->prepare("UPDATE users SET password_hash = :password_hash WHERE id = :user_id");
                $update_stmt->execute([':password_hash' => $new_password_hash, ':user_id' => $user_id]);
                $message = 'Password changed successfully.'; $message_type = 'success';
            }
        }
        if ($action === 'change_email') {
            $new_email = filter_var($_POST['new_email'] ?? '', FILTER_VALIDATE_EMAIL); $password = $_POST['password_for_email'] ?? '';
            $stmt = $pdo->prepare("SELECT email, password_hash FROM users WHERE id = :user_id");
            $stmt->execute([':user_id' => $user_id]);
            $user = $stmt->fetch();
            if (!$new_email) {
                $message = 'Please enter a valid new email address.'; $message_type = 'error';
            } elseif ($new_email === $user['email']) {
                $message = 'This is already your current email address.'; $message_type = 'error';
            } elseif (!$user || !password_verify($password, $user['password_hash'])) {
                $message = 'Your current password is not correct.'; $message_type = 'error';
            } else {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
                $stmt->execute([':email' => $new_email]);
                if ($stmt->fetch()) {
                    $message = 'This email address is already registered with another account.'; $message_type = 'error';
                } else {
                    $otp = random_int(100000, 999999); $otp_expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                    $update_stmt = $pdo->prepare("UPDATE users SET new_email_pending = :new_email, email_change_otp = :otp, otp_expires_at = :expires WHERE id = :user_id");
                    $update_stmt->execute([':new_email' => $new_email, ':otp' => $otp, ':expires' => $otp_expires_at, ':user_id' => $user_id]);
                    $headers = "From: no-reply@fezalogistics.com";
                    $subject_new = "Verify Your New Email Address"; $message_new = "Your verification code is: {$otp}";
                    mail($new_email, $subject_new, $message_new, $headers);
                    $subject_old = "Security Alert: Email Change Requested"; $message_old = "A request was made to change the email address for your account to {$new_email}.";
                    mail($user['email'], $subject_old, $message_old, $headers);
                    header('Location: verify_email_change.php'); exit;
                }
            }
        }
    } catch (PDOException $e) {
        $message = 'A critical database error occurred.'; $message_type = 'error';
    }
}

// Fetch latest user data for display in forms
$current_first_name = $_SESSION['first_name'] ?? '';
$current_last_name = $_SESSION['last_name'] ?? '';
$current_username = $_SESSION['username'] ?? '';
$current_phone_number = $_SESSION['phone_number'] ?? '';
$current_email = $_SESSION['email'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings - Feza Logistics</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/design-system.css">
    <link rel="stylesheet" href="assets/css/application.css">
    
    <style>
        /* Additional profile-specific styles */
        .profile-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: var(--space-8);
        }
        
        .profile-header {
            background: linear-gradient(135deg, var(--primary-600), var(--primary-500));
            color: var(--text-inverse);
            border-radius: var(--radius-xl);
            padding: var(--space-8);
            margin-bottom: var(--space-8);
            text-align: center;
        }
        
        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: var(--radius-full);
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: var(--font-size-2xl);
            font-weight: var(--font-weight-bold);
            margin: 0 auto var(--space-4) auto;
            border: 4px solid rgba(255, 255, 255, 0.3);
        }
        
        .profile-tabs {
            display: flex;
            background: var(--bg-primary);
            border-radius: var(--radius-lg);
            padding: var(--space-2);
            margin-bottom: var(--space-6);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-primary);
        }
        
        .profile-tab {
            flex: 1;
            padding: var(--space-3) var(--space-4);
            text-align: center;
            border-radius: var(--radius-base);
            cursor: pointer;
            font-weight: var(--font-weight-medium);
            color: var(--text-muted);
            transition: all var(--transition-fast);
            user-select: none;
        }
        
        .profile-tab:hover {
            color: var(--text-primary);
            background-color: var(--bg-muted);
        }
        
        .profile-tab.active {
            background: var(--primary);
            color: var(--text-inverse);
            box-shadow: var(--shadow-sm);
        }
        
        .profile-content {
            background: var(--bg-primary);
            border-radius: var(--radius-lg);
            padding: var(--space-8);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-primary);
        }
        
        .tab-pane {
            display: none;
        }
        
        .tab-pane.active {
            display: block;
            animation: fadeInUp var(--transition-base);
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .profile-form {
            max-width: 500px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--space-4);
            margin-bottom: var(--space-5);
        }
        
        .form-row .form-group {
            margin-bottom: 0;
        }
        
        .current-email {
            background-color: var(--bg-muted);
            padding: var(--space-4);
            border-radius: var(--radius-base);
            margin-bottom: var(--space-6);
            border: 1px solid var(--border-primary);
        }
        
        .current-email strong {
            color: var(--primary);
        }
        
        @media (max-width: 768px) {
            .profile-container {
                padding: var(--space-4);
            }
            
            .profile-tabs {
                flex-direction: column;
                gap: var(--space-2);
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Header -->
        <header class="header-container">
            <a href="index.php" class="logo">Feza Logistics</a>
            <div class="user-menu">
                <div class="user-avatar" id="avatar-button"><?php echo htmlspecialchars($initials); ?></div>
                <ul class="dropdown-menu" id="dropdown-menu">
                    <li><a href="profile.php">Manage Profile</a></li>
                    <li><a href="document_list.php">My Documents</a></li>
                    <li><a href="transactions.php">Transactions</a></li>
                    <li class="divider"></li>
                    <li><a href="create_quotation.php">Create Quotation</a></li>
                    <li><a href="create_invoice.php">Create Invoice</a></li>
                    <li><a href="create_receipt.php">Create Receipt</a></li>
                    <li class="divider"></li>
                    <li><a href="logout.php">Logout</a></li>
                </ul>
            </div>
        </header>

        <!-- Profile Content -->
        <main class="profile-container">
            <!-- Profile Header -->
            <div class="profile-header">
                <div class="profile-avatar"><?php echo htmlspecialchars($initials); ?></div>
                <h1 class="mb-2">Profile Settings</h1>
                <p class="text-lg opacity-90">Manage your account information and preferences</p>
            </div>

            <!-- Message Display -->
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type; ?> mb-6">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Profile Tabs -->
            <div class="profile-tabs">
                <div class="profile-tab active" data-tab="profile">
                    Profile Information
                </div>
                <div class="profile-tab" data-tab="password">
                    Change Password
                </div>
                <div class="profile-tab" data-tab="email">
                    Change Email
                </div>
            </div>

            <!-- Profile Content -->
            <div class="profile-content">
                <!-- Profile Information Tab -->
                <div id="profile" class="tab-pane active">
                    <h2 class="mb-6">Profile Information</h2>
                    <form action="profile.php#profile" method="POST" class="profile-form">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" id="first_name" name="first_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($current_first_name); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" id="last_name" name="last_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($current_last_name); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" id="username" name="username" class="form-control" 
                                   value="<?php echo htmlspecialchars($current_username); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone_number" class="form-label">Phone Number</label>
                            <input type="text" id="phone_number" name="phone_number" class="form-control" 
                                   value="<?php echo htmlspecialchars($current_phone_number); ?>" 
                                   placeholder="Optional">
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </form>
                </div>

                <!-- Change Password Tab -->
                <div id="password" class="tab-pane">
                    <h2 class="mb-6">Change Password</h2>
                    <form action="profile.php#password" method="POST" class="profile-form">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="form-group">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" id="current_password" name="current_password" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" id="new_password" name="new_password" class="form-control" required minlength="8">
                            <div class="form-text">Password must be at least 8 characters long</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Update Password</button>
                    </form>
                </div>

                <!-- Change Email Tab -->
                <div id="email" class="tab-pane">
                    <h2 class="mb-6">Change Email Address</h2>
                    
                    <div class="current-email">
                        <p class="mb-0">Your current email is: <strong><?php echo htmlspecialchars($current_email); ?></strong></p>
                    </div>
                    
                    <form action="profile.php#email" method="POST" class="profile-form">
                        <input type="hidden" name="action" value="change_email">
                        
                        <div class="form-group">
                            <label for="new_email" class="form-label">New Email Address</label>
                            <input type="email" id="new_email" name="new_email" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="password_for_email" class="form-label">Verify with Current Password</label>
                            <input type="password" id="password_for_email" name="password_for_email" class="form-control" required>
                            <div class="form-text">Enter your current password to confirm this change</div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Request Email Change</button>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Avatar dropdown functionality
            const avatarButton = document.getElementById('avatar-button');
            const dropdownMenu = document.getElementById('dropdown-menu');

            avatarButton?.addEventListener('click', function(event) {
                event.stopPropagation();
                dropdownMenu.classList.toggle('show');
            });

            document.addEventListener('click', function(event) {
                if (!event.target.closest('.user-menu')) {
                    dropdownMenu.classList.remove('show');
                }
            });

            // Tab functionality
            const tabButtons = document.querySelectorAll('.profile-tab');
            const tabPanes = document.querySelectorAll('.tab-pane');

            function switchTab(tabId) {
                tabButtons.forEach(btn => {
                    btn.classList.toggle('active', btn.dataset.tab === tabId);
                });
                tabPanes.forEach(pane => {
                    pane.classList.toggle('active', pane.id === tabId);
                });
            }

            tabButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const tabId = this.dataset.tab;
                    switchTab(tabId);
                    history.pushState(null, null, `#${tabId}`);
                });
            });

            // Handle initial tab from URL hash
            const currentHash = window.location.hash.substring(1);
            if (currentHash && ['profile', 'password', 'email'].includes(currentHash)) {
                switchTab(currentHash);
            }

            // Password confirmation validation
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');

            function validatePasswordMatch() {
                if (newPassword.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity('Passwords do not match');
                } else {
                    confirmPassword.setCustomValidity('');
                }
            }

            newPassword?.addEventListener('input', validatePasswordMatch);
            confirmPassword?.addEventListener('input', validatePasswordMatch);
        });
    </script>
</body>
</html>