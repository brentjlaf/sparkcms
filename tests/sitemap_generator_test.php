<?php
require_once __DIR__ . '/../CMS/modules/sitemap/SitemapGenerator.php';

if (!class_exists('DOMDocument')) {
    throw new RuntimeException('DOMDocument extension is required for sitemap tests.');
}

$pages = [
    [
        'id' => 1,
        'title' => 'Home',
        'slug' => '',
        'published' => true,
        'last_modified' => strtotime('2024-01-15 12:30:00'),
    ],
    [
        'id' => 2,
        'title' => 'About Us',
        'slug' => '/about',
        'published' => true,
        'last_modified' => strtotime('2024-02-20 09:15:00'),
    ],
    [
        'id' => 3,
        'title' => 'Draft Page',
        'slug' => 'draft',
        'published' => false,
        'last_modified' => strtotime('2024-03-01 08:00:00'),
    ],
];

$domFile = tempnam(sys_get_temp_dir(), 'sitemap-dom-');
$manualFile = tempnam(sys_get_temp_dir(), 'sitemap-manual-');

if ($domFile === false || $manualFile === false) {
    throw new RuntimeException('Unable to create temporary sitemap paths.');
}

$generatorDom = new SitemapGenerator($domFile);
$domResult = $generatorDom->generate($pages, [
    'baseUrl' => 'https://example.com',
    'useDom' => true,
]);

if (!isset($domResult['success']) || $domResult['success'] !== true) {
    throw new RuntimeException('DOM-based generation should succeed.');
}

if ($domResult['entryCount'] !== 2) {
    throw new RuntimeException('Sitemap should only include published pages.');
}

$dom = new DOMDocument();
$domContent = file_get_contents($domFile);
if ($domContent === false || $domContent === '') {
    throw new RuntimeException('DOM-based sitemap should write XML content.');
}

if ($dom->loadXML($domContent) === false) {
    throw new RuntimeException('DOM-based sitemap should produce valid XML.');
}

$urlNodes = $dom->getElementsByTagName('url');
if ($urlNodes->length !== 2) {
    throw new RuntimeException('DOM-based sitemap should include two URL entries.');
}

$firstLoc = $urlNodes->item(0)->getElementsByTagName('loc')->item(0)->textContent ?? '';
if ($firstLoc !== 'https://example.com/') {
    throw new RuntimeException('Root page should resolve to the base URL with trailing slash.');
}

$secondLoc = $urlNodes->item(1)->getElementsByTagName('loc')->item(0)->textContent ?? '';
if ($secondLoc !== 'https://example.com/about') {
    throw new RuntimeException('Slugged pages should append to the base URL.');
}

$manualGenerator = new SitemapGenerator($manualFile);
$manualResult = $manualGenerator->generate($pages, [
    'baseUrl' => 'https://example.com',
    'useDom' => false,
]);

if (!isset($manualResult['success']) || $manualResult['success'] !== true) {
    throw new RuntimeException('Manual sitemap generation should succeed.');
}

$manualContent = file_get_contents($manualFile);
if ($manualContent === false) {
    throw new RuntimeException('Manual sitemap file should be readable.');
}

$expectedLines = [
    '<?xml version="1.0" encoding="UTF-8"?>',
    '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
    '  <url>',
    '    <loc>https://example.com/</loc>',
    '    <lastmod>2024-01-15</lastmod>',
    '  </url>',
    '  <url>',
    '    <loc>https://example.com/about</loc>',
    '    <lastmod>2024-02-20</lastmod>',
    '  </url>',
    '</urlset>',
];
$expectedContent = implode("\n", $expectedLines);

if ($manualContent !== $expectedContent) {
    throw new RuntimeException('Manual sitemap output did not match the expected XML structure.');
}

unlink($domFile);
unlink($manualFile);

echo "SitemapGenerator tests passed\n";
