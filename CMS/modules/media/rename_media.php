<?php
// File: rename_media.php
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$mediaFile = __DIR__ . '/../../data/media.json';
$media = file_exists($mediaFile) ? json_decode(file_get_contents($mediaFile), true) : [];

$id = $_POST['id'] ?? '';
$newName = trim($_POST['name'] ?? '');
if ($id === '' || $newName === '') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}
$root = dirname(__DIR__, 2);
$found = false;
foreach ($media as &$item) {
    if ($item['id'] === $id) {
        $oldPath = $root . '/' . $item['file'];
        $ext = pathinfo($oldPath, PATHINFO_EXTENSION);
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', pathinfo($newName, PATHINFO_FILENAME)) . '.' . $ext;
        $newRel = dirname($item['file']) . '/' . $safe;
        $newPath = $root . '/' . $newRel;

        if (!file_exists($oldPath)) {
            echo json_encode(['status' => 'error', 'message' => 'File not found']);
            exit;
        }
        if (file_exists($newPath)) {
            echo json_encode(['status' => 'error', 'message' => 'File already exists']);
            exit;
        }
        if (!rename($oldPath, $newPath)) {
            echo json_encode(['status' => 'error', 'message' => 'Rename failed']);
            exit;
        }

        @touch($newPath);
        if (!empty($item['thumbnail'])) {
            $thumbOld = $root . '/' . $item['thumbnail'];
            $thumbNewRel = dirname($item['thumbnail']) . '/' . $safe;
            $thumbNew = $root . '/' . $thumbNewRel;
            @rename($thumbOld, $thumbNew);
            @touch($thumbNew);
            $item['thumbnail'] = $thumbNewRel;
        }
        $item['name'] = $safe;
        $item['file'] = $newRel;
        $found = true;
        break;
    }
}

if (empty($found)) {
    echo json_encode(['status' => 'error', 'message' => 'File not found']);
    exit;
}

file_put_contents($mediaFile, json_encode($media, JSON_PRETTY_PRINT));

echo json_encode(['status' => 'success']);
