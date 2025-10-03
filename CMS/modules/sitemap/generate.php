<?php
// File: generate.php
// Generate sitemap.xml listing all published pages
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/SitemapRegenerator.php';
require_login();

header('Content-Type: application/json; charset=UTF-8');

try {
    $pagesFile = __DIR__ . '/../../data/pages.json';
    $pages = read_json_file($pagesFile);
    if (!is_array($pages)) {
        $pages = [];
    }

    $result = regenerate_sitemap($pages, __DIR__ . '/../../../sitemap.xml');
    if (($result['success'] ?? false) !== true) {
        http_response_code(500);
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    http_response_code(500);
    $message = $exception->getMessage();
    if (!is_string($message) || $message === '') {
        $message = 'Failed to regenerate sitemap.';
    }
    echo json_encode([
        'success' => false,
        'message' => $message,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
?>
