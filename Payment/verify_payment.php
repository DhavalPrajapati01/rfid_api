<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

date_default_timezone_set('Asia/Kolkata'); // Set timezone

require 'razorpay-php/Razorpay.php';
use Razorpay\Api\Api;

// Razorpay API credentials
$keyId = 'rzp_test_kz6NrKGGxbnS9n';
$keySecret = '45KELM1WreuP8aLCnOorIMPh';
$api = new Api($keyId, $keySecret);

// Database connection
$conn = new mysqli("localhost", "u980461598_rfid_based", "Rfid@2308", "u980461598_rfid_dustbin");
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit;
}

// ✅ Check if required POST data is present
$order_id = $_POST['order_id'] ?? null;
$payment_id = $_POST['payment_id'] ?? null;

if (!$order_id || !$payment_id) {
    echo json_encode(["status" => "error", "message" => "Missing order_id or payment_id"]);
    exit;
}

try {
    // Verify the payment with Razorpay
    $payment = $api->payment->fetch($payment_id);
    if ($payment->status !== 'captured') {
        echo json_encode(["status" => "error", "message" => "Payment not captured"]);
        exit;
    }

    // Get order details from the database
    $stmt = $conn->prepare("SELECT id, user_id, plan_name, amount, subscription_expiry FROM subscriptions WHERE order_id = ? AND order_status = 'pending'");
    $stmt->bind_param("s", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(["status" => "error", "message" => "Invalid or already processed order"]);
        exit;
    }

    $row = $result->fetch_assoc();
    $subscription_id = $row['id'];
    $user_id = $row['user_id'];
    $subscription_expiry = $row['subscription_expiry'];

    // Capture payment time
    $payment_time = date("Y-m-d H:i:s");

    // Function to remove dashes, colons, and spaces
    function formatTimestamp($timestamp) {
        return preg_replace('/[-: ]/', '', $timestamp);
    }

    // Generate formatted license key
    $formatted_payment_time = formatTimestamp($payment_time);
    $formatted_subscription_expiry = formatTimestamp($subscription_expiry);
    $license_key = $subscription_id . $formatted_payment_time . $formatted_subscription_expiry;

    // Debugging
    error_log("Captured payment_time: " . $payment_time);
    error_log("Captured subscription_expiry: " . $subscription_expiry);
    error_log("Generated license_key: " . $license_key);

    // Update the subscription status and store the payment details
    $update_stmt = $conn->prepare("UPDATE subscriptions SET payment_id = ?, payment_time = ?, order_status = 'done', license_key = ? WHERE order_id = ?");
    $update_stmt->bind_param("ssss", $payment_id, $payment_time, $license_key, $order_id);

    if ($update_stmt->execute()) {
        echo json_encode([
            "status" => "success",
            "message" => "Payment verified and subscription activated",
            "license_key" => $license_key
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Database update failed"]);
    }
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Verification failed: " . $e->getMessage()]);
}
?>