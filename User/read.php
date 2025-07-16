<?php
header("Content-Type: application/json");
require_once "db_connection.php"; // Database connection

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

// Fetch query parameters from URL
$user_id = isset($_GET['user_id']) ? $_GET['user_id'] : null;
$username = isset($_GET['username']) ? $_GET['username'] : null;
$email = isset($_GET['email']) ? $_GET['email'] : null;
$role = isset($_GET['role']) ? $_GET['role'] : null;

$query = "SELECT user_id, username, email, password, role, timestamp FROM users";
$conditions = [];
$params = [];
$types = "";

// Add filters based on query parameters
if ($user_id) {
    $conditions[] = "user_id = ?";
    $params[] = $user_id;
    $types .= "i";
}
if ($username) {
    $conditions[] = "username = ?";
    $params[] = $username;
    $types .= "s";
}
if ($email) {
    $conditions[] = "email = ?";
    $params[] = $email;
    $types .= "s";
}
if ($role) {
    $conditions[] = "role = ?";
    $params[] = $role;
    $types .= "s";
}

// If any conditions exist, append to query
if (!empty($conditions)) {
    $query .= " WHERE " . implode(" AND ", $conditions);
}

$stmt = $conn->prepare($query);

// Bind parameters if any exist
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

if (empty($users)) {
    http_response_code(404);
    echo json_encode(["error" => "No users found"]);
} else {
    http_response_code(200);
    echo json_encode(["users" => $users]);
}

$stmt->close();
$conn->close();
?>
