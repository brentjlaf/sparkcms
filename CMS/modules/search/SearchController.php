<?php
// File: SearchController.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/search_helpers.php';
require_once __DIR__ . '/SearchService.php';

class SearchController
{
    private SearchService $searchService;

    /** @var callable */
    private $searchFunction;

    public function __construct(SearchService $searchService, ?callable $searchFunction = null)
    {
        $this->searchService = $searchService;
        $this->searchFunction = $searchFunction ?: 'perform_search';
    }

    /**
     * Build the view context for the search module.
     *
     * @param array $queryParams
     * @return array
     */
    public function handle(array $queryParams): array
    {
        require_login();

        $query = isset($queryParams['q']) ? trim((string) $queryParams['q']) : '';
        $selectedTypes = $this->normaliseTypes($queryParams['types'] ?? []);

        $searchResult = $this->runSearch($query, ['types' => $selectedTypes]);
        $results = $searchResult['results'] ?? [];
        $typeCounts = $this->normaliseTypeCounts($searchResult['counts'] ?? []);

        $resultCount = count($results);
        $resultSummary = $resultCount === 1
            ? 'Showing 1 result'
            : 'Showing ' . number_format($resultCount) . ' results';

        if ($query !== '') {
            $historyRecords = $this->searchService->recordSearchTerm($query);
        } else {
            $historyRecords = $this->searchService->getHistory();
        }

        $suggestions = $this->searchService->getSuggestions();

        return [
            'query' => $query,
            'selected_types' => $selectedTypes,
            'results' => $results,
            'type_counts' => $typeCounts,
            'result_summary' => $resultSummary,
            'history' => $historyRecords,
            'suggestions' => $suggestions,
        ];
    }

    /**
     * Execute the configured search callable.
     *
     * @param string $query
     * @param array $filters
     * @return array
     */
    private function runSearch(string $query, array $filters): array
    {
        $callable = $this->searchFunction;
        $result = call_user_func($callable, $query, $filters);
        if (!is_array($result)) {
            return [
                'results' => [],
                'counts' => ['Page' => 0, 'Post' => 0, 'Media' => 0],
            ];
        }
        return $result;
    }

    /**
     * Normalise the incoming filter values into a lowercase list.
     *
     * @param mixed $typesParam
     * @return array
     */
    private function normaliseTypes($typesParam): array
    {
        $rawTypes = [];
        if (is_string($typesParam) && $typesParam !== '') {
            $rawTypes = array_map('trim', explode(',', $typesParam));
        } elseif (is_array($typesParam)) {
            foreach ($typesParam as $value) {
                if (is_string($value)) {
                    $trimmed = trim($value);
                    if ($trimmed !== '') {
                        $rawTypes[] = $trimmed;
                    }
                }
            }
        }

        $normalised = [];
        foreach ($rawTypes as $value) {
            $lower = strtolower($value);
            if ($lower === '') {
                continue;
            }
            if (!in_array($lower, $normalised, true)) {
                $normalised[] = $lower;
            }
        }

        return $normalised;
    }

    /**
     * Ensure the counts array always exposes all supported type keys as integers.
     *
     * @param array $counts
     * @return array
     */
    private function normaliseTypeCounts(array $counts): array
    {
        $defaultCounts = ['Page' => 0, 'Post' => 0, 'Media' => 0];
        foreach ($defaultCounts as $label => $default) {
            if (isset($counts[$label]) && is_numeric($counts[$label])) {
                $defaultCounts[$label] = (int) $counts[$label];
            }
        }
        return $defaultCounts;
    }
}
