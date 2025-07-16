<?php
header("Content-Type: application/json");

require_once "/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Database/db_connection.php"; // Database Connection
require_once "/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Audit/functions.php"; // Logging functions
require_once "/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Auth/vendor/autoload.php"; // JWT verification

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$secret_key = "1229c37e5d8ec1b046b3c89d87cae5a3e2536aaacc6b81c254fc12c7dd5ae61a"; // JWT Secret Key

// Verify Request Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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
        echo json_encode(["error" => "Access Denied. Only super_admin or admin can view employee logs."]);
        exit;
    }
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(["error" => "Invalid token"]);
    exit;
}

// Get JSON input
$data = json_decode(file_get_contents("php://input"), true);

// Validate input
if (!isset($data['card_id']) || empty($data['card_id'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing card_id"]);
    exit;
}

$card_id = $data['card_id'];

// Fetch logs by employee (card_id)
$stmt = $conn->prepare("SELECT dustbin_id, scan_time FROM scan_logs WHERE card_id = ? ORDER BY scan_time DESC");
$stmt->bind_param("s", $card_id);
$stmt->execute();
$result = $stmt->get_result();

$logs = [];
while ($row = $result->fetch_assoc()) {
    $logs[] = $row;
}

// ✅ **Log the event in `audit_logs`**
logEvent("User with email $email accessed scan logs for employee Card ID: $card_id");

if (empty($logs)) {
    http_response_code(404);
    echo json_encode(["error" => "No logs found for this employee"]);
} else {
    echo json_encode(["scan_logs" => $logs], JSON_PRETTY_PRINT);
}

// Close connection
$stmt->close();
$conn->close();
?>
