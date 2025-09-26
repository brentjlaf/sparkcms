<?php
// File: list_logs.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_login();

$pagesFile = __DIR__ . '/../../data/pages.json';
$pages = read_json_file($pagesFile);
$pageLookup = [];
foreach ($pages as $p) {
    $pageLookup[$p['id']] = $p['title'];
}

$historyFile = __DIR__ . '/../../data/page_history.json';
$historyData = read_json_file($historyFile);

$logs = [];

function normalize_action_label(?string $action): string {
    $label = trim((string)($action ?? ''));
    return $label !== '' ? $label : 'Updated content';
}

function slugify_action_label(string $label): string {
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $label));
    $slug = trim($slug ?? '', '-');
    return $slug !== '' ? $slug : 'unknown';
}

foreach ($historyData as $pid => $entries) {
    foreach ($entries as $entry) {
        $actionLabel = normalize_action_label($entry['action'] ?? '');
        $logs[] = [
            'time' => (int)($entry['time'] ?? 0),
            'user' => $entry['user'] ?? '',
            'page_title' => $pageLookup[$pid] ?? 'Unknown',
            'action' => $actionLabel,
            'action_slug' => slugify_action_label($actionLabel),
        ];
    }
}

usort($logs, function($a, $b){ return $b['time'] <=> $a['time']; });
header('Content-Type: application/json');
echo json_encode($logs);
