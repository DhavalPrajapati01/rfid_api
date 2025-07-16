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

    // Restrict 'employees' from assigning dustbins
    if ($role === 'employee') {
        http_response_code(403);
        echo json_encode(["error" => "Access Denied. Employees are not allowed to assign dustbins."]);
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

// Fetch employee details
$emp_stmt = $conn->prepare("SELECT emp_name, department, designation FROM employees WHERE card_id = ?");
$emp_stmt->bind_param("s", $card_id);
$emp_stmt->execute();
$emp_result = $emp_stmt->get_result();

if ($emp_result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(["error" => "Employee not found"]);
    exit;
}

$employee = $emp_result->fetch_assoc();
$emp_name = $employee['emp_name'];
$department = $employee['department'];
$designation = $employee['designation'];

// Fetch dustbin details
$dustbin_stmt = $conn->prepare("SELECT location FROM dustbins WHERE dustbin_id = ?");
$dustbin_stmt->bind_param("s", $dustbin_id);
$dustbin_stmt->execute();
$dustbin_result = $dustbin_stmt->get_result();

if ($dustbin_result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(["error" => "Dustbin not found"]);
    exit;
}

$dustbin = $dustbin_result->fetch_assoc();
$location = $dustbin['location'];

// Check if dustbin is already assigned
$check_stmt = $conn->prepare("SELECT * FROM dustbin_assignments WHERE card_id = ? AND dustbin_id = ?");
$check_stmt->bind_param("ss", $card_id, $dustbin_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    http_response_code(400);
    echo json_encode(["error" => "Dustbin already assigned to this employee"]);
    exit;
}

// Insert assignment into database
$insert_stmt = $conn->prepare("INSERT INTO dustbin_assignments (card_id, emp_name, department, designation, dustbin_id, location) VALUES (?, ?, ?, ?, ?, ?)");
$insert_stmt->bind_param("ssssss", $card_id, $emp_name, $department, $designation, $dustbin_id, $location);

if ($insert_stmt->execute()) {
    // ✅ **LOGGING THE EVENT AFTER SUCCESSFUL ASSIGNMENT**
    logEvent("User with email $email assigned Dustbin ID $dustbin_id to Employee ID $card_id at location $location");

    echo json_encode(["success" => "Dustbin assigned successfully"]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Failed to assign dustbin"]);
}

// Close connections
$emp_stmt->close();
$dustbin_stmt->close();
$check_stmt->close();
$insert_stmt->close();
$conn->close();
?>
