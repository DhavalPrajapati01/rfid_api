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
    echo json_encode(["error" => "Missing card_id or dustbin_id"]);
    exit;
}

$card_id = $data['card_id'];
$dustbin_id = $data['dustbin_id'];
$date = date("Y-m-d H:i:s"); // Capture current timestamp

// Check if the card_id exists in employees table
$emp_stmt = $conn->prepare("SELECT emp_name, department FROM employees WHERE card_id = ?");
$emp_stmt->bind_param("s", $card_id);
$emp_stmt->execute();
$emp_result = $emp_stmt->get_result();

if ($emp_result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(["error" => "Card ID not found"]);
    exit;
}

$employee = $emp_result->fetch_assoc();
$emp_name = $employee['emp_name'];
$department = $employee['department'];

// Check if the card is assigned to the dustbin
$assign_stmt = $conn->prepare("SELECT * FROM dustbin_assignments WHERE card_id = ? AND dustbin_id = ?");
$assign_stmt->bind_param("ss", $card_id, $dustbin_id);
$assign_stmt->execute();
$assign_result = $assign_stmt->get_result();

if ($assign_result->num_rows === 0) {
    http_response_code(403);
    echo json_encode(["error" => "Access Denied. Card is not assigned to this dustbin."]);
    exit;
}

// Insert log entry
$log_stmt = $conn->prepare("INSERT INTO scan_logs (card_id, emp_name, department, dustbin_id, scan_time) VALUES (?, ?, ?, ?, ?)");
$log_stmt->bind_param("sssss", $card_id, $emp_name, $department, $dustbin_id, $date);

if ($log_stmt->execute()) {
    // ✅ **Log the scan event**
    logEvent("Employee $emp_name (Card ID: $card_id) scanned dustbin $dustbin_id on $date");

    echo json_encode(["success" => "Scan logged successfully"]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Failed to log scan"]);
}

$emp_stmt->close();
$assign_stmt->close();
$log_stmt->close();
$conn->close();
?>
