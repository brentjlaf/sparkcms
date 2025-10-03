<?php
// File: save_page.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../sitemap/SitemapService.php';
require_once __DIR__ . '/PageService.php';

$pagesFile = __DIR__ . '/../../data/pages.json';
$historyFile = __DIR__ . '/../../data/page_history.json';

$id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
$title = sanitize_text($_POST['title'] ?? '');
$slug = sanitize_text($_POST['slug'] ?? '');
$content = trim($_POST['content'] ?? '');
// strip script tags to avoid XSS in stored content
$content = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $content);
$published = isset($_POST['published']) ? (bool)$_POST['published'] : false;
$template = sanitize_text($_POST['template'] ?? '');
$meta_title = sanitize_text($_POST['meta_title'] ?? '');
$meta_description = sanitize_text($_POST['meta_description'] ?? '');
$canonical_url = sanitize_url($_POST['canonical_url'] ?? '');
$og_title = sanitize_text($_POST['og_title'] ?? '');
$og_description = sanitize_text($_POST['og_description'] ?? '');
$og_image = sanitize_url($_POST['og_image'] ?? '');
$access = sanitize_text($_POST['access'] ?? 'public');

$service = new PageService(
    $pagesFile,
    $historyFile,
    function () use ($pagesFile) {
        $sitemapPath = __DIR__ . '/../../../sitemap.xml';
        return regenerate_sitemap($pagesFile, $sitemapPath, $_SERVER);
    }
);

$payload = [
    'id' => $id,
    'title' => $title,
    'slug' => $slug,
    'content' => $content,
    'published' => $published,
    'template' => $template,
    'meta_title' => $meta_title,
    'meta_description' => $meta_description,
    'canonical_url' => $canonical_url,
    'og_title' => $og_title,
    'og_description' => $og_description,
    'og_image' => $og_image,
    'access' => $access,
];

$response = $service->save($payload, $_SESSION['user']['username'] ?? 'Unknown');

http_response_code($response['status'] ?? 200);
header('Content-Type: application/json; charset=UTF-8');
echo json_encode($response);
