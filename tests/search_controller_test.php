<?php
require_once __DIR__ . '/../CMS/modules/search/SearchController.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$_SESSION['user'] = ['id' => 1, 'username' => 'tester'];

class StubSearchService extends SearchService
{
    public array $recordedTerms = [];
    private array $history;
    private array $suggestions;

    public function __construct(array $history, array $suggestions)
    {
        $this->history = $history;
        $this->suggestions = $suggestions;
    }

    public function recordSearchTerm(string $term): array
    {
        $this->recordedTerms[] = $term;
        return $this->history;
    }

    public function getHistory(int $limit = 10): array
    {
        return $this->history;
    }

    public function getSuggestions(int $limit = 60): array
    {
        return $this->suggestions;
    }
}

function assert_equals($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . '\nExpected: ' . var_export($expected, true) . '\nActual: ' . var_export($actual, true));
    }
}

$historyFixture = [
    ['term' => 'Analytics', 'count' => 3, 'last' => 100],
    ['term' => 'Reports', 'count' => 1, 'last' => 50],
];
$suggestionsFixture = [
    ['value' => 'Pages', 'type' => 'Page', 'label' => 'Pages'],
];

// Empty query scenario should return history and skip recording new entries.
$emptyService = new StubSearchService($historyFixture, $suggestionsFixture);
$emptySearchCalls = [];
$controller = new SearchController(
    $emptyService,
    function ($query, array $filters) use (&$emptySearchCalls) {
        $emptySearchCalls[] = ['query' => $query, 'filters' => $filters];
        return [
            'results' => [],
            'counts' => ['Page' => 0, 'Post' => 0, 'Media' => 0],
        ];
    }
);

$emptyContext = $controller->handle([]);
assert_equals('', $emptyContext['query'], 'Empty queries should be trimmed to an empty string.');
assert_equals([], $emptyContext['selected_types'], 'Empty query should not include any selected types.');
assert_equals($historyFixture, $emptyContext['history'], 'Controller should return existing history for empty queries.');
assert_equals($suggestionsFixture, $emptyContext['suggestions'], 'Controller should expose suggestion data.');
assert_equals('Showing 0 results', $emptyContext['result_summary'], 'Result summary should reflect zero results.');
assert_equals([['query' => '', 'filters' => ['types' => []]]], $emptySearchCalls, 'Search should run with empty filters for empty queries.');
assert_equals([], $emptyService->recordedTerms, 'Empty query should not be recorded in history.');

// Query with comma-delimited filters should normalise types and record history.
$serviceWithRecording = new StubSearchService($historyFixture, $suggestionsFixture);
$recordingCalls = [];
$recordingController = new SearchController(
    $serviceWithRecording,
    function ($query, array $filters) use (&$recordingCalls) {
        $recordingCalls[] = ['query' => $query, 'filters' => $filters];
        return [
            'results' => [
                [
                    'id' => 1,
                    'type' => 'Page',
                    'title' => 'Alpha',
                    'slug' => 'alpha',
                    'score' => 1.0,
                    'snippet' => '',
                    'record' => ['id' => 1, 'slug' => 'alpha', 'published' => true],
                ],
                [
                    'id' => 2,
                    'type' => 'Post',
                    'title' => 'Beta',
                    'slug' => 'beta',
                    'score' => 0.9,
                    'snippet' => '',
                    'record' => ['slug' => 'beta', 'status' => 'draft'],
                ],
            ],
            'counts' => ['Page' => 1, 'Post' => 1, 'Media' => 0],
        ];
    }
);

$recordingContext = $recordingController->handle(['q' => 'Test', 'types' => 'Page, Post ,']);
assert_equals('Test', $recordingContext['query'], 'Query parameter should be preserved.');
assert_equals(['page', 'post'], $recordingContext['selected_types'], 'Filters should normalise to lowercase unique values.');
assert_equals(1, count($serviceWithRecording->recordedTerms), 'Recording service should receive the search term.');
assert_equals('Showing 2 results', $recordingContext['result_summary'], 'Summary should pluralise when multiple results are returned.');
assert_equals(['Page' => 1, 'Post' => 1, 'Media' => 0], $recordingContext['type_counts'], 'Counts should reflect search response.');
assert_equals([
    ['query' => 'Test', 'filters' => ['types' => ['page', 'post']]],
], $recordingCalls, 'Search callable should receive normalised filters.');

// Query with array filters should de-duplicate and respect ordering.
$serviceWithArrayFilters = new StubSearchService($historyFixture, $suggestionsFixture);
$arrayCalls = [];
$arrayController = new SearchController(
    $serviceWithArrayFilters,
    function ($query, array $filters) use (&$arrayCalls) {
        $arrayCalls[] = ['query' => $query, 'filters' => $filters];
        return [
            'results' => [
                [
                    'id' => 3,
                    'type' => 'Media',
                    'title' => 'Gamma Asset',
                    'slug' => 'gamma.png',
                    'score' => 0.8,
                    'snippet' => '',
                    'record' => ['file' => 'uploads/gamma.png', 'size' => 2048],
                ],
            ],
            'counts' => ['Media' => 1],
        ];
    }
);

$arrayContext = $arrayController->handle([
    'q' => '  Another  ',
    'types' => ['MEDIA', '', 'Page', 'Media', 'Post'],
]);
assert_equals('Another', $arrayContext['query'], 'Query should be trimmed.');
assert_equals(['media', 'page', 'post'], $arrayContext['selected_types'], 'Array filters should be trimmed, normalised, and deduplicated.');
assert_equals('Showing 1 result', $arrayContext['result_summary'], 'Summary should show singular form for one result.');
assert_equals(['Page' => 0, 'Post' => 0, 'Media' => 1], $arrayContext['type_counts'], 'Missing count keys should default to zero.');
assert_equals([
    ['query' => 'Another', 'filters' => ['types' => ['media', 'page', 'post']]],
], $arrayCalls, 'Search callable should receive normalised array filters.');

unset($_SESSION['user']);

echo "SearchController tests passed\n";
