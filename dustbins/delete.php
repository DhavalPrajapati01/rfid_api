<?php

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set response header to JSON
header("Content-Type: application/json");

// Database connection details
$host = "localhost";
$dbname = "u980461598_rfid_dustbin";
$username = "u980461598_rfid_based";  
$password = "Rfid@2308";      

try {
    // Create a new PDO connection
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(["error" => "Database connection failed: " . $e->getMessage()]);
    exit;
}

// Read input JSON data
$input = file_get_contents("php://input");
$data = json_decode($input, true);

// Validate input: At least one parameter must be provided
if (empty($data['id']) && empty($data['location']) && empty($data['capacity']) && empty($data['status']) && empty($data['timestamp'])) {
    echo json_encode(["error" => "At least one parameter (id, location, capacity, status, or timestamp) is required"]);
    exit;
}

// Build the delete query dynamically
$query = "DELETE FROM dustbins WHERE 1=1";
$params = [];

// Add filters based on provided parameters
if (!empty($data['id'])) {
    $query .= " AND id = :id";
    $params[':id'] = $data['id'];
}
if (!empty($data['location'])) {
    $query .= " AND location LIKE :location";
    $params[':location'] = "%" . $data['location'] . "%";
}
if (!empty($data['capacity'])) {
    $query .= " AND capacity = :capacity";
    $params[':capacity'] = $data['capacity'];
}
if (!empty($data['status'])) {
    $query .= " AND status = :status";
    $params[':status'] = $data['status'];
}
if (!empty($data['timestamp'])) {
    $query .= " AND timestamp >= :timestamp";
    $params[':timestamp'] = $data['timestamp'];
}

// Prepare and execute the delete query
$stmt = $pdo->prepare($query);
$success = $stmt->execute($params);

// Check if the dustbin was deleted
if ($stmt->rowCount() > 0) {
    echo json_encode(["success" => "Dustbin deleted successfully"]);
} else {
    echo json_encode(["error" => "No dustbin found with the provided details"]);
}

// Close database connection
$pdo = null;

?>
