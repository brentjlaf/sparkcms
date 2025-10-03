<?php
// File: import.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/ImportExportManager.php';

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
$manager = new ImportExportManager();

try {
    $importedKeys = $manager->importDatasets($datasets);
} catch (RuntimeException $exception) {
    http_response_code(500);
    echo json_encode(['error' => $exception->getMessage()]);
    exit;
}

if (empty($importedKeys)) {
    http_response_code(400);
    echo json_encode(['error' => 'No recognizable data sets were found in the import file.']);
    exit;
}

$originalName = isset($upload['name']) ? basename((string) $upload['name']) : 'import.json';
$datasetCount = count(array_unique($importedKeys));
$datasetLabel = $manager->formatDatasetCountLabel($datasetCount);

$meta = isset($data['meta']) && is_array($data['meta']) ? $data['meta'] : [];
if (isset($meta['site_name'])) {
    $meta['site_name'] = (string) $meta['site_name'];
}

if (!$manager->recordImport($originalName, $datasetCount, $meta)) {
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
