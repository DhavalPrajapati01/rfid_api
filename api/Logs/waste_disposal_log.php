<?php
header("Content-Type: application/json");

$host = "localhost";
$dbname = "u980461598_rfid_dustbin";
$username = "u980461598_rfid_based";  
$password = "Rfid@2308";      

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(["error" => "Connection failed: " . $conn->connect_error]));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (isset($data['dustbin_id'], $data['employee_id'], $data['action'])) {
        $dustbin_id = $conn->real_escape_string($data['dustbin_id']);
        $employee_id = $conn->real_escape_string($data['employee_id']);
        $action = $conn->real_escape_string($data['action']);
        $timestamp = isset($data['timestamp']) ? $conn->real_escape_string($data['timestamp']) : date('Y-m-d H:i:s');
        
        $sql = "INSERT INTO logs (dustbin_id, employee_id, action, timestamp) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiss", $dustbin_id, $employee_id, $action, $timestamp);
        
        if ($stmt->execute()) {
            echo json_encode(["message" => "Waste disposal logged successfully"]);
        } else {
            echo json_encode(["error" => "Failed to log waste disposal"]);
        }
        
        $stmt->close();
    } else {
        echo json_encode(["error" => "Missing required parameters"]);
    }
} else {
    echo json_encode(["error" => "Invalid request method"]);
}

$conn->close();
?>
