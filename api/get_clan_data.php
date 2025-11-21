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

// Try to find player's clan via players table
$clan = null;
$members = [];
$playerId = null;
$pstmt = $conn->prepare("SELECT Id, ClanId FROM players WHERE AccountId = ? LIMIT 1");
if ($pstmt) {
    $pstmt->bind_param('i', $me);
    $pstmt->execute();
    $pres = $pstmt->get_result();
    if ($prow = $pres->fetch_assoc()) {
        $playerId = (int)$prow['Id'];
        $clanId = $prow['ClanId'] ?? null;
    }
}
// fallback: token might be player id directly
if (empty($playerId)) {
    $playerId = (int)$me;
    $r = $conn->query("SELECT ClanId FROM players WHERE Id = " . intval($playerId) . " LIMIT 1");
    if ($r && $rr = $r->fetch_assoc()) $clanId = $rr['ClanId'] ?? null;
}

if (!empty($clanId)) {
    $cstmt = $conn->prepare("SELECT Id, Name, Icon, Description FROM clans WHERE Id = ? LIMIT 1");
    if ($cstmt) {
        $cstmt->bind_param('i', $clanId);
        $cstmt->execute();
        $cres = $cstmt->get_result();
        if ($crow = $cres->fetch_assoc()) {
            $clan = [ 'id' => (int)$crow['Id'], 'name' => $crow['Name'], 'icon' => $crow['Icon'] ?? null, 'description' => $crow['Description'] ?? null ];
        }
    }

    // members
    $mstmt = $conn->prepare("SELECT cm.PlayerId, a.Username, p.Level FROM clan_members cm LEFT JOIN accounts a ON cm.PlayerId = a.Id LEFT JOIN players p ON cm.PlayerId = p.Id WHERE cm.ClanId = ? LIMIT 200");
    if ($mstmt) {
        $mstmt->bind_param('i', $clanId);
        $mstmt->execute();
        $mres = $mstmt->get_result();
        while ($m = $mres->fetch_assoc()) {
            $members[] = [ 'id' => (int)$m['PlayerId'], 'username' => $m['Username'] ?? ('Player ' . $m['PlayerId']), 'level' => $m['Level'] ?? null ];
        }
    }
}

if (!$clan) {
    // return empty clan info
    echo json_encode(['success' => true, 'message' => 'No clan', 'clan' => null, 'members' => [], 'data' => ['clan' => null, 'members' => []]]);
    exit();
}

echo json_encode(['success' => true, 'message' => 'Clan loaded', 'clan' => $clan, 'members' => $members, 'data' => ['clan' => $clan, 'members' => $members]]);
exit();

?>
