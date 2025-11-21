<?php
require_once __DIR__ . '/../config.php';

// Verify token
$auth = null;
if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $h = trim($_SERVER['HTTP_AUTHORIZATION']);
    if (stripos($h, 'Bearer ') === 0) $auth = substr($h, 7);
}
if (!$auth) sendResponse(false, 'Unauthorized', null, 401);
$payload = verifyJWT($auth);
if (!$payload || !isset($payload->user_id)) sendResponse(false, 'Unauthorized', null, 401);
$me = (int)$payload->user_id;

$other = isset($_GET['conversation_id']) ? (int)$_GET['conversation_id'] : null;
if (!$other) sendResponse(false, 'conversation_id required', null, 400);

// Mark messages to me from other as read
$upd = $conn->prepare("UPDATE player_mails SET IsMailNew = 0 WHERE PlayerId = ? AND SenderPlayerId = ? AND IsMailNew = 1");
$upd->bind_param('ii', $me, $other);
$upd->execute();

$stmt = $conn->prepare("SELECT pm.Id, pm.PlayerId, pm.SenderPlayerId, pm.Message, pm.SentDate, a.Username as SenderName FROM player_mails pm LEFT JOIN accounts a ON pm.SenderPlayerId = a.Id WHERE (pm.PlayerId = ? AND pm.SenderPlayerId = ?) OR (pm.PlayerId = ? AND pm.SenderPlayerId = ?) ORDER BY pm.SentDate ASC");
$stmt->bind_param('iiii', $me, $other, $other, $me);
$stmt->execute();
$res = $stmt->get_result();

$messages = [];
while ($r = $res->fetch_assoc()) {
    $isSent = ((int)$r['SenderPlayerId'] === $me);
    $messages[] = [
        'id' => (int)$r['Id'],
        'sender_id' => (int)$r['SenderPlayerId'],
        'sender_name' => $r['SenderName'] ?? ('Player ' . $r['SenderPlayerId']),
        'text' => $r['Message'],
        'timestamp' => (int)$r['SentDate']
    ];
}

// return with both 'data' and top-level 'messages' for compatibility
$out = ['success' => true, 'message' => 'Messages fetched', 'data' => $messages, 'messages' => $messages];
echo json_encode($out);
exit();

?>
