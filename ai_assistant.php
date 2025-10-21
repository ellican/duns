<?php
/**
 * Conversational Financial AI Assistant
 * Uses Ollama with tinyllama for natural language database queries
 */

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once 'db.php';

// Configuration
define('OLLAMA_API_URL', 'http://localhost:11434/api/generate');
define('OLLAMA_MODEL', 'tinyllama'); // Faster response times, lightweight model
define('MAX_TOKENS', 400); // Optimized for tinyllama's context window
define('MAX_RETRIES', 3); // Number of retry attempts for transient failures
define('RETRY_DELAY', 2); // Delay in seconds between retries
define('BRANDING_FOOTER', '<p>All rights reserved by Mr. Joseph</p>');
define('FALLBACK_MESSAGE', 'Sorry! Mr. Joseph now told me to not answer this question as not related to our company.');

$request_body = file_get_contents('php://input');
$data = json_decode($request_body, true);

if (!$data || !isset($data['query'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Query required']);
    exit;
}

$user_query = trim($data['query']);
$user_id = $_SESSION['user_id'];

/**
 * Hybrid AI workflow:
 * - General Knowledge Mode: Answer questions directly
 * - Database Mode: Convert to SQL, execute, format results
 */

try {
    $start_time = microtime(true);
    
    // Build hybrid system prompt
    $system_prompt = buildHybridSystemPrompt();
    
    // Stage 1: Get AI response (may be direct answer or SQL request)
    $ai_response = queryOllama($user_query, $system_prompt, 0.5, 300);
    
    // Check if AI wants to query database
    if (containsSQLRequest($ai_response)) {
        // Extract SQL
        $sql = extractSQL($ai_response);
        
        if (!$sql) {
            logError("SQL extraction failed", $user_query);
            throw new Exception("Could not extract SQL from AI response");
        }
        
        // Validate and execute
        $sql = validateAndCleanSQL($sql);
        $results = executeSafeSQL($pdo, $sql);
        
        // Stage 2: Send results back to AI for natural formatting
        $final_response = generateNaturalResponseFromResults($user_query, $results);
        
        // Add branding footer
        $final_response_with_footer = $final_response . "\n\n" . BRANDING_FOOTER;
        
        // Log
        logInteraction($pdo, $user_id, $user_query, $sql, $final_response_with_footer, 'database');
        
        echo json_encode([
            'success' => true,
            'response' => $final_response_with_footer,
            'sql' => $sql,
            'type' => 'database'
        ]);
    } else {
        // Direct general knowledge response
        // Add branding footer
        $ai_response_with_footer = $ai_response . "\n\n" . BRANDING_FOOTER;
        
        logInteraction($pdo, $user_id, $user_query, null, $ai_response_with_footer, 'general');
        
        echo json_encode([
            'success' => true,
            'response' => $ai_response_with_footer,
            'type' => 'general'
        ]);
    }
    
} catch (Exception $e) {
    // Log the error
    logError($e->getMessage(), $user_query, $e->getTrace());
    
    // Use the predefined fallback message for Ollama connection issues
    $fallback_message = FALLBACK_MESSAGE;
    
    // Add branding footer to fallback message
    $fallback_with_footer = $fallback_message . "\n\n" . BRANDING_FOOTER;
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'response' => $fallback_with_footer
    ]);
}

/**
 * Build hybrid system prompt (optimized for tinyllama)
 */
function buildHybridSystemPrompt() {
    return "You are a helpful financial assistant with two modes:

**MODE 1 - GENERAL KNOWLEDGE:**
Answer general questions about finance, accounting, or concepts. Be conversational and helpful.

**MODE 2 - DATABASE:**
For company data questions (payments, clients, invoices), output 'SQL:' followed by a SELECT query.
Use the database, never make up data.

**SCHEMA:**
clients: id, reg_no, client_name, date, Responsible, TIN, service, amount, currency, paid_amount, due_amount, status
users: id, username, first_name, last_name, email

**SQL RULES:**
- Only SELECT
- Use ORDER BY date DESC LIMIT 1 for 'latest'
- Use SUM() for totals
- Use COUNT() for counts
- Always include LIMIT

**EXAMPLES:**

General:
Q: What is gross profit?
A: Gross profit is revenue minus direct costs. If you sell for \$100 and costs are \$60, gross profit is \$40.

Q: Hi!
A: Hello! I can help with your financial data or explain concepts. What would you like to know?

Database:
Q: Who is the latest person paid?
A: SQL: SELECT client_name FROM clients WHERE status = 'PAID' ORDER BY date DESC LIMIT 1

Q: How much revenue last week?
A: SQL: SELECT SUM(paid_amount) as total FROM clients WHERE date >= DATE_SUB(NOW(), INTERVAL 1 WEEK)

Q: Top 5 clients
A: SQL: SELECT client_name, SUM(paid_amount) as total FROM clients GROUP BY client_name ORDER BY total DESC LIMIT 5";
}

/**
 * Detect if AI response contains SQL request
 */
function containsSQLRequest($response) {
    return (
        preg_match('/\bSQL:/i', $response) || 
        preg_match('/^\s*SELECT\s+/i', $response)
    );
}

/**
 * Extract SQL from AI response
 */
function extractSQL($response) {
    // Look for "SQL:" marker
    if (preg_match('/SQL:\s*(.+?)(?:\n|$)/is', $response, $matches)) {
        return trim($matches[1]);
    }
    
    // Or just extract SELECT statement
    if (preg_match('/^\s*(SELECT\s+.+?)(?:;|\n|$)/is', $response, $matches)) {
        return trim($matches[1]);
    }
    
    return null;
}

/**
 * Validate and clean SQL
 */
function validateAndCleanSQL($sql) {
    // Extract and clean SQL
    $sql = trim($sql);
    $sql = preg_replace('/```sql\s*/i', '', $sql);
    $sql = preg_replace('/```\s*/', '', $sql);
    $sql = rtrim($sql, ';');
    
    // Validate it's SELECT only
    if (!preg_match('/^\s*SELECT\s+/i', $sql)) {
        throw new Exception("Invalid query generated");
    }
    
    // Block dangerous keywords
    $dangerous = ['INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'CREATE', 'TRUNCATE'];
    foreach ($dangerous as $keyword) {
        if (preg_match('/\b' . $keyword . '\b/i', $sql)) {
            throw new Exception("Cannot modify database");
        }
    }
    
    // Add LIMIT if missing
    if (!preg_match('/LIMIT\s+\d+/i', $sql)) {
        $sql .= ' LIMIT 100';
    }
    
    return $sql;
}

/**
 * Query Ollama with custom parameters (optimized for tinyllama)
 */
function queryOllama($question, $system_prompt, $temperature = 0.5, $num_predict = 300) {
    $payload = [
        'model' => OLLAMA_MODEL,
        'prompt' => $system_prompt . "\n\nUser: " . $question . "\n\nAssistant:",
        'stream' => false,
        'options' => [
            'num_predict' => $num_predict,
            'temperature' => $temperature,
            'top_p' => 0.9,
            'top_k' => 40
        ]
    ];
    
    return callOllama($payload);
}

/**
 * Generate natural conversational response from SQL results (optimized for tinyllama)
 */
function generateNaturalResponseFromResults($question, $results) {
    if (empty($results)) {
        logError("Empty database results", $question);
        return "I couldn't find any data for that. Please try rephrasing your question.";
    }
    
    $system_prompt = "Convert SQL results to natural language. Be concise and friendly.

EXAMPLES:
Q: Latest person paid?
Data: [{\"client_name\": \"John Doe\"}]
A: The latest person paid is John Doe.

Q: Total last week?
Data: [{\"total\": \"3400000\"}]
A: Last week's total: 3,400,000 RWF.

Q: Top clients
Data: [{\"client_name\": \"John\"}, {\"client_name\": \"Mary\"}]
A: Top clients: John, Mary.

Convert:";
    
    $results_json = json_encode($results, JSON_PRETTY_PRINT);
    
    $payload = [
        'model' => OLLAMA_MODEL,
        'prompt' => $system_prompt . "\nQ: " . $question . "\nData: " . $results_json . "\nA:",
        'stream' => false,
        'options' => [
            'num_predict' => 150,
            'temperature' => 0.5,
            'top_p' => 0.9
        ]
    ];
    
    try {
        $response = callOllama($payload);
        return trim($response);
    } catch (Exception $e) {
        logError("Failed to format database results", $e->getMessage());
        // Return a simple formatted version if AI formatting fails
        return formatResultsSimply($results);
    }
}

/**
 * Simple fallback formatter for database results
 */
function formatResultsSimply($results) {
    if (empty($results)) {
        return "No data found.";
    }
    
    $output = "Here's what I found:\n\n";
    foreach ($results as $index => $row) {
        $output .= ($index + 1) . ". ";
        $values = [];
        foreach ($row as $key => $value) {
            $values[] = ucfirst(str_replace('_', ' ', $key)) . ": " . $value;
        }
        $output .= implode(", ", $values) . "\n";
    }
    
    return trim($output);
}

/**
 * Execute SQL safely
 */
function executeSafeSQL($pdo, $sql) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        throw new Exception("Database query failed");
    }
}

/**
 * Call Ollama API with retry mechanism
 */
function callOllama($payload) {
    $max_retries = MAX_RETRIES;
    $retry_delay = RETRY_DELAY;
    $last_error = null;
    
    for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
        try {
            $ch = curl_init(OLLAMA_API_URL);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);
            
            // Log attempt
            if ($attempt > 1) {
                logError("Retry attempt $attempt for Ollama", json_encode($payload));
            }
            
            // Check for connection errors
            if ($response === false) {
                $last_error = "Connection failed: " . $curl_error;
                logError("Ollama connection error on attempt $attempt", $last_error);
                
                // If not last attempt, wait and retry
                if ($attempt < $max_retries) {
                    sleep($retry_delay);
                    continue;
                }
                throw new Exception("AI service unavailable after $max_retries attempts");
            }
            
            // Check HTTP status code
            if ($http_code !== 200) {
                $last_error = "HTTP $http_code";
                logError("Ollama HTTP error on attempt $attempt", $last_error);
                
                // If not last attempt, wait and retry
                if ($attempt < $max_retries) {
                    sleep($retry_delay);
                    continue;
                }
                throw new Exception("AI service unavailable after $max_retries attempts");
            }
            
            // Parse response
            $result = json_decode($response, true);
            if (!$result || !isset($result['response'])) {
                $last_error = "Invalid response format";
                logError("Ollama invalid response on attempt $attempt", $response);
                
                // If not last attempt, wait and retry
                if ($attempt < $max_retries) {
                    sleep($retry_delay);
                    continue;
                }
                throw new Exception("Invalid AI response after $max_retries attempts");
            }
            
            // Check for empty response
            if (empty(trim($result['response']))) {
                $last_error = "Empty response";
                logError("Ollama empty response on attempt $attempt", "");
                
                // If not last attempt, wait and retry
                if ($attempt < $max_retries) {
                    sleep($retry_delay);
                    continue;
                }
                throw new Exception("Empty AI response after $max_retries attempts");
            }
            
            // Success! Log if we had to retry
            if ($attempt > 1) {
                logError("Ollama succeeded on attempt $attempt", "Success after retries");
            }
            
            return $result['response'];
            
        } catch (Exception $e) {
            $last_error = $e->getMessage();
            logError("Ollama exception on attempt $attempt", $last_error);
            
            // If not last attempt, wait and retry
            if ($attempt < $max_retries) {
                sleep($retry_delay);
                continue;
            }
            
            // Last attempt failed, throw the exception
            throw $e;
        }
    }
    
    // Should never reach here, but just in case
    throw new Exception("AI service unavailable after $max_retries attempts");
}

/**
 * Log interaction for audit
 */
function logInteraction($pdo, $user_id, $query, $sql, $response, $type = 'database') {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO ai_chat_logs 
            (user_id, session_id, user_query, sql_executed, ai_response, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $user_id,
            session_id(),
            $query,
            $sql,
            $response
        ]);
    } catch (PDOException $e) {
        // Fail silently - log table may not exist yet
    }
}

/**
 * Log errors for debugging
 */
function logError($error_type, $context, $trace = null) {
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] AI Assistant Error: $error_type";
    
    if ($context) {
        $log_message .= " | Context: " . (is_string($context) ? $context : json_encode($context));
    }
    
    if ($trace) {
        $log_message .= " | Trace: " . json_encode($trace);
    }
    
    // Log to PHP error log
    error_log($log_message);
    
    // Also log to console for debugging (will be visible in browser console via PHP)
    echo "<script>console.error(" . json_encode($log_message) . ");</script>";
}
?>
