<?php
// --- Enhanced Error Reporting for Debugging ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access Denied']);
    exit;
}

require_once 'db.php';
if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Configuration error: PDO connection object not found in db.php.']);
    exit;
}

// Get POST data using modern, safe methods
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$newData = [
    'reg_no' => isset($_POST['reg_no']) ? htmlspecialchars(trim($_POST['reg_no']), ENT_QUOTES, 'UTF-8') : '',
    'client_name' => isset($_POST['client_name']) ? htmlspecialchars(trim($_POST['client_name']), ENT_QUOTES, 'UTF-8') : '',
    'date' => isset($_POST['date']) ? trim($_POST['date']) : '',
    'Responsible' => isset($_POST['Responsible']) ? htmlspecialchars(trim($_POST['Responsible']), ENT_QUOTES, 'UTF-8') : '',
    'TIN' => isset($_POST['TIN']) ? htmlspecialchars(trim($_POST['TIN']), ENT_QUOTES, 'UTF-8') : '',
    'service' => isset($_POST['service']) ? htmlspecialchars(trim($_POST['service']), ENT_QUOTES, 'UTF-8') : '',
    'currency' => isset($_POST['currency']) ? htmlspecialchars(trim($_POST['currency']), ENT_QUOTES, 'UTF-8') : '',
    'amount' => filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT),
    'paid_amount' => filter_input(INPUT_POST, 'paid_amount', FILTER_VALIDATE_FLOAT, ['options' => ['default' => 0]])
];

if (!$id || empty($newData['client_name']) || $newData['amount'] === false || $newData['paid_amount'] === false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid input data. Client ID, Name, Amount, and Paid Amount are required.']);
    exit;
}

// Validate TIN if provided - must be numeric and max 9 digits
if (!empty($newData['TIN']) && (!ctype_digit($newData['TIN']) || strlen($newData['TIN']) > 9)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'TIN must be numeric and up to 9 digits.']);
    exit;
}

// Recalculate due amount and status
$newData['due_amount'] = $newData['amount'] - $newData['paid_amount'];
$newData['status'] = 'NOT PAID';
if ($newData['amount'] > 0 && $newData['paid_amount'] >= $newData['amount']) {
    $newData['status'] = 'PAID';
    $newData['due_amount'] = 0;
} elseif ($newData['paid_amount'] > 0) {
    $newData['status'] = 'PARTIALLY PAID';
}


try {
    $pdo->beginTransaction();

    // Get the old data for comparison in the history log
    $oldStmt = $pdo->prepare("SELECT * FROM clients WHERE id = :id");
    $oldStmt->execute([':id' => $id]);
    $oldData = $oldStmt->fetch(PDO::FETCH_ASSOC);
    if (!$oldData) {
        throw new Exception("Client with ID $id not found.");
    }

    $sql = "UPDATE clients SET reg_no=:reg_no, client_name=:client_name, date=:date, Responsible=:Responsible, TIN=:TIN, service=:service, amount=:amount, currency=:currency, paid_amount=:paid_amount, due_amount=:due_amount, status=:status WHERE id=:id";
    $stmt = $pdo->prepare($sql);
    $updateData = array_merge($newData, ['id' => $id]);
    // Convert empty TIN to null
    $updateData['TIN'] = !empty($updateData['TIN']) ? $updateData['TIN'] : null;
    $stmt->execute($updateData);

    // Generate a detailed history log
    $changes = [];
    $editableFields = ['reg_no', 'client_name', 'date', 'Responsible', 'TIN', 'service', 'amount', 'currency', 'paid_amount'];
    foreach ($editableFields as $key) {
        // Use string casting to handle type differences (e.g., '50.00' vs 50)
        if (isset($oldData[$key]) && isset($newData[$key]) && (string)$oldData[$key] !== (string)$newData[$key]) {
            $changes[] = "Changed '$key' from '{$oldData[$key]}' to '{$newData[$key]}'";
        }
    }
    if ($oldData['status'] !== $newData['status']) {
        $changes[] = "Status changed from '{$oldData['status']}' to '{$newData['status']}'";
    }
    $details = empty($changes) ? 'Record was resaved with no changes.' : implode('; ', $changes);

    $historySql = "INSERT INTO client_history (client_id, user_name, action, details) VALUES (:client_id, :user_name, :action, :details)";
    $historyStmt = $pdo->prepare($historySql);
    $historyStmt->execute([
        ':client_id' => $id,
        ':user_name' => $_SESSION['username'] ?? 'System',
        ':action' => 'UPDATE',
        ':details' => $details
    ]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Client updated successfully!']);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    $errorInfo = ($e instanceof PDOException) ? $e->errorInfo : null;
    echo json_encode([
        'success' => false,
        'error' => 'Database error during update.',
        'details' => $e->getMessage(),
        'sql_error_code' => $errorInfo[1] ?? null,
        'sql_error_message' => $errorInfo[2] ?? null,
    ]);
}
?>