<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Get POST data
 $data = json_decode(file_get_contents('php://input'), true);

// Validate input
if (empty($data['username'])) {
    sendResponse(false, 'Username harus diisi');
}

 $username = $data['username'];
 $password = $data['password'];

// Check if user exists
 $stmt = $conn->prepare("SELECT Id, Username, Nickname, Password, Salt FROM accounts WHERE Username = ? OR Nickname = ?");
 $stmt->bind_param("ss", $username, $username);
 $stmt->execute();
 $result = $stmt->get_result();

if ($result->num_rows === 0) {
    sendResponse(false, 'Username atau password salah');
}

 $user = $result->fetch_assoc();

// Verify password: support multiple legacy hash formats (no DB changes)
$stored = $user['Password'];
$salt = $user['Salt'] ?? '';

// First try PBKDF2 (stored hash/salt expected base64)
$matched = false;
if (!empty($stored) && !empty($salt)) {
    if (function_exists('check_password_pbkdf2') && check_password_pbkdf2($password, $stored, $salt)) {
        $matched = true;
    }
}

// If not matched yet, try legacy candidate hashes
if (!$matched) {
    $candidates = [
        hash('sha256', $password . $salt),
        md5($password . $salt),
        base64_encode(md5($password . $salt, true)),
        sha1($password . $salt),
        base64_encode(hash('sha256', $password . $salt, true))
    ];

    foreach ($candidates as $h) {
        if ($h === $stored) {
            $matched = true;
            break;
        }
    }
}

if (!$matched) {
    sendResponse(false, 'Username atau password salah');
}

unset($user['Salt']);
// Generate JWT token
 $token = generateJWT($user);

// Prepare a normalized user object for frontend (lowercase keys)
$normalizedUser = [
    'id' => isset($user['Id']) ? (int)$user['Id'] : null,
    'username' => $user['Username'] ?? $user['Nickname'] ?? '',
    'Level' => $user['Level'] ?? null
];

// Update LastLogin
 $updateStmt = $conn->prepare("UPDATE accounts SET LastLogin = NOW() WHERE Id = ?");
 $updateStmt->bind_param("i", $user['Id']);
 $updateStmt->execute();

sendResponse(true, 'Login berhasil', [
    'token' => $token,
    'user' => $normalizedUser
]);
?>