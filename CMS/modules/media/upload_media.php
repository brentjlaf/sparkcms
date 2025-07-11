<?php
// File: upload_media.php
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$allowed = [
    'images' => ['jpg','jpeg','png','gif','webp'],
    'videos' => ['mp4','webm','mov'],
    'documents' => ['pdf','doc','docx','txt']
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $folder = trim($_POST['folder'] ?? '');
    $tags = array_filter(array_map('trim', explode(',', $_POST['tags'] ?? '')));
    $root = dirname(__DIR__, 2);
    $baseDir = $root . '/uploads';
    if ($folder) {
        $baseDir .= '/' . basename($folder);
    }
    if (!is_dir($baseDir)) {
        mkdir($baseDir, 0777, true);
    }

    $mediaFile = $root . '/data/media.json';
    $media = file_exists($mediaFile) ? json_decode(file_get_contents($mediaFile), true) : [];

    $maxOrder = -1;
    foreach ($media as $m) {
        if (isset($m['order']) && $m['order'] > $maxOrder) $maxOrder = $m['order'];
    }
    $order = $maxOrder + 1;

    foreach ($_FILES['files']['name'] as $i => $name) {
        $tmp = $_FILES['files']['tmp_name'][$i];
        $size = $_FILES['files']['size'][$i];
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $category = null;
        foreach ($allowed as $cat => $exts) {
            if (in_array($ext, $exts)) { $category = $cat; break; }
        }
        if (!$category) continue;

        $safe = uniqid() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
        $dest = $baseDir . '/' . $safe;
        if (!move_uploaded_file($tmp, $dest)) continue;

        $thumbPath = null;
        if ($category === 'images') {
            $thumbDir = $baseDir . '/thumbs';
            if (!is_dir($thumbDir)) mkdir($thumbDir, 0777, true);
            $thumbPath = $thumbDir . '/' . $safe;
            create_thumbnail($dest, $thumbPath, 200);
            $thumbPath = str_replace($root . '/', '', $thumbPath);
        }

        $media[] = [
            'id' => uniqid(),
            'name' => $name,
            'file' => str_replace($root . '/', '', $dest),
            'folder' => $folder,
            'size' => $size,
            'type' => $category,
            'uploaded_at' => time(),
            'thumbnail' => $thumbPath,
            'tags' => $tags,
            'order' => $order++
        ];
    }

    file_put_contents($mediaFile, json_encode($media, JSON_PRETTY_PRINT));
    echo json_encode(['status' => 'success']);
    exit;
}

function create_thumbnail($src, $dest, $maxWidth) {
    $info = @getimagesize($src);
    if (!$info) return;
    list($width, $height, $type) = $info;
    $ratio = $maxWidth / $width;
    $newW = $maxWidth;
    $newH = (int)($height * $ratio);
    switch ($type) {
        case IMAGETYPE_JPEG: $img = @imagecreatefromjpeg($src); break;
        case IMAGETYPE_PNG:  $img = @imagecreatefrompng($src); break;
        case IMAGETYPE_GIF:  $img = @imagecreatefromgif($src); break;
        case IMAGETYPE_WEBP: $img = @imagecreatefromwebp($src); break;
        default: return;
    }
    if (!$img) return;
    $thumb = imagecreatetruecolor($newW, $newH);
    imagecopyresampled($thumb, $img, 0,0,0,0,$newW,$newH,$width,$height);
    switch ($type) {
        case IMAGETYPE_JPEG: imagejpeg($thumb, $dest, 80); break;
        case IMAGETYPE_PNG:  imagepng($thumb, $dest); break;
        case IMAGETYPE_GIF:  imagegif($thumb, $dest); break;
        case IMAGETYPE_WEBP: imagewebp($thumb, $dest); break;
    }
    imagedestroy($img);
    imagedestroy($thumb);
}
