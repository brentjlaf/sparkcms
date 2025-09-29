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
$datasetMetadata = import_export_get_dataset_metadata();

$dataDir = import_export_get_data_dir();
$draftsDir = $dataDir . '/drafts';
if (is_dir($draftsDir)) {
    $draftFiles = glob($draftsDir . '/*.json');
    if ($draftFiles !== false && count($draftFiles) > 0) {
        $datasets[] = 'drafts';
    }
}

$datasetDetails = [];
foreach ($datasets as $datasetKey) {
    $meta = $datasetMetadata[$datasetKey] ?? [];
    $datasetDetails[] = [
        'key' => $datasetKey,
        'label' => isset($meta['label']) ? (string) $meta['label'] : import_export_format_dataset_label($datasetKey),
        'description' => isset($meta['description']) ? (string) $meta['description'] : '',
    ];
}

$historyEntries = [];
if (isset($stats['history']) && is_array($stats['history'])) {
    foreach ($stats['history'] as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $type = isset($entry['type']) ? (string) $entry['type'] : 'activity';
        $historyEntries[] = [
            'type' => $type,
            'timestamp' => isset($entry['timestamp']) && $entry['timestamp'] !== '' ? (string) $entry['timestamp'] : null,
            'label' => isset($entry['label']) && $entry['label'] !== '' ? (string) $entry['label'] : ($type === 'import' ? 'Import completed' : ($type === 'export' ? 'Export completed' : 'Activity recorded')),
            'summary' => isset($entry['summary']) ? (string) $entry['summary'] : '',
            'file' => isset($entry['file']) ? (string) $entry['file'] : null,
            'dataset_count' => isset($entry['dataset_count']) ? (int) $entry['dataset_count'] : null,
        ];
    }
}

$response = [
    'last_export_at' => isset($stats['last_export_at']) && $stats['last_export_at'] !== '' ? $stats['last_export_at'] : null,
    'last_import_at' => isset($stats['last_import_at']) && $stats['last_import_at'] !== '' ? $stats['last_import_at'] : null,
    'export_count' => isset($stats['export_count']) ? (int) $stats['export_count'] : 0,
    'available_profiles' => isset($stats['available_profiles']) ? (int) $stats['available_profiles'] : 0,
    'datasets' => $datasets,
    'dataset_details' => $datasetDetails,
    'dataset_count' => count($datasets),
    'dataset_count_label' => import_export_format_dataset_count_label(count($datasets)),
    'history' => $historyEntries,
    'history_count' => count($historyEntries),
];

echo json_encode($response);
exit;
