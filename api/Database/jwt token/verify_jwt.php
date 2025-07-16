<?php
require_once "/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Database/jwt%20token/src/JWT.php";
require_once "/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Database/jwt%20token/src/Key.php";
require_once "/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Database/jwt%20token/src/ExpiredException.php";
require_once "/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Database/jwt%20token/src/SignatureInvalidException.php";
require_once "/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Database/jwt%20token/src/BeforeValidException.php";


use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$secret_key = "rrfg454rg5tg4rr851f5r5s5fg";
$token = $_GET['token']; // Token passed as URL parameter

try {
    $decoded = JWT::decode($token, new Key($secret_key, 'HS256'));
    echo "Token is valid: ";
    print_r($decoded);
} catch (Exception $e) {
    echo "Invalid Token: " . $e->getMessage();
}
?>
