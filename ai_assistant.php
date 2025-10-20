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
define('LOG_FILE', __DIR__ . '/logs/ai_assistant.log'); // Log file for debugging

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
 * Log debug information to file
 */
function logDebug($message, $data = null) {
    // Ensure logs directory exists
    $log_dir = dirname(LOG_FILE);
    if (!file_exists($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[{$timestamp}] {$message}";
    
    if ($data !== null) {
        $log_message .= "\n" . print_r($data, true);
    }
    
    $log_message .= "\n" . str_repeat('-', 80) . "\n";
    
    @file_put_contents(LOG_FILE, $log_message, FILE_APPEND);
}

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
    
    $system_prompt = "You are a helpful and friendly financial assistant for Feza Logistics. You help users with financial data queries.

IMPORTANT BEHAVIORAL RULES:
1. If the user greets you (hi, hello, hey, good morning, etc.) or engages in casual conversation, respond naturally and warmly without generating SQL. Just have a friendly conversation.
2. If the user asks about your capabilities or who you are, explain that you're a financial assistant that can help query financial data, and provide examples of what you can do.
3. ONLY generate SQL queries when the user is asking for specific financial data (revenue, invoices, clients, amounts, etc.)
4. For data queries: Return ONLY the SQL query without any additional text, explanations, or markdown.

RESPONSE FORMAT:
- For greetings/conversation: Respond naturally in plain text (e.g., \"Hello! I'm your financial assistant. How can I help you today?\")
- For capability questions: Explain what you can do in plain text
- For data queries: Return ONLY a SQL SELECT query

SQL GENERATION RULES (only when generating SQL):
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

EXAMPLE RESPONSES:
- User: 'hi' → \"Hello! I'm your financial assistant. I can help you with information about revenue, invoices, clients, and financial data. What would you like to know?\"
- User: 'what can you do?' → \"I can help you query financial data such as: revenue totals, unpaid invoices, client information, payment status, and more. Just ask me any question about your financial data!\"
- User: 'total revenue this month' → SELECT SUM(paid_amount) as total FROM clients WHERE MONTH(date) = MONTH(CURRENT_DATE()) AND YEAR(date) = YEAR(CURRENT_DATE())
- User: 'unpaid invoices' → SELECT * FROM clients WHERE status = 'NOT PAID' LIMIT 100
- User: 'top 5 clients' → SELECT client_name, SUM(amount) as total_revenue FROM clients GROUP BY client_name ORDER BY total_revenue DESC LIMIT 5
- User: 'USD revenue' → SELECT SUM(paid_amount) as total FROM clients WHERE currency = 'USD'

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
    
    // Log the request
    logDebug("=== OLLAMA API REQUEST ===");
    logDebug("URL: " . OLLAMA_API_URL);
    logDebug("Payload:", $payload);
    
    $ch = curl_init(OLLAMA_API_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Log the response
    logDebug("=== OLLAMA API RESPONSE ===");
    logDebug("HTTP Code: " . $http_code);
    
    if ($curl_error) {
        logDebug("cURL Error: " . $curl_error);
        throw new Exception("Failed to connect to Ollama API: " . $curl_error);
    }
    
    if ($http_code !== 200) {
        logDebug("Error Response:", $response);
        throw new Exception("Ollama API returned status code: $http_code. Response: " . substr($response, 0, 200));
    }
    
    logDebug("Raw Response:", $response);
    
    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        logDebug("JSON Decode Error: " . json_last_error_msg());
        throw new Exception("Invalid JSON response from Ollama API: " . json_last_error_msg());
    }
    
    if (!$result || !isset($result['response'])) {
        logDebug("Invalid Response Structure:", $result);
        throw new Exception("Invalid response from Ollama API - missing 'response' field");
    }
    
    logDebug("AI Generated Response: " . $result['response']);
    
    return $result['response'];
}

/**
 * Check if the AI response is conversational (not SQL)
 */
function isConversationalResponse($ai_response) {
    $trimmed = trim($ai_response);
    
    // Check if it starts with SELECT (SQL query)
    if (preg_match('/^\s*SELECT\s+/i', $trimmed)) {
        return false;
    }
    
    // Check for conversational patterns
    $conversational_patterns = [
        '/^(hello|hi|hey|good morning|good afternoon|good evening|greetings)/i',
        '/^(I\'m|I am|My name is)/i',
        '/^(Sure|Of course|Certainly|Yes|No)/i',
        '/\?$/', // Ends with question
        '/(can help|here to assist|how may I|what can I do)/i'
    ];
    
    foreach ($conversational_patterns as $pattern) {
        if (preg_match($pattern, $trimmed)) {
            return true;
        }
    }
    
    // If it's multiple sentences without SELECT keyword, likely conversational
    $sentences = preg_split('/[.!?]+/', $trimmed);
    if (count($sentences) > 2 && !stripos($trimmed, 'SELECT')) {
        return true;
    }
    
    return false;
}

/**
 * Extract and validate SQL query from AI response
 */
function extractAndValidateSQL($ai_response) {
    logDebug("=== EXTRACTING SQL ===");
    logDebug("AI Response to Parse:", $ai_response);
    
    // Remove markdown code blocks if present
    $sql = preg_replace('/```sql\s*(.*?)\s*```/s', '$1', $ai_response);
    $sql = preg_replace('/```\s*(.*?)\s*```/s', '$1', $sql);
    
    // Clean up the SQL
    $sql = trim($sql);
    
    // Remove any trailing semicolons
    $sql = rtrim($sql, ';');
    
    logDebug("Cleaned SQL:", $sql);
    
    // Validate that it's a SELECT query
    if (!preg_match('/^\s*SELECT\s+/i', $sql)) {
        logDebug("ERROR: Not a SELECT query");
        throw new Exception("Only SELECT queries are allowed. The AI did not generate a valid SELECT query.");
    }
    
    // Block dangerous keywords
    $dangerous_keywords = ['INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'CREATE', 'TRUNCATE', 'REPLACE', 'GRANT', 'REVOKE'];
    foreach ($dangerous_keywords as $keyword) {
        if (preg_match('/\b' . $keyword . '\b/i', $sql)) {
            logDebug("ERROR: Blocked dangerous keyword: {$keyword}");
            throw new Exception("Blocked dangerous keyword: $keyword");
        }
    }
    
    // Ensure LIMIT is present (add if missing)
    if (!preg_match('/\bLIMIT\s+\d+/i', $sql)) {
        $sql .= ' LIMIT 100';
        logDebug("Added LIMIT clause:", $sql);
    }
    
    logDebug("Final Validated SQL:", $sql);
    
    return $sql;
}

/**
 * Execute SQL query safely
 */
function executeSQLQuery($pdo, $sql) {
    logDebug("=== EXECUTING SQL ===");
    logDebug("Query:", $sql);
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        logDebug("Query executed successfully");
        logDebug("Result count: " . count($results));
        if (count($results) > 0) {
            logDebug("First row sample:", $results[0]);
        }
        
        return $results;
    } catch (PDOException $e) {
        logDebug("=== SQL EXECUTION ERROR ===");
        logDebug("Error Message: " . $e->getMessage());
        logDebug("Error Code: " . $e->getCode());
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
    logDebug("=== NEW AI ASSISTANT REQUEST ===");
    logDebug("User ID: " . $user_id);
    logDebug("Session ID: " . $session_id);
    logDebug("User Query: " . $user_query);
    
    // Build system prompt with schema
    $system_prompt = buildSystemPrompt($pdo);
    logDebug("System Prompt Length: " . strlen($system_prompt) . " characters");
    
    // Query Ollama
    $ai_sql_response = queryOllama($user_query, $system_prompt);
    logDebug("AI Raw Response: " . $ai_sql_response);
    
    // Check if the response is conversational (not SQL)
    if (isConversationalResponse($ai_sql_response)) {
        logDebug("=== CONVERSATIONAL RESPONSE DETECTED ===");
        
        $execution_time = round((microtime(true) - $start_time) * 1000);
        
        // Log the conversational interaction
        logAIInteraction($pdo, $user_id, $session_id, $user_query, $ai_sql_response, null, 0, $execution_time, 'conversational');
        
        // Return conversational response directly
        echo json_encode([
            'success' => true,
            'response' => $ai_sql_response,
            'type' => 'conversational',
            'execution_time_ms' => $execution_time
        ]);
        
        logDebug("=== CONVERSATIONAL REQUEST SUCCESSFUL ===");
        logDebug("Execution time: {$execution_time}ms");
        exit;
    }
    
    // Try to extract and validate SQL
    $sql = extractAndValidateSQL($ai_sql_response);
    
    // Execute SQL
    $results = executeSQLQuery($pdo, $sql);
    
    // Format results
    $formatted_response = formatResults($results, $user_query);
    
    // Calculate execution time
    $execution_time = round((microtime(true) - $start_time) * 1000);
    
    logDebug("=== REQUEST SUCCESSFUL ===");
    logDebug("Execution time: {$execution_time}ms");
    logDebug("Result count: " . count($results));
    logDebug("Final Response to User: " . substr($formatted_response, 0, 200) . "...");
    
    // Log the interaction
    logAIInteraction($pdo, $user_id, $session_id, $user_query, $formatted_response, $sql, count($results), $execution_time, 'success');
    
    // Return success response
    echo json_encode([
        'success' => true,
        'response' => $formatted_response,
        'sql' => $sql, // For debugging/transparency
        'result_count' => count($results),
        'type' => 'data_query',
        'execution_time_ms' => $execution_time
    ]);
    
} catch (Exception $e) {
    // Calculate execution time even on error
    $execution_time = round((microtime(true) - $start_time) * 1000);
    
    // Determine error type
    $error_message = $e->getMessage();
    $status = 'error';
    
    logDebug("=== REQUEST FAILED ===");
    logDebug("Error Type: " . $status);
    logDebug("Error Message: " . $error_message);
    logDebug("Execution time: {$execution_time}ms");
    
    if (strpos($error_message, 'Blocked dangerous keyword') !== false) {
        $status = 'blocked';
    }
    
    // Log the failed interaction
    logAIInteraction($pdo, $user_id, $session_id, $user_query, null, null, 0, $execution_time, $status, $error_message);
    
    // Provide more helpful error messages to users
    $user_friendly_message = "I encountered an error processing your request. Please try rephrasing your question or ask something simpler.";
    
    // Add specific guidance for common errors
    if (strpos($error_message, 'Failed to connect to Ollama') !== false) {
        $user_friendly_message = "The AI service is currently unavailable. Please contact your system administrator.";
    } elseif (strpos($error_message, 'SQL execution error') !== false) {
        $user_friendly_message = "I had trouble running the query. Please try rephrasing your question or simplifying your request.";
    } elseif (strpos($error_message, 'Only SELECT queries are allowed') !== false) {
        $user_friendly_message = "I can only answer questions that retrieve data. I cannot modify or delete information.";
    } elseif (strpos($error_message, 'Blocked dangerous keyword') !== false) {
        $user_friendly_message = "I can only answer questions that retrieve data. I cannot modify or delete information.";
    }
    
    logDebug("User-friendly message: " . $user_friendly_message);
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $error_message,
        'user_message' => $user_friendly_message,
        'type' => 'error'
    ]);
}
