<?php
// File: export.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/settings.php';
require_once __DIR__ . '/helpers.php';

require_login();

$dataDir = import_export_get_data_dir();
$datasetMap = import_export_get_dataset_map();

$export = [
    'meta' => [
        'generated_at' => gmdate('c'),
        'format_version' => 1,
    ],
    'data' => [],
];

$settings = get_site_settings();
if (!empty($settings['site_name'])) {
    $export['meta']['site_name'] = (string) $settings['site_name'];
}

foreach ($datasetMap as $key => $filename) {
    $path = $dataDir . '/' . $filename;
    $export['data'][$key] = read_json_file($path);
}

$draftsDir = $dataDir . '/drafts';
if (is_dir($draftsDir)) {
    $draftFiles = glob($draftsDir . '/*.json');
    if ($draftFiles !== false && count($draftFiles) > 0) {
        $drafts = [];
        foreach ($draftFiles as $draftFile) {
            $drafts[basename($draftFile, '.json')] = read_json_file($draftFile);
        }
        $export['data']['drafts'] = $drafts;
    }
}

$export['meta']['dataset_count'] = count($export['data']);
$export['meta']['datasets'] = array_keys($export['data']);

$json = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($json === false) {
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['error' => 'Unable to generate export.']);
    exit;
}

$filename = 'sparkcms-export-' . date('Ymd-His') . '.json';

$statsFile = import_export_get_stats_file();
$stats = read_json_file($statsFile);
if (!is_array($stats)) {
    $stats = [];
}
$stats['last_export_at'] = gmdate('c');
$stats['last_export_file'] = $filename;
$stats['export_count'] = isset($stats['export_count']) ? (int) $stats['export_count'] + 1 : 1;
$datasetCount = isset($export['meta']['dataset_count']) ? (int) $export['meta']['dataset_count'] : 0;
$stats = import_export_append_history_entry($stats, [
    'type' => 'export',
    'timestamp' => $stats['last_export_at'],
    'label' => 'Export generated',
    'summary' => $filename . ' • ' . import_export_format_dataset_count_label($datasetCount),
    'file' => $filename,
    'dataset_count' => $datasetCount,
]);
write_json_file($statsFile, $stats);

header('Content-Type: application/json; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

echo $json;
exit;
