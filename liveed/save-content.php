<?php
require_once __DIR__ . '/../CMS/includes/auth.php';
require_login();

$pagesFile = __DIR__ . '/../CMS/data/pages.json';
$pages = file_exists($pagesFile) ? json_decode(file_get_contents($pagesFile), true) : [];

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$content = $_POST['content'] ?? '';

if (!$id) {
    http_response_code(400);
    echo 'Invalid ID';
    exit;
}

foreach ($pages as &$p) {
    if ((int)$p['id'] === $id) {
        $p['content'] = $content;
        $p['last_modified'] = time();
        break;
    }
}
unset($p);

file_put_contents($pagesFile, json_encode($pages, JSON_PRETTY_PRINT));
require_once __DIR__ . '/../CMS/modules/sitemap/generate.php';

echo 'OK';
