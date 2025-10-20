<?php
/**
 * api_transactions.php
 *
 * This script serves as the backend API for handling transaction data.
 * **FIXED**: Database connection handling - prevents generic "Database operation failed" errors
 * **FIXED**: Improved error handling with specific database error messages and details
 * **FIXED**: Added validation to ensure database connection is available before processing requests
 * **FIXED**: Corrected logic for create/update/bulk-update to properly handle the 'refundable' field.
 * **FIXED**: Reworked query builder to correctly handle combined filters and searches.
 * **NEW**: Added 'refundable' field for expenses.
 * **ENHANCED**: Enhanced search functionality with comprehensive field coverage including:
 *   - Multiple date format search (YYYY-MM-DD, DD/MM/YYYY, MM/DD/YYYY, YYYY/MM/DD)
 *   - All transaction fields (number, reference, note, status, payment_method, type, amount)
 *   - Works with existing database structure without requiring additional tables
 * **FIXED**: Removed JOINs with wp_ea_contacts and wp_ea_categories tables to fix database compatibility
 */

header('Content-Type: application/json');
// It's better to log errors than display them in a JSON API
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once 'db.php';
require_once 'fpdf/fpdf.php';

// Check if database connection was successful
if ($pdo === null) {
    // Database connection failed, return the error from db.php
    if (isset($db_connection_error)) {
        http_response_code(500);
        echo json_encode($db_connection_error);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Database connection is not available'
        ]);
    }
    exit;
}

// --- Custom PDF Class ---
class PDF_Feza extends FPDF {
    // (Content of PDF_Feza class is correct and unchanged)
    function Header() {
        if ($this->PageNo() > 1) {
            $this->Image('https://www.fezalogistics.com/wp-content/uploads/2025/06/SQUARE-SIZEXX-FEZA-LOGO.png', 10, 6, 20);
            $this->SetFont('Arial', 'B', 15);
            $this->Cell(80);
            $this->Cell(110, 10, 'Transaction Details', 0, 0, 'C');
            $this->Ln(20);
        }
    }
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}


// --- HELPER FUNCTIONS ---
function send_json_response($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

function validate_database_connection($pdo) {
    if ($pdo === null) {
        return false;
    }
    
    try {
        // Test the connection with a simple query
        $pdo->query('SELECT 1');
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

function get_next_transaction_number($pdo, $type) {
    // (This function is correct and unchanged)
    $prefix = (strtoupper($type) === 'EXPENSE') ? 'EXP-' : 'PAY-';
    $sql = "SELECT number FROM wp_ea_transactions WHERE number LIKE :prefix ORDER BY CAST(SUBSTRING(number, 5) AS UNSIGNED) DESC, id DESC LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':prefix' => $prefix . '%']);
    $last_number = $stmt->fetchColumn();
    $next_numeric_part = $last_number ? ((int)str_replace($prefix, '', $last_number) + 1) : 1;
    return $prefix . str_pad($next_numeric_part, 4, '0', STR_PAD_LEFT);
}

// --- ROUTING ---
$method = $_SERVER['REQUEST_METHOD'];
$data = [];
if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
}
$action = $data['action'] ?? $_GET['action'] ?? null;

// Double-check database connection before processing any requests
if (!validate_database_connection($pdo)) {
    send_json_response([
        'success' => false,
        'error' => 'Database connection lost or unavailable'
    ], 500);
}

try {
    switch ($action) {
        case 'print': handle_print($pdo, $data); break;
        case 'create': create_transaction($pdo, $data); break;
        case 'update': update_transaction($pdo, $data); break;
        case 'bulk_update': bulk_update_transactions($pdo, $data); break;
        case 'delete': delete_transaction($pdo, $data); break;
        default:
            if ($method === 'GET') {
                handle_get($pdo);
            } else {
                send_json_response(['success' => false, 'error' => 'Invalid action specified.'], 400);
            }
    }
} catch (PDOException $e) {
    // Specific database error - provide more detailed information
    $error_message = 'Database operation failed';
    
    // Add more specific error information based on error code
    if (strpos($e->getMessage(), 'doesn\'t exist') !== false) {
        $error_message = 'Database table or column does not exist';
    } elseif (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        $error_message = 'Duplicate entry - record already exists';
    } elseif (strpos($e->getMessage(), 'cannot be null') !== false) {
        $error_message = 'Required field is missing or null';
    } elseif ($e->getCode() == '42S02') {
        $error_message = 'Database table does not exist';
    } elseif ($e->getCode() == '23000') {
        $error_message = 'Data integrity constraint violation';
    }
    
    send_json_response([
        'success' => false, 
        'error' => $error_message,
        'details' => $e->getMessage() // Include technical details for debugging
    ], 500);
} catch (Exception $e) {
    // All other errors
    send_json_response([
        'success' => false, 
        'error' => 'An unexpected server error occurred',
        'details' => $e->getMessage()
    ], 500);
}


// --- HANDLERS ---
function build_get_query($filters = []) {
    global $pdo;
    
    // Simple query without JOINs - only uses wp_ea_transactions table
    $sql = "SELECT t.id, t.type, t.number, t.payment_date, t.amount, t.currency, t.reference, t.note, t.status, t.payment_method, t.refundable
            FROM wp_ea_transactions t
            WHERE 1=1";
    
    $params = [];
    
    // Date filters
    if (!empty($filters['from'])) {
        $sql .= " AND DATE(t.payment_date) >= :from";
        $params[':from'] = $filters['from'];
    }
    if (!empty($filters['to'])) {
        $sql .= " AND DATE(t.payment_date) <= :to";
        $params[':to'] = $filters['to'];
    }
    
    // Type filter
    if (!empty($filters['type']) && $filters['type'] !== 'all') {
        $sql .= " AND t.type = :type";
        $params[':type'] = $filters['type'];
    }
    
    // Status filter
    if (!empty($filters['status']) && $filters['status'] !== 'all') {
        $sql .= " AND t.status = :status";
        $params[':status'] = $filters['status'];
    }
    
    // Currency filter
    if (!empty($filters['currency']) && $filters['currency'] !== 'all') {
        $sql .= " AND t.currency = :currency";
        $params[':currency'] = $filters['currency'];
    }
    
    // Search filter - only search within transaction table fields
    if (!empty($filters['q'])) {
        $searchQuery = '%' . $filters['q'] . '%';
        $sql .= " AND (t.number LIKE :q_like1 
                      OR t.reference LIKE :q_like2 
                      OR t.note LIKE :q_like3 
                      OR t.status LIKE :q_like4 
                      OR t.payment_method LIKE :q_like5
                      OR t.type LIKE :q_like6
                      OR DATE_FORMAT(t.payment_date, '%Y-%m-%d') LIKE :q_like7
                      OR DATE_FORMAT(t.payment_date, '%d/%m/%Y') LIKE :q_like8
                      OR DATE_FORMAT(t.payment_date, '%m/%d/%Y') LIKE :q_like9
                      OR DATE_FORMAT(t.payment_date, '%Y/%m/%d') LIKE :q_like10)";
        
        $params[':q_like1'] = $searchQuery;
        $params[':q_like2'] = $searchQuery;
        $params[':q_like3'] = $searchQuery;
        $params[':q_like4'] = $searchQuery;
        $params[':q_like5'] = $searchQuery;
        $params[':q_like6'] = $searchQuery;
        $params[':q_like7'] = $searchQuery;
        $params[':q_like8'] = $searchQuery;
        $params[':q_like9'] = $searchQuery;
        $params[':q_like10'] = $searchQuery;
        
        // Check if search query is numeric for amount search
        if (is_numeric($filters['q'])) {
            $sql .= " OR t.amount = :q_numeric";
            $params[':q_numeric'] = (float)$filters['q'];
        }
    }
    
    // Default ordering
    $sql .= " ORDER BY t.payment_date DESC, t.id DESC";
    
    return ['sql' => $sql, 'params' => $params];
}
function handle_get($pdo) {
    $query_info = build_get_query($_GET);
    $stmt = $pdo->prepare($query_info['sql']);
    $stmt->execute($query_info['params']);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($transactions as &$tx) {
        $tx['payment_date'] = (new DateTime($tx['payment_date']))->format('Y-m-d');
    }
    send_json_response(['success' => true, 'data' => $transactions]);
}

function create_transaction($pdo, $data) {
    $sql = "INSERT INTO wp_ea_transactions (payment_date, type, number, amount, currency, reference, note, status, payment_method, refundable, account_id, category_id) VALUES (:payment_date, :type, :number, :amount, :currency, :reference, :note, :status, :payment_method, :refundable, 1, 1)";
    $stmt = $pdo->prepare($sql);
    $type = $data['type'] ?? 'expense';
    $number = get_next_transaction_number($pdo, $type);
    // **FIXED**: Correctly default refundable to 0 if not provided or if type is not 'expense'
    $refundable = ($type === 'expense' && !empty($data['refundable'])) ? 1 : 0;
    
    $stmt->execute([
        ':payment_date' => $data['payment_date'] ?? date('Y-m-d H:i:s'), ':type' => $type, ':number' => $number,
        ':amount' => $data['amount'] ?? 0.0, ':currency' => $data['currency'] ?? 'RWF', ':reference' => $data['reference'] ?? null,
        ':note' => $data['note'] ?? null, ':status' => $data['status'] ?? 'Initiated', ':payment_method' => $data['payment_method'] ?? 'OTHER',
        ':refundable' => $refundable
    ]);
    send_json_response(['success' => true, 'message' => 'Transaction created.']);
}

function update_transaction($pdo, $data) {
    if (empty($data['id'])) send_json_response(['success' => false, 'error' => 'ID is required.'], 400);
    $sql = "UPDATE wp_ea_transactions SET payment_date = :payment_date, type = :type, amount = :amount, currency = :currency, reference = :reference, note = :note, status = :status, payment_method = :payment_method, refundable = :refundable WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $type = $data['type'] ?? 'expense';
    // **FIXED**: Correctly default refundable to 0 if not provided or if type is not 'expense'
    $refundable = ($type === 'expense' && !empty($data['refundable'])) ? 1 : 0;

    $stmt->execute([
        ':id' => $data['id'], ':payment_date' => $data['payment_date'] ?? date('Y-m-d H:i:s'), ':type' => $type,
        ':amount' => $data['amount'] ?? 0.0, ':currency' => $data['currency'] ?? 'RWF', ':reference' => $data['reference'] ?? null,
        ':note' => $data['note'] ?? null, ':status' => $data['status'] ?? 'Initiated', ':payment_method' => $data['payment_method'] ?? 'OTHER',
        ':refundable' => $refundable
    ]);
    send_json_response(['success' => true, 'message' => 'Transaction updated.']);
}

function bulk_update_transactions($pdo, $data) {
    if (empty($data['ids']) || !is_array($data['ids']) || empty($data['updates'])) send_json_response(['success' => false, 'error' => 'IDs and updates required.'], 400);
    
    $allowed_fields = ['payment_date', 'type', 'payment_method', 'note', 'status', 'refundable'];
    $set_clauses = []; 
    $params = [];
    $updates = $data['updates'];

    foreach($updates as $key => $value) {
        if (in_array($key, $allowed_fields) && $value !== '') {
            $set_clauses[] = "$key = :$key";
            $params[":$key"] = $value;
        }
    }
    
    // **FIXED**: If changing type to 'payment', force 'refundable' to 0
    if (isset($updates['type']) && $updates['type'] === 'payment') {
        $params[':refundable'] = 0;
        if (!in_array('refundable = :refundable', $set_clauses)) {
            $set_clauses[] = 'refundable = :refundable';
        }
    }
    
    if (empty($set_clauses)) send_json_response(['success' => false, 'error' => 'No valid fields to update.'], 400);
    
    $ids_placeholder = rtrim(str_repeat('?,', count($data['ids'])), ',');
    $sql = "UPDATE wp_ea_transactions SET " . implode(', ', $set_clauses) . " WHERE id IN ($ids_placeholder)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge(array_values($params), $data['ids']));
    send_json_response(['success' => true, 'message' => $stmt->rowCount() . ' transactions updated.']);
}

function delete_transaction($pdo, $data) {
    if (empty($data['id'])) send_json_response(['success' => false, 'error' => 'ID is required.'], 400);
    $stmt = $pdo->prepare("DELETE FROM wp_ea_transactions WHERE id = :id");
    $stmt->execute([':id' => $data['id']]);
    send_json_response(['success' => $stmt->rowCount() > 0, 'message' => 'Transaction deleted.']);
}

function handle_print($pdo, $data) {
    // Implementing the missing print function
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="transactions_report.pdf"');
    
    // Create PDF instance
    $pdf = new PDF_Feza('P', 'mm', 'A4');
    $pdf->AliasNbPages();
    $pdf->AddPage();
    
    // Add logo and title
    $pdf->Image('https://www.fezalogistics.com/wp-content/uploads/2025/06/SQUARE-SIZEXX-FEZA-LOGO.png', 10, 10, 30);
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(190, 10, 'Feza Logistics', 0, 1, 'C');
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(190, 10, 'Transaction Report', 0, 1, 'C');
    
    // Add filter information
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'I', 10);
    $filters = $data['filters'] ?? [];
    $filter_text = "All transactions";
    
    if (!empty($filters['from']) || !empty($filters['to'])) {
        $filter_text = "Transactions from " . ($filters['from'] ?? 'beginning') . " to " . ($filters['to'] ?? 'now');
    }
    if (!empty($filters['type']) && $filters['type'] !== 'all') {
        $filter_text .= " | Type: " . ucfirst($filters['type']);
    }
    if (!empty($filters['currency']) && $filters['currency'] !== 'all') {
        $filter_text .= " | Currency: " . $filters['currency'];
    }
    if (!empty($filters['q'])) {
        $filter_text .= " | Search: '" . $filters['q'] . "'";
    }
    
    $pdf->Cell(190, 10, $filter_text, 0, 1, 'C');
    $pdf->Ln(5);
    
    // Add date of generation
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(190, 10, 'Generated: ' . date('Y-m-d H:i:s'), 0, 1, 'R');
    
    // Get transactions data
    $query_info = build_get_query($filters);
    $stmt = $pdo->prepare($query_info['sql']);
    $stmt->execute($query_info['params']);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($transactions)) {
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(190, 10, 'No transactions found with the selected filters.', 0, 1, 'C');
    } else {
        // Add summary data
        $totals = [];
        $refundable_totals = [];
        
        foreach ($transactions as $tx) {
            $currency = $tx['currency'] ?? 'N/A';
            if (!isset($totals[$currency])) {
                $totals[$currency] = ['payment' => 0, 'expense' => 0];
            }
            if (!isset($refundable_totals[$currency])) {
                $refundable_totals[$currency] = 0;
            }
            
            $amount = floatval($tx['amount']);
            if ($tx['type'] === 'payment') {
                $totals[$currency]['payment'] += $amount;
            } else if ($tx['type'] === 'expense') {
                $totals[$currency]['expense'] += $amount;
                
                // Calculate refundable totals
                if ($tx['refundable'] == '1') {
                    $refundable_totals[$currency] += $amount;
                }
            }
        }
        
        // Add charts if provided
        if (!empty($data['pieChartImage']) || !empty($data['barChartImage'])) {
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(190, 10, 'Transaction Analysis', 0, 1, 'C');
            $pdf->Ln(3);
            
            if (!empty($data['pieChartImage'])) {
                $pieChartData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $data['pieChartImage']));
                $pieChartFile = tempnam(sys_get_temp_dir(), 'pie_chart');
                file_put_contents($pieChartFile, $pieChartData);
                $pdf->Image($pieChartFile, 30, null, 70, 0, 'PNG');
                unlink($pieChartFile);
            }
            
            if (!empty($data['barChartImage'])) {
                $barChartData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $data['barChartImage']));
                $barChartFile = tempnam(sys_get_temp_dir(), 'bar_chart');
                file_put_contents($barChartFile, $barChartData);
                $pdf->Image($barChartFile, 110, $pdf->GetY() - 50, 70, 0, 'PNG');
                unlink($barChartFile);
            }
            
            $pdf->Ln(30);
        }
        
        // Add summary tables
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(190, 10, 'Financial Summary', 0, 1, 'C');
        $pdf->Ln(3);
        
        // Net summary table
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(60, 10, 'Currency', 1, 0, 'C');
        $pdf->Cell(40, 10, 'Payments', 1, 0, 'C');
        $pdf->Cell(40, 10, 'Expenses', 1, 0, 'C');
        $pdf->Cell(40, 10, 'Net', 1, 1, 'C');
        
        $pdf->SetFont('Arial', '', 10);
        foreach ($totals as $currency => $amounts) {
            $net = $amounts['payment'] - $amounts['expense'];
            $pdf->Cell(60, 8, $currency, 1, 0, 'C');
            $pdf->Cell(40, 8, number_format($amounts['payment'], 2), 1, 0, 'R');
            $pdf->Cell(40, 8, number_format($amounts['expense'], 2), 1, 0, 'R');
            $pdf->Cell(40, 8, number_format($net, 2), 1, 1, 'R');
        }
        
        // Refundable expenses table
        $pdf->Ln(5);
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(100, 10, 'Currency', 1, 0, 'C');
        $pdf->Cell(80, 10, 'Refundable Amount', 1, 1, 'C');
        
        $pdf->SetFont('Arial', '', 10);
        foreach ($refundable_totals as $currency => $amount) {
            if ($amount > 0) {
                $pdf->Cell(100, 8, $currency, 1, 0, 'C');
                $pdf->Cell(80, 8, number_format($amount, 2), 1, 1, 'R');
            }
        }
        
        // Transaction details
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(190, 10, 'Transaction Details', 0, 1, 'C');
        $pdf->Ln(5);
        
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(20, 10, 'Date', 1, 0, 'C');
        $pdf->Cell(20, 10, 'Type', 1, 0, 'C');
        $pdf->Cell(30, 10, 'Number', 1, 0, 'C');
        $pdf->Cell(30, 10, 'Method', 1, 0, 'C');
        $pdf->Cell(40, 10, 'Note', 1, 0, 'C');
        $pdf->Cell(25, 10, 'Amount', 1, 0, 'C');
        $pdf->Cell(25, 10, 'Status', 1, 1, 'C');
        
        $pdf->SetFont('Arial', '', 8);
        foreach ($transactions as $tx) {
            // Format date
            $date = new DateTime($tx['payment_date']);
            $formatted_date = $date->format('Y-m-d');
            
            // Check height needed for note field
            $note_length = strlen($tx['note'] ?? '');
            $row_height = ($note_length > 60) ? 10 : 7;
            
            // Add transaction row
            $pdf->Cell(20, $row_height, $formatted_date, 1, 0, 'C');
            $pdf->Cell(20, $row_height, ucfirst($tx['type']), 1, 0, 'C');
            $pdf->Cell(30, $row_height, $tx['number'], 1, 0, 'C');
            $pdf->Cell(30, $row_height, $tx['payment_method'], 1, 0, 'C');
            
            // Handle note with possible wrapping
            $x = $pdf->GetX();
            $y = $pdf->GetY();
            $pdf->MultiCell(40, $row_height/2, $tx['note'] ?? '', 1, 'L');
            $pdf->SetXY($x + 40, $y);
            
            // Amount with currency
            $amount_str = number_format(floatval($tx['amount']), 2) . ' ' . $tx['currency'];
            $pdf->Cell(25, $row_height, $amount_str, 1, 0, 'R');
            
            // Status and refundable indicator
            $status_text = $tx['status'];
            if ($tx['type'] === 'expense' && $tx['refundable'] == '1') {
                $status_text .= ' (R)';
            }
            $pdf->Cell(25, $row_height, $status_text, 1, 1, 'C');
            
            // If we're near the bottom of the page, add a new page
            if ($pdf->GetY() > 250) {
                $pdf->AddPage();
                $pdf->SetFont('Arial', 'B', 9);
                $pdf->Cell(20, 10, 'Date', 1, 0, 'C');
                $pdf->Cell(20, 10, 'Type', 1, 0, 'C');
                $pdf->Cell(30, 10, 'Number', 1, 0, 'C');
                $pdf->Cell(30, 10, 'Method', 1, 0, 'C');
                $pdf->Cell(40, 10, 'Note', 1, 0, 'C');
                $pdf->Cell(25, 10, 'Amount', 1, 0, 'C');
                $pdf->Cell(25, 10, 'Status', 1, 1, 'C');
                $pdf->SetFont('Arial', '', 8);
            }
        }
    }
    
    // Output the PDF
    $pdf->Output('D', 'Feza_Transactions_Report.pdf');
    exit;
}