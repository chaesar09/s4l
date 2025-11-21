<?php
// Simple DB connectivity test endpoint
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$response = [
    'success' => false,
    'message' => 'Unknown',
    'data' => null
];

if (!isset($conn) || !($conn instanceof mysqli)) {
    $response['message'] = 'No mysqli connection found in config.php';
    echo json_encode($response);
    exit();
}

if ($conn->connect_error) {
    $response['message'] = 'Connection error: ' . $conn->connect_error;
    echo json_encode($response);
    exit();
}

// Try a simple query
$ok = false;
$info = [];
if ($res = $conn->query("SELECT DATABASE() AS db, VERSION() AS version, NOW() AS now")) {
    $row = $res->fetch_assoc();
    $info['database'] = $row['db'];
    $info['version'] = $row['version'];
    $info['now'] = $row['now'];
    $ok = true;
} else {
    $info['error'] = $conn->error;
}

$response['success'] = $ok;
$response['message'] = $ok ? 'Connected to database' : 'Query failed';
$response['data'] = $info;

echo json_encode($response);
exit();
?>