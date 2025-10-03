<?php
// File: generate.php
// Generate sitemap.xml listing all published pages
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/SitemapService.php';
require_login();

header('Content-Type: application/json; charset=UTF-8');

$pagesFile = __DIR__ . '/../../data/pages.json';
$sitemapPath = __DIR__ . '/../../../sitemap.xml';

$result = regenerate_sitemap($pagesFile, $sitemapPath, $_SERVER);
if (($result['success'] ?? false) !== true) {
    http_response_code(500);
}

echo json_encode($result);
?>
