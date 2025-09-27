<?php
// File: save_settings.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_login();

header('Content-Type: application/json');

$settingsFile = __DIR__ . '/../../data/settings.json';
$settings = read_json_file($settingsFile);
if (!is_array($settings)) {
    $settings = [];
}

$settings['site_name'] = sanitize_text($_POST['site_name'] ?? ($settings['site_name'] ?? ''));
$settings['tagline'] = sanitize_text($_POST['tagline'] ?? ($settings['tagline'] ?? ''));
$settings['admin_email'] = sanitize_text($_POST['admin_email'] ?? ($settings['admin_email'] ?? ''));
$settings['timezone'] = sanitize_text($_POST['timezone'] ?? ($settings['timezone'] ?? 'America/Los_Angeles'));
$settings['googleAnalytics'] = sanitize_text($_POST['googleAnalytics'] ?? ($settings['googleAnalytics'] ?? ''));
$settings['googleSearchConsole'] = sanitize_text($_POST['googleSearchConsole'] ?? ($settings['googleSearchConsole'] ?? ''));
$settings['facebookPixel'] = sanitize_text($_POST['facebookPixel'] ?? ($settings['facebookPixel'] ?? ''));
$settings['generateSitemap'] = isset($_POST['generateSitemap']);
$settings['allowIndexing'] = isset($_POST['allowIndexing']);

$uploadDir = __DIR__ . '/../../uploads';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$allowedImageTypes = [
    'image/gif',
    'image/jpeg',
    'image/png',
    'image/webp',
];
$maxUploadSize = 5 * 1024 * 1024; // 5 MB

function validate_image_upload(array $file, array $allowedTypes, int $maxSize, string $fieldLabel)
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE || empty($file['tmp_name'])) {
        return ['valid' => true];
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return [
            'valid' => false,
            'message' => sprintf('%s upload failed. Please try again.', $fieldLabel),
        ];
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        return [
            'valid' => false,
            'message' => sprintf('%s upload failed validation.', $fieldLabel),
        ];
    }

    if (isset($file['size']) && $file['size'] > $maxSize) {
        return [
            'valid' => false,
            'message' => sprintf('%s must be smaller than %d MB.', $fieldLabel, (int) ($maxSize / (1024 * 1024))),
        ];
    }

    $mime = null;
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
    }

    if (!$mime && function_exists('getimagesize')) {
        $imageInfo = @getimagesize($file['tmp_name']);
        if ($imageInfo && isset($imageInfo['mime'])) {
            $mime = $imageInfo['mime'];
        }
    }

    if (!$mime || !in_array($mime, $allowedTypes, true)) {
        return [
            'valid' => false,
            'message' => sprintf('%s must be a valid image (PNG, JPG, GIF, or WebP).', $fieldLabel),
        ];
    }

    return ['valid' => true];
}

$logoValidation = validate_image_upload($_FILES['logo'] ?? [], $allowedImageTypes, $maxUploadSize, 'Logo');
if (!$logoValidation['valid']) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $logoValidation['message'],
    ]);
    exit;
}

$ogValidation = validate_image_upload($_FILES['ogImage'] ?? [], $allowedImageTypes, $maxUploadSize, 'Open graph image');
if (!$ogValidation['valid']) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $ogValidation['message'],
    ]);
    exit;
}

if (!empty($_FILES['logo']['name']) && is_uploaded_file($_FILES['logo']['tmp_name'])) {
    $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
    $safe = 'logo_' . uniqid('', true) . '.' . $ext;
    $dest = $uploadDir . '/' . $safe;
    if (move_uploaded_file($_FILES['logo']['tmp_name'], $dest)) {
        $settings['logo'] = 'uploads/' . $safe;
    }
}

$social = is_array($settings['social'] ?? null) ? $settings['social'] : [];
$socialFields = ['facebook', 'twitter', 'instagram', 'linkedin', 'youtube', 'tiktok'];
foreach ($socialFields as $field) {
    $social[$field] = sanitize_url($_POST[$field] ?? ($social[$field] ?? ''));
}
$settings['social'] = $social;

$openGraph = is_array($settings['open_graph'] ?? null) ? $settings['open_graph'] : [];
$openGraph['title'] = sanitize_text($_POST['ogTitle'] ?? ($openGraph['title'] ?? ''));
$openGraph['description'] = sanitize_text($_POST['ogDescription'] ?? ($openGraph['description'] ?? ''));

if (!empty($_FILES['ogImage']['name']) && is_uploaded_file($_FILES['ogImage']['tmp_name'])) {
    $ext = strtolower(pathinfo($_FILES['ogImage']['name'], PATHINFO_EXTENSION));
    $safe = 'og_' . uniqid('', true) . '.' . $ext;
    $dest = $uploadDir . '/' . $safe;
    if (move_uploaded_file($_FILES['ogImage']['tmp_name'], $dest)) {
        $openGraph['image'] = 'uploads/' . $safe;
    }
}

$settings['open_graph'] = $openGraph;
$settings['last_updated'] = date('c');

write_json_file($settingsFile, $settings);

echo json_encode([
    'status' => 'ok',
    'last_updated' => $settings['last_updated'],
    'logo' => $settings['logo'] ?? null,
    'open_graph' => [
        'image' => $openGraph['image'] ?? null,
    ],
]);
