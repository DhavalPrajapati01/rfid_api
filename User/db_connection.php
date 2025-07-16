
<?php
$host = "localhost"; // Change if needed
$username = "u980461598_rfid_based"; // Change to your DB username
$password = "Rfid@2308"; // Change to your DB password
$database = "u980461598_rfid_dustbin"; // Change to your DB name

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die(json_encode(["error" => "Database connection failed: " . $conn->connect_error]));
}
?>
