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
