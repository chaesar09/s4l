<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Get Authorization header
 $headers = getallheaders();
 $authHeader = $headers['Authorization'] ?? '';

if (empty($authHeader) || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
    sendResponse(false, 'Token tidak valid');
}

 $token = $matches[1];

// Verify token
 $payload = verifyJWT($token);
if ($payload === null) {
    sendResponse(false, 'Token tidak valid atau kadaluarsa');
}

// In a real application, you might want to blacklist the token
// For simplicity, we'll just return success
sendResponse(true, 'Logout berhasil');
?>