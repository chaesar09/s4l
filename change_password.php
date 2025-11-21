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

// Get POST data
 $data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['currentPassword']) || !isset($data['newPassword'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

 $currentPassword = $data['currentPassword'];
 $newPassword = $data['newPassword'];

// Validate input
if (empty($currentPassword) || empty($newPassword)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

if (strlen($newPassword) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters']);
    exit;
}

// Change password
 $userModel = new User($conn);
 $result = $userModel->changePassword($payload->user_id, $currentPassword, $newPassword);

echo json_encode($result);
?>