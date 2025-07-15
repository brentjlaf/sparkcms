<?php
// File: generate.php
// Generate sitemap.xml listing all published pages

$pagesFile = __DIR__ . '/../../data/pages.json';
$pages = read_json_file($pagesFile);

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
foreach ($pages as $p) {
    if (!empty($p['published'])) {
        $url = $dom->createElement('url');
        $loc = $dom->createElement('loc', $baseUrl . '/' . $p['slug']);
        $lastmodDate = isset($p['last_modified']) ? date('Y-m-d', $p['last_modified']) : date('Y-m-d');
        $lastmod = $dom->createElement('lastmod', $lastmodDate);
        $url->appendChild($loc);
        $url->appendChild($lastmod);
        $urlset->appendChild($url);
    }
}
$dom->appendChild($urlset);
$dom->formatOutput = true;
$sitemapPath = __DIR__ . '/../../../sitemap.xml';
$dom->save($sitemapPath);
?>
