<?php
// File: save-content.php
require_once __DIR__ . '/../CMS/includes/auth.php';
require_once __DIR__ . '/../CMS/includes/data.php';
require_login();

$pagesFile = __DIR__ . '/../CMS/data/pages.json';
$pages = read_json_file($pagesFile);

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$content = $_POST['content'] ?? '';
$previousContent = '';

if (!$id) {
    http_response_code(400);
    echo 'Invalid ID';
    exit;
}

foreach ($pages as &$p) {
    if ((int)$p['id'] === $id) {
        $previousContent = $p['content'] ?? '';
        $p['content'] = $content;
        $p['last_modified'] = time();
        $timestamp = $p['last_modified'];
        break;
    }
}
unset($p);

$action = 'updated content';
$oldWordCount = str_word_count(strip_tags($previousContent));
$newWordCount = str_word_count(strip_tags($content));
$wordDiff = $newWordCount - $oldWordCount;
$details = [
    'Word count: ' . $oldWordCount . ' → ' . $newWordCount . ($wordDiff === 0 ? '' : ($wordDiff > 0 ? ' (+' . $wordDiff . ')' : ' (' . $wordDiff . ')')),
];
$details[] = 'Characters: ' . strlen(strip_tags($previousContent)) . ' → ' . strlen(strip_tags($content));

$historyFile = __DIR__ . '/../CMS/data/page_history.json';
$historyData = read_json_file($historyFile);
if (!isset($historyData[$id])) $historyData[$id] = [];
$user = $_SESSION['user']['username'] ?? 'Unknown';
$historyData[$id][] = [
    'time' => $timestamp,
    'user' => $user,
    'action' => $action,
    'details' => $details,
    'context' => 'page',
    'page_id' => $id,
];
$historyData[$id] = array_slice($historyData[$id], -20);

if (!isset($historyData['__system__'])) {
    $historyData['__system__'] = [];
}
$historyData['__system__'][] = [
    'time' => time(),
    'user' => '',
    'action' => 'Regenerated sitemap',
    'details' => [
        'Automatic sitemap refresh after editing content for page ID ' . $id,
    ],
    'context' => 'system',
    'meta' => [
        'trigger' => 'sitemap_regeneration',
        'page_id' => $id,
    ],
    'page_title' => 'CMS Backend',
];
$historyData['__system__'] = array_slice($historyData['__system__'], -50);

file_put_contents($historyFile, json_encode($historyData, JSON_PRETTY_PRINT));

file_put_contents($pagesFile, json_encode($pages, JSON_PRETTY_PRINT));
require_once __DIR__ . '/../CMS/modules/sitemap/generate.php';

// remove saved draft if exists
$draftFile = __DIR__ . '/../CMS/data/drafts/page-' . $id . '.json';
if (is_file($draftFile)) {
    unlink($draftFile);
}

echo 'OK';
