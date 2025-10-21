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
            throw new Exception("Could not extract SQL from AI response");
        }
        
        // Validate and execute
        $sql = validateAndCleanSQL($sql);
        $results = executeSafeSQL($pdo, $sql);
        
        // Stage 2: Send results back to AI for natural formatting
        $final_response = generateNaturalResponseFromResults($user_query, $results);
        
        // Log
        logInteraction($pdo, $user_id, $user_query, $sql, $final_response, 'database');
        
        echo json_encode([
            'success' => true,
            'response' => $final_response,
            'sql' => $sql,
            'type' => 'database'
        ]);
    } else {
        // Direct general knowledge response
        logInteraction($pdo, $user_id, $user_query, null, $ai_response, 'general');
        
        echo json_encode([
            'success' => true,
            'response' => $ai_response,
            'type' => 'general'
        ]);
    }
    
} catch (Exception $e) {
    // Provide helpful fallback responses based on error type
    $fallback_message = "I'm having trouble processing that. Could you rephrase your question?";
    
    if (strpos($e->getMessage(), 'unavailable') !== false) {
        $fallback_message = "The AI service is temporarily unavailable. Please try again in a moment.";
    } elseif (strpos($e->getMessage(), 'Database') !== false) {
        $fallback_message = "I couldn't access the data. Please check your question and try again.";
    } elseif (strpos($e->getMessage(), 'SQL') !== false) {
        $fallback_message = "I had trouble understanding your request. Try asking in a different way.";
    }
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'response' => $fallback_message
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
    
    $response = callOllama($payload);
    return trim($response);
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
 * Call Ollama API
 */
function callOllama($payload) {
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
        throw new Exception("AI service unavailable");
    }
    
    $result = json_decode($response, true);
    if (!$result || !isset($result['response'])) {
        throw new Exception("Invalid AI response");
    }
    
    return $result['response'];
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
?>
