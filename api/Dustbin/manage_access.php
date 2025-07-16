<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

    // Restrict 'employees' from managing dustbin access
    if ($role === 'employee') {
        http_response_code(403);
        echo json_encode(["error" => "Access Denied. Employees are not allowed to manage dustbin access."]);
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
if (!isset($data['card_id']) || !isset($data['action'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required parameters"]);
    exit;
}

$card_id = $data['card_id'];
$action = strtolower($data['action']); // "allow" or "block"

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

if ($action === "block") {
    // Remove all dustbin assignments for this employee
    $delete_stmt = $conn->prepare("DELETE FROM dustbin_assignments WHERE card_id = ?");
    $delete_stmt->bind_param("s", $card_id);
    
    if ($delete_stmt->execute()) {
        // ✅ **Log the action**
        logEvent("User with email $email blocked all dustbin access for Employee ID $card_id");

        echo json_encode(["success" => "All dustbin access blocked for employee"]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Failed to block access"]);
    }

    $delete_stmt->close();
} elseif ($action === "allow") {
    // Fetch all available dustbins
    $dustbin_stmt = $conn->prepare("SELECT dustbin_id, location FROM dustbins");
    $dustbin_stmt->execute();
    $dustbin_result = $dustbin_stmt->get_result();

    if ($dustbin_result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(["error" => "No dustbins available"]);
        exit;
    }

    // Insert all dustbins for the employee
    $insert_stmt = $conn->prepare("INSERT INTO dustbin_assignments (card_id, emp_name, department, designation, dustbin_id, location) VALUES (?, ?, ?, ?, ?, ?)");

    while ($dustbin = $dustbin_result->fetch_assoc()) {
        $dustbin_id = $dustbin['dustbin_id'];
        $location = $dustbin['location'];
        $insert_stmt->bind_param("ssssss", $card_id, $emp_name, $department, $designation, $dustbin_id, $location);
        $insert_stmt->execute();
    }

    // ✅ **Log the action**
    logEvent("User with email $email assigned all dustbins to Employee ID $card_id");

    echo json_encode(["success" => "All dustbins assigned to employee"]);
    
    $dustbin_stmt->close();
    $insert_stmt->close();
} else {
    http_response_code(400);
    echo json_encode(["error" => "Invalid action"]);
}

// Close connections
$emp_stmt->close();
$conn->close();
?>
