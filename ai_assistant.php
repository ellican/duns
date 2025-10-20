<?php
/**
 * ai_assistant.php
 * 
 * Backend API for AI-powered financial assistant using Ollama/TinyLlama
 * Handles natural language queries, converts them to SQL, and returns results
 */

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session and authenticate
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Please log in.']);
    exit;
}

// Include database connection
require_once 'db.php';

// Configuration
define('OLLAMA_API_URL', 'http://localhost:11434/api/generate');
define('OLLAMA_MODEL', 'tinyllama');
define('MAX_RESPONSE_TOKENS', 500);
define('SQL_TIMEOUT', 5); // seconds

// Get request data
$request_body = file_get_contents('php://input');
$data = json_decode($request_body, true);

if (!$data || !isset($data['query'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request. Query is required.']);
    exit;
}

$user_query = trim($data['query']);
$session_id = session_id();
$user_id = $_SESSION['user_id'];

// Start timing
$start_time = microtime(true);

/**
 * Build system prompt with database schema information
 */
function buildSystemPrompt($pdo) {
    $schema_info = "Database Schema:\n";
    
    // Get clients table structure
    $schema_info .= "Table: clients\n";
    $schema_info .= "Columns: id, reg_no, client_name, date, Responsible (contact person), TIN (tax ID), service, amount, currency (USD/EUR/RWF), paid_amount, due_amount, status (PAID/PARTIALLY PAID/NOT PAID), created_by_id\n\n";
    
    // Get users table structure (limited info for security)
    $schema_info .= "Table: users\n";
    $schema_info .= "Columns: id, username, first_name, last_name, email\n\n";
    
    // Get client_history table structure
    $schema_info .= "Table: client_history\n";
    $schema_info .= "Columns: id, client_id, user_name, action, details, changed_at\n\n";
    
    $system_prompt = "You are a professional financial assistant for Feza Logistics. Your ONLY source of truth is the application database. 

STRICT RULES:
1. ONLY generate SELECT queries - NEVER use INSERT, UPDATE, DELETE, DROP, ALTER, or any other modifying statements
2. Always use proper SQL syntax for MySQL/MariaDB
3. Return ONLY the SQL query without any explanations, markdown, or additional text
4. Use table and column names exactly as provided in the schema
5. For currency-related queries, remember to filter or group by currency field
6. Status values are: 'PAID', 'PARTIALLY PAID', 'NOT PAID'
7. Currency values are: 'USD', 'EUR', 'RWF'
8. Always use LIMIT to prevent overwhelming results (max 100 rows)
9. If user asks for totals or sums, use aggregate functions (SUM, COUNT, AVG, etc.)
10. Join tables when needed for comprehensive answers

EXAMPLE QUERIES:
- 'total revenue this month' → SELECT SUM(paid_amount) as total FROM clients WHERE MONTH(date) = MONTH(CURRENT_DATE()) AND YEAR(date) = YEAR(CURRENT_DATE())
- 'unpaid invoices' → SELECT * FROM clients WHERE status = 'NOT PAID' LIMIT 100
- 'top 5 clients' → SELECT client_name, SUM(amount) as total_revenue FROM clients GROUP BY client_name ORDER BY total_revenue DESC LIMIT 5
- 'USD revenue' → SELECT SUM(paid_amount) as total FROM clients WHERE currency = 'USD'

$schema_info

User Query: ";
    
    return $system_prompt;
}

/**
 * Query Ollama API
 */
function queryOllama($prompt, $system_prompt) {
    $full_prompt = $system_prompt . $prompt;
    
    $payload = [
        'model' => OLLAMA_MODEL,
        'prompt' => $full_prompt,
        'stream' => false,
        'options' => [
            'num_predict' => MAX_RESPONSE_TOKENS,
            'temperature' => 0.1, // Low temperature for more deterministic SQL generation
            'top_p' => 0.9
        ]
    ];
    
    $ch = curl_init(OLLAMA_API_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        throw new Exception("Ollama API returned status code: $http_code");
    }
    
    $result = json_decode($response, true);
    if (!$result || !isset($result['response'])) {
        throw new Exception("Invalid response from Ollama API");
    }
    
    return $result['response'];
}

/**
 * Extract and validate SQL query from AI response
 */
function extractAndValidateSQL($ai_response) {
    // Remove markdown code blocks if present
    $sql = preg_replace('/```sql\s*(.*?)\s*```/s', '$1', $ai_response);
    $sql = preg_replace('/```\s*(.*?)\s*```/s', '$1', $sql);
    
    // Clean up the SQL
    $sql = trim($sql);
    
    // Remove any trailing semicolons
    $sql = rtrim($sql, ';');
    
    // Validate that it's a SELECT query
    if (!preg_match('/^\s*SELECT\s+/i', $sql)) {
        throw new Exception("Only SELECT queries are allowed. The AI did not generate a valid SELECT query.");
    }
    
    // Block dangerous keywords
    $dangerous_keywords = ['INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'CREATE', 'TRUNCATE', 'REPLACE', 'GRANT', 'REVOKE'];
    foreach ($dangerous_keywords as $keyword) {
        if (preg_match('/\b' . $keyword . '\b/i', $sql)) {
            throw new Exception("Blocked dangerous keyword: $keyword");
        }
    }
    
    // Ensure LIMIT is present (add if missing)
    if (!preg_match('/\bLIMIT\s+\d+/i', $sql)) {
        $sql .= ' LIMIT 100';
    }
    
    return $sql;
}

/**
 * Execute SQL query safely
 */
function executeSQLQuery($pdo, $sql) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $results;
    } catch (PDOException $e) {
        throw new Exception("SQL execution error: " . $e->getMessage());
    }
}

/**
 * Format results for human-readable response
 */
function formatResults($results, $user_query) {
    if (empty($results)) {
        return "I couldn't find any data matching your query. Please try rephrasing or asking something else.";
    }
    
    $count = count($results);
    
    // Check if it's an aggregate query (SUM, COUNT, etc.)
    $first_row = $results[0];
    $keys = array_keys($first_row);
    
    // If single row with aggregate functions
    if ($count === 1 && (
        isset($first_row['COUNT(*)']) || 
        isset($first_row['SUM']) || 
        isset($first_row['AVG']) ||
        isset($first_row['total']) ||
        isset($first_row['count'])
    )) {
        $response = "Here's what I found:\n\n";
        foreach ($first_row as $key => $value) {
            $formatted_key = str_replace('_', ' ', ucfirst($key));
            if (is_numeric($value)) {
                $value = number_format($value, 2);
            }
            $response .= "**{$formatted_key}:** {$value}\n";
        }
        return $response;
    }
    
    // For regular queries, return formatted table data
    $response = "I found {$count} result(s):\n\n";
    
    // Limit display to first 10 rows for readability
    $display_count = min($count, 10);
    
    for ($i = 0; $i < $display_count; $i++) {
        $row = $results[$i];
        $response .= "**Record " . ($i + 1) . ":**\n";
        foreach ($row as $key => $value) {
            $formatted_key = str_replace('_', ' ', ucfirst($key));
            if ($value === null) {
                $value = 'N/A';
            }
            $response .= "• {$formatted_key}: {$value}\n";
        }
        $response .= "\n";
    }
    
    if ($count > 10) {
        $response .= "_Showing first 10 of {$count} results._\n";
    }
    
    // Check if user is asking for a report/document
    if (detectReportRequest($user_query) && $count > 0) {
        $response .= "\n\n💡 **Tip:** To generate a printable PDF report of these results, you can use the document generation features in the dashboard. Click on any client row to access invoice and receipt generation.\n";
    }
    
    return $response;
}

/**
 * Detect if user is asking for a report or document
 */
function detectReportRequest($query) {
    $report_keywords = ['report', 'pdf', 'print', 'document', 'invoice', 'receipt', 'statement', 'summary', 'export', 'download'];
    $query_lower = strtolower($query);
    
    foreach ($report_keywords as $keyword) {
        if (strpos($query_lower, $keyword) !== false) {
            return true;
        }
    }
    
    return false;
}

/**
 * Log AI interaction to database
 */
function logAIInteraction($pdo, $user_id, $session_id, $user_query, $ai_response, $sql, $result_count, $execution_time, $status, $error = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO ai_chat_logs 
            (user_id, session_id, user_query, ai_response, sql_executed, sql_result_count, execution_time_ms, status, error_message) 
            VALUES (:user_id, :session_id, :user_query, :ai_response, :sql, :result_count, :execution_time, :status, :error)
        ");
        
        $stmt->execute([
            ':user_id' => $user_id,
            ':session_id' => $session_id,
            ':user_query' => $user_query,
            ':ai_response' => $ai_response,
            ':sql' => $sql,
            ':result_count' => $result_count,
            ':execution_time' => $execution_time,
            ':status' => $status,
            ':error' => $error
        ]);
    } catch (PDOException $e) {
        // Log error but don't fail the request
        error_log("Failed to log AI interaction: " . $e->getMessage());
    }
}

// Main execution flow
try {
    // Build system prompt with schema
    $system_prompt = buildSystemPrompt($pdo);
    
    // Query Ollama
    $ai_sql_response = queryOllama($user_query, $system_prompt);
    
    // Extract and validate SQL
    $sql = extractAndValidateSQL($ai_sql_response);
    
    // Execute SQL
    $results = executeSQLQuery($pdo, $sql);
    
    // Format results
    $formatted_response = formatResults($results, $user_query);
    
    // Calculate execution time
    $execution_time = round((microtime(true) - $start_time) * 1000);
    
    // Log the interaction
    logAIInteraction($pdo, $user_id, $session_id, $user_query, $formatted_response, $sql, count($results), $execution_time, 'success');
    
    // Return success response
    echo json_encode([
        'success' => true,
        'response' => $formatted_response,
        'sql' => $sql, // For debugging/transparency
        'result_count' => count($results),
        'execution_time_ms' => $execution_time
    ]);
    
} catch (Exception $e) {
    // Calculate execution time even on error
    $execution_time = round((microtime(true) - $start_time) * 1000);
    
    // Determine error type
    $error_message = $e->getMessage();
    $status = 'error';
    
    if (strpos($error_message, 'Blocked dangerous keyword') !== false) {
        $status = 'blocked';
    }
    
    // Log the failed interaction
    logAIInteraction($pdo, $user_id, $session_id, $user_query, null, null, 0, $execution_time, $status, $error_message);
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $error_message,
        'user_message' => "I encountered an error processing your request. Please try rephrasing your question or ask something simpler."
    ]);
}
