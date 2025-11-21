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

$others = [];
// collect other participant ids (either as sender or recipient)
$stmt = $conn->prepare("SELECT SenderPlayerId as other FROM player_mails WHERE PlayerId = ? AND IsMailDeleted = 0 UNION SELECT PlayerId as other FROM player_mails WHERE SenderPlayerId = ? AND IsMailDeleted = 0");
$stmt->bind_param('ii', $me, $me);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $others[(int)$r['other']] = (int)$r['other'];
}

$conversations = [];
foreach ($others as $otherId) {
    // get username
    $username = null;
    $avatar = null;
    $s = $conn->prepare("SELECT Username, Avatar FROM accounts WHERE Id = ? LIMIT 1");
    $s->bind_param('i', $otherId);
    $s->execute();
    $r = $s->get_result()->fetch_assoc();
    if ($r) { $username = $r['Username']; $avatar = $r['Avatar'] ?? null; }

    // last message
    $m = $conn->prepare("SELECT Message, SentDate FROM player_mails WHERE (PlayerId = ? AND SenderPlayerId = ?) OR (PlayerId = ? AND SenderPlayerId = ?) ORDER BY SentDate DESC LIMIT 1");
    $m->bind_param('iiii', $me, $otherId, $otherId, $me);
    $m->execute();
    $mr = $m->get_result()->fetch_assoc();

    // unread count (mails sent to me by other)
    $u = $conn->prepare("SELECT COUNT(*) as cnt FROM player_mails WHERE PlayerId = ? AND SenderPlayerId = ? AND IsMailNew = 1");
    $u->bind_param('ii', $me, $otherId);
    $u->execute();
    $uc = $u->get_result()->fetch_assoc();

    $conversations[] = [
        'id' => $otherId,
        'username' => $username ?: ('Player ' . $otherId),
        'avatar' => $avatar,
        'last_message' => $mr['Message'] ?? '',
        'last_date' => isset($mr['SentDate']) ? (int)$mr['SentDate'] : 0,
        'unread' => isset($uc['cnt']) ? (int)$uc['cnt'] : 0
    ];
}

// sort by last_date desc
usort($conversations, function($a,$b){ return ($b['last_date'] ?? 0) - ($a['last_date'] ?? 0); });

$out = ['success' => true, 'message' => 'Conversations fetched', 'data' => $conversations, 'conversations' => $conversations];
echo json_encode($out);
exit();

?>
