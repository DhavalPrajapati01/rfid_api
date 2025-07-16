<?php

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
header("Content-Type: application/json");
require_once "db_connection.php"; // Database connection

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

// Check if user_id is provided in the request
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($user_id <= 0) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid user_id parameter"]);
    exit;
}

// Fetch employee details from users and employee tables
$query = "
    SELECT 
        u.username, u.email, u.password, u.role, 
        e.name, e.phone, e.position 
    FROM users u
    INNER JOIN employee e ON u.user_id = e.user_id
    WHERE u.user_id = ? AND u.role = 'employee'
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $employee = $result->fetch_assoc();
    http_response_code(200);
    echo json_encode($employee);
} else {
    http_response_code(404);
    echo json_encode(["error" => "Employee not found"]);
}

$stmt->close();
$conn->close();
?>
