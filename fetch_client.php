<?php
// Set the correct header to send JSON data
header('Content-Type: application/json');
require 'db.php'; // Ensure this points to your database connection file

// Enable mysqli error reporting to catch any issues
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Get the ID from the URL, ensuring it's an integer
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($id <= 0) {
        // If no valid ID is provided, return an error
        http_response_code(400); // Bad Request
        echo json_encode(['error' => 'Invalid Client ID provided.']);
        exit;
    }

    // Prepare a statement to securely fetch the client
    $stmt = $conn->prepare("SELECT * FROM clients WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $client = $result->fetch_assoc(); // Fetch the single row of data

    if ($client) {
        // If a client was found, send the data back
        echo json_encode($client);
    } else {
        // If no client was found for that ID, return an error
        http_response_code(404); // Not Found
        echo json_encode(['error' => 'Client not found.']);
    }

} catch (mysqli_sql_exception $e) {
    // If there's any database error, report it
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Database Error: ' . $e->getMessage()]);
} finally {
    // Clean up the connection
    if (isset($stmt)) $stmt->close();
    if (isset($conn)) $conn->close();
}
?>