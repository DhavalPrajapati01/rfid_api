<?php
header("Content-Type: application/json");
require_once "/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Database/db_connection.php"; // Database connection
require_once "/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Audit/functions.php"; // Logging functions
require_once "/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Auth/vendor/autoload.php"; // JWT verification

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$secret_key = "1229c37e5d8ec1b046b3c89d87cae5a3e2536aaacc6b81c254fc12c7dd5ae61a"; // Updated secret key

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

    // Allow only 'super_admin' or 'admin'
    if (!in_array($role, ['super_admin', 'admin'])) {
        http_response_code(403);
        echo json_encode(["error" => "Access Denied. Only super_admin or admin can add dustbins"]);
        exit;
    }
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(["error" => "Invalid token"]);
    exit;
}

// Get JSON data from the request body
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['dustbin_id']) || !isset($data['location'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required parameters"]);
    exit;
}

$dustbin_id = $data['dustbin_id'];
$location = $data['location'];

// Check if the dustbin already exists
$check_stmt = $conn->prepare("SELECT * FROM dustbins WHERE dustbin_id = ?");
$check_stmt->bind_param("s", $dustbin_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    http_response_code(409);
    echo json_encode(["error" => "Dustbin ID already exists"]);
    exit;
}

// Insert dustbin details into the database
$stmt = $conn->prepare("INSERT INTO dustbins (dustbin_id, location) VALUES (?, ?)");
$stmt->bind_param("ss", $dustbin_id, $location);

if ($stmt->execute()) {
    // ✅ Log the dustbin addition event
    logEvent("User with email $email added dustbin ID: $dustbin_id at location: $location");

    echo json_encode(["success" => "Dustbin added successfully"]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Failed to add dustbin"]);
}

$stmt->close();
$check_stmt->close();
$conn->close();
?>
