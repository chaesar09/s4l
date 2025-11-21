<?php
header('Content-Type: application/json');

require_once 'config.php';
require_once 'jwt_helper.php';
require_once 'User.php';

// Get POST data
 $data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['email']) || !isset($data['password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

 $email = trim($data['email']);
 $password = $data['password'];

// Validate input
if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

// Login user
 $userModel = new User($conn);
 $result = $userModel->login($email, $password);

if ($result['success']) {
    // Generate JWT token
    $payload = [
        'user_id' => $result['user']['id'],
        'username' => $result['user']['username'],
        'exp' => time() + (7 * 24 * 60 * 60) // 7 days
    ];
    
    $token = JWT::encode($payload, JWT_SECRET);
    
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'token' => $token,
        'user' => $result['user']
    ]);
} else {
    http_response_code(401);
    echo json_encode($result);
}
?>