<?php
session_start();
header('Content-Type: application/json');

// --- Authenticate User ---
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required.']);
    exit;
}

// --- Database Connection ---
require_once 'db.php';

try {
    // --- Build the SQL Query based on filters ---
    $clientsSql = "SELECT *, 
           (CASE 
               WHEN paid_amount >= amount THEN 'PAID'
               WHEN paid_amount > 0 THEN 'PARTIALLY PAID'
               ELSE 'NOT PAID'
           END) AS status 
        FROM clients";

    $params = [];
    $where_clauses = [];

    // Handle the main search query
    if (isset($_GET['searchQuery']) && trim($_GET['searchQuery']) !== '') {
        $searchQuery = '%' . trim($_GET['searchQuery']) . '%';
        $where_clauses[] = "(reg_no LIKE :searchQuery OR client_name LIKE :searchQuery OR phone_number LIKE :searchQuery)";
        $params[':searchQuery'] = $searchQuery;
    }

    // Handle date range filters
    if (!empty($_GET['filterDateFrom'])) {
        $where_clauses[] = "date >= :dateFrom";
        $params[':dateFrom'] = $_GET['filterDateFrom'];
    }
    if (!empty($_GET['filterDateTo'])) {
        $where_clauses[] = "date <= :dateTo";
        $params[':dateTo'] = $_GET['filterDateTo'];
    }

    // Handle payment status filter
    if (!empty($_GET['filterPaidStatus'])) {
        switch ($_GET['filterPaidStatus']) {
            case 'PAID': $where_clauses[] = "paid_amount >= amount"; break;
            case 'PARTIALLY PAID': $where_clauses[] = "paid_amount > 0 AND paid_amount < amount"; break;
            case 'NOT PAID': $where_clauses[] = "paid_amount = 0"; break;
        }
    }

    // Handle currency filter
    if (!empty($_GET['filterCurrency'])) {
        $where_clauses[] = "currency = :currency";
        $params[':currency'] = $_GET['filterCurrency'];
    }

    // If there are any WHERE clauses, append them to the SQL query
    if (!empty($where_clauses)) {
        $clientsSql .= " WHERE " . implode(" AND ", $where_clauses);
    }

    // Add ordering to the results
    $clientsSql .= " ORDER BY date DESC, id DESC";

    // --- Execute the Query ---
    $clientsStmt = $pdo->prepare($clientsSql);
    $clientsStmt->execute($params);
    $clients = $clientsStmt->fetchAll(PDO::FETCH_ASSOC);

    // --- Return the results as JSON ---
    echo json_encode([
        'success' => true,
        'clients' => $clients
    ]);

} catch (PDOException $e) {
    // Handle any database errors
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'A database error occurred while searching.',
        'details' => $e->getMessage() // For debugging purposes
    ]);
}
?>
