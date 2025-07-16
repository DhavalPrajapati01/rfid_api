<?php
header("Content-Type: application/json");

require_once "/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Database/db_connection.php"; // Database Connection
require_once "/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Audit/functions.php"; // Logging functions
require_once "/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Auth/vendor/autoload.php"; // JWT verification

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$secret_key = "1229c37e5d8ec1b046b3c89d87cae5a3e2536aaacc6b81c254fc12c7dd5ae61a"; // JWT Secret Key

// Verify Request Method
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

// Get Authorization Header
$headers = getallheaders();
if (!isset($headers['Authorization'])) {
    http_response_code(401);
    echo json_encode(["error" => "Authorization token required"]);
    exit;
}

$authHeader = $headers['Authorization'];
$token = str_replace("Bearer ", "", $authHeader);

// Verify JWT Token
try {
    $decoded = JWT::decode($token, new Key($secret_key, 'HS256'));
    $email = $decoded->email;
    $role = $decoded->role;

    // Restrict access to super_admin and admin only
    if (!in_array($role, ['super_admin', 'admin'])) {
        http_response_code(403);
        echo json_encode(["error" => "Access Denied. Only super_admin or admin can view scan logs."]);
        exit;
    }
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(["error" => "Invalid token"]);
    exit;
}

// Get query parameters
$card_id = isset($_GET['card_id']) ? $_GET['card_id'] : null;
$emp_name = isset($_GET['emp_name']) ? $_GET['emp_name'] : null;
$department = isset($_GET['department']) ? $_GET['department'] : null;
$dustbin_id = isset($_GET['dustbin_id']) ? $_GET['dustbin_id'] : null;
$scan_time = isset($_GET['scan_time']) ? $_GET['scan_time'] : null;

// Build SQL Query
$sql = "SELECT card_id, emp_name, department, dustbin_id, scan_time FROM scan_logs";
$params = [];
$types = "";

if ($card_id || $emp_name || $department || $dustbin_id || $scan_time) {
    $sql .= " WHERE ";
    $conditions = [];

    if ($card_id) {
        $conditions[] = "card_id = ?";
        $params[] = $card_id;
        $types .= "s";
    }
    if ($emp_name) {
        $conditions[] = "emp_name LIKE ?";
        $params[] = "%" . $emp_name . "%";
        $types .= "s";
    }
    if ($department) {
        $conditions[] = "department LIKE ?";
        $params[] = "%" . $department . "%";
        $types .= "s";
    }
    if ($dustbin_id) {
        $conditions[] = "dustbin_id = ?";
        $params[] = $dustbin_id;
        $types .= "s";
    }
    if ($scan_time) {
        $conditions[] = "DATE(scan_time) = ?";
        $params[] = $scan_time;
        $types .= "s";
    }

    $sql .= implode(" AND ", $conditions);
}

$stmt = $conn->prepare($sql);

// Bind parameters dynamically
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$logs = [];
while ($row = $result->fetch_assoc()) {
    $logs[] = $row;
}

// ✅ **Log the event in `audit_logs`**
logEvent("User with email $email accessed scan logs");

if (empty($logs)) {
    http_response_code(404);
    echo json_encode(["error" => "No logs found"]);
} else {
    echo json_encode(["scan_logs" => $logs], JSON_PRETTY_PRINT);
}

$stmt->close();
$conn->close();
?>
