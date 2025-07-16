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
    $admin_email = $decoded->email;
    $admin_role = $decoded->role;

    // Restrict 'employees' from updating roles
    if ($admin_role !== 'super_admin' && $admin_role !== 'admin') {
        http_response_code(403);
        echo json_encode(["error" => "Access Denied. Only super_admin or admin can update roles."]);
        exit;
    }
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(["error" => "Invalid token"]);
    exit;
}

// Get JSON data from the request
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['email']) || !isset($data['new_role'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing parameters"]);
    exit;
}

$email = $data['email'];
$new_role = $data['new_role'];

// Allowed roles
$allowed_roles = ['super_admin', 'admin', 'employee'];

if (!in_array($new_role, $allowed_roles)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid role. Allowed roles: super_admin, admin, employee"]);
    exit;
}

// Check if the user exists and fetch the current role
$stmt = $conn->prepare("SELECT role FROM employees WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(["error" => "User not found"]);
    exit;
}

$row = $result->fetch_assoc();
$current_role = $row['role']; // Fetch the existing role

// If the new role is the same as the current role, return an error
if ($current_role === $new_role) {
    http_response_code(400);
    echo json_encode(["error" => "Role is already assigned as $new_role"]);
    exit;
}

// Update role
$update_stmt = $conn->prepare("UPDATE employees SET role = ? WHERE email = ?");
$update_stmt->bind_param("ss", $new_role, $email);

if ($update_stmt->execute()) {
    // ✅ **Log the role update event**
    logEvent("User with email $admin_email updated role for $email from $current_role to $new_role");

    echo json_encode(["success" => "Role updated successfully"]);
} else {
    echo json_encode(["error" => "Failed to update role"]);
}

$stmt->close();
$update_stmt->close();
$conn->close();
?>
