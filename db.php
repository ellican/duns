<?php
/**
 * db.php
 * 
 * This file connects to the database using PDO and creates a connection
 * object named $pdo that other scripts can use. This is the single source
 * of truth for database connections across the application.
 */

// --- DATABASE CREDENTIALS ---
$host = 'localhost';
$dbname = 'duns';
$username = 'duns';
$password = 'QRJ5M0VuI1nkMQW';
$charset = 'utf8mb4';

// --- PDO CONNECTION ---

// Data Source Name (DSN)
$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";

// PDO options for security and efficiency
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,      // Throw exceptions on errors
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,          // Fetch associative arrays by default
    PDO::ATTR_EMULATE_PREPARES   => false,                       // Use native prepared statements for security
];

try {
    // Create the PDO instance and assign it to the $pdo variable.
    // All other scripts should use this $pdo variable.
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    // Set $pdo to null so calling scripts can check for connection failure
    $pdo = null;
    
    // Store the error details for use by calling scripts
    $db_connection_error = [
        'success' => false, 
        'error' => 'Database Connection Failed',
        'details' => $e->getMessage()
    ];
    
    // Don't exit here - let the calling script handle the error appropriately
}

// If the script reaches this point, the connection was successful.
?>