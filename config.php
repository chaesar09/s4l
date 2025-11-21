<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 's4league');

// Create connection
 $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4
 $conn->set_charset("utf8mb4");

// JWT Secret Key
define('JWT_SECRET', 'your_super_secret_key_here');

// Salt untuk SHA256 (opsional, tapi direkomendasikan untuk keamanan tambahan)
define('PASSWORD_SALT', 'S4League_2023_Secure_Salt!@#');

// CORS Headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}
?>