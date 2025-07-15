<?php
require_once __DIR__ . '/../CMS/includes/auth.php';
require_once __DIR__ . '/../CMS/includes/data.php';
require_login();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) {
    http_response_code(400);
    echo 'Invalid ID';
    exit;
}

$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
if ($limit <= 0) {
    $limit = 20;
}

$historyFile = __DIR__ . '/../CMS/data/page_history.json';
$historyData = get_cached_json($historyFile);
$entries = isset($historyData[$id]) ? array_slice($historyData[$id], -$limit) : [];
header('Content-Type: application/json');
echo json_encode(['history' => $entries]);

