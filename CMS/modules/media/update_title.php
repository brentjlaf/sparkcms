<?php
// File: update_title.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_login();

$mediaFile = __DIR__ . '/../../data/media.json';
$media = read_json_file($mediaFile);

$id = sanitize_text($_POST['id'] ?? '');
$title = sanitize_text($_POST['title'] ?? '');

if ($id === '') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$found = false;
foreach ($media as &$item) {
    if (($item['id'] ?? '') === $id) {
        $item['title'] = $title;
        $found = true;
        break;
    }
}
unset($item);

if (!$found) {
    echo json_encode(['status' => 'error', 'message' => 'File not found']);
    exit;
}

write_json_file($mediaFile, $media);

echo json_encode(['status' => 'success', 'title' => $title]);
