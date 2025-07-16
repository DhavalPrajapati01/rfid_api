<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
header("Content-Type: application/json");

require_once "/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Database/db_connection.php"; // Database connection
require_once "/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Audit/functions.php"; // Logging functions

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['Rfid_scanTag_id']) || empty($data['Rfid_scanTag_id'])) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid or missing RFID scan tag ID"]);
    exit;
}

$rfid_scanTag_id = $data['Rfid_scanTag_id'];

// Insert new scan entry into the database
$stmt = $conn->prepare("INSERT INTO rfid_logs (card_id, timestamp) VALUES (?, NOW())");
$stmt->bind_param("s", $rfid_scanTag_id);

if ($stmt->execute()) {
    // ✅ Log the event in the audit log
    logEvent("RFID scan recorded: $rfid_scanTag_id");

    http_response_code(200);
    echo json_encode(["success" => "Scan recorded successfully"]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Database error"]);
}

$stmt->close();
$conn->close();
?>
