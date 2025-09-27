<?php
// File: upload_media.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_login();

$allowed = [
    'images' => ['jpg','jpeg','png','gif','webp'],
    'videos' => ['mp4','webm','mov'],
    'documents' => ['pdf','doc','docx','txt']
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $folder = sanitize_text($_POST['folder'] ?? '');
    $tags = sanitize_tags(explode(',', $_POST['tags'] ?? ''));
    $root = dirname(__DIR__, 2);
    $baseDir = $root . '/uploads';
    if ($folder) {
        $baseDir .= '/' . basename($folder);
    }
    if (!is_dir($baseDir)) {
        mkdir($baseDir, 0777, true);
    }

    $mediaFile = $root . '/data/media.json';
    $media = read_json_file($mediaFile);

    $maxOrder = -1;
    foreach ($media as $m) {
        if (isset($m['order']) && $m['order'] > $maxOrder) $maxOrder = $m['order'];
    }
    $order = $maxOrder + 1;

    header('Content-Type: application/json');

    if (!isset($_FILES['files'])) {
        echo json_encode([
            'status' => 'error',
            'message' => 'No files uploaded.'
        ]);
        exit;
    }

    $errors = [];
    $newEntries = [];

    foreach ($_FILES['files']['name'] as $i => $name) {
        $originalName = $name;
        $displayName = sanitize_text($originalName) ?: $originalName;
        $errorCode = $_FILES['files']['error'][$i] ?? UPLOAD_ERR_NO_FILE;

        if ($errorCode !== UPLOAD_ERR_OK) {
            $errors[] = upload_error_message($displayName, $errorCode);
            continue;
        }

        $tmp = $_FILES['files']['tmp_name'][$i];
        $size = $_FILES['files']['size'][$i];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $category = null;
        foreach ($allowed as $cat => $exts) {
            if (in_array($ext, $exts)) { $category = $cat; break; }
        }
        if (!$category) {
            $errors[] = $displayName . ' is not a supported file type.';
            continue;
        }

        if (!is_uploaded_file($tmp)) {
            $errors[] = 'Could not validate the upload for ' . $displayName . '.';
            continue;
        }

        $safe = uniqid() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName);
        $dest = $baseDir . '/' . $safe;
        if (!move_uploaded_file($tmp, $dest)) {
            $errors[] = 'Failed to save ' . $displayName . '.';
            continue;
        }

        $thumbPath = null;
        if ($category === 'images') {
            $thumbDir = $baseDir . '/thumbs';
            if (!is_dir($thumbDir)) mkdir($thumbDir, 0777, true);
            $thumbPath = $thumbDir . '/' . $safe;
            create_thumbnail($dest, $thumbPath, 200);
            if (file_exists($thumbPath)) {
                $thumbPath = str_replace($root . '/', '', $thumbPath);
            } else {
                $thumbPath = null;
            }
        }

        $newEntries[] = [
            'id' => uniqid(),
            'name' => $originalName,
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

    if (!empty($newEntries)) {
        $media = array_merge($media, $newEntries);
        file_put_contents($mediaFile, json_encode($media, JSON_PRETTY_PRINT));
    }

    $response = [
        'status' => !empty($newEntries) ? 'success' : 'error',
        'uploaded' => count($newEntries)
    ];

    if (!empty($errors)) {
        $response['errors'] = $errors;
        if (!empty($newEntries)) {
            $response['partial'] = true;
            $response['message'] = 'Some files could not be uploaded.';
        } else {
            $response['message'] = 'No files were uploaded.';
        }
    } elseif (empty($newEntries)) {
        $response['message'] = 'No files to upload.';
    }

    echo json_encode($response);
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

function upload_error_message(string $name, int $code): string {
    $limit = get_php_upload_limit();
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            $limitText = $limit ? format_bytes($limit) : 'the server limit';
            return $name . ' exceeds the maximum upload size of ' . $limitText . '.';
        case UPLOAD_ERR_PARTIAL:
            return $name . ' was only partially uploaded. Please try again.';
        case UPLOAD_ERR_NO_FILE:
            return 'No file was uploaded for ' . $name . '.';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Missing a temporary folder for ' . $name . '. Contact support.';
        case UPLOAD_ERR_CANT_WRITE:
            return 'The server could not write ' . $name . ' to disk.';
        case UPLOAD_ERR_EXTENSION:
            return 'A server extension stopped the upload of ' . $name . '.';
        default:
            return 'An unknown error occurred while uploading ' . $name . '.';
    }
}

function get_php_upload_limit(): ?int {
    $limits = [ini_get('upload_max_filesize'), ini_get('post_max_size')];
    $bytes = array_filter(array_map('convert_shorthand_to_bytes', $limits));
    if (empty($bytes)) {
        return null;
    }
    return (int) min($bytes);
}

function convert_shorthand_to_bytes($value): ?int {
    if (!$value) return null;
    $value = trim($value);
    if ($value === '') return null;
    $last = strtolower(substr($value, -1));
    $number = (float) $value;
    switch ($last) {
        case 'g':
            $number *= 1024;
            // no break
        case 'm':
            $number *= 1024;
            // no break
        case 'k':
            $number *= 1024;
            break;
    }
    return (int) round($number);
}

function format_bytes(int $bytes): string {
    if ($bytes <= 0) {
        return '0 B';
    }
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = (int) floor(log($bytes, 1024));
    $i = min($i, count($units) - 1);
    $value = $bytes / pow(1024, $i);
    return number_format($value, $value >= 10 ? 0 : 1) . ' ' . $units[$i];
}
