<?php
require_once __DIR__ . '/../CMS/includes/template_renderer.php';
require_once __DIR__ . '/../CMS/modules/accessibility/AccessibilityReport.php';

$pages = [
    [
        'title' => 'Sample Page',
        'slug' => 'sample-page',
        'content' => '<main><h1>Accessible Title</h1><img src="image.jpg" alt=""><a href="#">Click here</a></main>',
    ],
];

$settings = [];
$menus = [];
$scriptBase = '';
$templateDir = null;

$service = new AccessibilityReport(
    $pages,
    $settings,
    $menus,
    $scriptBase,
    $templateDir,
    static fn () => 77,
    'Jan 1, 2024 12:00 AM'
);

$report = $service->generateReport();
$pagesData = $report['pages'];
$stats = $report['stats'];

if (count($pagesData) !== 1) {
    throw new RuntimeException('Expected a single page entry in the report.');
}

$page = $pagesData[0];

if ($page['metrics']['images'] !== 1) {
    throw new RuntimeException('Expected a single image to be detected.');
}

if ($page['metrics']['missingAlt'] !== 1) {
    throw new RuntimeException('Missing alt text count should equal one.');
}

if ($page['metrics']['genericLinks'] !== 1) {
    throw new RuntimeException('Generic link count should equal one.');
}

if ($page['metrics']['landmarks'] !== 1) {
    throw new RuntimeException('Landmark count should equal one when a <main> element is present.');
}

if ($page['wcagLevel'] !== 'Partial') {
    throw new RuntimeException('WCAG level should be Partial when moderate and critical issues exist.');
}

if ($page['violations']['critical'] !== 1 || $page['violations']['moderate'] !== 1) {
    throw new RuntimeException('Violation counts did not match expectations.');
}

if (strpos($page['summaryLine'], '1 critical') === false) {
    throw new RuntimeException('Summary line should mention critical issues.');
}

if ($page['previousScore'] !== 77) {
    throw new RuntimeException('Previous score resolver should feed through to the report data.');
}

if ($stats['avgScore'] !== 77 || $stats['criticalIssues'] !== 1) {
    throw new RuntimeException('Aggregated stats are incorrect.');
}

if ($stats['filterCounts']['partial'] !== 1 || $stats['filterCounts']['failing'] !== 1) {
    throw new RuntimeException('Filter counts do not reflect page status.');
}

if ($stats['filterCounts']['compliant'] !== 0) {
    throw new RuntimeException('Compliant filter count should remain zero.');
}

echo "AccessibilityReport service test passed\n";
