<?php
// File: analytics_data.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/analytics.php';
require_login();

$pagesFile = __DIR__ . '/../../data/pages.json';
$pages = read_json_file($pagesFile);

$data = [];
foreach ($pages as $p) {
    $slug = $p['slug'] ?? '';
    $views = $p['views'] ?? 0;
    $data[] = [
        'title' => $p['title'],
        'slug' => $slug,
        'views' => $views,
        'previousViews' => analytics_previous_views($slug, $views),
    ];
}

usort($data, function ($a, $b) {
    return ($b['views'] ?? 0) <=> ($a['views'] ?? 0);
});

header('Content-Type: application/json');
echo json_encode($data);
