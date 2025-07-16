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

    // ✅ **Allow only super_admin and admin**
    if (!in_array($role, ['super_admin', 'admin'])) {
        http_response_code(403);
        echo json_encode(["error" => "Access Denied. Employees are not allowed to fetch employee details."]);
        exit;
    }
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(["error" => "Invalid token"]);
    exit;
}

// ✅ **Get Optional Filters**
$card_id = isset($_GET['card_id']) ? $_GET['card_id'] : null;
$emp_name = isset($_GET['emp_name']) ? $_GET['emp_name'] : null;
$department = isset($_GET['department']) ? $_GET['department'] : null;
$designation = isset($_GET['designation']) ? $_GET['designation'] : null;
$email_param = isset($_GET['email']) ? $_GET['email'] : null;
$role_param = isset($_GET['role']) ? $_GET['role'] : null;
$dustbin_id = isset($_GET['dustbin_id']) ? $_GET['dustbin_id'] : null;

// ✅ **Construct SQL Query**
$sql = "
    SELECT e.card_id, e.emp_name, e.department, e.designation, e.email, e.role, d.dustbin_id 
    FROM employees e 
    LEFT JOIN dustbin_assignments d ON e.card_id = d.card_id
";
$params = [];
$types = "";

if ($card_id || $emp_name || $department || $designation || $email_param || $role_param || $dustbin_id) {
    $sql .= " WHERE ";
    $conditions = [];

    if ($card_id) {
        $conditions[] = "e.card_id = ?";
        $params[] = $card_id;
        $types .= "s";
    }
    if ($emp_name) {
        $conditions[] = "e.emp_name LIKE ?";
        $params[] = "%" . $emp_name . "%";
        $types .= "s";
    }
    if ($department) {
        $conditions[] = "e.department LIKE ?";
        $params[] = "%" . $department . "%";
        $types .= "s";
    }
    if ($designation) {
        $conditions[] = "e.designation LIKE ?";
        $params[] = "%" . $designation . "%";
        $types .= "s";
    }
    if ($email_param) {
        $conditions[] = "e.email = ?";
        $params[] = $email_param;
        $types .= "s";
    }
    if ($role_param) {
        $conditions[] = "e.role = ?";
        $params[] = $role_param;
        $types .= "s";
    }
    if ($dustbin_id) {
        $conditions[] = "d.dustbin_id = ?";
        $params[] = $dustbin_id;
        $types .= "s";
    }

    $sql .= implode(" AND ", $conditions);
}

// ✅ **Execute the Query**
$stmt = $conn->prepare($sql);

// Bind parameters dynamically
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$employees = [];
while ($row = $result->fetch_assoc()) {
    $employees[] = $row;
}

// ✅ **Log the event in audit logs**
logEvent("User with email $email fetched employee details");

if (empty($employees)) {
    http_response_code(404);
    echo json_encode(["error" => "No employees found"]);
} else {
    echo json_encode($employees, JSON_PRETTY_PRINT);
}

$stmt->close();
$conn->close();
?>
