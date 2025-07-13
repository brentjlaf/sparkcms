<?php
// File: save-content.php
require_once __DIR__ . '/../CMS/includes/auth.php';
require_login();

$pagesFile = __DIR__ . '/../CMS/data/pages.json';
$pages = file_exists($pagesFile) ? json_decode(file_get_contents($pagesFile), true) : [];

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$content = $_POST['content'] ?? '';

if (!$id) {
    http_response_code(400);
    echo 'Invalid ID';
    exit;
}

foreach ($pages as &$p) {
    if ((int)$p['id'] === $id) {
        $p['content'] = $content;
        $p['last_modified'] = time();
        $timestamp = $p['last_modified'];
        break;
    }
}
unset($p);

$historyFile = __DIR__ . '/../CMS/data/page_history.json';
$historyData = file_exists($historyFile) ? json_decode(file_get_contents($historyFile), true) : [];
if (!isset($historyData[$id])) $historyData[$id] = [];
$user = $_SESSION['user']['username'] ?? 'Unknown';
$historyData[$id][] = ['time' => $timestamp, 'user' => $user];
$historyData[$id] = array_slice($historyData[$id], -20);
file_put_contents($historyFile, json_encode($historyData, JSON_PRETTY_PRINT));

file_put_contents($pagesFile, json_encode($pages, JSON_PRETTY_PRINT));
require_once __DIR__ . '/../CMS/modules/sitemap/generate.php';

echo 'OK';
