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

// Validate input: ID is required
if (empty($data['id'])) {
    echo json_encode(["error" => "Dustbin ID is required for updating"]);
    exit;
}

// Build the UPDATE query dynamically
$query = "UPDATE dustbins SET";
$params = [];

// Add fields to update if provided
if (!empty($data['location'])) {
    $query .= " location = :location,";
    $params[':location'] = $data['location'];
}
if (!empty($data['capacity'])) {
    $query .= " capacity = :capacity,";
    $params[':capacity'] = $data['capacity'];
}
if (!empty($data['status'])) {
    $query .= " status = :status,";
    $params[':status'] = $data['status'];
}
if (!empty($data['timestamp'])) {
    $query .= " timestamp = :timestamp,";
    $params[':timestamp'] = $data['timestamp'];
}

// Remove trailing comma
$query = rtrim($query, ',');

// Append WHERE clause
$query .= " WHERE id = :id";
$params[':id'] = $data['id'];

// Prepare and execute the update query
$stmt = $pdo->prepare($query);
$success = $stmt->execute($params);

// Check if the update was successful
if ($stmt->rowCount() > 0) {
    echo json_encode(["success" => "Dustbin details updated successfully"]);
} else {
    echo json_encode(["error" => "No dustbin found or no changes made"]);
}

// Close database connection
$pdo = null;

?>
