<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json');

if (!file_exists('db.php')) {
    http_response_code(500);
    echo json_encode(['error' => 'Configuration error: db.php not found.']);
    exit;
}
require_once 'db.php'; // CORRECTED FILENAME
if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['error' => 'Configuration error: PDO connection object not found in db.php.']);
    exit;
}

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied. Please log in.']);
    exit;
}

if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid or missing client ID.']);
    exit;
}

$clientId = (int)$_GET['id'];

try {
    $stmt = $pdo->prepare(
        "SELECT user_name, action, details, 
                DATE_FORMAT(changed_at, '%Y-%m-%d %H:%i:%s') AS changed_at 
         FROM client_history 
         WHERE client_id = :client_id 
         ORDER BY changed_at DESC"
    );
    $stmt->bindParam(':client_id', $clientId, PDO::PARAM_INT);
    $stmt->execute();
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($history);

} catch (PDOException $e) {
    http_response_code(500);
    $errorInfo = $e->errorInfo;
    echo json_encode([
        'error' => 'Database query failed.',
        'details' => $e->getMessage(),
        'sql_error_code' => isset($errorInfo[1]) ? $errorInfo[1] : null,
        'sql_error_message' => isset($errorInfo[2]) ? $errorInfo[2] : null,
    ]);
}
?>