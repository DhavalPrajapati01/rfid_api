<?php
header("Content-Type: application/json");
require_once "/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Database/db_connection.php"; // Database connection

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

// Get JSON input
$data = json_decode(file_get_contents("php://input"), true);

// Validate input parameters
if (!isset($data['event'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required parameter: event"]);
    exit;
}

$event = $data['event'];

// Insert event into audit_logs table
$stmt = $conn->prepare("INSERT INTO audit_logs (event) VALUES (?)");
$stmt->bind_param("s", $event);

if ($stmt->execute()) {
    echo json_encode(["success" => "Event logged successfully"]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Failed to log event"]);
}

// Close connection
$stmt->close();
$conn->close();
?>
