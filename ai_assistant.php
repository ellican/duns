<?php
/**
 * Conversational Financial AI Assistant
 * Uses a capable, fast, and stable generative AI model to handle both
 * generic and company-related questions without timing out.
 */

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once 'db.php';

// --- Configuration ---
// Prioritize using the latest available and stable local or external AI model.
define('OLLAMA_API_URL', 'http://localhost:11434/api/generate');
define('OLLAMA_MODEL', 'tinyllama'); // Use a capable, fast, and stable model. 'tinyllama' is great for performance.
define('MAX_TOKENS', 400); 

// Auto-retry mechanism if the first attempt fails.
define('MAX_RETRIES', 3);
define('RETRY_DELAY', 2); // seconds

// --- Custom AI Behavior ---
// Footer to be included with every AI response.
define('BRANDING_FOOTER', 'All rights reserved by Mr. Joseph');

// Exact response for non-permitted or unrelated questions.
define('FALLBACK_MESSAGE', 'Sorry! Mr. Joseph now told me to not answer this question as not related to our company.');

$request_body = file_get_contents('php://input');
$data = json_decode($request_body, true);

if (!$data || !isset($data['query'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Query is required.']);
    exit;
}

$user_query = trim($data['query']);
$user_id = $_SESSION['user_id'];

/**
 * Hybrid AI workflow to process open-ended, contextual, and domain-specific queries.
 * 1. General Knowledge Mode: Answers conceptual questions directly.
 * 2. Database Mode: Converts questions to SQL, executes them, and formats the results into natural language.
 */
try {
    // Build the system prompt for the AI.
    $system_prompt = buildHybridSystemPrompt();
    
    // Stage 1: Get the initial AI response. This might be a direct answer or a SQL query.
    $ai_response = queryOllamaWithRetry($user_query, $system_prompt);
    
    // Stage 2: Determine if the AI's response is a database query or a general answer.
    if (isDatabaseQueryRequest($ai_response)) {
        // The AI wants to query the database.
        $sql = extractSQL($ai_response);
        
        if (!$sql) {
            // If SQL extraction fails, use the fallback response.
            throw new Exception("SQL extraction failed.");
        }
        
        // Validate and clean the SQL
        $sql = validateAndCleanSQL($sql);
        
        // Execute the query and get results.
        $results = executeSafeSQL($pdo, $sql);
        
        // Stage 3: Send the results back to the AI to generate a human-friendly response.
        $final_response = generateNaturalResponseFromResults($user_query, $results);
        
        // Log the interaction for auditing.
        logInteraction($pdo, $user_id, $user_query, $sql, $final_response, 'database');
        
        // Send the final, formatted response to the user.
        echo json_encode([
            'success' => true,
            'response' => formatFinalResponse($final_response),
            'type' => 'database'
        ]);

    } else {
        // The AI provided a direct general knowledge answer.
        // Log the interaction.
        logInteraction($pdo, $user_id, $user_query, null, $ai_response, 'general');
        
        // Send the formatted response.
        echo json_encode([
            'success' => true,
            'response' => formatFinalResponse($ai_response),
            'type' => 'general'
        ]);
    }
    
} catch (Exception $e) {
    // Fallback handling for any failure.
    logError($e->getMessage(), $user_query);
    echo json_encode([
        'success' => true, // Return success to prevent "AI not available" messages.
        'response' => formatFinalResponse(FALLBACK_MESSAGE)
    ]);
}

/**
 * Queries the Ollama model with an auto-retry mechanism.
 */
function queryOllamaWithRetry($query, $system_prompt) {
    for ($attempt = 1; $attempt <= MAX_RETRIES; $attempt++) {
        try {
            $response = queryOllama($query, $system_prompt);
            if ($response) {
                return $response;
            }
        } catch (Exception $e) {
            if ($attempt === MAX_RETRIES) {
                throw $e; // Rethrow the final exception.
            }
            sleep(RETRY_DELAY);
        }
    }
    throw new Exception("AI model failed to respond after multiple attempts.");
}

/**
 * Formats the final response to include the footer and ensures clean, structured text.
 */
function formatFinalResponse($text) {
    // Return clean structured text, not code blocks, and add the footer.
    $cleaned_text = trim(strip_tags($text)); // Example of cleaning.
    return $cleaned_text . "\n\n" . BRANDING_FOOTER;
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
function isDatabaseQueryRequest($response) {
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
