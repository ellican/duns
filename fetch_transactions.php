<?php
/**
 * fetch_transactions.php
 * 
 * Enhanced transaction fetching API with comprehensive search functionality.
 * 
 * ENHANCEMENTS:
 * - Multiple date format search support (YYYY-MM-DD, DD/MM/YYYY, MM/DD/YYYY, YYYY/MM/DD)
 * - All transaction fields searchable (number, reference, note, status, payment_method, type, amount)
 * - Works with existing database structure without requiring additional tables
 * - Maintains existing functionality while expanding search capabilities
 * - Uses prepared statements for security
 * FIXED: Removed JOINs with wp_ea_contacts and wp_ea_categories tables to fix database compatibility
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required.']);
    exit;
}

require_once 'db.php';

try {
    // Enhanced query with comprehensive search functionality within wp_ea_transactions table
    // Removed JOINs with wp_ea_contacts and wp_ea_categories tables as they don't exist in current database
  try {
    // Simple query without JOINs - only uses wp_ea_transactions table
    $sql = "SELECT t.id, t.type, t.number, t.payment_date, t.amount, t.currency, t.reference, t.note, t.status, t.payment_method, t.refundable
            FROM wp_ea_transactions t
            WHERE 1=1";
    $params = [];
    
    // Build WHERE clauses based on filters
    if (!empty($_GET['from'])) {
        $sql .= " AND DATE(t.payment_date) >= :from";
        $params[':from'] = $_GET['from'];
    }
    if (!empty($_GET['to'])) {
        $sql .= " AND DATE(t.payment_date) <= :to";
        $params[':to'] = $_GET['to'];
    }
    if (!empty($_GET['type']) && $_GET['type'] !== 'all') {
        $sql .= " AND t.type = :type";
        $params[':type'] = $_GET['type'];
    }
    if (!empty($_GET['status']) && $_GET['status'] !== 'all') {
        $sql .= " AND t.status = :status";
        $params[':status'] = $_GET['status'];
    }
    if (!empty($_GET['currency']) && $_GET['currency'] !== 'all') {
        $sql .= " AND t.currency = :currency";
        $params[':currency'] = $_GET['currency'];
    }
    
    if (!empty(trim($_GET['q']))) {
        $searchQuery = '%' . trim($_GET['q']) . '%';
        // Search within transaction table fields and formatted dates
        $sql .= " AND (t.number LIKE :searchQuery1 
                      OR t.reference LIKE :searchQuery2 
                      OR t.note LIKE :searchQuery3 
                      OR t.status LIKE :searchQuery4 
                      OR t.payment_method LIKE :searchQuery5
                      OR t.type LIKE :searchQuery6
                      OR DATE_FORMAT(t.payment_date, '%Y-%m-%d') LIKE :searchQuery7
                      OR DATE_FORMAT(t.payment_date, '%d/%m/%Y') LIKE :searchQuery8
                      OR DATE_FORMAT(t.payment_date, '%m/%d/%Y') LIKE :searchQuery9
                      OR DATE_FORMAT(t.payment_date, '%Y/%m/%d') LIKE :searchQuery10)";
        
        $params[':searchQuery1'] = $searchQuery;
        $params[':searchQuery2'] = $searchQuery;
        $params[':searchQuery3'] = $searchQuery;
        $params[':searchQuery4'] = $searchQuery;
        $params[':searchQuery5'] = $searchQuery;
        $params[':searchQuery6'] = $searchQuery;
        $params[':searchQuery7'] = $searchQuery;
        $params[':searchQuery8'] = $searchQuery;
        $params[':searchQuery9'] = $searchQuery;
        $params[':searchQuery10'] = $searchQuery;
        
        // Check if search query is numeric for amount search
        if (is_numeric(trim($_GET['q']))) {
            $sql .= " OR t.amount = :searchQueryNumeric";
            $params[':searchQueryNumeric'] = (float)trim($_GET['q']);
        }
    }
    
    $sql .= " ORDER BY t.payment_date DESC, t.id DESC";
    
    // Execute the query
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($transactions);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}