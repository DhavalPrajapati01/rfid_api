<?php
header("Content-Type: application/json");
require_once "/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Database/db_connection.php"; // Database connection
require_once "/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Audit/functions.php"; // Logging functions
require_once "/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Auth/vendor/autoload.php"; // JWT verification

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$secret_key = "1229c37e5d8ec1b046b3c89d87cae5a3e2536aaacc6b81c254fc12c7dd5ae61a"; // JWT secret key

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
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

    // Restrict 'employees' from updating dustbin details
    if ($role === 'employee') {
        http_response_code(403);
        echo json_encode(["error" => "Access Denied. Employees are not allowed to update dustbin details."]);
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
if (!isset($data['dustbin_id']) || !isset($data['location'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing dustbin_id or location"]);
    exit;
}

$dustbin_id = $data['dustbin_id'];
$location = $data['location'];

// Check if the dustbin exists
$stmt = $conn->prepare("SELECT * FROM dustbins WHERE dustbin_id = ?");
$stmt->bind_param("s", $dustbin_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(["error" => "Dustbin not found"]);
    exit;
}

// Update dustbin details
$update_stmt = $conn->prepare("UPDATE dustbins SET location = ? WHERE dustbin_id = ?");
$update_stmt->bind_param("ss", $location, $dustbin_id);

if ($update_stmt->execute()) {
    // ✅ Log the event
    logEvent("User with email $email updated Dustbin ID $dustbin_id with new location: $location");

    echo json_encode(["success" => "Dustbin details updated successfully"]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Failed to update dustbin details"]);
}

$stmt->close();
$update_stmt->close();
$conn->close();
?>
