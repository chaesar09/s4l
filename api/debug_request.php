<?php
// Debug endpoint to inspect incoming request routing
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

$response = [
    'requested_uri' => $_SERVER['REQUEST_URI'] ?? null,
    'script_filename' => $_SERVER['SCRIPT_FILENAME'] ?? null,
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? null,
    'php_self' => $_SERVER['PHP_SELF'] ?? null,
    'method' => $_SERVER['REQUEST_METHOD'] ?? null,
    'query_string' => $_SERVER['QUERY_STRING'] ?? null,
    'headers' => function_exists('getallheaders') ? getallheaders() : null,
    'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null,
];

echo json_encode(['success' => true, 'message' => 'debug', 'data' => $response], JSON_PRETTY_PRINT);
exit;
