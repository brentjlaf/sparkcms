<?php
// File: list_pages.php
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$pagesFile = __DIR__ . '/../../data/pages.json';
$pages = file_exists($pagesFile) ? json_decode(file_get_contents($pagesFile), true) : [];
$result = [];
foreach ($pages as $p) {
    $result[] = ['id' => $p['id'], 'title' => $p['title'], 'slug' => $p['slug']];
}

echo json_encode($result);
?>
