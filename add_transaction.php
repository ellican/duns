<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required.']);
    exit;
}

require_once 'db.php';
$data = json_decode(file_get_contents('php://input'), true);

if (empty($data['date']) || empty($data['item']) || !isset($data['amount'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields.']);
    exit;
}

try {
    $sql = "INSERT INTO transactions (user_id, type, date, item, service, payment_method, amount, currency, status) 
            VALUES (:user_id, :type, :date, :item, :service, :payment_method, :amount, :currency, :status)";
    $stmt = $pdo->prepare($sql);
    
    $stmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':type' => $data['type'],
        ':date' => $data['date'],
        ':item' => $data['item'],
        ':service' => $data['service'] ?? null,
        ':payment_method' => $data['payment_method'] ?? null,
        ':amount' => $data['amount'],
        ':currency' => $data['currency'],
        ':status' => $data['status']
    ]);
    
    $data['id'] = $pdo->lastInsertId();
    echo json_encode(['success' => true, 'transaction' => $data]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>