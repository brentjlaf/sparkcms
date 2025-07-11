<?php
// File: dashboard_data.php
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$pagesFile = __DIR__ . '/../../data/pages.json';
$mediaFile = __DIR__ . '/../../data/media.json';
$usersFile = __DIR__ . '/../../data/users.json';

$pages = file_exists($pagesFile) ? json_decode(file_get_contents($pagesFile), true) : [];
$media = file_exists($mediaFile) ? json_decode(file_get_contents($mediaFile), true) : [];
$users = file_exists($usersFile) ? json_decode(file_get_contents($usersFile), true) : [];

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
