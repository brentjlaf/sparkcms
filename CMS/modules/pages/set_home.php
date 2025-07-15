<?php
// File: set_home.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../includes/data.php';
require_login();

$slug = sanitize_text($_POST['slug'] ?? '');
if ($slug === '') {
    http_response_code(400);
    echo 'Missing slug';
    exit;
}

$settingsFile = __DIR__ . '/../../data/settings.json';
$settings = read_json_file($settingsFile);
$settings['homepage'] = $slug;
write_json_file($settingsFile, $settings);

echo 'OK';
?>
