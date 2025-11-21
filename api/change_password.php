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

// Get POST data
 $data = json_decode(file_get_contents('php://input'), true);

// Validate input
if (empty($data['currentPassword']) || empty($data['newPassword'])) {
    sendResponse(false, 'Password saat ini dan password baru harus diisi');
}

 $currentPassword = $data['currentPassword'];
 $newPassword = $data['newPassword'];
 $userId = $payload->user_id;

// Get user data
 $stmt = $conn->prepare("SELECT Id, Username, Password, Salt FROM accounts WHERE Id = ?");
 $stmt->bind_param("i", $userId);
 $stmt->execute();
 $result = $stmt->get_result();

if ($result->num_rows === 0) {
    sendResponse(false, 'User tidak ditemukan');
}

 $user = $result->fetch_assoc();

// Verify current password: try PBKDF2 first, then fallback to legacy hashes
$isValid = false;
// Try PBKDF2 (stored hash/salt expected base64)
if (!empty($user['Salt']) && !empty($user['Password'])) {
    if (check_password_pbkdf2($currentPassword, $user['Password'], $user['Salt'])) {
        $isValid = true;
    }
}
// Legacy fallback (md5/sha/etc)
if (!$isValid) {
    $candidates = [
        hash('sha256', $currentPassword . ($user['Salt'] ?? '')), 
        md5($currentPassword . ($user['Salt'] ?? '')),
        base64_encode(md5($currentPassword . ($user['Salt'] ?? ''), true)),
        sha1($currentPassword . ($user['Salt'] ?? ''))
    ];
    foreach ($candidates as $h) {
        if ($h === $user['Password']) {
            $isValid = true;
            break;
        }
    }
}

if (!$isValid) {
    sendResponse(false, 'Password saat ini salah');
}

// Generate new PBKDF2 hash+salt for updated password
 $pw = create_password_pbkdf2($newPassword);
 $hashedNewPassword = $pw->hash;
 $newSalt = $pw->salt;

// Update password
 $stmt = $conn->prepare("UPDATE accounts SET Password = ?, Salt = ? WHERE Id = ?");
 $stmt->bind_param("ssi", $hashedNewPassword, $newSalt, $userId);

if ($stmt->execute()) {
    sendResponse(true, 'Password berhasil diubah');
} else {
    sendResponse(false, 'Gagal mengubah password');
}
?>