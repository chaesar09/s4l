<?php
class User {
    private $conn;
    private $salt;
    
    public function __construct($conn) {
        $this->conn = $conn;
        $this->salt = PASSWORD_SALT; // Menggunakan salt dari config
    }
    
    public function create($username, $email, $password) {
        // Check if user exists
        $stmt = $this->conn->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $stmt->bind_param("ss", $email, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            return ['success' => false, 'message' => 'Email atau username sudah terdaftar'];
        }
        
        // Hash password with SHA256 + Salt
        $saltedPassword = $password . $this->salt;
        $hashedPassword = hash('sha256', $saltedPassword);
        
        // Generate avatar
        $avatar = 'https://randomuser.me/api/portraits/' . (rand(0, 1) ? 'men' : 'women') . '/' . rand(1, 70) . '.jpg';
        
        // Insert user
        $stmt = $this->conn->prepare("INSERT INTO users (username, email, password, avatar, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssss", $username, $email, $hashedPassword, $avatar);
        
        if ($stmt->execute()) {
            $userId = $this->conn->insert_id;
            return [
                'success' => true, 
                'message' => 'User created successfully',
                'user' => [
                    'id' => $userId,
                    'username' => $username,
                    'email' => $email,
                    'avatar' => $avatar
                ]
            ];
        } else {
            return ['success' => false, 'message' => 'Failed to create user'];
        }
    }
    
    public function login($email, $password) {
        // Get user by email or username
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE email = ? OR username = ?");
        $stmt->bind_param("ss", $email, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return ['success' => false, 'message' => 'Email/Username atau password salah'];
        }
        
        $user = $result->fetch_assoc();
        
        // Verify password with SHA256 + Salt
        $saltedInputPassword = $password . $this->salt;
        $hashedInputPassword = hash('sha256', $saltedInputPassword);
        
        if ($hashedInputPassword !== $user['password']) {
            return ['success' => false, 'message' => 'Email/Username atau password salah'];
        }
        
        // Remove password from response
        unset($user['password']);
        
        return [
            'success' => true,
            'message' => 'Login successful',
            'user' => $user
        ];
    }
    
    public function getById($id) {
        $stmt = $this->conn->prepare("SELECT id, username, email, avatar, created_at FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return null;
        }
        
        return $result->fetch_assoc();
    }
    
    // Method untuk mengubah password
    public function changePassword($userId, $currentPassword, $newPassword) {
        // Get current password
        $stmt = $this->conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return ['success' => false, 'message' => 'User not found'];
        }
        
        $user = $result->fetch_assoc();
        
        // Verify current password
        $saltedCurrentPassword = $currentPassword . $this->salt;
        $hashedCurrentPassword = hash('sha256', $saltedCurrentPassword);
        
        if ($hashedCurrentPassword !== $user['password']) {
            return ['success' => false, 'message' => 'Current password is incorrect'];
        }
        
        // Hash new password
        $saltedNewPassword = $newPassword . $this->salt;
        $hashedNewPassword = hash('sha256', $saltedNewPassword);
        
        // Update password
        $stmt = $this->conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashedNewPassword, $userId);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Password changed successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to change password'];
        }
    }
    
    // Method untuk reset password (tanpa verifikasi password lama)
    public function resetPassword($userId, $newPassword) {
        // Hash new password
        $saltedNewPassword = $newPassword . $this->salt;
        $hashedNewPassword = hash('sha256', $saltedNewPassword);
        
        // Update password
        $stmt = $this->conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashedNewPassword, $userId);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Password reset successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to reset password'];
        }
    }
}
?>