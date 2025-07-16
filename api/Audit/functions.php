<?php
function logEvent($event) {
    require "/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Database/db_connection.php";
    
    $stmt = $conn->prepare("INSERT INTO audit_logs (event) VALUES (?)");
    $stmt->bind_param("s", $event);
    $stmt->execute();
    
    $stmt->close();
    $conn->close();
}
?>
