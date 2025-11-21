<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// Get clans
 $stmt = $conn->prepare("SELECT c.*, a.Username as OwnerUsername, COUNT(cm.Id) as MemberCount FROM clans c LEFT JOIN accounts a ON c.OwnerId = a.Id LEFT JOIN clan_members cm ON c.Id = cm.ClanId GROUP BY c.Id ORDER BY c.Id DESC");
 $stmt->execute();
 $result = $stmt->get_result();

 $clans = [];
while ($row = $result->fetch_assoc()) {
    $clans[] = $row;
}

sendResponse(true, 'Data clan berhasil diambil', $clans);
?>