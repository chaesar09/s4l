<?php
// tools/check_hash.php
// Usage: http://localhost/dashboard/s4league/tools/check_hash.php?username=chaersar&password=yourpass
require_once __DIR__ . '/../../config.php'; // sesuaikan path jika perlu

$username = $_GET['username'] ?? '';
$password = $_GET['password'] ?? '';

if (!$username || !$password) {
    echo "Provide username & password in query string\n";
    exit;
}

$stmt = $conn->prepare("SELECT Id, Username, Password, Salt FROM accounts WHERE Username = ? OR Nickname = ?");
$stmt->bind_param("ss", $username, $username);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    echo \"User not found\\n\";
    exit;
}
$user = $res->fetch_assoc();
$stored = $user['Password'];
$salt = $user['Salt'] ?? '';

echo \"Found user: \" . $user['Username'] . \" (Id: \" . $user['Id'] . \")\\n\\n\";
echo \"Stored Password: $stored\\n\";
echo \"Stored Salt: $salt\\n\\n\";

$tests = [
    'sha256_hex' => hash('sha256', $password . $salt),
    'sha256_base64' => base64_encode(hash('sha256', $password . $salt, true)),
    'md5_hex' => md5($password . $salt),
    'md5_base64' => base64_encode(md5($password . $salt, true)),
    'sha1_hex' => sha1($password . $salt),
    'sha1_base64' => base64_encode(sha1($password . $salt, true)),
];

foreach ($tests as $name => $val) {
    $match = ($val === $stored) ? 'MATCH' : '---';
    echo sprintf("%-15s : %s %s\n", $name, $val, $match);
}