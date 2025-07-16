<?php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

require 'vendor/autoload.php'; // Install via composer: composer require firebase/php-jwt

$SECRET_KEY = "your_secret_key"; // Change this to a secure key

function generate_jwt($user_id, $role) {
    global $SECRET_KEY;
    
    $payload = [
        "user_id" => $user_id,
        "role" => $role,
        "iat" => time(),
        "exp" => time() + (60 * 60) // Token valid for 1 hour
    ];

    return JWT::encode($payload, $SECRET_KEY, 'HS256');
}

function verify_jwt($token) {
    global $SECRET_KEY;

    try {
        return JWT::decode($token, new Key($SECRET_KEY, 'HS256'));
    } catch (Exception $e) {
        return null;
    }
}
?>
