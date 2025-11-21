<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 's4l');

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
// Load JWT helper (standalone)
require_once __DIR__ . '/jwt_helper.php';

// Utility: standardized JSON response and exit
function sendResponse($success, $message = '', $data = null, $httpCode = 200) {
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    http_response_code($httpCode);
    $response = ['success' => $success, 'message' => $message];
    if (!is_null($data)) {
        $response['data'] = $data;
    }
    echo json_encode($response);
    exit();
}

// Generate JWT token from a user record/array
function generateJWT($user) {
    $id = null;
    if (is_array($user)) {
        $id = $user['Id'] ?? $user['id'] ?? $user['user_id'] ?? null;
    } elseif (is_object($user)) {
        $id = $user->Id ?? $user->id ?? $user->user_id ?? null;
    }
    $payload = [
        'user_id' => $id,
        'iat' => time(),
        'exp' => time() + (7 * 24 * 60 * 60) // 7 days
    ];
    return JWT::encode($payload, JWT_SECRET);
}

// Verify token and return payload or null
function verifyJWT($token) {
    $payload = JWT::decode($token, JWT_SECRET);
    return $payload ? $payload : null;
}

// PBKDF2-based password helpers (from user)
function pbkdf2($password, $salt) {
    if(!in_array('sha1', hash_algos(), true))
    {
        die('Couldn\'t initialise PBKDF2');
    }

    $hash_length = strlen(hash('sha1', '', true));
    $block_count = ceil(24 / $hash_length);

    $output = '';
    for($i = 1; $i <= $block_count; $i++)
    {
        $last = $salt.pack('N', $i);
        $last = $xorsum = hash_hmac('sha1', $last, $password, true);
        for($j = 1; $j < 24000; $j++)
        {
            $xorsum ^= ($last = hash_hmac('sha1', $last, $password, true));
        }

        $output .= $xorsum;
    }

    return substr($output, 0, 24);
}

function hash_equal_custom($a, $b) {
    $ret = strlen($a) ^ strlen($b);
    $ret |= array_sum(unpack("C*", $a^$b));
    return !$ret;
}

function check_password_pbkdf2($password, $hash, $salt) {
    $salt = base64_decode($salt);
    $password_guess = pbkdf2($password, $salt);
    $actual_password = base64_decode($hash);

    return hash_equal_custom($actual_password, $password_guess);
}

function create_password_pbkdf2($password) {
    $result = new stdClass;

    $salt = random_bytes(24);
    $hash = pbkdf2($password, $salt);

    $result->salt = base64_encode($salt);
    $result->hash = base64_encode($hash);

    return $result;
}

?>