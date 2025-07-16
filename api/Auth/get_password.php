<?php
header("Content-Type: application/json");
require_once "/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Database/db_connection.php"; // Database connection

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Include PHPMailer files
require '/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Auth/src/PHPMailer.php';
require '/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Auth/src/SMTP.php';
require '/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Auth/src/Exception.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['email']) || !isset($data['uuid'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing email or UUID"]);
    exit;
}

$email = $data['email'];
$uuid = $data['uuid'];

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

// Check if the provided UUID is already registered
$uuid_check_stmt = $conn->prepare("SELECT COUNT(*) FROM login_restrictions WHERE email = ? AND uuid = ?");
$uuid_check_stmt->bind_param("ss", $email, $uuid);
$uuid_check_stmt->execute();
$uuid_check_stmt->bind_result($uuid_exists);
$uuid_check_stmt->fetch();
$uuid_check_stmt->close();

if ($uuid_exists > 0) {
    // UUID already exists → Allow login
} else {
    // Check total UUID count
    $uuid_count_stmt = $conn->prepare("SELECT COUNT(*) FROM login_restrictions WHERE email = ?");
    $uuid_count_stmt->bind_param("s", $email);
    $uuid_count_stmt->execute();
    $uuid_count_stmt->bind_result($uuid_count);
    $uuid_count_stmt->fetch();
    $uuid_count_stmt->close();

    if ($uuid_count >= 3) {
        http_response_code(403);
        echo json_encode(["error" => "Login restricted. Maximum UUID limit reached for this email."]);
        exit;
    }

    // Insert new UUID
    $uuid_insert_stmt = $conn->prepare("INSERT INTO login_restrictions (email, uuid) VALUES (?, ?)");
    $uuid_insert_stmt->bind_param("ss", $email, $uuid);
    $uuid_insert_stmt->execute();
    $uuid_insert_stmt->close();
}

// Map role to type character
$role_map = ['super_admin' => 'SA', 'admin' => 'AD', 'employee' => 'EE'];
$type = $role_map[$role];

// Generate password format: MM:DD:SS:UT
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
$update_stmt->close();

// Send OTP via email
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com'; // Change for your SMTP server
    $mail->SMTPAuth = true;
    $mail->Username = 'marketing.dprofiz@gmail.com'; // Your email
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

$stmt->close();
$conn->close();
?>
