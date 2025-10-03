<?php
require_once __DIR__ . '/../CMS/modules/seo/SeoReport.php';

$service = new SeoReport([], [], [], '', null, null, 'Jan 1, 2024 12:00 AM');
$classifications = [
    'Meta description is missing' => SeoReport::IMPACT_SERIOUS,
    'Page title length is outside the recommended 30-65 characters' => SeoReport::IMPACT_SERIOUS,
    'Robots meta tag blocks indexing (noindex)' => SeoReport::IMPACT_CRITICAL,
];

foreach ($classifications as $issue => $expectedImpact) {
    $detail = $service->classifyIssue($issue);
    if ($detail['impact'] !== $expectedImpact) {
        throw new RuntimeException('Unexpected impact classification for: ' . $issue);
    }
    if (!is_string($detail['recommendation']) || $detail['recommendation'] === '') {
        throw new RuntimeException('Recommendation should be provided for issue: ' . $issue);
    }
}

$contentBlock = str_repeat('one two three four five six seven eight nine ten. ', 10);
$html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Short Title</title>
    <meta name="description" content="Brief overview of this page for testing.">
</head>
<body>
    <h1>Test Heading</h1>
    <p>{$contentBlock}</p>
    <img src="image.jpg">
    <a href="/internal-link">Internal reference</a>
</body>
</html>
HTML;

$page = [
    'title' => 'Sample SEO Page',
    'slug' => 'sample-seo-page',
    'content' => $html,
];

$reportService = new SeoReport([$page], [], [], '', null, null, 'Jan 2, 2024 09:00 AM');
$report = $reportService->generateReport();

if (count($report['pages']) !== 1) {
    throw new RuntimeException('Report should contain exactly one analysed page.');
}

$pageEntry = $report['pages'][0];

if ($pageEntry['metrics']['wordCount'] < 100 || $pageEntry['metrics']['wordCount'] >= 150) {
    throw new RuntimeException('Word count analysis did not fall within the expected range.');
}

if ($pageEntry['violations'][SeoReport::IMPACT_SERIOUS] !== 2) {
    throw new RuntimeException('Serious issue count mismatch.');
}

if ($pageEntry['violations'][SeoReport::IMPACT_MODERATE] !== 4) {
    throw new RuntimeException('Moderate issue count mismatch.');
}

if ($pageEntry['violations'][SeoReport::IMPACT_MINOR] !== 2) {
    throw new RuntimeException('Minor issue count mismatch.');
}

$expectedSummary = 'SEO health score: 40%. 2 serious, 4 moderate, 2 minor issues.';
if ($pageEntry['summaryLine'] !== $expectedSummary) {
    throw new RuntimeException('Summary line did not match expectations.');
}

if ($pageEntry['optimizationLevel'] !== SeoReport::OPTIMISATION_CRITICAL) {
    throw new RuntimeException('Optimisation level should reflect the severity of issues.');
}

if ($report['stats']['filterCounts']['critical'] !== 1) {
    throw new RuntimeException('Filter counts should register the analysed page as critical.');
}

if ($pageEntry['issues']['details'][0]['impact'] !== SeoReport::IMPACT_SERIOUS) {
    throw new RuntimeException('Issue classification details are not being propagated correctly.');
}

echo "SeoReport tests passed\n";
