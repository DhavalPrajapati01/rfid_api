<?php

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: application/json");
require_once "db_connection.php"; // Database connection

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'PUT') {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

// Get JSON data from the request body
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['user_id'])) {
    http_response_code(400);
    echo json_encode(["error" => "User ID is required"]);
    exit;
}

$user_id = $data['user_id'];
$username = isset($data['username']) ? $data['username'] : null;
$email = isset($data['email']) ? $data['email'] : null;
$password = isset($data['password']) ? password_hash($data['password'], PASSWORD_BCRYPT) : null;
$role = isset($data['role']) ? $data['role'] : null;

// Validate role if provided
$allowed_roles = ['super_admin', 'admin', 'employee'];
if ($role !== null && !in_array($role, $allowed_roles)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid role"]);
    exit;
}

// Check if user exists
$sql = "SELECT user_id FROM users WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    http_response_code(404);
    echo json_encode(["error" => "User not found"]);
    exit;
}
$stmt->close();

// Construct the UPDATE query dynamically
$update_fields = [];
$params = [];
$types = "";

if ($username) {
    $update_fields[] = "username = ?";
    $params[] = $username;
    $types .= "s";
}
if ($email) {
    $update_fields[] = "email = ?";
    $params[] = $email;
    $types .= "s";
}
if ($password) {
    $update_fields[] = "password = ?";
    $params[] = $password;
    $types .= "s";
}
if ($role) {
    $update_fields[] = "role = ?";
    $params[] = $role;
    $types .= "s";
}

if (empty($update_fields)) {
    http_response_code(400);
    echo json_encode(["error" => "No fields to update"]);
    exit;
}

// Append user_id for WHERE clause
$params[] = $user_id;
$types .= "i";

$query = "UPDATE users SET " . implode(", ", $update_fields) . " WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);

try {
    if ($stmt->execute()) {
        http_response_code(200);
        echo json_encode(["message" => "User updated successfully"]);
    } else {
        throw new Exception("Failed to update user");
    }
} catch (mysqli_sql_exception $e) {
    if ($e->getCode() == 1062) {
        http_response_code(409);
        echo json_encode(["error" => "Duplicate entry: Username or Email already exists"]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Database error: " . $e->getMessage()]);
    }
}

$stmt->close();
$conn->close();
?>
