<?php
// File: set_home.php
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$slug = trim($_POST['slug'] ?? '');
if ($slug === '') {
    http_response_code(400);
    echo 'Missing slug';
    exit;
}

$settingsFile = __DIR__ . '/../../data/settings.json';
$settings = file_exists($settingsFile) ? json_decode(file_get_contents($settingsFile), true) : [];
$settings['homepage'] = $slug;
file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT));

echo 'OK';
?>
