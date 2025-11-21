<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

// Auth
$auth = null;
if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $h = trim($_SERVER['HTTP_AUTHORIZATION']);
    if (stripos($h, 'Bearer ') === 0) $auth = substr($h, 7);
}
if (!$auth) sendResponse(false, 'Unauthorized', null, 401);
$payload = verifyJWT($auth);
if (!$payload) sendResponse(false, 'Unauthorized', null, 401);
$me = $payload->user_id ?? ($payload->id ?? null);
if (!$me) sendResponse(false, 'Unauthorized', null, 401);

// Basic friends list: look for friends table or fall back to recent accounts
$friends = [];
// If a friends table exists, try to use it
$colCheck = $conn->query("SHOW TABLES LIKE 'friends'");
if ($colCheck && $colCheck->num_rows > 0) {
    $stmt = $conn->prepare("SELECT f.friend_id, a.Username, p.Level FROM friends f LEFT JOIN accounts a ON f.friend_id = a.Id LEFT JOIN players p ON f.friend_id = p.Id WHERE f.user_id = ? LIMIT 200");
    if ($stmt) {
        $stmt->bind_param('i', $me);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $friends[] = ['id' => (int)$r['friend_id'], 'username' => $r['Username'], 'level' => $r['Level'] ?? null, 'online' => false];
        }
    }
}

// fallback: return a short list of other accounts (exclude current)
if (empty($friends)) {
    $limit = 50;
    $stmt = $conn->prepare("SELECT Id, Username FROM accounts WHERE Id != ? ORDER BY Id DESC LIMIT ?");
    $stmt->bind_param('ii', $me, $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $friends[] = ['id' => (int)$r['Id'], 'username' => $r['Username'], 'level' => null, 'online' => false];
    }
}

echo json_encode(['success' => true, 'message' => 'Friends fetched', 'friends' => $friends, 'data' => ['friends' => $friends]]);
exit();

?>
