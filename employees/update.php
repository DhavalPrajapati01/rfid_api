<?php
header("Content-Type: application/json");
require_once "db_connection.php"; // Database connection

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'PUT') {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

// Get request body
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['user_id'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required parameter: user_id"]);
    exit;
}

$user_id = $data['user_id'];
$username = $data['username'] ?? null;
$password = !empty($data['password']) ? password_hash($data['password'], PASSWORD_DEFAULT) : null;
$email = $data['email'] ?? null;

$name = $data['name'] ?? null;
$phone = $data['phone'] ?? null;
$position = $data['position'] ?? null;

// Step 1: Check if user exists
$query = "SELECT user_id FROM users WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    http_response_code(400);
    echo json_encode(["error" => "User ID not found"]);
    exit;
}
$stmt->close();

try {
    // Step 2: Update users table
    if ($username || $password || $email) {
        $query = "UPDATE users SET ";
        $params = [];
        $types = "";

        if ($username) {
            $query .= "username = ?, ";
            $params[] = $username;
            $types .= "s";
        }
        if ($password) {
            $query .= "password = ?, ";
            $params[] = $password;
            $types .= "s";
        }
        if ($email) {
            $query .= "email = ?, ";
            $params[] = $email;
            $types .= "s";
        }

        $query = rtrim($query, ", ") . " WHERE user_id = ?";
        $params[] = $user_id;
        $types .= "i";

        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            throw new Exception("Failed to update user details");
        }
        $stmt->close();
    }

    // Step 3: Update employee table
    if ($name || $email || $phone || $position) {
        $query = "UPDATE employee SET ";
        $params = [];
        $types = "";

        if ($name) {
            $query .= "name = ?, ";
            $params[] = $name;
            $types .= "s";
        }
        if ($email) {
            $query .= "email = ?, ";
            $params[] = $email;
            $types .= "s";
        }
        if ($phone) {
            $query .= "phone = ?, ";
            $params[] = $phone;
            $types .= "s";
        }
        if ($position) {
            $query .= "position = ?, ";
            $params[] = $position;
            $types .= "s";
        }

        $query = rtrim($query, ", ") . " WHERE user_id = ?";
        $params[] = $user_id;
        $types .= "i";

        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            throw new Exception("Failed to update employee details");
        }
        $stmt->close();
    }

    http_response_code(200);
    echo json_encode(["success" => "User and employee details updated successfully"]);
} catch (mysqli_sql_exception $e) {
    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        http_response_code(400);
        preg_match("/Duplicate entry '(.*?)' for key '(.*?)'/", $e->getMessage(), $matches);
        $duplicate_value = $matches[1] ?? "Unknown";
        $duplicate_field = $matches[2] ?? "Unknown";

        echo json_encode(["error" => "Duplicate entry: '$duplicate_value' already exists in field '$duplicate_field'"]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Database error: " . $e->getMessage()]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}

$conn->close();
?>
