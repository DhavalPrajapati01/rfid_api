<?php
header("Content-Type: application/json");
require_once "db_connection.php"; // Database connection
require_once "jwt_utils.php"; // JWT token utility

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

// Read JSON input
$data = json_decode(file_get_contents("php://input"), true);

// Validate input
if (empty($data['username_or_email']) || empty($data['password']) || empty($data['role'])) {
    http_response_code(400);
    echo json_encode(["error" => "Username/Email, Password, and Role are required"]);
    exit;
}

$username_or_email = $data['username_or_email'];
$password = $data['password'];
$role = $data['role'];

// Allowed roles
$allowed_roles = ['super_admin', 'admin', 'employee'];
if (!in_array($role, $allowed_roles)) {
    http_response_code(403);
    echo json_encode(["error" => "Invalid role specified"]);
    exit;
}

// Check if user exists
$query = "SELECT user_id, username, email, password, role FROM users WHERE (username = ? OR email = ?) AND role = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("sss", $username_or_email, $username_or_email, $role);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(401);
    echo json_encode(["error" => "Invalid credentials"]);
    exit;
}

$user = $result->fetch_assoc();

// Verify password
if (!password_verify($password, $user['password'])) {
    http_response_code(401);
    echo json_encode(["error" => "Invalid credentials"]);
    exit;
}

// Generate JWT token
$token = generate_jwt($user['user_id'], $user['role']);

http_response_code(200);
echo json_encode([
    "success" => "Login successful",
    "user_id" => $user['user_id'],
    "username" => $user['username'],
    "email" => $user['email'],
    "role" => $user['role'],
    "token" => $token
]);

$stmt->close();
$conn->close();
?>
