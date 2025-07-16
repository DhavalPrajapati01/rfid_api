<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header("Content-Type: application/json");

require_once "/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Database/db_connection.php";
require_once "/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Audit/functions.php";
require_once "/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Auth/vendor/autoload.php";

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$secret_key = "1229c37e5d8ec1b046b3c89d87cae5a3e2536aaacc6b81c254fc12c7dd5ae61a";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

// Check Authorization Header
$headers = getallheaders();
if (!isset($headers['Authorization'])) {
    http_response_code(401);
    echo json_encode(["error" => "Authorization token required"]);
    exit;
}

$authHeader = $headers['Authorization'];
$token = str_replace("Bearer ", "", $authHeader);

// Decode token
try {
    $decoded = JWT::decode($token, new Key($secret_key, 'HS256'));
    $email_user = $decoded->email;
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

// Validate required fields
$required_fields = ['card_id', 'email', 'emp_name', 'department', 'designation'];
foreach ($required_fields as $field) {
    if (!isset($data[$field]) || empty(trim($data[$field]))) {
        http_response_code(400);
        echo json_encode(["error" => "Missing or empty field: $field"]);
        exit;
    }
}

$card_id = trim($data['card_id']);
$email = trim($data['email']);
$emp_name = trim($data['emp_name']);
$department = trim($data['department']);
$designation = trim($data['designation']);
$role = "employee";

// Check for duplicate card_id
$check_card_stmt = $conn->prepare("SELECT * FROM employees WHERE card_id = ?");
$check_card_stmt->bind_param("s", $card_id);
$check_card_stmt->execute();
$check_card_result = $check_card_stmt->get_result();

if ($check_card_result->num_rows > 0) {
    http_response_code(409);
    echo json_encode(["error" => "RFID tag already assigned to another employee."]);
    exit;
}

// Check for duplicate email or emp_name
$check_stmt = $conn->prepare("SELECT * FROM employees WHERE email = ? OR emp_name = ?");
$check_stmt->bind_param("ss", $email, $emp_name);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    http_response_code(409);
    echo json_encode(["error" => "Employee name or email already exists."]);
    exit;
}

// Insert employee
$insert_stmt = $conn->prepare("INSERT INTO employees (card_id, emp_name, department, designation, email, role) VALUES (?, ?, ?, ?, ?, ?)");
$insert_stmt->bind_param("ssssss", $card_id, $emp_name, $department, $designation, $email, $role);

if ($insert_stmt->execute()) {
    logEvent("User with email $email_user added employee $emp_name with RFID: $card_id");
    echo json_encode(["success" => "Employee added successfully", "card_id" => $card_id]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Failed to add employee"]);
}

// Close connections
$check_card_stmt->close();
$check_stmt->close();
$insert_stmt->close();
$conn->close();
?>
