<?php
header('Content-Type: application/json');

require_once 'config.php';
require_once 'jwt_helper.php';
require_once 'User.php';

// Get Authorization header
 $headers = getallheaders();
 $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';

if (empty($authHeader) || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Missing or invalid token']);
    exit;
}

 $token = $matches[1];

// Verify token
 $payload = JWT::decode($token, JWT_SECRET);

if (!$payload) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid token']);
    exit;
}

// Get user data
 $userModel = new User($conn);
 $user = $userModel->getById($payload->user_id);

if (!$user) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

echo json_encode([
    'success' => true,
    'user' => $user
]);
?>