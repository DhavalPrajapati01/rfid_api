<?php

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
header("Content-Type: application/json");
require_once "db_connection.php"; // Database connection

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

// Get request body
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['user_id'], $data['name'], $data['phone'], $data['position'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required parameters"]);
    exit;
}

$user_id = $data['user_id'];
$name = $data['name'];
$phone = $data['phone'];
$position = $data['position'];

// Step 1: Check if user exists and fetch email
$query = "SELECT email FROM users WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    http_response_code(400);
    echo json_encode(["error" => "User ID not found"]);
    exit;
}

$stmt->bind_result($email);
$stmt->fetch();
$stmt->close();

// Step 2: Check if employee with same user_id already exists
$query = "SELECT employee_id FROM employee WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    http_response_code(400);
    echo json_encode(["error" => "Employee record already exists for this user_id"]);
    exit;
}
$stmt->close();

// Step 3: Insert employee details
$query = "INSERT INTO employee (user_id, name, email, phone, position) VALUES (?, ?, ?, ?, ?)";
$stmt = $conn->prepare($query);
$stmt->bind_param("issss", $user_id, $name, $email, $phone, $position);

if ($stmt->execute()) {
    http_response_code(201);
    echo json_encode(["success" => "Employee added successfully", "employee_id" => $stmt->insert_id]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Failed to insert employee"]);
}

$stmt->close();
$conn->close();
?>
