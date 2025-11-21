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

$body = json_decode(file_get_contents('php://input'), true);
$recipient = isset($body['recipient_id']) ? (int)$body['recipient_id'] : null;
$message = isset($body['message']) ? trim($body['message']) : '';
if (!$recipient || $message === '') sendResponse(false, 'recipient_id and message required', null, 400);

// Insert message as a mail record
$ts = time();
$ins = $conn->prepare("INSERT INTO player_mails (PlayerId, SenderPlayerId, SentDate, Title, Message, IsMailNew, IsMailDeleted, IsClubMail) VALUES (?, ?, ?, '', ?, 1, 0, 0)");
$ins->bind_param('iiis', $recipient, $me, $ts, $message);
if (!$ins->execute()) {
    sendResponse(false, 'Failed to send message', null, 500);
}
$insertId = $conn->insert_id;

// Return inserted message info
$msgObj = [
    'id' => (int)$insertId,
    'sender_id' => $me,
    'recipient_id' => $recipient,
    'text' => $message,
    'timestamp' => $ts
];

// Return both top-level 'message' (object) and 'data' for compatibility with frontend
$out = ['success' => true, 'message' => $msgObj, 'data' => $msgObj, 'status' => 'Message sent'];
echo json_encode($out);
exit();

?>
