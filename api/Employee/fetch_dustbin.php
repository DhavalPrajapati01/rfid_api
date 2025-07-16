
<?php
header("Content-Type: application/json");
require_once "/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Database/db_connection.php"; // Database connection
require_once "/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Audit/functions.php"; // Audit log functions
require_once "/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Auth/vendor/autoload.php"; // JWT verification

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$secret_key = "1229c37e5d8ec1b046b3c89d87cae5a3e2536aaacc6b81c254fc12c7dd5ae61a"; // Replace with actual secret key

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

// ✅ **Authenticate using JWT Token**
$headers = getallheaders();
if (!isset($headers['Authorization'])) {
    http_response_code(401);
    echo json_encode(["error" => "Authorization token required"]);
    exit;
}

$authHeader = $headers['Authorization'];
$token = str_replace("Bearer ", "", $authHeader);

try {
    $decoded = JWT::decode($token, new Key($secret_key, 'HS256'));
    $email = $decoded->email;
    $role = $decoded->role;
    $user_card_id = $decoded->card_id;

    // ✅ **Allow only super_admin, admin, and employees**
    if (!in_array($role, ['super_admin', 'admin', 'employee'])) {
        http_response_code(403);
        echo json_encode(["error" => "Access Denied. You do not have permission to fetch dustbin details."]);
        exit;
    }
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(["error" => "Invalid token"]);
    exit;
}

// ✅ **Fetch Assigned Dustbin Details for the Logged-in User**
$query = "SELECT dustbin_id, location FROM dustbin_assignments WHERE card_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $user_card_id);
$stmt->execute();
$result = $stmt->get_result();

$dustbins = [];
while ($row = $result->fetch_assoc()) {
    $dustbins[] = $row;
}

if (empty($dustbins)) {
    http_response_code(404);
    echo json_encode(["error" => "No dustbins assigned"]);
} else {
    // ✅ **Log the event in audit logs**
    logEvent("User with email $email fetched their assigned dustbin details");

    echo json_encode(["dustbins" => $dustbins], JSON_PRETTY_PRINT);
}

// Close the database connection
$stmt->close();
$conn->close();
?>

