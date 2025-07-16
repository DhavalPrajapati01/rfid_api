<?php
header("Content-Type: application/json");
require_once "/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Database/db_connection.php"; 

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

// Get request data
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['user_id'], $data['email'], $data['password'], $data['confirm_password'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required parameters"]);
    exit;
}

$user_id = $data['user_id'];
$email = $data['email'];
$password = $data['password'];
$confirm_password = $data['confirm_password'];

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid email format"]);
    exit;
}

// Check if passwords match
if ($password !== $confirm_password) {
    http_response_code(400);
    echo json_encode(["error" => "Passwords do not match"]);
    exit;
}

// Hash the password
$hashed_password = password_hash($password, PASSWORD_BCRYPT);

// Check if email already exists
$check_stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
$check_stmt->bind_param("s", $email);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows > 0) {
    http_response_code(409);
    echo json_encode(["error" => "Email already registered"]);
    exit;
}

// Insert user into database
$stmt = $conn->prepare("INSERT INTO users (user_id, email, password) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $user_id, $email, $hashed_password);

if ($stmt->execute()) {
    echo json_encode(["success" => "User registered successfully"]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Failed to register user"]);
}

// Close connections
$check_stmt->close();
$stmt->close();
$conn->close();
?>
