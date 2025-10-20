<?php
session_start();
header('Content-Type: application/json');

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'db.php'; // Uses the new PDO connection

if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection object not found.']);
    exit;
}

// SQL query to group by currency and calculate totals
$sql = "SELECT 
            currency, 
            SUM(amount) as total_amount, 
            SUM(due_amount) as total_due, 
            SUM(paid_amount) as total_paid 
        FROM clients 
        GROUP BY currency";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Re-format the array to be keyed by currency name for easy access in JavaScript
    $summary = [];
    foreach ($results as $row) {
        $summary[$row['currency']] = [
            'total_amount' => $row['total_amount'],
            'total_due' => $row['total_due'],
            'total_paid' => $row['total_paid']
        ];
    }

    echo json_encode($summary);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database query failed in fetch_currency_summary.php.',
        'details' => $e->getMessage()
    ]);
}
?>