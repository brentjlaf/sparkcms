<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$folder = trim($_POST['folder'] ?? '');
if ($folder === '') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid folder']);
    exit;
}

$root = dirname(__DIR__, 2);
$dir = $root . '/uploads/' . basename($folder);
if (!is_dir($dir)) {
    echo json_encode(['status' => 'error', 'message' => 'Folder not found']);
    exit;
}

$mediaFile = $root . '/data/media.json';
$media = file_exists($mediaFile) ? json_decode(file_get_contents($mediaFile), true) : [];
$new = [];
foreach ($media as $m) {
    if ($m['folder'] === $folder) {
        $file = $root . '/' . $m['file'];
        if (is_file($file)) @unlink($file);
        if (!empty($m['thumbnail'])) {
            $thumb = $root . '/' . $m['thumbnail'];
            if (is_file($thumb)) @unlink($thumb);
        }
    } else {
        $new[] = $m;
    }
}
file_put_contents($mediaFile, json_encode(array_values($new), JSON_PRETTY_PRINT));

// remove directory recursively
$iterator = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
$files = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::CHILD_FIRST);
$success = true;
foreach ($files as $file) {
    if ($file->isDir()) {
        if (!@rmdir($file->getPathname())) {
            $success = false;
        }
    } else {
        if (!@unlink($file->getPathname())) {
            $success = false;
        }
    }
}
if (!@rmdir($dir)) {
    $success = false;
}

if (!$success) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to delete folder']);
    exit;
}

echo json_encode(['status' => 'success']);

