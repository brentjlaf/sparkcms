<?php
// File: list_media.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/MediaLibrary.php';

require_login();

$mediaFile = __DIR__ . '/../../data/media.json';
$rootDir = dirname(__DIR__, 2);

$query = sanitize_text($_GET['q'] ?? '');
$folder = sanitize_text($_GET['folder'] ?? '');
$sort = strtolower(sanitize_text($_GET['sort'] ?? MediaLibrary::DEFAULT_SORT));
if (!in_array($sort, MediaLibrary::ALLOWED_SORTS, true)) {
    $sort = MediaLibrary::DEFAULT_SORT;
}
$order = strtolower(sanitize_text($_GET['order'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

$limit = 0;
if (isset($_GET['limit'])) {
    $limitValue = filter_var($_GET['limit'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    $limit = $limitValue !== false ? $limitValue : 0;
}
$offset = 0;
if (isset($_GET['offset'])) {
    $offsetValue = filter_var($_GET['offset'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    $offset = $offsetValue !== false ? $offsetValue : 0;
}

$library = new MediaLibrary($mediaFile, $rootDir);

$response = $library->listMedia([
    'query' => $query,
    'folder' => $folder,
    'sort' => $sort,
    'order' => $order,
    'limit' => $limit,
    'offset' => $offset,
]);
$response['folders'] = $library->listFolders();

header('Content-Type: application/json');
echo json_encode($response);
?>
