<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required.']);
    exit;
}

require_once 'db.php';

try {
    // Base query that calculates the 'status' column. 
    // We will use this as a subquery (derived table) to allow filtering on the calculated status.
    $baseSql = "SELECT *, 
           (CASE 
               WHEN paid_amount >= amount THEN 'PAID'
               WHEN paid_amount > 0 THEN 'PARTIALLY PAID'
               ELSE 'NOT PAID'
           END) AS status 
        FROM clients";
    
    // The main query now selects from our derived table.
    $clientsSql = "SELECT * FROM ($baseSql) AS clients_with_status";

    $params = [];
    $where_clauses = [];

    // --- Build WHERE clauses based on filters ---
    if (!empty(trim($_GET['searchQuery']))) {
        $searchQuery = '%' . trim($_GET['searchQuery']) . '%';
        $where_clauses[] = "(reg_no LIKE :searchQuery OR client_name LIKE :searchQuery OR phone_number LIKE :searchQuery)";
        $params[':searchQuery'] = $searchQuery;
    }
    if (!empty($_GET['filterDateFrom'])) {
        $where_clauses[] = "date >= :dateFrom";
        $params[':dateFrom'] = $_GET['filterDateFrom'];
    }
    if (!empty($_GET['filterDateTo'])) {
        $where_clauses[] = "date <= :dateTo";
        $params[':dateTo'] = $_GET['filterDateTo'];
    }
    // This is the key part: filtering by the 'status' alias is now possible.
    if (!empty($_GET['filterPaidStatus'])) {
        $where_clauses[] = "status = :status";
        $params[':status'] = $_GET['filterPaidStatus'];
    }
    if (!empty($_GET['filterCurrency'])) {
        $where_clauses[] = "currency = :currency";
        $params[':currency'] = $_GET['filterCurrency'];
    }

    // --- Append WHERE clauses to the main query if any exist ---
    if (!empty($where_clauses)) {
        $clientsSql .= " WHERE " . implode(" AND ", $where_clauses);
    }

    $clientsSql .= " ORDER BY date DESC, id DESC";

    $clientsStmt = $pdo->prepare($clientsSql);
    $clientsStmt->execute($params);
    $clients = $clientsStmt->fetchAll(PDO::FETCH_ASSOC);

    // --- Return only the client list ---
    echo json_encode($clients);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'A database error occurred while fetching clients.',
        'details' => $e->getMessage() // Be cautious about sending detailed errors in production
    ]);
}
?>