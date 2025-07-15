<?php
// File: delete_page.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../includes/data.php';
require_login();
$pagesFile = __DIR__ . '/../../data/pages.json';
if (!file_exists($pagesFile)) {
    exit('No pages');
}
$pages = read_json_file($pagesFile);
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;
$deletedPage = null;
foreach ($pages as $p) {
    if ($p['id'] == $id) { $deletedPage = $p; break; }
}
$pages = array_filter($pages, function($p) use ($id) { return $p['id'] != $id; });
write_json_file($pagesFile, array_values($pages));
// Update sitemap after a page is deleted
require_once __DIR__ . '/../sitemap/generate.php';

$historyFile = __DIR__ . '/../../data/page_history.json';
$historyData = read_json_file($historyFile);
if (!isset($historyData[$id])) $historyData[$id] = [];
$user = $_SESSION['user']['username'] ?? 'Unknown';
$action = 'deleted page';
if ($deletedPage && !empty($deletedPage['template'])) {
    $action .= ' (' . $deletedPage['template'] . ')';
}
$historyData[$id][] = ['time' => time(), 'user' => $user, 'action' => $action];
$historyData[$id] = array_slice($historyData[$id], -20);
write_json_file($historyFile, $historyData);

echo 'OK';
