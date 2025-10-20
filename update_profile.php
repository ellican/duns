<?php
session_start();
require_once 'db.php';

// Ensure user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// Get user ID from session
$userId = $_SESSION['user_id'];

// --- Handle Information Update ---
if (isset($_POST['update_info'])) {
    $name = trim($_POST['name']);

    if (empty($name)) {
        $_SESSION['message'] = 'Name cannot be empty.';
        $_SESSION['message_type'] = 'error';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE users SET name = :name WHERE id = :id");
            $stmt->execute([':name' => $name, ':id' => $userId]);

            // Update session to reflect the change immediately
            $_SESSION['username'] = $name;

            $_SESSION['message'] = 'Profile information updated successfully!';
            $_SESSION['message_type'] = 'success';

        } catch (PDOException $e) {
            $_SESSION['message'] = 'Database error. Could not update profile.';
            $_SESSION['message_type'] = 'error';
        }
    }
    header('Location: profile.php');
    exit;
}

// --- Handle Password Change ---
if (isset($_POST['update_password'])) {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmNewPassword = $_POST['confirm_new_password'];

    // Validations
    if (empty($currentPassword) || empty($newPassword) || empty($confirmNewPassword)) {
        $_SESSION['message'] = 'All password fields are required.';
        $_SESSION['message_type'] = 'error';
    } elseif ($newPassword !== $confirmNewPassword) {
        $_SESSION['message'] = 'New passwords do not match.';
        $_SESSION['message_type'] = 'error';
    } else {
        try {
            // Fetch the current hashed password from the DB
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = :id");
            $stmt->execute([':id' => $userId]);
            $user = $stmt->fetch();

            // Verify the current password
            if ($user && password_verify($currentPassword, $user['password'])) {
                // Hash the new password
                $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

                // Update the database with the new password
                $updateStmt = $pdo->prepare("UPDATE users SET password = :password WHERE id = :id");
                $updateStmt->execute([':password' => $newPasswordHash, ':id' => $userId]);

                $_SESSION['message'] = 'Password changed successfully!';
                $_SESSION['message_type'] = 'success';
            } else {
                $_SESSION['message'] = 'Incorrect current password.';
                $_SESSION['message_type'] = 'error';
            }
        } catch (PDOException $e) {
            $_SESSION['message'] = 'Database error. Could not change password.';
            $_SESSION['message_type'] = 'error';
        }
    }
    header('Location: profile.php');
    exit;
}

// Redirect back if accessed directly
header('Location: profile.php');
exit;