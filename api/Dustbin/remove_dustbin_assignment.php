<?php
header("Content-Type: application/json");
require_once "/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Database/db_connection.php"; // Database connection
require_once "/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Audit/functions.php"; // Logging functions
require_once "/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Auth/vendor/autoload.php"; // JWT verification

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$secret_key = "1229c37e5d8ec1b046b3c89d87cae5a3e2536aaacc6b81c254fc12c7dd5ae61a"; // JWT secret key

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

// Get Authorization Header
$headers = getallheaders();
if (!isset($headers['Authorization'])) {
    http_response_code(401);
    echo json_encode(["error" => "Authorization token required"]);
    exit;
}

$authHeader = $headers['Authorization'];
$token = str_replace("Bearer ", "", $authHeader);

// Verify JWT Token
try {
    $decoded = JWT::decode($token, new Key($secret_key, 'HS256'));
    $email = $decoded->email;
    $role = $decoded->role;

    // Restrict 'employees' from removing dustbin assignments
    if ($role === 'employee') {
        http_response_code(403);
        echo json_encode(["error" => "Access Denied. Employees are not allowed to remove dustbin assignments."]);
        exit;
    }
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(["error" => "Invalid token"]);
    exit;
}

// Get JSON input
$data = json_decode(file_get_contents("php://input"), true);

// Validate input parameters
if (!isset($data['card_id']) || !isset($data['dustbin_id'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required parameters"]);
    exit;
}

$card_id = $data['card_id'];
$dustbin_id = $data['dustbin_id'];

// Check if the dustbin is assigned to the employee
$check_stmt = $conn->prepare("SELECT * FROM dustbin_assignments WHERE card_id = ? AND dustbin_id = ?");
$check_stmt->bind_param("ss", $card_id, $dustbin_id);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(["error" => "Dustbin is not assigned to this employee"]);
    exit;
}

// Remove the dustbin assignment
$delete_stmt = $conn->prepare("DELETE FROM dustbin_assignments WHERE card_id = ? AND dustbin_id = ?");
$delete_stmt->bind_param("ss", $card_id, $dustbin_id);

if ($delete_stmt->execute()) {
    // ✅ **Log the removal event**
    logEvent("User with email $email removed Dustbin ID $dustbin_id from Employee ID $card_id");

    echo json_encode(["success" => "Dustbin assignment removed for employee"]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Failed to remove dustbin assignment"]);
}

// Close connections
$check_stmt->close();
$delete_stmt->close();
$conn->close();
?>
