<?php
// File: export.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/settings.php';
require_once __DIR__ . '/ImportExportManager.php';

require_login();

$manager = new ImportExportManager();

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

$export['data'] = $manager->collectExportDatasets();

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

$datasetCount = isset($export['meta']['dataset_count']) ? (int) $export['meta']['dataset_count'] : 0;
$manager->recordExport($filename, $datasetCount);

header('Content-Type: application/json; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

echo $json;
exit;
