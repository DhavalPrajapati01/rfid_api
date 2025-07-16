<?php
require_once "/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Database/db_connection.php"; // Database connectio

$sql = "UPDATE employees SET temp_password = NULL, password_expiry = NULL WHERE password_expiry <= NOW()";
$conn->query($sql);

$conn->close();
?>
