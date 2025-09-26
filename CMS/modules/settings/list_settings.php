<?php
// File: list_settings.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_login();

header('Content-Type: application/json');

$settingsFile = __DIR__ . '/../../data/settings.json';
$settings = read_json_file($settingsFile);

echo json_encode($settings);
?>
