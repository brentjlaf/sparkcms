<?php
// File: export.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/AnalyticsService.php';

require_login();

$filter = isset($_GET['filter']) ? strtolower((string) $_GET['filter']) : 'all';
$search = isset($_GET['search']) ? trim((string) $_GET['search']) : '';

$service = new AnalyticsService();
$allowedFilters = ['all', 'top', 'growing', 'no-views'];
if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'all';
}

$filtered = $service->filterForExport($filter, $search);

$filename = 'analytics-export-' . date('Ymd-His') . '.csv';

$output = fopen('php://output', 'wb');
if ($output === false) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unable to generate export.']);
    exit;
}

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

fputcsv($output, ['Title', 'Slug', 'Views', 'Segment', 'Rank']);

foreach ($filtered as $entry) {
    $slug = $entry['slug'] !== '' ? '/' . ltrim($entry['slug'], '/') : '/';
    fputcsv($output, [
        $entry['title'],
        $slug,
        $entry['views'],
        $entry['label'],
        $entry['rank'],
    ]);
}

fclose($output);
exit;
