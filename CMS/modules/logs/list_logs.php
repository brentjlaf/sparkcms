<?php
// File: list_logs.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/LogFormatter.php';

require_login();

$pagesFile = __DIR__ . '/../../data/pages.json';
$pages = read_json_file($pagesFile);

$historyFile = __DIR__ . '/../../data/page_history.json';
$historyData = read_json_file($historyFile);

$formatter = new LogFormatter($pages);
$logs = $formatter->format($historyData);

header('Content-Type: application/json');
echo json_encode($logs);
