<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$settingsFile = __DIR__ . '/../../data/settings.json';
$settings = file_exists($settingsFile) ? json_decode(file_get_contents($settingsFile), true) : [];

echo json_encode($settings);
?>
