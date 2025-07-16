<?php
header("Content-Type: application/json");
require_once "db_connection.php"; // Database connection

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'DELETE') {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

// Get JSON data from request body
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['user_id']) && !isset($data['username'])) {
    http_response_code(400);
    echo json_encode(["error" => "Either user_id or username is required"]);
    exit;
}

$user_id = isset($data['user_id']) ? $data['user_id'] : null;
$username = isset($data['username']) ? $data['username'] : null;

// Check if the user exists
if ($user_id) {
    $check_query = "SELECT user_id FROM users WHERE user_id = ?";
    $delete_query = "DELETE FROM users WHERE user_id = ?";
    $param = $user_id;
    $type = "i";
} elseif ($username) {
    $check_query = "SELECT user_id FROM users WHERE username = ?";
    $delete_query = "DELETE FROM users WHERE username = ?";
    $param = $username;
    $type = "s";
}

$stmt = $conn->prepare($check_query);
$stmt->bind_param($type, $param);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    http_response_code(404);
    echo json_encode(["error" => "User not found"]);
    exit;
}
$stmt->close();

// Proceed to delete the user
$stmt = $conn->prepare($delete_query);
$stmt->bind_param($type, $param);

if ($stmt->execute()) {
    http_response_code(200);
    echo json_encode(["message" => "User deleted successfully"]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Failed to delete user"]);
}

$stmt->close();
$conn->close();
?>
