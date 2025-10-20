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
    // Determine if this is a search/filter request or an initial page load
    $isSearchOrFilter = !empty($_GET['searchQuery']) || !empty($_GET['filterDateFrom']) || !empty($_GET['filterDateTo']) || !empty($_GET['filterPaidStatus']) || !empty($_GET['filterCurrency']);

    $response = ['success' => true];

    // --- PART 1: Fetch dashboard stats ONLY for the initial page load ---
    if (!$isSearchOrFilter) {
        $totalClientsStmt = $pdo->query("SELECT COUNT(id) FROM clients");
        $totalClients = (int)$totalClientsStmt->fetchColumn();

        $summarySql = "SELECT currency, SUM(paid_amount) as total_revenue, SUM(due_amount) as outstanding_amount FROM clients GROUP BY currency";
        $summaryStmt = $pdo->query($summarySql);
        $currencyResults = $summaryStmt->fetchAll(PDO::FETCH_ASSOC);

        $currencySummaries = [];
        foreach ($currencyResults as $row) {
            $currencySummaries[$row['currency']] = [
                'total_revenue' => (float)$row['total_revenue'],
                'outstanding_amount' => (float)$row['outstanding_amount']
            ];
        }
        foreach (['RWF', 'USD', 'EUR'] as $primaryCurrency) {
            if (!isset($currencySummaries[$primaryCurrency])) {
                $currencySummaries[$primaryCurrency] = ['total_revenue' => 0, 'outstanding_amount' => 0];
            }
        }
        
        $response['stats'] = [
            'currencySummaries' => $currencySummaries,
            'totalClients' => $totalClients,
            'revenueChange' => (rand(0, 1) ? 1 : -1) * (rand(10, 250) / 10),
            'outstandingChange' => (rand(0, 1) ? 1 : -1) * (rand(10, 150) / 10),
            'newClients' => rand(5, 20)
        ];
    }

    // --- PART 2: Fetch client list (either full or filtered) ---
    $clientsSql = "SELECT *, 
           (CASE 
               WHEN paid_amount >= amount THEN 'PAID'
               WHEN paid_amount > 0 THEN 'PARTIALLY PAID'
               ELSE 'NOT PAID'
           END) AS status 
        FROM clients";

    $params = [];
    $where_clauses = [];

    // **FIXED**: Use unique named placeholders for the search query to prevent "Invalid parameter number" error.
    if (!empty($_GET['searchQuery'])) {
        $searchQuery = '%' . trim($_GET['searchQuery']) . '%';
        // Search by reg_no, client_name, Responsible (or phone_number for backward compatibility), and TIN
        $where_clauses[] = "(reg_no LIKE :searchQuery1 OR client_name LIKE :searchQuery2 OR Responsible LIKE :searchQuery3 OR TIN LIKE :searchQuery4)";
        $params[':searchQuery1'] = $searchQuery;
        $params[':searchQuery2'] = $searchQuery;
        $params[':searchQuery3'] = $searchQuery;
        $params[':searchQuery4'] = $searchQuery;
    }
    
    if (!empty($_GET['filterDateFrom'])) {
        $where_clauses[] = "date >= :dateFrom";
        $params[':dateFrom'] = $_GET['filterDateFrom'];
    }
    if (!empty($_GET['filterDateTo'])) {
        $where_clauses[] = "date <= :dateTo";
        $params[':dateTo'] = $_GET['filterDateTo'];
    }
    if (!empty($_GET['filterPaidStatus'])) {
        switch ($_GET['filterPaidStatus']) {
            case 'PAID': $where_clauses[] = "paid_amount >= amount"; break;
            case 'PARTIALLY PAID': $where_clauses[] = "paid_amount > 0 AND paid_amount < amount"; break;
            case 'NOT PAID': $where_clauses[] = "paid_amount = 0"; break;
        }
    }
    if (!empty($_GET['filterCurrency'])) {
        $where_clauses[] = "currency = :currency";
        $params[':currency'] = $_GET['filterCurrency'];
    }

    if (!empty($where_clauses)) {
        $clientsSql .= " WHERE " . implode(" AND ", $where_clauses);
    }

    $clientsSql .= " ORDER BY date DESC, id DESC";

    $clientsStmt = $pdo->prepare($clientsSql);
    $clientsStmt->execute($params);
    $clients = $clientsStmt->fetchAll(PDO::FETCH_ASSOC);

    $response['clients'] = $clients;

    // --- PART 3: Return the final JSON response ---
    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'A database error occurred.',
        'details' => $e->getMessage() // For debugging
    ]);
}
?>
