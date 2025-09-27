<?php
// File: rename_folder.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/sanitize.php';
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

if ($oldDir === $newDir) {
    echo json_encode(['status' => 'error', 'message' => 'The folder name is unchanged.']);
    exit;
}

if (file_exists($newDir)) {
    echo json_encode(['status' => 'error', 'message' => 'A folder with that name already exists.']);
    exit;
}

error_clear_last();
if (!@rename($oldDir, $newDir)) {
    $error = error_get_last();
    $details = $error['message'] ?? 'Rename failed due to an unknown error.';
    $details = preg_replace('/^warning:\s*/i', '', $details);
    if (strpos($details, 'rename(') !== false) {
        $details = preg_replace('/rename\([^)]*\):\s*/i', '', $details);
    }
    echo json_encode([
        'status' => 'error',
        'message' => 'Rename failed: ' . $details,
    ]);
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
file_put_contents($mediaFile, json_encode($media, JSON_PRETTY_PRINT));

echo json_encode(['status' => 'success']);
