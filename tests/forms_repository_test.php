<?php
require_once __DIR__ . '/../CMS/modules/forms/FormRepository.php';
require_once __DIR__ . '/../CMS/modules/forms/FormAnalytics.php';

$formsFile = tempnam(sys_get_temp_dir(), 'forms');
$submissionsFile = tempnam(sys_get_temp_dir(), 'submissions');

if ($formsFile === false || $submissionsFile === false) {
    throw new RuntimeException('Unable to create temporary dataset.');
}

$formsFixture = [
    ['id' => 10, 'name' => 'Contact'],
    ['id' => 11, 'name' => 'Support'],
];

$submissionsFixture = [
    [
        'id' => 1,
        'form_id' => 10,
        'submitted_at' => '2024-01-15T12:00:00Z',
        'data' => ['email' => 'one@example.com'],
        'meta' => ['user_agent' => 'Mozilla'],
    ],
    [
        'id' => 2,
        'form_id' => 10,
        'created_at' => 1705600000000,
        'data' => ['email' => 'two@example.com'],
        'meta' => ['ip' => '10.0.0.2'],
    ],
    [
        'id' => 3,
        'form_id' => 10,
        'timestamp' => 1703000000,
        'ip' => '203.0.113.5',
        'source' => 'Landing page',
    ],
    [
        'id' => 4,
        'form_id' => 10,
        'submitted_at' => 'not-a-date',
        'data' => ['email' => 'invalid@example.com'],
    ],
    [
        'id' => 5,
        'form_id' => 99,
        'submitted_at' => '2023-12-01T00:00:00Z',
    ],
];

$repository = new FormRepository($formsFile, $submissionsFile);
$repository->saveForms($formsFixture);
$repository->saveSubmissions($submissionsFixture);

$normalized = $repository->getNormalizedSubmissions(10);

if (count($normalized) !== 4) {
    throw new RuntimeException('Filtered submissions should include four entries.');
}

if ($normalized[0]['id'] !== 2) {
    throw new RuntimeException('Submissions should be sorted by most recent timestamp.');
}

if ($normalized[0]['submitted_at'] !== date(DATE_ATOM, 1705600000)) {
    throw new RuntimeException('Millisecond timestamps should normalize to seconds and ISO 8601.');
}

if (!($normalized[0]['data'] instanceof stdClass)) {
    throw new RuntimeException('Submission data should be converted to an object.');
}

if (!($normalized[0]['meta'] instanceof stdClass)) {
    throw new RuntimeException('Submission meta should be converted to an object.');
}

if (!property_exists($normalized[2]['meta'], 'ip') || $normalized[2]['meta']->ip !== '203.0.113.5') {
    throw new RuntimeException('Standalone IP addresses should be merged into the meta payload.');
}

if ($normalized[3]['submitted_at'] !== 'not-a-date') {
    throw new RuntimeException('Unparseable timestamps should retain their original value.');
}

$allNormalized = $repository->getNormalizedSubmissions();
if (count($allNormalized) !== 5) {
    throw new RuntimeException('Requesting all submissions should include every record.');
}

$analytics = new FormAnalytics($repository, 1706000000);
$context = $analytics->getDashboardContext();

if ($context['totalForms'] !== 2) {
    throw new RuntimeException('Total forms count is incorrect.');
}

if ($context['totalSubmissions'] !== 5) {
    throw new RuntimeException('Total submissions count is incorrect.');
}

if ($context['recentSubmissions'] !== 2) {
    throw new RuntimeException('Recent submissions count should include entries within 30 days.');
}

if ($context['activeForms'] !== 2) {
    throw new RuntimeException('Active forms should match the number of unique form IDs with submissions.');
}

if ($context['lastSubmissionTimestamp'] !== 1705600000) {
    throw new RuntimeException('Last submission timestamp did not match the latest entry.');
}

if ($context['lastSubmissionLabel'] !== date('M j, Y g:i A', 1705600000)) {
    throw new RuntimeException('Last submission label should use a friendly formatted date.');
}

unlink($formsFile);
unlink($submissionsFile);

echo "FormRepository and FormAnalytics tests passed\n";
