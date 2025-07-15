<?php
// File: list_media.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_login();

$mediaFile = __DIR__ . '/../../data/media.json';
$media = file_exists($mediaFile) ? json_decode(file_get_contents($mediaFile), true) : [];
$query = strtolower(sanitize_text($_GET['q'] ?? ''));
$folder = sanitize_text($_GET['folder'] ?? '');

usort($media, function($a,$b){
    return ($a['order'] ?? 0) <=> ($b['order'] ?? 0);
});

$results = array_filter($media, function($item) use ($query, $folder) {
    if ($folder !== '' && $item['folder'] !== $folder) return false;
    if ($query && stripos($item['name'], $query) === false &&
        (!isset($item['tags']) || stripos(implode(',', $item['tags']), $query) === false)) return false;
    return true;
});

$root = dirname(__DIR__,2);
$default = $root.'/uploads/general';
if(!is_dir($default)) mkdir($default, 0777, true);
$folderDirs = array_filter(glob($root.'/uploads/*'), 'is_dir');
$folders = [];
foreach ($folderDirs as $dir) {
    $name = basename($dir);
    $thumb = null;
    foreach ($media as $m) {
        if (($m['folder'] ?? '') === $name && ($m['type'] ?? '') === 'images') {
            $thumb = $m['thumbnail'] ?: $m['file'];
            break;
        }
    }
    if (!$thumb) {
        $candidates = glob($dir . '/thumbs/*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);
        if (!$candidates) $candidates = glob($dir.'/*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);
        if ($candidates) {
            $thumb = str_replace($root.'/', '', $candidates[0]);
        }
    }
    $folders[] = ['name' => $name, 'thumbnail' => $thumb];
}

foreach ($results as &$item) {
    $path = $root . '/' . $item['file'];
    if (is_file($path)) {
        $item['modified_at'] = filemtime($path);
        if ($item['type'] === 'images') {
            $info = @getimagesize($path);
            if ($info) {
                $item['width'] = $info[0];
                $item['height'] = $info[1];
            }
        }
    }
}

header('Content-Type: application/json');
echo json_encode(['media'=>array_values($results), 'folders'=>$folders]);
?>
