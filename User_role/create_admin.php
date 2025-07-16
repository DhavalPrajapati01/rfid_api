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

// Validate input parameters
if (empty($data['username']) || empty($data['email']) || empty($data['password']) || empty($data['role'])) {
    echo json_encode(["error" => "Username, email, password, and role are required"]);
    exit;
}

// Prepare the SQL query to insert data
$query = "INSERT INTO admins (username, email, password, role, timestamp) 
          VALUES (:username, :email, :password, :role, NOW())";

try {
    // Hash the password before storing it
    $hashed_password = password_hash($data['password'], PASSWORD_BCRYPT);

    // Prepare the SQL statement
    $stmt = $pdo->prepare($query);

    // Execute the statement with provided data
    $stmt->execute([
        ':username' => $data['username'],
        ':email' => $data['email'],
        ':password' => $hashed_password,
        ':role' => $data['role']
    ]);

    echo json_encode(["success" => "Admin created successfully"]);
} catch (PDOException $e) {
    echo json_encode(["error" => "Failed to create admin: " . $e->getMessage()]);
}

// Close database connection
$pdo = null;

?>
