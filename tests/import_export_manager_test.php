<?php
require_once __DIR__ . '/../CMS/modules/import_export/ImportExportManager.php';

function remove_directory(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }

    $items = scandir($directory);
    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $directory . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            remove_directory($path);
        } elseif (is_file($path)) {
            @unlink($path);
        }
    }

    @rmdir($directory);
}

$baseDir = sys_get_temp_dir() . '/sparkcms_ie_' . uniqid('', true);
$sourceDir = $baseDir . '/source';
$targetDir = $baseDir . '/target';

$managerSource = new ImportExportManager($sourceDir);

$fixtures = [
    'settings' => ['site_name' => 'Example Site'],
    'pages' => [
        ['id' => 1, 'title' => 'Home'],
        ['id' => 2, 'title' => 'About'],
    ],
    'forms' => [
        ['id' => 10, 'name' => 'Contact'],
    ],
    'drafts' => [
        'Landing Page Draft' => ['id' => 101, 'title' => 'Landing'],
        '!! invalid key !!' => ['id' => 102, 'title' => 'Offer'],
        '   ' => ['id' => 103, 'title' => 'Whitespace'],
    ],
];

$managerSource->importDatasets($fixtures);

$exportedData = $managerSource->collectExportDatasets();

if (!isset($exportedData['drafts']) || count($exportedData['drafts']) !== 2) {
    remove_directory($baseDir);
    throw new RuntimeException('Export should include sanitized draft entries.');
}

if (!isset($exportedData['settings']['site_name']) || $exportedData['settings']['site_name'] !== 'Example Site') {
    remove_directory($baseDir);
    throw new RuntimeException('Settings dataset did not round-trip correctly during export.');
}

$managerTarget = new ImportExportManager($targetDir);
$importedKeys = $managerTarget->importDatasets($exportedData);

if (!in_array('drafts', $importedKeys, true)) {
    remove_directory($baseDir);
    throw new RuntimeException('Draft datasets were not recorded during import.');
}

$roundTripData = $managerTarget->collectExportDatasets();

if ($roundTripData['pages'][1]['title'] !== 'About') {
    remove_directory($baseDir);
    throw new RuntimeException('Page dataset lost information during import.');
}

if (!isset($roundTripData['drafts']['Landing-Page-Draft'])) {
    remove_directory($baseDir);
    throw new RuntimeException('Draft keys should be sanitized consistently.');
}

$draftFiles = glob($targetDir . '/drafts/*.json');
if ($draftFiles === false || count($draftFiles) !== 2) {
    remove_directory($baseDir);
    throw new RuntimeException('Draft directory should contain exactly two sanitized files.');
}

sort($draftFiles);
if (basename($draftFiles[0]) !== 'Landing-Page-Draft.json' || basename($draftFiles[1]) !== 'invalid-key.json') {
    remove_directory($baseDir);
    throw new RuntimeException('Draft filenames were not sanitized as expected.');
}

$datasetCount = count($roundTripData);
if ($managerTarget->formatDatasetCountLabel($datasetCount) === '') {
    remove_directory($baseDir);
    throw new RuntimeException('Dataset count labels should not be empty.');
}

if (!$managerTarget->recordExport('test-export.json', $datasetCount)) {
    remove_directory($baseDir);
    throw new RuntimeException('Failed to record export statistics.');
}

if (!$managerTarget->recordImport('test-import.json', $datasetCount, ['site_name' => 'Example Site', 'available_profiles' => 3])) {
    remove_directory($baseDir);
    throw new RuntimeException('Failed to record import statistics.');
}

$stats = $managerTarget->readStats();

if (!isset($stats['export_count']) || $stats['export_count'] !== 1) {
    remove_directory($baseDir);
    throw new RuntimeException('Export statistics were not persisted correctly.');
}

if (!isset($stats['last_import_file']) || $stats['last_import_file'] !== 'test-import.json') {
    remove_directory($baseDir);
    throw new RuntimeException('Import metadata was not persisted correctly.');
}

if (!isset($stats['available_profiles']) || $stats['available_profiles'] !== 3) {
    remove_directory($baseDir);
    throw new RuntimeException('Available profile metadata should be retained.');
}

$history = $managerTarget->getHistory($stats);

if (count($history) < 2) {
    remove_directory($baseDir);
    throw new RuntimeException('History entries should include the recorded export and import.');
}

if ($history[0]['type'] !== 'import' || $history[0]['label'] !== 'Import completed') {
    remove_directory($baseDir);
    throw new RuntimeException('Latest history entry should reflect the import operation.');
}

if ($history[1]['type'] !== 'export') {
    remove_directory($baseDir);
    throw new RuntimeException('Second history entry should reflect the export operation.');
}

remove_directory($baseDir);

echo "ImportExportManager integration tests passed\n";
