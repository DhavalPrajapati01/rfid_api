<?php
header("Content-Type: application/json");
require_once "db_connection.php"; // Database connection


$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

// Get JSON request body
$data = json_decode(file_get_contents("php://input"), true);

// Validate required parameters
if (!isset($data['email'], $data['new_password'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required parameters: email or new_password"]);
    exit;
}

$email = $data['email'];
$new_password = password_hash($data['new_password'], PASSWORD_DEFAULT); // Hash the password

try {
    // Check if the email exists
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(["error" => "Email not found"]);
        exit;
    }

    // Update the password
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
    $stmt->bind_param("ss", $new_password, $email);

    if ($stmt->execute()) {
        http_response_code(200);
        echo json_encode(["success" => "Password updated successfully"]);
    } else {
        throw new Exception("Password update failed");
    }

    $stmt->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}

$conn->close();
?>
