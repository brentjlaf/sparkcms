<?php
// File: list_pages.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_login();

$pagesFile = __DIR__ . '/../../data/pages.json';
$pages = read_json_file($pagesFile);
$result = [];
foreach ($pages as $p) {
    $result[] = ['id' => $p['id'], 'title' => $p['title'], 'slug' => $p['slug']];
}

echo json_encode($result);
?>
