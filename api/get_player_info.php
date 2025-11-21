<?php
require_once __DIR__ . '/../config.php';

// Authenticate via Bearer token
$auth = null;
if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $h = trim($_SERVER['HTTP_AUTHORIZATION']);
    if (stripos($h, 'Bearer ') === 0) $auth = substr($h, 7);
}
if (!$auth) sendResponse(false, 'Unauthorized', null, 401);
$payload = verifyJWT($auth);
if (!$payload) sendResponse(false, 'Unauthorized', null, 401);

// Flexible claim detection: accept multiple possible claim names
$me = null;
$possibleClaims = ['user_id','userId','id','Id','player_id','playerId','uid','account_id','AccountId'];
foreach ($possibleClaims as $c) {
    if (is_object($payload) && isset($payload->$c)) { $me = (int)$payload->$c; break; }
    if (is_array($payload) && isset($payload[$c])) { $me = (int)$payload[$c]; break; }
}
if (!$me) sendResponse(false, 'Unauthorized', null, 401);

// Ensure $me corresponds to a players.Id. If it's actually an AccountId, map it to players.Id
$checkStmt = $conn->prepare('SELECT Id FROM players WHERE Id = ? LIMIT 1');
if ($checkStmt) {
    $checkStmt->bind_param('i', $me);
    $checkStmt->execute();
    $res = $checkStmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    if (!$row) {
        // try mapping as AccountId -> players.Id
        $mapStmt = $conn->prepare('SELECT Id FROM players WHERE AccountId = ? LIMIT 1');
        if ($mapStmt) {
            $mapStmt->bind_param('i', $me);
            $mapStmt->execute();
            $r2 = $mapStmt->get_result();
            $row2 = $r2 ? $r2->fetch_assoc() : null;
            if ($row2) {
                $me = (int)$row2['Id'];
            } else {
                sendResponse(false, 'Unauthorized', null, 401);
            }
        } else {
            sendResponse(false, 'Unauthorized', null, 401);
        }
    }
}

$tables = [
    'battleroyal' => 'player_info_battleroyal',
    'captain' => 'player_info_captain',
    'chaser' => 'player_info_chaser',
    'deathmatch' => 'player_info_deathmatch',
    'touchdown' => 'player_info_touchdown'
];

$data = [];
foreach ($tables as $key => $table) {
    // Ensure table name is safe (from our fixed list)
    $sql = "SELECT * FROM `$table` WHERE PlayerId = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $data[$key] = null;
        continue;
    }
    $stmt->bind_param('i', $me);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $data[$key] = $row ? $row : null;
}

sendResponse(true, 'Player info fetched', $data);

?>
