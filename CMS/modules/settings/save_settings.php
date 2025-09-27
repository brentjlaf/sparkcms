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

function delete_upload_file($relativePath, $uploadsRoot, $baseDir)
{
    if (!is_string($relativePath) || $relativePath === '') {
        return;
    }

    $relativePath = ltrim($relativePath, '/');
    if (strpos($relativePath, 'uploads/') !== 0) {
        return;
    }

    if (!$uploadsRoot || !$baseDir) {
        return;
    }

    $fullPath = $baseDir . DIRECTORY_SEPARATOR . $relativePath;
    $realFullPath = realpath($fullPath);

    if ($realFullPath === false) {
        return;
    }

    if (strpos($realFullPath, $uploadsRoot) === 0 && is_file($realFullPath)) {
        @unlink($realFullPath);
    }
}

$uploadDir = __DIR__ . '/../../uploads';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}
$baseDir = realpath(__DIR__ . '/../../');
$uploadsRoot = realpath($uploadDir);

$previousLogo = $settings['logo'] ?? null;

$logoCleared = !empty($_POST['clear_logo']);
$newLogo = $previousLogo;
$logoUploaded = false;

if (!empty($_FILES['logo']['name']) && is_uploaded_file($_FILES['logo']['tmp_name'])) {
    $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
    $safe = 'logo_' . uniqid('', true) . '.' . $ext;
    $dest = $uploadDir . '/' . $safe;
    if (move_uploaded_file($_FILES['logo']['tmp_name'], $dest)) {
        $newLogo = 'uploads/' . $safe;
        $logoUploaded = true;
    }
}

if ($logoCleared && !$logoUploaded) {
    $newLogo = null;
}

if ($newLogo !== null) {
    $settings['logo'] = $newLogo;
} else {
    unset($settings['logo']);
}

if ($previousLogo && $previousLogo !== $newLogo) {
    delete_upload_file($previousLogo, $uploadsRoot, $baseDir);
}

$social = is_array($settings['social'] ?? null) ? $settings['social'] : [];
$socialFields = ['facebook', 'twitter', 'instagram', 'linkedin', 'youtube', 'tiktok'];
foreach ($socialFields as $field) {
    $social[$field] = sanitize_url($_POST[$field] ?? ($social[$field] ?? ''));
}
$settings['social'] = $social;

$openGraph = is_array($settings['open_graph'] ?? null) ? $settings['open_graph'] : [];
$previousOgImage = $openGraph['image'] ?? null;
$openGraph['title'] = sanitize_text($_POST['ogTitle'] ?? ($openGraph['title'] ?? ''));
$openGraph['description'] = sanitize_text($_POST['ogDescription'] ?? ($openGraph['description'] ?? ''));

$ogCleared = !empty($_POST['clear_og_image']);
$newOgImage = $previousOgImage;
$ogUploaded = false;

if (!empty($_FILES['ogImage']['name']) && is_uploaded_file($_FILES['ogImage']['tmp_name'])) {
    $ext = strtolower(pathinfo($_FILES['ogImage']['name'], PATHINFO_EXTENSION));
    $safe = 'og_' . uniqid('', true) . '.' . $ext;
    $dest = $uploadDir . '/' . $safe;
    if (move_uploaded_file($_FILES['ogImage']['tmp_name'], $dest)) {
        $newOgImage = 'uploads/' . $safe;
        $ogUploaded = true;
    }
}

if ($ogCleared && !$ogUploaded) {
    $newOgImage = null;
}

if ($newOgImage !== null) {
    $openGraph['image'] = $newOgImage;
} else {
    unset($openGraph['image']);
}

if ($previousOgImage && $previousOgImage !== $newOgImage) {
    delete_upload_file($previousOgImage, $uploadsRoot, $baseDir);
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
