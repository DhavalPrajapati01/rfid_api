<?php
header("Content-Type: application/json");
require_once "/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Database/db_connection.php"; // Database connection

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

// Read input data
$data = json_decode(file_get_contents("php://input"), true);

// Validate input parameters
if (!isset($data['card_id']) || !isset($data['dustbin_id'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required parameters"]);
    exit;
}

$card_id = $data['card_id'];
$dustbin_id = $data['dustbin_id'];

// Check if the card_id is assigned to the given dustbin_id
$query = "SELECT * FROM dustbin_assignments WHERE card_id = ? AND dustbin_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $card_id, $dustbin_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode(["success" => "Permission is granted"]);
} else {
    http_response_code(403);
    echo json_encode(["error" => "Access denied"]);
}

// Close the database connection
$stmt->close();
$conn->close();
?>
