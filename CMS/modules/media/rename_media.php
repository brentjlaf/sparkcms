<?php
// File: rename_media.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_login();

$mediaFile = __DIR__ . '/../../data/media.json';
$media = read_json_file($mediaFile);

$id = sanitize_text($_POST['id'] ?? '');
$newNameRaw = $_POST['name'] ?? '';
$newName = $newNameRaw !== '' ? sanitize_text($newNameRaw) : '';
$titleProvided = array_key_exists('title', $_POST);
$title = $titleProvided ? sanitize_text($_POST['title'] ?? '') : null;
$renameFlag = $_POST['renamePhysical'] ?? null;
$renamePhysical = $renameFlag === null ? true : filter_var($renameFlag, FILTER_VALIDATE_BOOLEAN);

if ($id === '') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

if ($renamePhysical && $newName === '') {
    echo json_encode(['status' => 'error', 'message' => 'File name required']);
    exit;
}
$root = dirname(__DIR__, 2);
$found = false;
foreach ($media as &$item) {
    if ($item['id'] === $id) {
        if ($titleProvided) {
            $item['title'] = $title;
        }

        if ($renamePhysical) {
            $oldPath = $root . '/' . $item['file'];
            if (!file_exists($oldPath)) {
                echo json_encode(['status' => 'error', 'message' => 'File not found']);
                exit;
            }

            $ext = strtolower(pathinfo($oldPath, PATHINFO_EXTENSION));
            $requestedExt = strtolower(pathinfo($newName, PATHINFO_EXTENSION));
            if ($requestedExt && $requestedExt !== $ext) {
                $ext = $requestedExt;
            }
            $base = pathinfo($newName, PATHINFO_FILENAME);
            if ($base === '') {
                $base = pathinfo($item['name'] ?? '', PATHINFO_FILENAME);
            }
            if ($base === '') {
                $base = 'file';
            }
            $safeBase = preg_replace('/[^A-Za-z0-9._-]/', '_', $base);
            $safe = $safeBase . ($ext ? '.' . $ext : '');
            $currentRel = $item['file'];
            $newRel = dirname($currentRel) . '/' . $safe;
            $newRel = ltrim($newRel, './');
            $newPath = $root . '/' . $newRel;

            if ($newPath !== $oldPath) {
                if (file_exists($newPath)) {
                    echo json_encode(['status' => 'error', 'message' => 'File already exists']);
                    exit;
                }
                if (!rename($oldPath, $newPath)) {
                    echo json_encode(['status' => 'error', 'message' => 'Rename failed']);
                    exit;
                }
                @touch($newPath);
            }

            if (!empty($item['thumbnail'])) {
                $thumbOld = $root . '/' . $item['thumbnail'];
                $thumbNewRel = dirname($item['thumbnail']) . '/' . $safe;
                $thumbNewRel = ltrim($thumbNewRel, './');
                $thumbNew = $root . '/' . $thumbNewRel;
                if ($thumbNew !== $thumbOld) {
                    @rename($thumbOld, $thumbNew);
                    @touch($thumbNew);
                }
                $item['thumbnail'] = $thumbNewRel;
            }

            $item['name'] = $safe;
            $item['file'] = $newRel;
            if (!$titleProvided) {
                $item['title'] = $safe;
            }
        } elseif ($titleProvided) {
            // No filesystem rename; ensure we keep cached metadata consistent
            $item['name'] = $item['name'] ?? basename($item['file']);
        }
        if (!array_key_exists('title', $item)) {
            $item['title'] = (string)($item['name'] ?? '');
        }
        $found = true;
        break;
    }
}
unset($item);

if (empty($found)) {
    echo json_encode(['status' => 'error', 'message' => 'File not found']);
    exit;
}

file_put_contents($mediaFile, json_encode($media, JSON_PRETTY_PRINT));

echo json_encode(['status' => 'success']);
