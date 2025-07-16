<?php
header("Content-Type: application/json");
require_once "/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Database/db_connection.php"; // Database connection
require_once "/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Audit/functions.php"; // Logging functions
require_once "/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Auth/vendor/autoload.php"; // JWT verification

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$secret_key = "1229c37e5d8ec1b046b3c89d87cae5a3e2536aaacc6b81c254fc12c7dd5ae61a"; // JWT secret key

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

    // Restrict 'employees' from accessing dustbin details
    if ($role === 'employee') {
        http_response_code(403);
        echo json_encode(["error" => "Access Denied. Employees are not allowed to view dustbin details."]);
        exit;
    }
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(["error" => "Invalid token"]);
    exit;
}

// Check if a specific dustbin_id is provided
if (isset($_GET['dustbin_id'])) {
    $dustbin_id = $_GET['dustbin_id'];

    // Fetch a single dustbin record
    $stmt = $conn->prepare("SELECT * FROM dustbins WHERE dustbin_id = ?");
    $stmt->bind_param("s", $dustbin_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(["error" => "Dustbin not found"]);
        exit;
    }

    $dustbin = $result->fetch_assoc();
    
    // ✅ Log the audit event
    logEvent("User with email $email viewed details of Dustbin ID: $dustbin_id");

    echo json_encode($dustbin);
} else {
    // Fetch all dustbin records
    $stmt = $conn->prepare("SELECT * FROM dustbins");
    $stmt->execute();
    $result = $stmt->get_result();

    $dustbins = [];
    while ($row = $result->fetch_assoc()) {
        $dustbins[] = $row;
    }

    // ✅ Log the audit event
    logEvent("User with email $email viewed all dustbin details");

    echo json_encode($dustbins);
}

$stmt->close();
$conn->close();
?>
