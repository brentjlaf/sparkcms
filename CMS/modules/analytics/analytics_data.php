<?php
// File: analytics_data.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_login();

$pagesFile = __DIR__ . '/../../data/pages.json';
$pages = read_json_file($pagesFile);

$startParam = isset($_GET['start']) ? (string) $_GET['start'] : null;
$endParam = isset($_GET['end']) ? (string) $_GET['end'] : null;

$startTimestamp = null;
$endTimestamp = null;

if ($startParam) {
    $startTime = strtotime($startParam . ' 00:00:00');
    if ($startTime !== false) {
        $startTimestamp = $startTime;
    }
}

if ($endParam) {
    $endTime = strtotime($endParam . ' 23:59:59');
    if ($endTime !== false) {
        $endTimestamp = $endTime;
    }
}

$data = [];
foreach ($pages as $p) {
    $lastModified = isset($p['last_modified']) ? (int) $p['last_modified'] : 0;

    if ($startTimestamp !== null && $lastModified > 0 && $lastModified < $startTimestamp) {
        continue;
    }

    if ($endTimestamp !== null && $lastModified > 0 && $lastModified > $endTimestamp) {
        continue;
    }

    $data[] = [
        'title' => $p['title'],
        'slug' => $p['slug'],
        'views' => $p['views'] ?? 0
    ];
}

usort($data, function ($a, $b) {
    return ($b['views'] ?? 0) <=> ($a['views'] ?? 0);
});

header('Content-Type: application/json');
echo json_encode($data);
