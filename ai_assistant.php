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
 * Get current database context for AI analysis
 */
function getDatabaseContext($pdo) {
    $context = [];
    
    try {
        // Total clients
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM clients");
        $context['total_clients'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Current month revenue
        $stmt = $pdo->query("SELECT SUM(paid_amount) as total FROM clients WHERE MONTH(date) = MONTH(CURRENT_DATE()) AND YEAR(date) = YEAR(CURRENT_DATE())");
        $context['current_month_revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        
        // Outstanding amounts
        $stmt = $pdo->query("SELECT SUM(due_amount) as total FROM clients WHERE status IN ('NOT PAID', 'PARTIALLY PAID')");
        $context['outstanding_amount'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        
        // Unpaid invoice count
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM clients WHERE status = 'NOT PAID'");
        $context['unpaid_count'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
    } catch (PDOException $e) {
        // If context gathering fails, continue without it
        error_log("Failed to get database context: " . $e->getMessage());
    }
    
    return $context;
}

/**
 * Build system prompt with database schema information
 */
function buildSystemPrompt($pdo) {
    $context = getDatabaseContext($pdo);
    
    $schema_info = "Database Schema:\n";
    
    // Get clients table structure
    $schema_info .= "Table: clients\n";
    $schema_info .= "Columns: id, reg_no, client_name, date, phone_number, service, amount, currency (USD/EUR/RWF), paid_amount, due_amount, status (PAID/PARTIALLY PAID/NOT PAID), created_by_id\n\n";
    
    // Get users table structure (limited info for security)
    $schema_info .= "Table: users\n";
    $schema_info .= "Columns: id, username, first_name, last_name, email\n\n";
    
    // Get client_history table structure
    $schema_info .= "Table: client_history\n";
    $schema_info .= "Columns: id, client_id, user_name, action, details, changed_at\n\n";
    
    // Add current context
    $context_info = "Current Business Context:\n";
    $context_info .= "- Total Clients: " . ($context['total_clients'] ?? 0) . "\n";
    $context_info .= "- This Month Revenue: $" . number_format($context['current_month_revenue'] ?? 0, 2) . "\n";
    $context_info .= "- Outstanding Amounts: $" . number_format($context['outstanding_amount'] ?? 0, 2) . "\n";
    $context_info .= "- Unpaid Invoices: " . ($context['unpaid_count'] ?? 0) . "\n\n";
    
    $system_prompt = "You are a professional financial assistant and accountant for Feza Logistics.

YOUR ROLE:
- Analyze financial data from the database
- Provide insights, trends, and professional explanations
- Act as a knowledgeable accountant who understands business context
- Always ground your responses in actual database data
- Be helpful and conversational, but data-focused

WHEN USER ASKS A QUESTION:
1. First, determine if they need database information
2. If yes, generate a safe SQL SELECT query
3. Return ONLY the SQL query (the system will execute it and give you results)
4. If it's a simple greeting, respond briefly and offer to help with financial data

RESPONSE STRATEGY:
- For greetings like 'hi': Briefly greet and mention you can provide financial insights
- For 'what can you do?': Briefly explain and suggest asking about revenue, invoices, or clients
- For financial questions: Generate ONLY a SQL SELECT query without explanations
- The query results will be automatically analyzed and formatted with context

SQL GENERATION RULES:
1. ONLY generate SELECT queries - NEVER use INSERT, UPDATE, DELETE, DROP, ALTER, or any modifying statements
2. Use proper MySQL/MariaDB syntax
3. Return ONLY the raw SQL query without markdown, explanations, or additional text
4. Always include LIMIT clause (max 100 rows)
5. Filter by currency when relevant
6. Use aggregate functions (SUM, COUNT, AVG) for totals
7. Status values: 'PAID', 'PARTIALLY PAID', 'NOT PAID'
8. Currency values: 'USD', 'EUR', 'RWF'

EXAMPLE RESPONSES:
- User: 'hi' → \"Hello! I'm your financial assistant. I can show you revenue, invoices, and client data. What would you like to know?\"
- User: 'what can you do?' → \"I can help with: revenue totals, unpaid invoices, client information, payment status, and financial analysis. Try asking about this month's revenue!\"
- User: 'total revenue this month' → SELECT SUM(paid_amount) as total, currency FROM clients WHERE MONTH(date) = MONTH(CURRENT_DATE()) AND YEAR(date) = YEAR(CURRENT_DATE()) GROUP BY currency
- User: 'unpaid invoices' → SELECT client_name, reg_no, amount, currency, due_amount, date FROM clients WHERE status = 'NOT PAID' ORDER BY date ASC LIMIT 100
- User: 'top 5 clients' → SELECT client_name, SUM(paid_amount) as total_revenue, currency FROM clients GROUP BY client_name, currency ORDER BY total_revenue DESC LIMIT 5

$context_info
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
 * Minimized to only catch simple greetings and capability questions
 */
function isConversationalResponse($ai_response) {
    $trimmed = trim($ai_response);
    
    // Check if it starts with SELECT (SQL query) - definitely not conversational
    if (preg_match('/^\s*SELECT\s+/i', $trimmed)) {
        return false;
    }
    
    // Only mark as conversational if it's clearly a greeting or capability response
    // We're being very conservative here to minimize false positives
    $conversational_patterns = [
        '/^(hello|hi|hey|greetings)[\s!,.].*I\'m your financial assistant/i',
        '/^(hello|hi|hey|greetings)[\s!,.].*I can help/i',
        '/^I\'m (a|your) (professional )?financial assistant/i',
        '/^I can help (you )?(with|query)/i'
    ];
    
    foreach ($conversational_patterns as $pattern) {
        if (preg_match($pattern, $trimmed)) {
            return true;
        }
    }
    
    // If response is very short (less than 20 chars) and contains greeting words, treat as conversational
    if (strlen($trimmed) < 20 && preg_match('/^(hello|hi|hey|greetings)/i', $trimmed)) {
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
 * Format currency with proper symbol
 */
function formatCurrency($amount, $currency = 'USD') {
    $amount = floatval($amount);
    $formatted = number_format($amount, 2);
    
    switch (strtoupper($currency)) {
        case 'USD':
            return '$' . $formatted;
        case 'EUR':
            return '€' . $formatted;
        case 'RWF':
            return number_format($amount, 0) . ' RWF';
        default:
            return $formatted . ' ' . $currency;
    }
}

/**
 * Analyze results and provide financial insights using AI
 */
function analyzeResultsWithAI($pdo, $results, $user_query, $sql) {
    // Create a summary of the results for AI analysis
    $result_summary = "Query Results Summary:\n";
    $result_summary .= "Total rows returned: " . count($results) . "\n\n";
    
    if (empty($results)) {
        $result_summary .= "No data found.\n";
    } else {
        // For aggregate results
        $first_row = $results[0];
        if (count($results) === 1 && (
            isset($first_row['total']) || 
            isset($first_row['count']) ||
            isset($first_row['SUM']) ||
            isset($first_row['COUNT(*)']) ||
            isset($first_row['AVG'])
        )) {
            $result_summary .= "Aggregate Result:\n";
            foreach ($first_row as $key => $value) {
                $result_summary .= "- {$key}: {$value}\n";
            }
        } else {
            // For detailed results, show summary
            $result_summary .= "Data Fields: " . implode(', ', array_keys($first_row)) . "\n";
            $result_summary .= "First few records:\n";
            
            $preview_count = min(5, count($results));
            for ($i = 0; $i < $preview_count; $i++) {
                $row = $results[$i];
                $result_summary .= "\nRecord " . ($i + 1) . ":\n";
                foreach ($row as $key => $value) {
                    if ($value !== null && strlen($value) > 100) {
                        $value = substr($value, 0, 100) . '...';
                    }
                    $result_summary .= "  {$key}: {$value}\n";
                }
            }
            
            if (count($results) > 5) {
                $result_summary .= "\n... and " . (count($results) - 5) . " more records.\n";
            }
        }
    }
    
    // Create analysis prompt
    $analysis_prompt = "You are a professional financial accountant analyzing data for Feza Logistics.

Original Question: {$user_query}

{$result_summary}

YOUR TASK:
Provide a professional financial analysis of these results. Your response should:
1. Start with a clear summary of the key findings
2. Use proper currency formatting (USD: $X,XXX.XX, EUR: €X,XXX.XX, RWF: X,XXX RWF)
3. Highlight important insights or patterns
4. If there are concerning issues (overdue invoices, large outstanding amounts), mention them
5. Provide context and interpretation (what do these numbers mean?)
6. Be concise but informative (3-5 paragraphs maximum)
7. Use professional but conversational language
8. Include emojis occasionally for visual appeal (📊 💰 ⚠️ 💡)

Format your response with:
- Clear headings where appropriate
- Bullet points for lists
- Bold text for emphasis (**text**)
- Proper spacing for readability

Be helpful and actionable - if relevant, suggest next steps or offer to provide more detailed information.";
    
    try {
        $analysis = queryOllama($analysis_prompt, "");
        return $analysis;
    } catch (Exception $e) {
        // Fallback to basic formatting if AI analysis fails
        logDebug("AI analysis failed, falling back to basic formatting: " . $e->getMessage());
        return formatResultsBasic($results, $user_query);
    }
}

/**
 * Format results for human-readable response (basic formatting as fallback)
 */
function formatResultsBasic($results, $user_query) {
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
        $response = "📊 **Financial Summary**\n\n";
        foreach ($first_row as $key => $value) {
            $formatted_key = str_replace('_', ' ', ucwords(str_replace('_', ' ', $key)));
            
            // Try to format as currency if it's a money field
            if (is_numeric($value) && (stripos($key, 'amount') !== false || stripos($key, 'total') !== false || stripos($key, 'revenue') !== false)) {
                // Check if there's a currency field
                $currency = isset($first_row['currency']) ? $first_row['currency'] : 'USD';
                $value = formatCurrency($value, $currency);
            } elseif (is_numeric($value)) {
                $value = number_format($value, 2);
            }
            
            $response .= "**{$formatted_key}:** {$value}\n";
        }
        
        // Add a helpful insight
        if (isset($first_row['total']) && is_numeric($first_row['total'])) {
            $response .= "\n💡 This represents your current financial position based on the query criteria.";
        }
        
        return $response;
    }
    
    // For regular queries, return formatted table data
    $response = "📋 **Query Results**\n\n";
    $response .= "Found **{$count}** record(s):\n\n";
    
    // Limit display to first 10 rows for readability
    $display_count = min($count, 10);
    
    for ($i = 0; $i < $display_count; $i++) {
        $row = $results[$i];
        $response .= "**" . ($i + 1) . ".";
        
        // Show most important fields first
        if (isset($row['client_name'])) {
            $response .= " " . $row['client_name'];
        }
        $response .= "**\n";
        
        foreach ($row as $key => $value) {
            if ($key === 'client_name') continue; // Already shown
            
            $formatted_key = str_replace('_', ' ', ucwords(str_replace('_', ' ', $key)));
            if ($value === null) {
                $value = 'N/A';
            } elseif (is_numeric($value) && (stripos($key, 'amount') !== false)) {
                $currency = isset($row['currency']) ? $row['currency'] : 'USD';
                $value = formatCurrency($value, $currency);
            }
            $response .= "   • {$formatted_key}: {$value}\n";
        }
        $response .= "\n";
    }
    
    if ($count > 10) {
        $response .= "_Showing first 10 of {$count} results._\n";
    }
    
    // Check if user is asking for a report/document
    if (detectReportRequest($user_query) && $count > 0) {
        $response .= "\n\n💡 **Tip:** To generate a printable PDF report, use the document generation features in the dashboard. Click on any client row to access invoice and receipt generation.\n";
    }
    
    return $response;
}

/**
 * Format results for human-readable response (wrapper function)
 */
function formatResults($results, $user_query) {
    return formatResultsBasic($results, $user_query);
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
    
    // Query Ollama for SQL generation
    $ai_sql_response = queryOllama($user_query, $system_prompt);
    logDebug("AI Raw Response: " . $ai_sql_response);
    
    // Check if the response is conversational (not SQL) - minimized to only simple greetings
    if (isConversationalResponse($ai_sql_response)) {
        logDebug("=== CONVERSATIONAL RESPONSE DETECTED ===");
        
        // For greetings, enhance with a financial overview
        $enhanced_response = $ai_sql_response;
        
        // Try to add a quick financial snapshot for greetings
        if (preg_match('/^(hello|hi|hey|good morning|good afternoon|good evening|greetings)/i', trim($user_query))) {
            try {
                $context = getDatabaseContext($pdo);
                $enhanced_response .= "\n\n📊 **Quick Overview:**\n";
                $enhanced_response .= "• Total Clients: " . ($context['total_clients'] ?? 0) . "\n";
                $enhanced_response .= "• This Month Revenue: " . formatCurrency($context['current_month_revenue'] ?? 0) . "\n";
                $enhanced_response .= "• Unpaid Invoices: " . ($context['unpaid_count'] ?? 0) . "\n";
                $enhanced_response .= "\nWhat would you like to explore?";
            } catch (Exception $e) {
                // If context fails, just use the original response
                logDebug("Failed to add context to greeting: " . $e->getMessage());
            }
        }
        
        $execution_time = round((microtime(true) - $start_time) * 1000);
        
        // Log the conversational interaction
        logAIInteraction($pdo, $user_id, $session_id, $user_query, $enhanced_response, null, 0, $execution_time, 'conversational');
        
        // Return conversational response
        echo json_encode([
            'success' => true,
            'response' => $enhanced_response,
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
    
    logDebug("=== ANALYZING RESULTS WITH AI ===");
    
    // Use AI to analyze and format results with financial insights
    $formatted_response = analyzeResultsWithAI($pdo, $results, $user_query, $sql);
    
    logDebug("=== AI ANALYSIS COMPLETE ===");
    
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
    $user_friendly_message = "I encountered an error processing your request. ";
    
    // Add specific guidance for common errors with financial context
    if (strpos($error_message, 'Failed to connect to Ollama') !== false) {
        $user_friendly_message = "⚠️ The AI service is currently unavailable. Please contact your system administrator or try again later.";
    } elseif (strpos($error_message, 'SQL execution error') !== false) {
        $user_friendly_message = "I had trouble querying the financial data. ";
        $user_friendly_message .= "Please try rephrasing your question. For example:\n";
        $user_friendly_message .= "• 'show me total revenue this month'\n";
        $user_friendly_message .= "• 'list unpaid invoices'\n";
        $user_friendly_message .= "• 'who are my top 5 clients?'";
    } elseif (strpos($error_message, 'Only SELECT queries are allowed') !== false || strpos($error_message, 'Blocked dangerous keyword') !== false) {
        $user_friendly_message = "I can only retrieve and analyze financial data, not modify it. ";
        $user_friendly_message .= "Try asking about:\n";
        $user_friendly_message .= "• Revenue and payments\n";
        $user_friendly_message .= "• Client information\n";
        $user_friendly_message .= "• Outstanding invoices\n";
        $user_friendly_message .= "• Financial summaries";
    } else {
        $user_friendly_message .= "Please try rephrasing your question or ask something simpler.\n\n";
        $user_friendly_message .= "💡 **Suggestions:**\n";
        $user_friendly_message .= "• 'show total revenue for this month'\n";
        $user_friendly_message .= "• 'list unpaid invoices'\n";
        $user_friendly_message .= "• 'show me top clients by revenue'";
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
