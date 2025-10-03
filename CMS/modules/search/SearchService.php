<?php
// File: SearchService.php
require_once __DIR__ . '/../../includes/search_helpers.php';

class SearchService
{
    /**
     * Record a search term in the session-backed history and return the updated collection.
     *
     * @param string $term
     * @return array
     */
    public function recordSearchTerm(string $term): array
    {
        return push_search_history($term);
    }

    /**
     * Retrieve the stored search history records.
     *
     * @param int $limit
     * @return array
     */
    public function getHistory(int $limit = 10): array
    {
        return get_search_history($limit);
    }

    /**
     * Retrieve globally available search suggestions.
     *
     * @param int $limit
     * @return array
     */
    public function getSuggestions(int $limit = 60): array
    {
        return get_search_suggestions($limit);
    }
}
