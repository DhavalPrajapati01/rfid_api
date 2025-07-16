<?php
header('Content-Type: application/json');

// Database credentials
$host = 'localhost';    // Change as needed
$dbname = 'rfid_api';   // Change as needed
$username = 'root';     // Change as needed
$password = '';         // Change as needed

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed: " . $e->getMessage()]);
    exit;
}

// Get parameters from request (GET or POST)
$id = isset($_REQUEST['id']) ? $_REQUEST['id'] : null;
$dustbin_id = isset($_REQUEST['dustbin_id']) ? $_REQUEST['dustbin_id'] : null;
$employee_id = isset($_REQUEST['employee_id']) ? $_REQUEST['employee_id'] : null;
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : null;
$timestamp = isset($_REQUEST['timestamp']) ? $_REQUEST['timestamp'] : null;

// Construct SQL query dynamically
$query = "SELECT * FROM logs WHERE 1=1";
$params = [];

if ($id !== null) {
    $query .= " AND id = :id";
    $params[':id'] = $id;
}
if ($dustbin_id !== null) {
    $query .= " AND dustbin_id = :dustbin_id";
    $params[':dustbin_id'] = $dustbin_id;
}
if ($employee_id !== null) {
    $query .= " AND employee_id = :employee_id";
    $params[':employee_id'] = $employee_id;
}
if ($action !== null) {
    $query .= " AND action = :action";
    $params[':action'] = $action;
}
if ($timestamp !== null) {
    $query .= " AND timestamp = :timestamp";
    $params[':timestamp'] = $timestamp;
}

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(["status" => "success", "logs" => $logs]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Query failed: " . $e->getMessage()]);
}
?>
