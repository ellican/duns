<?php
// This file should be included at the top of every page.
// We start the session here to ensure it's available everywhere.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect to login if the user is not logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// Get user's initials for the avatar
$username_for_avatar = $_SESSION['username'] ?? 'User';
$initials = strtoupper(substr($username_for_avatar, 0, 2));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- The title will be set on each individual page -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        /* Global Styles */
        :root {
            --primary-color: #0052cc; --primary-hover: #0041a3; --secondary-color: #f4f7f6; 
            --text-color: #333; --border-color: #dee2e6; --white-color: #fff;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--secondary-color);
            margin: 0;
            padding-top: 80px; /* Provide space for the fixed header */
        }
        /* Header Styles */
        .main-header {
            background-color: var(--white-color);
            border-bottom: 1px solid var(--border-color);
            padding: 0 40px;
            height: 70px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
        }
        .main-header .logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            text-decoration: none;
        }
        .user-menu {
            position: relative;
        }
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--primary-color);
            color: var(--white-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            cursor: pointer;
            user-select: none; /* Prevents text selection */
        }
        .dropdown-menu {
            display: none; /* Hidden by default */
            position: absolute;
            top: 55px;
            right: 0;
            background-color: var(--white-color);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            width: 220px;
            overflow: hidden;
        }
        .dropdown-menu.show {
            display: block; /* Shown with JavaScript */
        }
        .dropdown-menu a {
            display: block;
            padding: 12px 20px;
            color: var(--text-color);
            text-decoration: none;
            font-size: 0.95rem;
        }
        .dropdown-menu a:hover {
            background-color: #f8f9fa;
        }
        .dropdown-menu .divider {
            height: 1px;
            background-color: var(--border-color);
            margin: 8px 0;
        }
    </style>
</head>
<body>
    <header class="main-header">
        <a href="index.php" class="logo">Feza Logistics</a>
        <div class="user-menu">
            <div class="user-avatar" id="avatar-button"><?php echo htmlspecialchars($initials); ?></div>
            <div class="dropdown-menu" id="dropdown-menu">
                <a href="profile.php">Manage Profile</a>
                <a href="document_list.php">My Documents</a>
                <a href="transactions.php">Transactions</a>
                <div class="divider"></div>
                <a href="create_quotation.php">Create Quotation</a>
                <a href="create_invoice.php">Create Invoice</a>
                <a href="create_receipt.php">Create Receipt</a>
                <div class="divider"></div>
                <a href="logout.php">Logout</a>
            </div>
        </div>
    </header>

    <main class="page-content">
        <!-- The content of each page will go here -->

    <script>
        // JavaScript for the dropdown menu
        document.addEventListener('DOMContentLoaded', function() {
            const avatarButton = document.getElementById('avatar-button');
            const dropdownMenu = document.getElementById('dropdown-menu');

            if (avatarButton) {
                avatarButton.addEventListener('click', function(event) {
                    event.stopPropagation(); // Prevent the click from closing the menu immediately
                    dropdownMenu.classList.toggle('show');
                });
            }

            // Close the dropdown if the user clicks outside of it
            window.addEventListener('click', function(event) {
                if (dropdownMenu && !dropdownMenu.contains(event.target) && !avatarButton.contains(event.target)) {
                    if (dropdownMenu.classList.contains('show')) {
                        dropdownMenu.classList.remove('show');
                    }
                }
            });
        });
    </script>
</body> <!-- The body and html tags will be closed by a footer.php file -->