<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json'); // ✅ Ensure JSON response

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

// ✅ Check POST data properly
$user_id = $_POST['user_id'] ?? null;
$plan = $_POST['plan'] ?? null;

// ✅ If user_id or plan is missing, return JSON error
if (!$user_id || !$plan) {
    echo json_encode(["status" => "error", "message" => "Missing user_id or plan"]);
    exit;
}

// Subscription plans
$plans = [
    "free_trial" => ["name" => "Free Trial", "amount" => 1, "days" => 14], // ✅ Match "free_trial"
    "basic" => ["name" => "Basic Plan", "amount" => 1500, "days" => 30],
    "standard" => ["name" => "Standard Plan", "amount" => 5999, "days" => 180],
    "premium" => ["name" => "Premium Plan", "amount" => 10000, "days" => 365]
];

if (!isset($plans[$plan])) {
    echo json_encode(["status" => "error", "message" => "Invalid plan selected"]);
    exit;
}

$amount = $plans[$plan]['amount'] * 100; // Convert to paise
$expiry_date = date("Y-m-d H:i:s", strtotime("+".$plans[$plan]['days']." days"));

try {
    // Create Razorpay Order
    $order = $api->order->create([
        'amount' => $amount,
        'currency' => 'INR',
        'payment_capture' => 1
    ]);

    $order_id = $order['id'];

    // Store order in the database
    $stmt = $conn->prepare("INSERT INTO subscriptions (user_id, plan_name, amount, order_id, subscription_expiry, order_status) VALUES (?, ?, ?, ?, ?, 'pending')");
    $stmt->bind_param("issss", $user_id, $plans[$plan]['name'], $plans[$plan]['amount'], $order_id, $expiry_date);
    $stmt->execute();

    echo json_encode([
        "status" => "success",
        "order_id" => $order_id,
        "amount" => $plans[$plan]['amount'],
        "key" => $keyId
    ]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Razorpay order creation failed: " . $e->getMessage()]);
}
?> 