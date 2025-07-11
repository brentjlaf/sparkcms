<?php
$pagesFile = __DIR__ . '/../../data/pages.json';
if (!file_exists($pagesFile)) {
    exit('No pages');
}
$pages = json_decode(file_get_contents($pagesFile), true) ?: [];
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$pages = array_filter($pages, function($p) use ($id) { return $p['id'] != $id; });
file_put_contents($pagesFile, json_encode(array_values($pages), JSON_PRETTY_PRINT));
// Update sitemap after a page is deleted
require_once __DIR__ . '/../sitemap/generate.php';
echo 'OK';
