<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
header("Content-Type: application/json");

require_once "/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Database/db_connection.php";
require_once "/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Audit/functions.php";
require_once "/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Auth/vendor/autoload.php";

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$secret_key = "1229c37e5d8ec1b046b3c89d87cae5a3e2536aaacc6b81c254fc12c7dd5ae61a";

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

// Get JSON payload
$data = json_decode(file_get_contents("php://input"), true);

// Check if it's a simple scan (only RFID tag)
if (isset($data['Rfid_scanTag_id']) && count($data) === 1) {
    $rfid_scanTag_id = $data['Rfid_scanTag_id'];

    // Insert scan record
    $stmt = $conn->prepare("INSERT INTO rfid_logs (card_id, timestamp) VALUES (?, NOW())");
    $stmt->bind_param("s", $rfid_scanTag_id);

    if ($stmt->execute()) {
        logEvent("RFID scan recorded: $rfid_scanTag_id");
        http_response_code(200);
        echo json_encode(["success" => "Scan recorded successfully"]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Database error"]);
    }

    $stmt->close();
    $conn->close();
    exit;
}

// Check JWT auth
$headers = getallheaders();
if (!isset($headers['Authorization'])) {
    http_response_code(401);
    echo json_encode(["error" => "Authorization token required"]);
    exit;
}

$authHeader = $headers['Authorization'];
$token = str_replace("Bearer ", "", $authHeader);

try {
    $decoded = JWT::decode($token, new Key($secret_key, 'HS256'));
    $email = $decoded->email;
    $role = $decoded->role;

    if ($role === 'employee') {
        http_response_code(403);
        echo json_encode(["error" => "Access Denied. Employees are not allowed to add new employees."]);
        exit;
    }
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(["error" => "Invalid token"]);
    exit;
}

// Validate required fields for employee registration
if (!isset($data['Rfid_scanTag_id'], $data['email'], $data['emp_name'], $data['department'], $data['designation'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required parameters"]);
    exit;
}

$card_id = $data['Rfid_scanTag_id'];
$email = $data['email'];
$emp_name = $data['emp_name'];
$department = $data['department'];
$designation = $data['designation'];
$role = "employee";

// Check if RFID exists in logs
$check_rfid_stmt = $conn->prepare("SELECT * FROM rfid_logs WHERE card_id = ?");
$check_rfid_stmt->bind_param("s", $card_id);
$check_rfid_stmt->execute();
$check_rfid_result = $check_rfid_stmt->get_result();

if ($check_rfid_result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(["error" => "RFID tag not found in logs. Please scan it first."]);
    exit;
}

// Check if card already assigned
$check_card_stmt = $conn->prepare("SELECT * FROM employees WHERE card_id = ?");
$check_card_stmt->bind_param("s", $card_id);
$check_card_stmt->execute();
$check_card_result = $check_card_stmt->get_result();

if ($check_card_result->num_rows > 0) {
    http_response_code(409);
    echo json_encode(["error" => "RFID tag already assigned to another employee."]);
    exit;
}

// Check for existing email or emp_name
$check_stmt = $conn->prepare("SELECT * FROM employees WHERE email = ? OR emp_name = ?");
$check_stmt->bind_param("ss", $email, $emp_name);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    http_response_code(409);
    echo json_encode(["error" => "Employee name or email already exists."]);
    exit;
}

// Insert new employee
$insert_stmt = $conn->prepare("INSERT INTO employees (card_id, emp_name, department, designation, email, role) VALUES (?, ?, ?, ?, ?, ?)");
$insert_stmt->bind_param("ssssss", $card_id, $emp_name, $department, $designation, $email, $role);

if ($insert_stmt->execute()) {
    logEvent("User with email $email added a new employee with RFID tag: $card_id");
    echo json_encode(["success" => "Employee added successfully", "Rfid_scanTag_id" => $card_id]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Failed to add employee"]);
}

// Close everything
$check_rfid_stmt->close();
$check_card_stmt->close();
$check_stmt->close();
$insert_stmt->close();
$conn->close();
?>
