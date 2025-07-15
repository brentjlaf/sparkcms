<?php
// File: list_logs.php
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$pagesFile = __DIR__ . '/../../data/pages.json';
$pages = file_exists($pagesFile) ? json_decode(file_get_contents($pagesFile), true) : [];
$pageLookup = [];
foreach ($pages as $p) {
    $pageLookup[$p['id']] = $p['title'];
}

$historyFile = __DIR__ . '/../../data/page_history.json';
$historyData = file_exists($historyFile) ? json_decode(file_get_contents($historyFile), true) : [];

$logs = [];
foreach ($historyData as $pid => $entries) {
    foreach ($entries as $entry) {
        $logs[] = [
            'time' => $entry['time'] ?? 0,
            'user' => $entry['user'] ?? '',
            'page_title' => $pageLookup[$pid] ?? 'Unknown',
            'action' => $entry['action'] ?? ''
        ];
    }
}

usort($logs, function($a, $b){ return $b['time'] <=> $a['time']; });
header('Content-Type: application/json');
echo json_encode($logs);
