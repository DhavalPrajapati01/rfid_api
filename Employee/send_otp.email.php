<?php
header("Content-Type: application/json");
require_once "/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Database/db_connection.php"; 
require_once "/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Auth/vendor/autoload.php"; // JWT verification

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Include PHPMailer files
require '/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Auth/src/PHPMailer.php';
require '/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Auth/src/SMTP.php';
require '/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Auth/src/Exception.php';

$secret_key = "1229c37e5d8ec1b046b3c89d87cae5a3e2536aaacc6b81c254fc12c7dd5ae61a"; // JWT Secret Key

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

// ✅ **Authenticate using JWT**
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
    $auth_email = $decoded->email; // Email from token
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(["error" => "Invalid token"]);
    exit;
}

// ✅ **Process OTP Request**
$data = json_decode(file_get_contents("php://input"), true);
if (!isset($data['email'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing email"]);
    exit;
}

$email = $data['email'];

// Check if the employee exists
$stmt = $conn->prepare("SELECT card_id, role FROM employees WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(["error" => "Employee not found"]);
    exit;
}

$employee = $result->fetch_assoc();
$card_id = $employee['card_id'];
$role = $employee['role'];

// Map role to type character
$role_map = ['super_admin' => 'SA', 'admin' => 'AD', 'employee' => 'EE'];
$type = $role_map[$role];

// Generate OTP format: MM:DD:SS:UT
$password_pattern = date('i:d:s') . ":$type";

// Convert pattern into an **8-digit integer OTP**
$numeric_part = preg_replace('/[^0-9]/', '', $password_pattern); // Extract only numbers
$otp = substr($numeric_part, 0, 8); // Ensure it's an 8-digit OTP

// Set expiry (5 minutes from now)
$expiry = date('Y-m-d H:i:s', strtotime('+5 minutes'));

// Store OTP in database
$update_stmt = $conn->prepare("UPDATE employees SET temp_password = ?, password_expiry = ? WHERE email = ?");
$update_stmt->bind_param("sss", $otp, $expiry, $email);
$update_stmt->execute();

// ✅ **Send OTP via Email**
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'marketing.dprofiz@gmail.com';
    $mail->Password = 'brdb kgxp zwga xivd'; // Use App Password for Gmail
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    $mail->setFrom('marketing.dprofiz@gmail.com', 'dprofiz');
    $mail->addAddress($email);

    $mail->isHTML(true);
    $mail->Subject = "Your One-Time Password (OTP)";
    $mail->Body = "Your OTP is: <b>$otp</b><br>Valid for 5 minutes.";

    $mail->send();
    echo json_encode(["success" => "OTP sent to email"]);
} catch (Exception $e) {
    echo json_encode(["error" => "Email could not be sent: " . $mail->ErrorInfo]);
}

// ✅ **Close connections**
$stmt->close();
$update_stmt->close();
$conn->close();
?>
