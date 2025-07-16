<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header("Content-Type: application/json");

require_once "/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Database/db_connection.php"; 
require_once "/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Audit/functions.php";
require_once "/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Auth/vendor/autoload.php"; 

use Firebase\JWT\JWT;

$secret_key = "1229c37e5d8ec1b046b3c89d87cae5a3e2536aaacc6b81c254fc12c7dd5ae61a"; 

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['email']) || !isset($data['otp']) || !isset($data['user_id'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing email, OTP, or user ID"]);
    exit;
}

$email = $data['email'];
$user_otp = $data['otp']; 
$input_user_id = $data['user_id'];
$current_time = time();

function isOtpValid($userOtp, $storedOtp, $expiryTime) {
    if ($userOtp !== $storedOtp) return "invalid";
    if (time() > strtotime($expiryTime)) return "expired";
    return "valid";
}

// 1. Try logging in as organization
$stmt = $conn->prepare("SELECT user_id, organization_id, temp_password, password_expiry FROM organizations WHERE email = ? AND user_id = ?");
$stmt->bind_param("si", $email, $input_user_id);
$stmt->execute();
$result = $stmt->get_result();

$is_organization = false;
$login_successful = false;
$user_id = null;
$organization_id = null;

if ($result->num_rows > 0) {
    $org = $result->fetch_assoc();
    $otp_status = isOtpValid($user_otp, $org['temp_password'], $org['password_expiry']);

    if ($otp_status === "invalid") {
        http_response_code(401);
        echo json_encode(["error" => "Invalid OTP"]);
        exit;
    }

    if ($otp_status === "expired") {
        $update_stmt = $conn->prepare("UPDATE organizations SET temp_password = NULL, password_expiry = NULL WHERE email = ?");
        $update_stmt->bind_param("s", $email);
        $update_stmt->execute();
        $update_stmt->close();

        echo json_encode([
            "error" => "OTP expired",
            "current_time" => date('Y-m-d H:i:s', time()),
            "expiry_time" => $org['password_expiry']
        ]);
        exit;
    }

    $user_id = $org['user_id'];
    $organization_id = $org['organization_id'];
    $is_organization = true;
    $login_successful = true;
    logEvent("Organization with email $email and user ID $user_id logged in successfully");

} else {
    // Try logging in as employee
    $stmt = $conn->prepare("SELECT user_id, organization_id, temp_password, password_expiry FROM employees WHERE email = ? AND user_id = ?");
    $stmt->bind_param("si", $email, $input_user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(["error" => "No matching organization or employee found for this email and user ID"]);
        exit;
    }

    $emp = $result->fetch_assoc();
    $otp_status = isOtpValid($user_otp, $emp['temp_password'], $emp['password_expiry']);

    if ($otp_status === "invalid") {
        http_response_code(401);
        echo json_encode(["error" => "Invalid OTP"]);
        exit;
    }

    if ($otp_status === "expired") {
        $update_stmt = $conn->prepare("UPDATE employees SET temp_password = NULL, password_expiry = NULL WHERE email = ?");
        $update_stmt->bind_param("s", $email);
        $update_stmt->execute();
        $update_stmt->close();

        echo json_encode([
            "error" => "OTP expired",
            "current_time" => date('Y-m-d H:i:s', time()),
            "expiry_time" => $emp['password_expiry']
        ]);
        exit;
    }

    $user_id = $emp['user_id'];
    $organization_id = $emp['organization_id'];
    $login_successful = true;
    logEvent("Employee with email $email and user ID $user_id logged in successfully");
}

// 2. Check subscription
$sub_stmt = $conn->prepare("SELECT license_key, subscription_expiry, order_status FROM subscriptions WHERE organization_id = ?");
$sub_stmt->bind_param("s", $organization_id);
$sub_stmt->execute();
$sub_result = $sub_stmt->get_result();

if ($sub_result->num_rows === 0) {
    echo json_encode([
        "status" => "pending_payment",
        "message" => "OTP verified. No subscription found. Please complete payment.",
        "user_id" => $user_id,
        "organization_id" => $organization_id,
        "email" => $email
    ]);
    exit;
}

$sub = $sub_result->fetch_assoc();
$license_key = $sub['license_key'];
$subscription_expiry = strtotime($sub['subscription_expiry']);
$order_status = $sub['order_status'];

if ($order_status !== 'done') {
    echo json_encode([
        "status" => "pending_payment",
        "message" => "Payment is incomplete. Please finish your subscription."
    ]);
    exit;
}

if ($current_time > $subscription_expiry) {
    http_response_code(403);
    echo json_encode(["error" => "Your subscription has expired"]);
    exit;
}

if (!$license_key) {
    http_response_code(400);
    echo json_encode(["error" => "License key not generated"]);
    exit;
}

// ✅ All checks passed — return JWT
$payload = [
    "user_id" => $user_id,
    "exp" => time() + (24 * 60 * 60)
];

$jwt = JWT::encode($payload, $secret_key, 'HS256');

echo json_encode([
    "success" => "Login successful",
    "user_id" => $user_id,
    "organization_id" => $organization_id,
    "license_key" => $license_key,
    "subscription_expiry" => date('d-m-Y', $subscription_expiry),
    "token" => $jwt
]);

$stmt->close();
$sub_stmt->close();
$conn->close();
?>
