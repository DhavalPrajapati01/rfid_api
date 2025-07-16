<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Database connection details
$host = "localhost";
$dbname = "u980461598_rfid_dustbin";
$username = "u980461598_rfid_based";  
$password = "Rfid@2308";     

// Create connection
$conn = new mysqli($host, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die(json_encode(["error" => "Database connection failed: " . $conn->connect_error]));
}

// Retrieve request parameters
$id          = isset($_GET['id']) ? $_GET['id'] : null;
$dustbin_id  = isset($_GET['dustbin_id']) ? $_GET['dustbin_id'] : null;
$employee_id = isset($_GET['employee_id']) ? $_GET['employee_id'] : null;
$action      = isset($_GET['action']) ? $_GET['action'] : null;
$timestamp   = isset($_GET['timestamp']) ? $_GET['timestamp'] : null;

// Prepare the SQL query dynamically
$sql = "SELECT * FROM logs WHERE 1=1";
$params = [];
$types = "";

// Apply filters only if parameters are provided
if (!empty($id)) {
    $sql .= " AND id = ?";
    $params[] = $id;
    $types .= "i";
}
if (!empty($dustbin_id)) {
    $sql .= " AND dustbin_id = ?";
    $params[] = $dustbin_id;
    $types .= "i";
}
if (!empty($employee_id)) {
    $sql .= " AND employee_id = ?";
    $params[] = $employee_id;
    $types .= "i";
}
if (!empty($action)) {
    $sql .= " AND action = ?";
    $params[] = $action;
    $types .= "s";
}
if (!empty($timestamp)) {
    $sql .= " AND timestamp >= ?";
    $params[] = $timestamp;
    $types .= "s";
}

// Prepare and execute the statement
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die(json_encode(["error" => "SQL Error: " . $conn->error]));
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

// Fetch data and return JSON response
$logs = [];
while ($row = $result->fetch_assoc()) {
    $logs[] = $row;
}

echo json_encode(["logs" => $logs]);

// Close connections
$stmt->close();
$conn->close();
?>
