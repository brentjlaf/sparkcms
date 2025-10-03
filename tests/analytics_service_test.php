<?php
require_once __DIR__ . '/../CMS/modules/analytics/AnalyticsService.php';

$fixture = [
    [
        'title' => 'Home',
        'slug' => '',
        'views' => 150,
        'last_modified' => 1704067200,
    ],
    [
        'title' => 'Documentation',
        'slug' => 'docs',
        'views' => 120,
        'last_modified' => 1703980800,
    ],
    [
        'title' => 'Welcome Blog',
        'slug' => 'blog/welcome',
        'views' => 90,
        'last_modified' => 1703894400,
    ],
    [
        'title' => 'Landing Page',
        'slug' => 'landing-page',
        'views' => 40,
        'last_modified' => 1703808000,
    ],
    [
        'title' => 'Release Notes',
        'slug' => 'changelog',
        'views' => 10,
        'last_modified' => 1703721600,
    ],
    [
        'title' => 'Archive',
        'slug' => 'archive',
        'views' => 0,
        'last_modified' => 1703635200,
    ],
];

$tempFile = tempnam(sys_get_temp_dir(), 'analytics');
if ($tempFile === false) {
    throw new RuntimeException('Unable to create temporary analytics dataset.');
}

file_put_contents($tempFile, json_encode($fixture));

$service = new AnalyticsService($tempFile);
$dashboard = $service->getDashboardData();

if ($dashboard['totalViews'] !== 410) {
    throw new RuntimeException('Total view count mismatch.');
}

if (count($dashboard['topPages']) !== 3) {
    throw new RuntimeException('Top pages list should contain the top three entries.');
}

if ($dashboard['topPages'][0]['title'] !== 'Home') {
    throw new RuntimeException('Top pages should be ordered by views.');
}

if ($dashboard['zeroViewCount'] !== 1) {
    throw new RuntimeException('Zero view count should reflect entries without traffic.');
}

if ($dashboard['summaryComparisons']['totalViews']['current'] !== 410) {
    throw new RuntimeException('Summary comparison for total views is incorrect.');
}

if ($dashboard['lastUpdatedTimestamp'] !== 1704067200) {
    throw new RuntimeException('Last updated timestamp should match the latest modification time.');
}

$top = $service->filterForExport('top', '');
if (count($top) !== 3) {
    throw new RuntimeException('Top filter should include three entries.');
}

foreach ($top as $entry) {
    if ($entry['status'] !== 'top') {
        throw new RuntimeException('Top filter returned a non-top entry.');
    }
}

$growing = $service->filterForExport('growing', '');
if (count($growing) !== 2) {
    throw new RuntimeException('Growing filter should include two entries.');
}

foreach ($growing as $entry) {
    if ($entry['status'] !== 'growing') {
        throw new RuntimeException('Growing filter returned an unexpected status.');
    }
}

$noViews = $service->filterForExport('no-views', '');
if (count($noViews) !== 1) {
    throw new RuntimeException('No-views filter should include a single entry.');
}

if ($noViews[0]['slug'] !== 'archive') {
    throw new RuntimeException('No-views filter should surface the archive page.');
}

$searchResults = $service->filterForExport('all', 'landing');
if (count($searchResults) !== 1 || $searchResults[0]['slug'] !== 'landing-page') {
    throw new RuntimeException('Search filtering did not match the expected entry.');
}

$caseInsensitive = $service->filterForExport('no-views', 'ARCH');
if (count($caseInsensitive) !== 1 || $caseInsensitive[0]['slug'] !== 'archive') {
    throw new RuntimeException('Search filtering should be case-insensitive.');
}

$defaulted = $service->filterForExport('unknown', 'home');
if (count($defaulted) === 0) {
    throw new RuntimeException('Unknown filters should default to returning results.');
}

unlink($tempFile);

echo "AnalyticsService tests passed\n";
