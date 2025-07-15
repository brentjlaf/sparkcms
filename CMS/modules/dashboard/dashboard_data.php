<?php
// File: dashboard_data.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_login();

$pagesFile = __DIR__ . '/../../data/pages.json';
$mediaFile = __DIR__ . '/../../data/media.json';
$usersFile = __DIR__ . '/../../data/users.json';

$pages = read_json_file($pagesFile);
$media = read_json_file($mediaFile);
$users = read_json_file($usersFile);

$views = 0;
foreach ($pages as $p) {
    $views += $p['views'] ?? 0;
}

$data = [
    'pages' => count($pages),
    'media' => count($media),
    'users' => count($users),
    'views' => $views,
];

header('Content-Type: application/json');
echo json_encode($data);
