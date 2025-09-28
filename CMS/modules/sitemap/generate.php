<?php
// File: generate.php
// Generate sitemap.xml listing all published pages
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_login();

header('Content-Type: application/json');

try {
    $pagesFile = __DIR__ . '/../../data/pages.json';
    $pages = read_json_file($pagesFile);
    if (!is_array($pages)) {
        $pages = [];
    }

    $published = array_values(array_filter($pages, function ($page) {
        return !empty($page['published']);
    }));

    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptBase = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    if (substr($scriptBase, -4) === '/CMS') {
        $scriptBase = substr($scriptBase, 0, -4);
    }
    $scriptBase = rtrim($scriptBase, '/');
    $baseUrl = $scheme . '://' . $host . $scriptBase;

    $dom = new DOMDocument('1.0', 'UTF-8');
    $urlset = $dom->createElement('urlset');
    $urlset->setAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

    $entries = [];
    foreach ($published as $page) {
        $slug = ltrim((string)($page['slug'] ?? ''), '/');
        $lastModified = isset($page['last_modified']) ? (int)$page['last_modified'] : time();

        $url = $dom->createElement('url');
        $loc = $dom->createElement('loc', $baseUrl . '/' . $slug);
        $lastmodDate = date('Y-m-d', $lastModified);
        $lastmod = $dom->createElement('lastmod', $lastmodDate);

        $url->appendChild($loc);
        $url->appendChild($lastmod);
        $urlset->appendChild($url);

        $entries[] = [
            'title' => (string)($page['title'] ?? ''),
            'slug' => $slug,
            'url' => $baseUrl . '/' . $slug,
            'lastmodHuman' => date('F j, Y', $lastModified),
            'lastmod' => $lastmodDate,
        ];
    }

    $dom->appendChild($urlset);
    $dom->formatOutput = true;
    $sitemapPath = __DIR__ . '/../../../sitemap.xml';
    if ($dom->save($sitemapPath) === false) {
        throw new RuntimeException('Unable to write sitemap file.');
    }

    $generatedAt = filemtime($sitemapPath) ?: time();

    echo json_encode([
        'success' => true,
        'message' => 'Sitemap regenerated successfully.',
        'entryCount' => count($entries),
        'generatedAt' => $generatedAt,
        'generatedAtFormatted' => date('F j, Y g:i a', $generatedAt),
        'entries' => $entries,
        'sitemapUrl' => $baseUrl . '/sitemap.xml',
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to regenerate sitemap.',
        'error' => $exception->getMessage(),
    ]);
}
?>
