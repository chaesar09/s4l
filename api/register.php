<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Get POST data
 $data = json_decode(file_get_contents('php://input'), true);

// Validate input
if (empty($data['username']) || empty($data['password'])) {
    sendResponse(false, 'Username dan password harus diisi');
}

 $username = $data['username'];
 $password = $data['password'];

// Check if username already exists
 $stmt = $conn->prepare("SELECT Id FROM accounts WHERE Username = ? OR Nickname = ?");
 $stmt->bind_param("ss", $username, $username);
 $stmt->execute();
 $result = $stmt->get_result();

if ($result->num_rows > 0) {
    sendResponse(false, 'Username sudah digunakan');
}

// Create PBKDF2 password (hash and salt in base64)
 $pw = create_password_pbkdf2($password);
 $hashedPassword = $pw->hash;
 $salt = $pw->salt;

// Insert new user
 $stmt = $conn->prepare("INSERT INTO accounts (Username, Nickname, Password, Salt, SecurityLevel) VALUES (?, ?, ?, ?, 0)");
 $stmt->bind_param("ssss", $username, $username, $hashedPassword, $salt);

if ($stmt->execute()) {
    $userId = $conn->insert_id;
    
    // Get user data
    $stmt = $conn->prepare("SELECT Id, Username, Nickname FROM accounts WHERE Id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    // Normalize user for frontend
    $normalizedUser = [
        'id' => isset($user['Id']) ? (int)$user['Id'] : null,
        'username' => $user['Username'] ?? $user['Nickname'] ?? ''
    ];

    // Generate JWT token
    $token = generateJWT($user);
    
    // Create player record
    $playerStmt = $conn->prepare("INSERT INTO players (Id, PlayTime, TutorialState, Level, TotalExperience, PEN, AP, Coins1, Coins2, CurrentCharacterSlot, TotalMatches, TotalWins, TotalLosses) VALUES (?, '', 1, 1, 0, 1000, 0, 0, 0, 0, 0, 0, 0)");
    $playerStmt->bind_param("i", $userId);
    $playerStmt->execute();
    
    sendResponse(true, 'Registrasi berhasil', [
        'token' => $token,
        'user' => $normalizedUser
    ]);
} else {
    sendResponse(false, 'Registrasi gagal');
}
?>