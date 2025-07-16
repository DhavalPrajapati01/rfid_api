<?php

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set response header to JSON format
header("Content-Type: application/json");

// Database connection details
$host = "localhost";
$dbname = "u980461598_rfid_dustbin";
$username = "u980461598_rfid_based";  
$password = "Rfid@2308";      

try {
    // Create a new PDO connection
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    echo json_encode(["error" => "Database connection failed: " . $e->getMessage()]);
    exit;
}

// Read input JSON data
$input = file_get_contents("php://input");
$data = json_decode($input, true);

// Validate input: At least one parameter must be provided
if (empty($data['id']) && empty($data['username']) && empty($data['email']) && empty($data['password']) && empty($data['timestamp'])) {
    echo json_encode(["error" => "At least one parameter (id, username, email, password, or timestamp) is required"]);
    exit;
}

// Build the search query dynamically for admins
$query = "SELECT * FROM users WHERE role = 'admin' AND 1=1";
$params = [];

// Add filters based on provided parameters
if (!empty($data['id'])) {
    $query .= " AND id = :id";
    $params[':id'] = $data['id'];
}
if (!empty($data['username'])) {
    $query .= " AND username LIKE :username";
    $params[':username'] = "%" . $data['username'] . "%";
}
if (!empty($data['email'])) {
    $query .= " AND email LIKE :email";
    $params[':email'] = "%" . $data['email'] . "%";
}
if (!empty($data['password'])) {
    $query .= " AND password LIKE :password";
    $params[':password'] = "%" . $data['password'] . "%";
}
if (!empty($data['timestamp'])) {
    $query .= " AND timestamp >= :timestamp";
    $params[':timestamp'] = $data['timestamp'];
}

try {
    // Prepare and execute the search query
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    // Fetch results
    $admins = $stmt->fetchAll();
    
    // Return results as JSON
    if (!empty($admins)) {
        echo json_encode(["success" => true, "data" => $admins]);
    } else {
        echo json_encode(["error" => "No admin found with the provided details"]);
    }
} catch (PDOException $e) {
    echo json_encode(["error" => "Query execution failed: " . $e->getMessage()]);
}

// Close database connection
$pdo = null;

?>
