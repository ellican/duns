<?php
/**
 * Conversational Financial AI Assistant
 * Uses Ollama with qwen2.5:7b-instruct for natural language database queries
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
define('OLLAMA_MODEL', 'qwen2.5:7b-instruct'); // Fallback: llama3.1:8b-instruct or tinyllama
define('MAX_TOKENS', 800);

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
 * Two-stage AI workflow:
 * Stage 1: Convert question to SQL
 * Stage 2: Convert SQL results to natural language
 */

try {
    // Stage 1: Generate SQL from natural language
    $sql_query = generateSQLFromQuestion($pdo, $user_query);
    
    // Execute SQL safely
    $results = executeSafeSQL($pdo, $sql_query);
    
    // Stage 2: Convert results to conversational response
    $natural_response = generateNaturalResponse($user_query, $sql_query, $results);
    
    // Log the interaction
    logInteraction($pdo, $user_id, $user_query, $sql_query, $natural_response);
    
    echo json_encode([
        'success' => true,
        'response' => $natural_response,
        'sql' => $sql_query // For debugging only
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'response' => "I couldn't process that request. Could you rephrase it?"
    ]);
}

/**
 * STAGE 1: Generate SQL from natural language question
 */
function generateSQLFromQuestion($pdo, $question) {
    $system_prompt = buildSQLGenerationPrompt($pdo);
    
    $payload = [
        'model' => OLLAMA_MODEL,
        'prompt' => $system_prompt . "\n\nUser Question: " . $question . "\n\nSQL Query:",
        'stream' => false,
        'options' => [
            'num_predict' => 200,
            'temperature' => 0.1,
            'stop' => [';', '\n\n']
        ]
    ];
    
    $response = callOllama($payload);
    
    // Extract and clean SQL
    $sql = trim($response);
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
 * Build system prompt for SQL generation
 */
function buildSQLGenerationPrompt($pdo) {
    return "You are a SQL expert for a financial management system.

DATABASE SCHEMA:
- Table: clients
  Columns: id, reg_no, client_name, date, Responsible, TIN, service, amount, currency (USD/EUR/RWF), paid_amount, due_amount, status (PAID/PARTIALLY PAID/NOT PAID)

- Table: users
  Columns: id, username, first_name, last_name, email

TASK: Convert the user's natural language question into a single, safe SQL SELECT query.

RULES:
1. Return ONLY the SQL query, nothing else
2. Only SELECT queries allowed
3. Use proper MySQL syntax
4. Always include a reasonable LIMIT
5. For 'latest', use ORDER BY date DESC LIMIT 1
6. For 'total', use SUM()
7. For 'how many', use COUNT()
8. For 'top X', use ORDER BY ... DESC LIMIT X

EXAMPLES:
Q: Who is the latest person paid?
SQL: SELECT client_name FROM clients WHERE status = 'PAID' ORDER BY date DESC LIMIT 1

Q: How much did we pay last week?
SQL: SELECT SUM(paid_amount) as total FROM clients WHERE date >= DATE_SUB(NOW(), INTERVAL 1 WEEK)

Q: List top 5 clients
SQL: SELECT client_name, SUM(paid_amount) as total FROM clients GROUP BY client_name ORDER BY total DESC LIMIT 5";
}

/**
 * STAGE 2: Generate natural conversational response from SQL results
 */
function generateNaturalResponse($question, $sql, $results) {
    if (empty($results)) {
        return "I couldn't find any data for that. Could you try asking something else?";
    }
    
    $system_prompt = "You are a friendly, professional financial assistant.

Your task: Convert the SQL query results into a natural, conversational response.

RESPONSE STYLE:
- Sound like a helpful human, not a robot
- Be concise but warm
- Use natural phrasing
- Include numbers with proper formatting
- Don't mention SQL or technical details

EXAMPLES:
Q: Who is the latest person paid?
Results: [{\"client_name\": \"John Doe\"}]
Response: The latest person who was paid is John Doe.

Q: How much did we pay last week?
Results: [{\"total\": \"3400000\"}]
Response: Last week, we paid a total of 3,400,000 RWF.

Q: List top 5 clients
Results: [{\"client_name\": \"John\"}, {\"client_name\": \"Mary\"}, ...]
Response: Here are our top 5 clients: John, Mary, Felix, Kane, and Alice.

Now convert these results:";
    
    $results_json = json_encode($results, JSON_PRETTY_PRINT);
    
    $payload = [
        'model' => OLLAMA_MODEL,
        'prompt' => $system_prompt . "\n\nUser Question: " . $question . "\n\nSQL Results:\n" . $results_json . "\n\nNatural Response:",
        'stream' => false,
        'options' => [
            'num_predict' => MAX_TOKENS,
            'temperature' => 0.7
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
function logInteraction($pdo, $user_id, $query, $sql, $response) {
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
        // Fail silently
    }
}
?>
