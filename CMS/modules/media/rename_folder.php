<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$old = trim($_POST['old'] ?? '');
$new = trim($_POST['new'] ?? '');
if ($old === '' || $new === '') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid folder']);
    exit;
}

$root = dirname(__DIR__,2);
$uploads = $root . '/uploads';
$oldDir = $uploads . '/' . basename($old);
$newDir = $uploads . '/' . basename($new);

if (!is_dir($oldDir)) {
    echo json_encode(['status' => 'error', 'message' => 'Folder not found']);
    exit;
}

if (!rename($oldDir, $newDir)) {
    echo json_encode(['status' => 'error', 'message' => 'Rename failed']);
    exit;
}

$mediaFile = $root . '/data/media.json';
$media = file_exists($mediaFile) ? json_decode(file_get_contents($mediaFile), true) : [];
foreach ($media as &$m) {
    if ($m['folder'] === $old) {
        $m['folder'] = basename($new);
        $m['file'] = preg_replace('#^uploads/' . preg_quote(basename($old), '#') . '#', 'uploads/' . basename($new), $m['file']);
        if (!empty($m['thumbnail'])) {
            $m['thumbnail'] = preg_replace('#^uploads/' . preg_quote(basename($old), '#') . '#', 'uploads/' . basename($new), $m['thumbnail']);
        }
    }
}
file_put_contents($mediaFile, json_encode($media, JSON_PRETTY_PRINT));

echo json_encode(['status' => 'success']);
