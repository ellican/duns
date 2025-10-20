<?php
header('Content-Type: application/json');
require 'db.php';

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (empty($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'No data received or invalid JSON format.']);
    exit;
}

$conn->begin_transaction();
try {
    $stmt = $conn->prepare(
        "INSERT INTO clients (reg_no, client_name, date, phone_number, service, amount, currency, paid_amount, due_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    
    $importedCount = 0;
    foreach ($data as $row) {
        $reg_no = $row['Reg No'] ?? $row['reg_no'] ?? null;
        $client_name = $row['Client Name'] ?? $row['client_name'] ?? null;
        
        $date_str = $row['Date'] ?? $row['date'] ?? null;
        $date = null;
        if (is_numeric($date_str)) {
             $date = \DateTime::createFromFormat('Y-m-d', gmdate("Y-m-d", ($date_str - 25569) * 86400));
        } else {
            $date = new \DateTime($date_str);
        }
        $date = $date ? $date->format('Y-m-d') : null;

        $phone_number = $row['Phone Number'] ?? $row['phone_number'] ?? null;
        $service = $row['Service'] ?? $row['service'] ?? null;
        $amount = (float)($row['Amount'] ?? $row['amount'] ?? 0);
        $currency = $row['Currency'] ?? $row['currency'] ?? null;
        $paid_amount = (float)($row['Paid Amount'] ?? $row['paid_amount'] ?? 0);
        $due_amount = $amount - $paid_amount;

        if (empty($client_name) || empty($date)) {
            continue;
        }

        $stmt->bind_param(
            "sssssdsdd",
            $reg_no, $client_name, $date, $phone_number, $service,
            $amount, $currency, $paid_amount, $due_amount
        );
        
        $stmt->execute();
        $importedCount++;
    }

    $stmt->close();
    $conn->commit();
    echo json_encode(['success' => true, 'message' => "Successfully imported {$importedCount} records."]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['error' => 'Transaction failed: ' . $e->getMessage()]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>