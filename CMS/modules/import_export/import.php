<?php
// File: import.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/helpers.php';

require_login();

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Invalid request method.']);
    exit;
}

if (!isset($_FILES['import_file'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No import file was provided.']);
    exit;
}

$upload = $_FILES['import_file'];
if (!is_array($upload) || !isset($upload['tmp_name'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid upload payload.']);
    exit;
}

if ($upload['error'] !== UPLOAD_ERR_OK) {
    $message = 'Unable to upload the import file.';
    switch ($upload['error']) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            $message = 'The selected file is too large to import.';
            break;
        case UPLOAD_ERR_PARTIAL:
            $message = 'The import file upload did not complete. Please try again.';
            break;
        case UPLOAD_ERR_NO_FILE:
            $message = 'No import file was selected.';
            break;
    }
    http_response_code(400);
    echo json_encode(['error' => $message]);
    exit;
}

if (!is_uploaded_file($upload['tmp_name'])) {
    http_response_code(400);
    echo json_encode(['error' => 'The uploaded file could not be verified.']);
    exit;
}

$contents = file_get_contents($upload['tmp_name']);
if ($contents === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to read the uploaded file.']);
    exit;
}

if (trim($contents) === '') {
    http_response_code(400);
    echo json_encode(['error' => 'The selected file is empty.']);
    exit;
}

$data = json_decode($contents, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'The selected file is not a valid SparkCMS export.']);
    exit;
}

if (!isset($data['data']) || !is_array($data['data'])) {
    http_response_code(400);
    echo json_encode(['error' => 'The selected file does not contain any data to import.']);
    exit;
}

$datasets = $data['data'];
$dataDir = import_export_get_data_dir();
if (!is_dir($dataDir)) {
    if (!mkdir($dataDir, 0775, true) && !is_dir($dataDir)) {
        http_response_code(500);
        echo json_encode(['error' => 'Unable to prepare the data directory for import.']);
        exit;
    }
}

$datasetMap = import_export_get_dataset_map();
$importedKeys = [];

foreach ($datasetMap as $key => $filename) {
    if (!array_key_exists($key, $datasets)) {
        continue;
    }

    $path = $dataDir . '/' . $filename;
    if (write_json_file($path, $datasets[$key]) === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update the ' . import_export_format_dataset_label($key) . ' data set.']);
        exit;
    }
    $importedKeys[] = $key;
}

$draftsDir = $dataDir . '/drafts';
if (array_key_exists('drafts', $datasets)) {
    $drafts = is_array($datasets['drafts']) ? $datasets['drafts'] : [];

    if (!is_dir($draftsDir)) {
        if (!mkdir($draftsDir, 0775, true) && !is_dir($draftsDir)) {
            http_response_code(500);
            echo json_encode(['error' => 'Unable to prepare the drafts directory for import.']);
            exit;
        }
    }

    $existingDrafts = glob($draftsDir . '/*.json');
    if ($existingDrafts !== false) {
        foreach ($existingDrafts as $draftFile) {
            if (is_file($draftFile)) {
                @unlink($draftFile);
            }
        }
    }

    foreach ($drafts as $draftKey => $draftValue) {
        $safeKey = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $draftKey);
        $safeKey = trim($safeKey, '-_');
        if ($safeKey === '') {
            continue;
        }
        $draftPath = $draftsDir . '/' . $safeKey . '.json';
        if (write_json_file($draftPath, $draftValue) === false) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update draft content during import.']);
            exit;
        }
    }

    $importedKeys[] = 'drafts';
}

if (empty($importedKeys)) {
    http_response_code(400);
    echo json_encode(['error' => 'No recognizable data sets were found in the import file.']);
    exit;
}

$timestamp = gmdate('c');
$originalName = isset($upload['name']) ? basename((string) $upload['name']) : 'import.json';
$datasetCount = count(array_unique($importedKeys));
$datasetLabel = import_export_format_dataset_count_label($datasetCount);

$statsFile = import_export_get_stats_file();
$stats = read_json_file($statsFile);
if (!is_array($stats)) {
    $stats = [];
}

$stats['last_import_at'] = $timestamp;
$stats['last_import_file'] = $originalName;
if (isset($data['meta']) && is_array($data['meta'])) {
    if (isset($data['meta']['available_profiles'])) {
        $stats['available_profiles'] = (int) $data['meta']['available_profiles'];
    }
}

$summaryParts = [];
if ($originalName !== '') {
    $summaryParts[] = $originalName;
}
if ($datasetCount > 0) {
    $summaryParts[] = $datasetLabel;
}
if (isset($data['meta']['site_name']) && $data['meta']['site_name'] !== '') {
    $summaryParts[] = (string) $data['meta']['site_name'];
}

$stats = import_export_append_history_entry($stats, [
    'type' => 'import',
    'timestamp' => $timestamp,
    'label' => 'Import completed',
    'summary' => implode(' • ', $summaryParts),
    'file' => $originalName,
    'dataset_count' => $datasetCount,
]);

if (write_json_file($statsFile, $stats) === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to record import history.']);
    exit;
}

$message = 'Import completed successfully.';
if ($datasetCount > 0) {
    $message .= ' ' . $datasetLabel . ' updated.';
} else {
    $message .= ' No data sets were updated.';
}

echo json_encode([
    'message' => $message,
    'dataset_count' => $datasetCount,
    'datasets' => array_values(array_unique($importedKeys)),
]);
exit;
