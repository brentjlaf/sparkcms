<?php
require_once __DIR__ . '/../CMS/includes/auth.php';
require_login();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$historyFile = __DIR__ . '/../CMS/data/page_history.json';
$historyData = file_exists($historyFile) ? json_decode(file_get_contents($historyFile), true) : [];
$entries = isset($historyData[$id]) ? array_slice($historyData[$id], -20) : [];
header('Content-Type: application/json');
echo json_encode(['history' => $entries]);

