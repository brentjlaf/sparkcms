<?php
// File: generate.php
// Generate sitemap.xml listing all published pages
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/SitemapGenerator.php';
require_login();

header('Content-Type: application/json');

try {
    $pagesFile = __DIR__ . '/../../data/pages.json';
    $pages = read_json_file($pagesFile);
    if (!is_array($pages)) {
        $pages = [];
    }

    $generator = new SitemapGenerator(__DIR__ . '/../../../sitemap.xml');
    $result = $generator->generate($pages);

    echo json_encode($result);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to regenerate sitemap.',
        'error' => $exception->getMessage(),
    ]);
}
?>
