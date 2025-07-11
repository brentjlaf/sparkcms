<?php
// File: update_tags.php
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$mediaFile = __DIR__ . '/../../data/media.json';
$media = file_exists($mediaFile) ? json_decode(file_get_contents($mediaFile), true) : [];

$id = $_POST['id'] ?? '';
$tags = array_filter(array_map('trim', explode(',', $_POST['tags'] ?? '')));

foreach ($media as &$item) {
    if ($item['id'] === $id) {
        $item['tags'] = $tags;
        break;
    }
}
file_put_contents($mediaFile, json_encode($media, JSON_PRETTY_PRINT));

echo json_encode(['status' => 'success']);
