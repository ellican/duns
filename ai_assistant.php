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
 * Hybrid AI workflow:
 * - General Knowledge Mode: Answer questions directly
 * - Database Mode: Convert to SQL, execute, format results
 */

try {
    $start_time = microtime(true);
    
    // Build hybrid system prompt
    $system_prompt = buildHybridSystemPrompt();
    
    // Stage 1: Get AI response (may be direct answer or SQL request)
    $ai_response = queryOllama($user_query, $system_prompt, 0.7, 600);
    
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
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'response' => "I'm having trouble processing that. Could you rephrase your question?"
    ]);
}

/**
 * Build hybrid system prompt
 */
function buildHybridSystemPrompt() {
    return "You are a smart and friendly financial assistant with two capabilities:

**GENERAL KNOWLEDGE MODE:**
1. Answer general knowledge questions conversationally, like a human teacher, mentor, or colleague
2. Explain accounting, finance, economics, or any concept naturally and clearly
3. Engage in small talk, greetings, and help with learning (like ChatGPT)
4. Teach concepts, provide examples, and be helpful
5. Be warm, friendly, and conversational

**DATABASE MODE:**
When the question is about THIS COMPANY'S data (payments, clients, invoices, transactions, revenue, etc.):
1. You MUST strictly use the company's database as your only source of truth
2. DO NOT make up or imagine any data
3. Convert the question into a safe SQL SELECT query
4. Output 'SQL:' followed by the query on a new line
5. The backend will execute it and return results
6. You will then convert those results into a friendly, natural response

**DATABASE SCHEMA:**
- Table: clients
  Columns: id, reg_no, client_name, date, Responsible, TIN, service, amount, currency (USD/EUR/RWF), paid_amount, due_amount, status (PAID/PARTIALLY PAID/NOT PAID)
- Table: users
  Columns: id, username, first_name, last_name, email

**SQL RULES:**
- Only SELECT queries allowed
- Use ORDER BY date DESC LIMIT 1 for 'latest'
- Use SUM() for 'total' or 'how much'
- Use COUNT() for 'how many'
- Use ORDER BY ... DESC LIMIT X for 'top X'
- Always include LIMIT clause

**RESPONSE STYLE:**
- For general questions: Answer freely, naturally, like ChatGPT
- For database questions: Output 'SQL:' then the query
- Be conversational, warm, and helpful
- Use natural phrasing, not robotic language
- Format numbers with currency symbols

**EXAMPLES:**

General Knowledge:
User: What is gross profit?
Assistant: Gross profit is the amount a business earns after deducting the direct costs of producing goods or services. It shows how efficiently a company produces and sells its products. For example, if you sell a product for \$100 and it costs \$60 to make, your gross profit is \$40.

User: Can you explain what invoicing means?
Assistant: Of course! Invoicing is the process of sending a bill to a customer for goods or services provided. An invoice includes details like what was sold, the quantity, price, and when payment is due. It's essential for tracking sales and getting paid on time.

User: Hi!
Assistant: Hello! I'm your financial assistant. How can I help you today? You can ask me about your company's financial data, or I can explain financial concepts if you'd like to learn something new.

Database Questions:
User: Who is the latest person paid?
Assistant: SQL: SELECT client_name FROM clients WHERE status = 'PAID' ORDER BY date DESC LIMIT 1

User: How much revenue did we make last week?
Assistant: SQL: SELECT SUM(paid_amount) as total FROM clients WHERE date >= DATE_SUB(NOW(), INTERVAL 1 WEEK)

User: List top 5 clients by payment
Assistant: SQL: SELECT client_name, SUM(paid_amount) as total FROM clients GROUP BY client_name ORDER BY total DESC LIMIT 5";
}

/**
 * Detect if AI response contains SQL request
 */
function containsSQLRequest($response) {
    return (
        preg_match('/^SQL:/mi', $response) || 
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
 * Query Ollama with custom parameters
 */
function queryOllama($question, $system_prompt, $temperature = 0.7, $num_predict = 600) {
    $payload = [
        'model' => OLLAMA_MODEL,
        'prompt' => $system_prompt . "\n\nUser: " . $question . "\n\nAssistant:",
        'stream' => false,
        'options' => [
            'num_predict' => $num_predict,
            'temperature' => $temperature,
            'top_p' => 0.9
        ]
    ];
    
    return callOllama($payload);
}

/**
 * Generate natural conversational response from SQL results
 */
function generateNaturalResponseFromResults($question, $results) {
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
            'temperature' => 0.7,
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
