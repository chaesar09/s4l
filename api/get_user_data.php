<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Get Authorization header
 $headers = getallheaders();
 $authHeader = $headers['Authorization'] ?? '';

if (empty($authHeader) || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
    sendResponse(false, 'Token tidak valid');
}

 $token = $matches[1];

// Verify token
 $payload = verifyJWT($token);
if ($payload === null) {
    sendResponse(false, 'Token tidak valid atau kadaluarsa');
}

 $userId = $payload->user_id;

// Get account + player summary (some fields)
 $stmt = $conn->prepare("SELECT a.Id, a.Username, a.Nickname, a.LastLogin, a.IsConnect FROM accounts a WHERE a.Id = ?");
 $stmt->bind_param("i", $userId);
 $stmt->execute();
 $result = $stmt->get_result();

if ($result->num_rows === 0) {
    sendResponse(false, 'User tidak ditemukan');
}

 $acct = $result->fetch_assoc();

// fetch full players row if exists
 $stmt = $conn->prepare("SELECT * FROM players WHERE Id = ?");
 $stmt->bind_param("i", $userId);
 $stmt->execute();
 $playerRes = $stmt->get_result();
 $player = $playerRes->num_rows ? $playerRes->fetch_assoc() : null;

// Normalize account/user for frontend (kept for backward compat)
 $normalizedUser = [
    'id' => isset($acct['Id']) ? (int)$acct['Id'] : null,
    'username' => $acct['Username'] ?? $acct['Nickname'] ?? '',
    'Nickname' => $acct['Nickname'] ?? null,
    'LastLogin' => $acct['LastLogin'] ?? null,
    'IsConnect' => $acct['IsConnect'] ?? null
];

// Get player items
$stmt = $conn->prepare("SELECT pi.Id, pi.ShopItemInfoId, pi.Period, pi.DaysLeft, pi.Color, sii.ShopItemId FROM player_items pi LEFT JOIN shop_iteminfos sii ON pi.ShopItemInfoId = sii.Id WHERE pi.PlayerId = ?");
 $stmt->bind_param("i", $userId);
 $stmt->execute();
 $itemsResult = $stmt->get_result();

 $items = [];
while ($row = $itemsResult->fetch_assoc()) {
    $items[] = $row;
}

// Get player characters
 $stmt = $conn->prepare("SELECT * FROM player_characters WHERE PlayerId = ?");
 $stmt->bind_param("i", $userId);
 $stmt->execute();
 $charactersResult = $stmt->get_result();

 $characters = [];
while ($row = $charactersResult->fetch_assoc()) {
    $characters[] = $row;
}

// Get clan info if user is in a clan
 $stmt = $conn->prepare("SELECT cm.*, c.Name as ClanName, c.Icon as ClanIcon FROM clan_members cm LEFT JOIN clans c ON cm.ClanId = c.Id WHERE cm.PlayerId = ?");
 $stmt->bind_param("i", $userId);
 $stmt->execute();
 $clanResult = $stmt->get_result();

 $clan = null;
if ($clanResult->num_rows > 0) {
    $clan = $clanResult->fetch_assoc();
}

// Build response data object
$data = [
    'user' => $normalizedUser,
    'player' => $player,
    'items' => $items,
    'characters' => $characters,
    'clan' => $clan
];

// Return both top-level keys (for older frontend) and wrapped data
http_response_code(200);
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'Data berhasil diambil',
    // keep backward compat: top-level user key
    'user' => $normalizedUser,
    'player' => $player,
    'data' => $data
]);
exit();
?>