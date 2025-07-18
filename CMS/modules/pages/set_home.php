<?php
// File: set_home.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/sanitize.php';
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
file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT));

echo 'OK';
?>
