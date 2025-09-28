<?php
// File: status.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/helpers.php';

require_login();

header('Content-Type: application/json; charset=UTF-8');

$statsFile = import_export_get_stats_file();
$stats = read_json_file($statsFile);
if (!is_array($stats)) {
    $stats = [];
}

$datasetMap = import_export_get_dataset_map();
$datasets = array_keys($datasetMap);

$dataDir = import_export_get_data_dir();
$draftsDir = $dataDir . '/drafts';
if (is_dir($draftsDir)) {
    $draftFiles = glob($draftsDir . '/*.json');
    if ($draftFiles !== false && count($draftFiles) > 0) {
        $datasets[] = 'drafts';
    }
}

$response = [
    'last_export_at' => isset($stats['last_export_at']) && $stats['last_export_at'] !== '' ? $stats['last_export_at'] : null,
    'last_import_at' => isset($stats['last_import_at']) && $stats['last_import_at'] !== '' ? $stats['last_import_at'] : null,
    'export_count' => isset($stats['export_count']) ? (int) $stats['export_count'] : 0,
    'available_profiles' => isset($stats['available_profiles']) ? (int) $stats['available_profiles'] : 0,
    'datasets' => $datasets,
    'dataset_count' => count($datasets),
];

echo json_encode($response);
exit;
