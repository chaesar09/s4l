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

// Accept several common claim names from tokens
$me = null;
$possibleClaims = ['user_id','userId','id','Id','uid','player_id'];
foreach ($possibleClaims as $c) {
    if (isset($payload->$c)) { $me = (int)$payload->$c; break; }
}
if (!$me) sendResponse(false, 'Unauthorized', null, 401);

$source = null;
// Fetch all items from player_characters table (no WHERE)
$stmt = $conn->prepare("SELECT * FROM `player_characters`;");
$stmt->execute();
$res = $stmt->get_result();
if ($res && $res->num_rows > 0) $source = 'player_characters_all';

// If no rows, try fallback: token might be an AccountId; map to players.Id then query player_characters
if ($res->num_rows === 0) {
    $pstmt = $conn->prepare("SELECT Id FROM players WHERE AccountId = ? LIMIT 1");
    if ($pstmt) {
        $pstmt->bind_param('i', $me);
        $pstmt->execute();
        $pres = $pstmt->get_result();
        if ($prow = $pres->fetch_assoc()) {
            $playerId = (int)$prow['Id'];
            $stmt = $conn->prepare("SELECT * FROM player_characters WHERE PlayerId = ?");
            $stmt->bind_param('i', $playerId);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && $res->num_rows > 0) $source = 'player_characters_mapped_from_account';
        }
    }
}

// If still no rows, try player_characters.AccountId if that column exists
if ($res->num_rows === 0) {
    $colCheck = $conn->query("SHOW COLUMNS FROM player_characters LIKE 'AccountId'");
    if ($colCheck && $colCheck->num_rows > 0) {
        $stmt = $conn->prepare("SELECT * FROM player_characters WHERE AccountId = ?");
        if ($stmt) {
            $stmt->bind_param('i', $me);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && $res->num_rows > 0) $source = 'player_characters_AccountId';
        }
    }
}

$items = [];
// Build item.xml mapping (item_key -> [name, icon]) if file exists
$itemMap = [];
$itemXmlPath = __DIR__ . '/../item.xml';
if (file_exists($itemXmlPath)) {
    libxml_use_internal_errors(true);
    $xml = simplexml_load_file($itemXmlPath);
    if ($xml) {
        foreach ($xml->item as $it) {
                $k = (string)$it['item_key'];
                $nm = '';
                $desc = '';
                $ic = '';
                // name may be in base name attribute or element
                if (isset($it->base) && isset($it->base['name'])) $nm = (string)$it->base['name'];
                if (isset($it->base) && isset($it->base['desc'])) $desc = (string)$it->base['desc'];
                if (isset($it->desc)) $desc = $desc ?: trim((string)$it->desc);
                if (isset($it->graphic) && isset($it->graphic['icon_image'])) $ic = (string)$it->graphic['icon_image'];
                $itemMap[$k] = ['name' => $nm, 'icon' => $ic, 'desc' => $desc];
            }
    }
}

    // Build item.json mapping (id -> icon_image) if file exists
    $jsonMap = [];
    $itemJsonPath = __DIR__ . '/../item.json';
    if (file_exists($itemJsonPath)) {
        $raw = @file_get_contents($itemJsonPath);
        $j = json_decode($raw, true);
        if (is_array($j)) {
            foreach ($j as $cat => $arr) {
                if (!is_array($arr)) continue;
                foreach ($arr as $entry) {
                    if (!isset($entry['id'])) continue;
                    $id = (string)$entry['id'];
                    $icon = $entry['icon_image'] ?? ($entry['icon'] ?? '-');
                    $jsonMap[$id] = $icon;
                }
            }
        }
    }

    // helper to resolve icon filename in images folder (prefer images/, fallback to weapon/)
    function resolveImagePath($iconImage) {
        if (!$iconImage || $iconImage === '-' || trim($iconImage) === '') return null;
        $imgDir = __DIR__ . '/../images/';
        $candidates = [];
        $candidates[] = $iconImage;
        $candidates[] = ltrim($iconImage, '_');
        $candidates[] = strtolower($iconImage);
        $candidates[] = strtoupper($iconImage);
        // try common extensions
        $exts = ['.dds', '.DDS', '.tga', '.TGA', '.png', '.PNG', '.jpg', '.JPG'];
        $baseNoExt = preg_replace('/\.[^.]+$/', '', $iconImage);
        foreach ($exts as $e) $candidates[] = $baseNoExt . $e;

        foreach ($candidates as $cand) {
            $path = $imgDir . $cand;
            if (file_exists($path)) return 'images/' . $cand;
        }

        // case-insensitive search in images dir
        $files = @scandir($imgDir);
        if ($files) {
            foreach ($files as $f) {
                if (strcasecmp($f, $iconImage) === 0) return 'images/' . $f;
                if (strcasecmp(ltrim($f, '_'), ltrim($iconImage, '_')) === 0) return 'images/' . $f;
            }
        }
        return null;
    }

// helper to resolve icon filename in weapon folder (case-insensitive and small heuristics)
function resolveIconPath($iconImage) {
    if (!$iconImage || $iconImage === '-' || trim($iconImage) === '') return null;
    $weaponDir = __DIR__ . '/../weapon/';
    $candidates = [];
    $candidates[] = $iconImage;
    $candidates[] = ltrim($iconImage, '_');
    $candidates[] = strtolower($iconImage);
    $candidates[] = strtoupper($iconImage);
    // try switching extensions case
    $exts = ['.dds', '.DDS', '.tga', '.TGA'];
    $baseNoExt = preg_replace('/\.[^.]+$/', '', $iconImage);
    foreach ($exts as $e) $candidates[] = $baseNoExt . $e;

    foreach ($candidates as $cand) {
        $path = $weaponDir . $cand;
        if (file_exists($path)) return 'weapon/' . $cand;
    }

    // last resort: try case-insensitive search in weapon dir for filename match
    $files = @scandir($weaponDir);
    if ($files) {
        foreach ($files as $f) {
            if (strcasecmp($f, $iconImage) === 0) return 'weapon/' . $f;
            // try without leading underscore
            if (strcasecmp(ltrim($f, '_'), ltrim($iconImage, '_')) === 0) return 'weapon/' . $f;
        }
    }
    return null;
}
while ($row = $res->fetch_assoc()) {
    // Each row in player_items represents one item entry in the bag
    // Try to be flexible with column names across different schemas
    $itemId = $row['Id'] ?? $row['ItemId'] ?? $row['id'] ?? null;
    $itemKey = $row['ItemKey'] ?? $row['item_key'] ?? $row['ItemNum'] ?? $row['ItemIndex'] ?? $row['ItemId'] ?? null;
    $quantity = $row['ItemCount'] ?? $row['ItemCnt'] ?? $row['Count'] ?? $row['Quantity'] ?? 1;
    $slot = $row['Slot'] ?? $row['slot'] ?? null;

    $name = 'Item';
    $desc = '';
    $iconPath = null;

    // Prefer JSON mapping (id -> icon) for images folder first
    $keyStr = (string)$itemKey;
    $numKey = (string)($itemId ?? $itemKey);
    if ($itemKey !== null && isset($jsonMap[$keyStr])) {
        $icon = $jsonMap[$keyStr];
        $iconPath = resolveImagePath($icon) ?: resolveIconPath($icon);
    } elseif ($numKey !== '' && isset($jsonMap[$numKey])) {
        $icon = $jsonMap[$numKey];
        $iconPath = resolveImagePath($icon) ?: resolveIconPath($icon);
    }

    // Then prefer item.xml metadata for name/desc/icon
    if ($itemKey !== null && isset($itemMap[$keyStr])) {
        $meta = $itemMap[$keyStr];
        if (!empty($meta['name'])) $name = $meta['name'];
        if (!empty($meta['desc'])) $desc = $meta['desc'];
        // only set iconPath if not already found via JSON
        if ($iconPath === null) $iconPath = resolveIconPath($meta['icon']);
    } elseif ($numKey !== '' && isset($itemMap[$numKey])) {
        $meta = $itemMap[$numKey];
        if (!empty($meta['name'])) $name = $meta['name'];
        if (!empty($meta['desc'])) $desc = $meta['desc'];
        if ($iconPath === null) $iconPath = resolveIconPath($meta['icon']);
    }

    // fallback name when nothing found
    if ($name === 'Item') {
        if ($itemKey) $name = 'Item ' . $itemKey;
        elseif ($itemId) $name = 'Item #' . $itemId;
    }

    // fallback icon
    if ($iconPath === null) {
        $default = __DIR__ . '/../weapon/icon_assult_rifle.dds';
        if (file_exists($default)) $iconPath = 'weapon/icon_assult_rifle.dds';
        else $iconPath = null;
    }

    $items[] = [
        'id' => $itemId,
        'key' => $itemKey,
        'name' => $name,
        'description' => $desc ?: ($row['Description'] ?? $row['description'] ?? ''),
        'icon' => $iconPath,
        'quantity' => (int)$quantity,
        'slot' => $slot,
        'raw' => $row
    ];
}
// Return items and a small source hint for debugging
$resp = ['success' => true, 'message' => 'Items fetched', 'items' => $items, 'data' => $items];
if ($source) $resp['source'] = $source; else $resp['source'] = 'none';
echo json_encode($resp);
exit();

?>
