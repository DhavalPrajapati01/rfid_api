<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: application/json");
require_once "/home/u980461598/domains/intervein.dprofiz.com/public_html/rfid_api/Config/db_connection.php"; // Database connection
require_once "/home/u980461598/domains/intervein.dprofiz.com/public_html/rfid_api/auth/jwt_utils.php"; // JWT verification
$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

// Verify JWT Token & Role (Only Super Admin & Admin can insert dustbins)
$token = getBearerToken();
$user_role = verifyJWT($token);

if (!in_array($user_role, ['super_admin', 'admin'])) {
    http_response_code(403);
    echo json_encode(["error" => "Access Denied. Only Super Admin and Admin can insert dustbins."]);
    exit;
}

// Get JSON request body
$data = json_decode(file_get_contents("php://input"), true);

// Validate required parameters
if (!isset($data['location'], $data['capacity'], $data['status'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required parameters: location, capacity, or status"]);
    exit;
}

$location = $data['location'];
$capacity = $data['capacity'];
$status = $data['status'];

if (!in_array($status, ['full', 'empty'])) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid status. Allowed values: 'full' or 'empty'"]);
    exit;
}

try {
    // Insert dustbin details
    $stmt = $conn->prepare("INSERT INTO dustbins (location, capacity, status) VALUES (?, ?, ?)");
    $stmt->bind_param("sis", $location, $capacity, $status);
    
    if ($stmt->execute()) {
        http_response_code(201);
        echo json_encode(["success" => "Dustbin added successfully", "dustbin_id" => $stmt->insert_id]);
    } else {
        throw new Exception("Failed to insert dustbin");
    }
    
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        http_response_code(400);
        echo json_encode(["error" => "Duplicate dustbin entry"]);
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
