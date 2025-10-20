<?php
session_start();
header('Content-Type: application/json');

// --- Authenticate & Authorize ---
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Authentication required.']);
    exit;
}

require_once 'db.php';

// --- Get and Decode Input ---
$data = json_decode(file_get_contents('php://input'), true);

if (empty($data) || !is_array($data)) {
    echo json_encode(['success' => false, 'error' => 'No data received or invalid format.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['username'];
$imported_count = 0;
$error_list = [];

$pdo->beginTransaction();

try {
    $stmt = $pdo->prepare(
        "INSERT INTO clients (reg_no, client_name, date, phone_number, service, amount, currency, paid_amount, due_amount, status, created_by_id, created_by_name) 
         VALUES (:reg_no, :client_name, :date, :phone_number, :service, :amount, :currency, :paid_amount, :due_amount, :status, :created_by_id, :created_by_name)"
    );

    foreach ($data as $index => $row) {
        // --- Server-Side Validation ---
        $reg_no = $row['reg_no'] ?? 'N/A';
        $client_name = $row['client_name'] ?? null;
        if (empty($client_name)) {
            $error_list[] = "Row " . ($index + 1) . ": Client Name is missing.";
            continue;
        }

        $amount = filter_var($row['amount'] ?? 0, FILTER_VALIDATE_FLOAT);
        $paid_amount = filter_var($row['paid_amount'] ?? 0, FILTER_VALIDATE_FLOAT);
        $due_amount = $amount - $paid_amount;

        $status = 'NOT PAID';
        if ($paid_amount >= $amount && $amount > 0) {
            $status = 'PAID';
        } elseif ($paid_amount > 0) {
            $status = 'PARTIALLY PAID';
        }

        $stmt->execute([
            ':reg_no' => $reg_no,
            ':client_name' => $client_name,
            ':date' => $row['date'] ?? date('Y-m-d'),
            ':phone_number' => $row['phone_number'] ?? '',
            ':service' => $row['service'] ?? 'Not specified',
            ':amount' => $amount,
            ':currency' => $row['currency'] ?? 'USD',
            ':paid_amount' => $paid_amount,
            ':due_amount' => $due_amount,
            ':status' => $status,
            ':created_by_id' => $user_id,
            ':created_by_name' => $user_name
        ]);
        $imported_count++;
    }

    if (!empty($error_list)) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Import failed due to validation errors.', 'details' => $error_list]);
    } else {
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => "Successfully imported {$imported_count} records."]);
    }

} catch (Exception $e) {
    $pdo->rollBack();
    // In production, log the error instead of echoing it
    // error_log($e->getMessage());
    echo json_encode(['success' => false, 'error' => 'A database error occurred during import.']);
}
?>