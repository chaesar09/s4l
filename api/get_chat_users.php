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

// Return a simple user list from accounts (limit 100)
$limit = 100;
$stmt = $conn->prepare("SELECT Id, Username FROM accounts ORDER BY Id ASC LIMIT ?");
$stmt->bind_param('i', $limit);
$stmt->execute();
$res = $stmt->get_result();
$users = [];
while ($r = $res->fetch_assoc()) {
    $users[] = [ 'id' => (int)$r['Id'], 'username' => $r['Username'], 'online' => false, 'rank' => 1 ];
}

echo json_encode(['success' => true, 'message' => 'Users fetched', 'users' => $users, 'data' => ['users' => $users]]);
exit();

?>
