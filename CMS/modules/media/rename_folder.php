<?php
// File: rename_folder.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../includes/data.php';
require_login();

$old = sanitize_text($_POST['old'] ?? '');
$new = sanitize_text($_POST['new'] ?? '');
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
$media = read_json_file($mediaFile);
foreach ($media as &$m) {
    if ($m['folder'] === $old) {
        $m['folder'] = basename($new);
        $m['file'] = preg_replace('#^uploads/' . preg_quote(basename($old), '#') . '#', 'uploads/' . basename($new), $m['file']);
        if (!empty($m['thumbnail'])) {
            $m['thumbnail'] = preg_replace('#^uploads/' . preg_quote(basename($old), '#') . '#', 'uploads/' . basename($new), $m['thumbnail']);
        }
    }
}
write_json_file($mediaFile, $media);

echo json_encode(['status' => 'success']);
