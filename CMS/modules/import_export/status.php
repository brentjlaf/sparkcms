<?php
// File: status.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/ImportExportManager.php';

require_login();

header('Content-Type: application/json; charset=UTF-8');

$manager = new ImportExportManager();
$stats = $manager->readStats();
$datasets = $manager->getAvailableDatasets();
$datasetDetails = $manager->getDatasetDetails($datasets);
$historyEntries = $manager->getHistory($stats);
$datasetCount = count($datasets);

$response = [
    'last_export_at' => isset($stats['last_export_at']) && $stats['last_export_at'] !== '' ? $stats['last_export_at'] : null,
    'last_import_at' => isset($stats['last_import_at']) && $stats['last_import_at'] !== '' ? $stats['last_import_at'] : null,
    'export_count' => isset($stats['export_count']) ? (int) $stats['export_count'] : 0,
    'available_profiles' => isset($stats['available_profiles']) ? (int) $stats['available_profiles'] : 0,
    'datasets' => $datasets,
    'dataset_details' => $datasetDetails,
    'dataset_count' => $datasetCount,
    'dataset_count_label' => $manager->formatDatasetCountLabel($datasetCount),
    'history' => $historyEntries,
    'history_count' => count($historyEntries),
];

echo json_encode($response);
exit;
