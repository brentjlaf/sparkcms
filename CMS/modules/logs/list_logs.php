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
        $context = $entry['context'] ?? (is_numeric($pid) ? 'page' : 'system');
        $details = $entry['details'] ?? [];
        if (!is_array($details)) {
            $details = $details !== '' ? [$details] : [];
        }
        $pageTitle = $entry['page_title'] ?? null;
        if ($pageTitle === null) {
            if ($context === 'system') {
                $pageTitle = 'System activity';
            } else {
                $pageTitle = $pageLookup[$pid] ?? 'Unknown';
            }
        }
        $logs[] = [
            'time' => (int)($entry['time'] ?? 0),
            'user' => $entry['user'] ?? '',
            'page_title' => $pageTitle,
            'action' => $actionLabel,
            'action_slug' => slugify_action_label($actionLabel),
            'details' => $details,
            'context' => $context,
            'meta' => $entry['meta'] ?? new stdClass(),
        ];
    }
}

usort($logs, function($a, $b){ return $b['time'] <=> $a['time']; });
header('Content-Type: application/json');
echo json_encode($logs);
