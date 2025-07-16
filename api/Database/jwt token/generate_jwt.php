<?php

require_once "/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Database/src/JWT.php";
require_once "/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Database/src/Key.php";

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$secret_key = "rrfg454rg5tg4rr851f5r5s5fg";
$payload = [
    "email" => "user@example.com",
    "exp" => time() + 3600 // Token expires in 1 hour
];

$jwt = JWT::encode($payload, $secret_key, 'HS256');
echo "Generated Token: " . $jwt;
?>
