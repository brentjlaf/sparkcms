<?php
// File: delete_page.php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
$pagesFile = __DIR__ . '/../../data/pages.json';
if (!file_exists($pagesFile)) {
    exit('No pages');
}
$pages = json_decode(file_get_contents($pagesFile), true) ?: [];
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$pages = array_filter($pages, function($p) use ($id) { return $p['id'] != $id; });
file_put_contents($pagesFile, json_encode(array_values($pages), JSON_PRETTY_PRINT));
// Update sitemap after a page is deleted
require_once __DIR__ . '/../sitemap/generate.php';

$historyFile = __DIR__ . '/../../data/page_history.json';
$historyData = file_exists($historyFile) ? json_decode(file_get_contents($historyFile), true) : [];
if (!isset($historyData[$id])) $historyData[$id] = [];
$user = $_SESSION['user']['username'] ?? 'Unknown';
$historyData[$id][] = ['time' => time(), 'user' => $user, 'action' => 'deleted page'];
$historyData[$id] = array_slice($historyData[$id], -20);
file_put_contents($historyFile, json_encode($historyData, JSON_PRETTY_PRINT));

echo 'OK';
