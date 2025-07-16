<?php
header("Content-Type: application/json");
require_once "db_connection.php"; // Include database connection

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['username'], $data['email'], $data['password'], $data['role'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required parameters"]);
    exit;
}

$username = $data['username'];
$email = $data['email'];
$password = password_hash($data['password'], PASSWORD_BCRYPT); // Hashing the password
$role = $data['role'];
$timestamp = date('Y-m-d H:i:s');

$allowed_roles = ['super_admin', 'admin', 'employee'];
if (!in_array($role, $allowed_roles)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid role"]);
    exit;
}

$sql = "INSERT INTO users (username, email, password, role, timestamp) VALUES (?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sssss", $username, $email, $password, $role, $timestamp);

try {
    if ($stmt->execute()) {
        http_response_code(201);
        echo json_encode(["message" => "User inserted successfully"]);
    } else {
        throw new Exception("Failed to insert user");
    }
} catch (mysqli_sql_exception $e) {
    if ($e->getCode() == 1062) { // MySQL error code for duplicate entry
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
