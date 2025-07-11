<?php
// File: save_settings.php
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$settingsFile = __DIR__ . '/../../data/settings.json';
$settings = file_exists($settingsFile) ? json_decode(file_get_contents($settingsFile), true) : [];

$settings['site_name'] = trim($_POST['site_name'] ?? ($settings['site_name'] ?? ''));
$settings['tagline'] = trim($_POST['tagline'] ?? ($settings['tagline'] ?? ''));
$settings['admin_email'] = trim($_POST['admin_email'] ?? ($settings['admin_email'] ?? ''));

$uploadDir = __DIR__ . '/../../uploads';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

if (!empty($_FILES['logo']['name']) && is_uploaded_file($_FILES['logo']['tmp_name'])) {
    $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
    $safe = 'logo_' . uniqid() . '.' . $ext;
    $dest = $uploadDir . '/' . $safe;
    if (move_uploaded_file($_FILES['logo']['tmp_name'], $dest)) {
        $settings['logo'] = 'uploads/' . $safe;
    }
}

file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT));

echo 'OK';
?>
