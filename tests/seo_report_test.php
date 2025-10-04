<?php
require_once __DIR__ . '/../CMS/includes/template_renderer.php';
require_once __DIR__ . '/../CMS/modules/seo/SeoReport.php';

$pages = [
    [
        'title' => 'SEO Sample Page',
        'slug' => 'seo-sample',
        'content' => '<main><h2>Heading Only</h2><p>Short copy.</p><a href="/about">About</a><a href="https://example.com">External</a><img src="hero.jpg"></main>',
        'meta_robots' => 'noindex, follow',
    ],
];

$settings = ['site_url' => 'https://mysite.test'];
$menus = [];
$scriptBase = '';
$templateDir = null;

$service = new SeoReport(
    $pages,
    $settings,
    $menus,
    $scriptBase,
    $templateDir,
    static fn () => 70,
    'Jan 1, 2024 12:00 AM'
);

$report = $service->generateReport();
$pagesData = $report['pages'];
$stats = $report['stats'];

if (count($pagesData) !== 1) {
    throw new RuntimeException('Expected a single page entry in the SEO report.');
}

$page = $pagesData[0];

if ($page['metrics']['wordCount'] <= 0) {
    throw new RuntimeException('Word count should be computed for the rendered page.');
}

if ($page['metrics']['internalLinks'] !== 1 || $page['metrics']['externalLinks'] !== 1) {
    throw new RuntimeException('Internal and external link counts did not match expectations.');
}

if ($page['metadata']['titleFallback'] !== true) {
    throw new RuntimeException('Missing title should trigger fallback usage.');
}

if (empty($page['issues']['details'])) {
    throw new RuntimeException('SEO report should flag issues for missing metadata and thin content.');
}

$impacts = array_column($page['issues']['details'], 'impact');
if (!in_array('critical', $impacts, true) || !in_array('medium', $impacts, true)) {
    throw new RuntimeException('Expected both critical and medium severity issues.');
}

if ($page['seoScore'] >= 100 || $page['seoScore'] <= 0) {
    throw new RuntimeException('SEO score should be normalised within expected bounds.');
}

if ($page['previousScore'] !== 70) {
    throw new RuntimeException('Previous score resolver should influence the page payload.');
}

if ($stats['avgScore'] !== $page['seoScore'] || $stats['optimizedPages'] !== 0) {
    throw new RuntimeException('Aggregated stats did not align with page metrics.');
}

echo "SeoReport service test passed\n";
