<?php
// File: update_tags.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_login();

$mediaFile = __DIR__ . '/../../data/media.json';
$media = file_exists($mediaFile) ? json_decode(file_get_contents($mediaFile), true) : [];

$id = sanitize_text($_POST['id'] ?? '');
$tags = sanitize_tags(explode(',', $_POST['tags'] ?? ''));

foreach ($media as &$item) {
    if ($item['id'] === $id) {
        $item['tags'] = $tags;
        break;
    }
}
file_put_contents($mediaFile, json_encode($media, JSON_PRETTY_PRINT));

echo json_encode(['status' => 'success']);
