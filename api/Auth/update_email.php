<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
header("Content-Type: application/json");

require_once "/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Database/db_connection.php"; // Database connection
require_once "/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Audit/functions.php"; // Audit Log
require_once "/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Auth/vendor/autoload.php"; // JWT verification

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$secret_key = "1229c37e5d8ec1b046b3c89d87cae5a3e2536aaacc6b81c254fc12c7dd5ae61a"; // JWT Secret Key

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
        echo json_encode(["error" => "Access Denied. Only super_admin or admin can update email"]);
        exit;
    }
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(["error" => "Invalid token"]);
    exit;
}

// Get request data
$data = json_decode(file_get_contents("php://input"), true);

// Validate required parameters
if (!isset($data['email']) || !isset($data['otp']) || !isset($data['new_email'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing parameters"]);
    exit;
}

$email = $data['email'];
$user_otp = $data['otp']; // User-entered OTP
$new_email = $data['new_email'];

// Check if the existing email exists in the database
$stmt = $conn->prepare("SELECT card_id, temp_password, password_expiry FROM employees WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(["error" => "User not found"]);
    exit;
}

$employee = $result->fetch_assoc();
$stored_otp = $employee['temp_password']; // Stored OTP
$expiry_time = strtotime($employee['password_expiry']);
$current_time = time();

// Validate OTP
if ($user_otp !== $stored_otp) {
    http_response_code(401);
    echo json_encode(["error" => "Invalid OTP"]);
    exit;
}

// Check if the OTP is expired
if ($current_time > $expiry_time) {
    // Expired: Set OTP fields to NULL
    $update_stmt = $conn->prepare("UPDATE employees SET temp_password = NULL, password_expiry = NULL WHERE email = ?");
    $update_stmt->bind_param("s", $email);
    $update_stmt->execute();
    $update_stmt->close();

    http_response_code(401);
    echo json_encode(["error" => "OTP expired"]);
    exit;
}

// Check if the new email is the same as the existing email
if ($email === $new_email) {
    http_response_code(400);
    echo json_encode(["error" => "New email is the same as the existing email"]);
    exit;
}

// Check if the new email already exists in the database
$check_stmt = $conn->prepare("SELECT email FROM employees WHERE email = ?");
$check_stmt->bind_param("s", $new_email);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    http_response_code(400);
    echo json_encode(["error" => "New email is already in use"]);
    exit;
}

// Update the email in the database
$update_email_stmt = $conn->prepare("UPDATE employees SET email = ? WHERE email = ?");
$update_email_stmt->bind_param("ss", $new_email, $email);

if ($update_email_stmt->execute()) {
    // ✅ Log the email update event
    logEvent("User with email $email successfully updated their email to $new_email");

    echo json_encode(["success" => "Email updated successfully"]);
} else {
    echo json_encode(["error" => "Failed to update email"]);
}

$stmt->close();
$check_stmt->close();
$update_email_stmt->close();
$conn->close();
?>
