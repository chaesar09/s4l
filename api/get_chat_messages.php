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

$channel = isset($_GET['channel']) ? $_GET['channel'] : 'all';

// Basic implementation: return recent player_mails as chat messages
// For real-time chat you'd replace with websocket or dedicated chat table
$limit = 50;
$stmt = $conn->prepare("SELECT pm.Id, pm.PlayerId, pm.SenderPlayerId, pm.Message, pm.SentDate, a.Username as SenderName FROM player_mails pm LEFT JOIN accounts a ON pm.SenderPlayerId = a.Id ORDER BY pm.SentDate DESC LIMIT ?");
$stmt->bind_param('i', $limit);
$stmt->execute();
$res = $stmt->get_result();
$messages = [];
while ($r = $res->fetch_assoc()) {
    $messages[] = [
        'id' => (int)$r['Id'],
        'sender_id' => (int)$r['SenderPlayerId'],
        'sender_name' => $r['SenderName'] ?? ('Player ' . $r['SenderPlayerId']),
        'text' => $r['Message'],
        'timestamp' => (int)$r['SentDate'],
        'channel' => 'all'
    ];
}

// return compatible shape used by frontend
echo json_encode(['success' => true, 'message' => 'Messages fetched', 'messages' => array_reverse($messages), 'data' => ['messages' => array_reverse($messages)]]);
exit();

?>
