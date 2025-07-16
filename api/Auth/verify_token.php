<?php
header("Content-Type: application/json");
require_once "jwt_utils.php"; // JWT token utility

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

// Extract token from Authorization header
$headers = apache_request_headers();
if (!isset($headers['Authorization'])) {
    http_response_code(401);
    echo json_encode(["error" => "Authorization header missing"]);
    exit;
}

list($tokenType, $token) = explode(" ", $headers['Authorization'], 2);
if ($tokenType !== 'Bearer') {
    http_response_code(401);
    echo json_encode(["error" => "Invalid token format"]);
    exit;
}

// Verify the JWT token
$decoded = verify_jwt($token);
if (!$decoded) {
    http_response_code(401);
    echo json_encode(["error" => "Invalid or expired token"]);
    exit;
}

// Allowed roles
$allowed_roles = ['super_admin', 'admin', 'employee'];
if (!in_array($decoded->role, $allowed_roles)) {
    http_response_code(403);
    echo json_encode(["error" => "Access denied"]);
    exit;
}

// Success response
http_response_code(200);
echo json_encode([
    "success" => "Token verified",
    "user_id" => $decoded->user_id,
    "role" => $decoded->role
]);
?>
