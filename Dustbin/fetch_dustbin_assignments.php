<?php
header("Content-Type: application/json");
require_once "/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Database/db_connection.php"; // Database Connection
require_once "/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Audit/functions.php"; // Logging functions
require_once "/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Auth/vendor/autoload.php"; // JWT verification

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$secret_key = "1229c37e5d8ec1b046b3c89d87cae5a3e2536aaacc6b81c254fc12c7dd5ae61a"; // JWT secret key

// Check if the request method is GET
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

    // Restrict access to only 'super_admin' and 'admin'
    if (!in_array($role, ['super_admin', 'admin'])) {
        http_response_code(403);
        echo json_encode(["error" => "Access Denied. Only super_admin or admin can fetch dustbin assignments."]);
        exit;
    }
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(["error" => "Invalid token"]);
    exit;
}

// Get search parameters
$card_id = isset($_GET['card_id']) ? $_GET['card_id'] : null;
$emp_name = isset($_GET['emp_name']) ? $_GET['emp_name'] : null;
$department = isset($_GET['department']) ? $_GET['department'] : null;
$designation = isset($_GET['designation']) ? $_GET['designation'] : null;
$dustbin_id = isset($_GET['dustbin_id']) ? $_GET['dustbin_id'] : null;
$location = isset($_GET['location']) ? $_GET['location'] : null;

// Build the SQL query dynamically
$sql = "SELECT card_id, emp_name, department, designation, dustbin_id, location FROM dustbin_assignments WHERE 1=1";
$params = [];
$types = "";

if ($card_id) {
    $sql .= " AND card_id = ?";
    $params[] = $card_id;
    $types .= "s";
}
if ($emp_name) {
    $sql .= " AND emp_name LIKE ?";
    $params[] = "%" . $emp_name . "%";
    $types .= "s";
}
if ($department) {
    $sql .= " AND department LIKE ?";
    $params[] = "%" . $department . "%";
    $types .= "s";
}
if ($designation) {
    $sql .= " AND designation LIKE ?";
    $params[] = "%" . $designation . "%";
    $types .= "s";
}
if ($dustbin_id) {
    $sql .= " AND dustbin_id = ?";
    $params[] = $dustbin_id;
    $types .= "s";
}
if ($location) {
    $sql .= " AND location LIKE ?";
    $params[] = "%" . $location . "%";
    $types .= "s";
}

$stmt = $conn->prepare($sql);

// Bind parameters dynamically
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$assignments = [];
while ($row = $result->fetch_assoc()) {
    $assignments[] = $row;
}

// Log the event
logEvent("User with email $email fetched dustbin assignments");

if (empty($assignments)) {
    http_response_code(404);
    echo json_encode(["error" => "No assignments found"]);
} else {
    echo json_encode(["assignments" => $assignments], JSON_PRETTY_PRINT);
}

$stmt->close();
$conn->close();
?>
