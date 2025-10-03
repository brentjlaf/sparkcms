<?php
// File: AnalyticsService.php

require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/analytics.php';

class AnalyticsService
{
    private string $pagesFile;
    /** @var array<string,mixed>|null */
    private ?array $cache = null;

    public function __construct(?string $pagesFile = null)
    {
        $this->pagesFile = $pagesFile ?? __DIR__ . '/../../data/pages.json';
    }

    /**
     * Retrieve dashboard data prepared for the analytics view.
     *
     * @return array<string,mixed>
     */
    public function getDashboardData(): array
    {
        $this->buildCache();

        return [
            'totalViews' => $this->cache['totalViews'],
            'averageViews' => $this->cache['averageViews'],
            'totalPages' => $this->cache['totalPages'],
            'zeroViewCount' => $this->cache['zeroViewCount'],
            'topPages' => $this->cache['topPages'],
            'zeroViewExamples' => $this->cache['zeroViewExamples'],
            'initialEntries' => $this->cache['initialEntries'],
            'summaryComparisons' => $this->cache['summaryComparisons'],
            'lastUpdatedTimestamp' => $this->cache['lastUpdatedTimestamp'],
        ];
    }

    /**
     * Filter ranked entries for export output.
     *
     * @param string $filter
     * @param string $search
     * @return array<int,array<string,mixed>>
     */
    public function filterForExport(string $filter, string $search): array
    {
        $this->buildCache();

        $allowedFilters = ['all', 'top', 'growing', 'no-views'];
        $filterKey = strtolower($filter);
        if (!in_array($filterKey, $allowedFilters, true)) {
            $filterKey = 'all';
        }

        $searchTerm = trim($search);

        $filtered = array_values(array_filter($this->cache['rankedEntries'], static function (array $entry) use ($filterKey, $searchTerm) {
            if ($filterKey !== 'all' && $entry['status'] !== $filterKey) {
                return false;
            }

            if ($searchTerm === '') {
                return true;
            }

            $title = $entry['title'];
            $slug = $entry['slug'];
            if (function_exists('mb_stripos')) {
                $encoding = 'UTF-8';
                return (mb_stripos($title, $searchTerm, 0, $encoding) !== false)
                    || (mb_stripos($slug, $searchTerm, 0, $encoding) !== false);
            }

            return (stripos($title, $searchTerm) !== false)
                || (stripos($slug, $searchTerm) !== false);
        }));

        return array_map(static function (array $entry) {
            return [
                'title' => $entry['title'],
                'slug' => $entry['slug'],
                'views' => $entry['views'],
                'label' => $entry['label'],
                'rank' => $entry['rank'],
                'status' => $entry['status'],
            ];
        }, $filtered);
    }

    private function buildCache(): void
    {
        if ($this->cache !== null) {
            return;
        }

        $rawPages = read_json_file($this->pagesFile);
        if (!is_array($rawPages)) {
            $rawPages = [];
        }

        $normalized = [];
        $totalViews = 0;
        $previousTotalViews = 0;
        $zeroViewCount = 0;
        $previousZeroCount = 0;
        $lastUpdated = 0;

        foreach ($rawPages as $page) {
            if (!is_array($page)) {
                continue;
            }

            $title = isset($page['title']) ? (string) $page['title'] : 'Untitled';
            $slug = isset($page['slug']) ? (string) $page['slug'] : '';
            $views = isset($page['views']) ? (int) $page['views'] : 0;
            if ($views < 0) {
                $views = 0;
            }

            $lastModified = isset($page['last_modified']) ? (int) $page['last_modified'] : 0;
            if ($lastModified > $lastUpdated) {
                $lastUpdated = $lastModified;
            }

            $previousViews = analytics_previous_views($slug, $views);

            $normalized[] = [
                'title' => $title,
                'slug' => $slug,
                'views' => $views,
                'previousViews' => $previousViews,
                'lastModified' => $lastModified,
            ];

            $totalViews += $views;
            $previousTotalViews += $previousViews;
            if ($views === 0) {
                $zeroViewCount++;
            }
            if ($previousViews === 0) {
                $previousZeroCount++;
            }
        }

        usort($normalized, static function (array $a, array $b) {
            return ($b['views'] ?? 0) <=> ($a['views'] ?? 0);
        });

        $totalPages = count($normalized);
        $averageViews = $totalPages > 0 ? $totalViews / $totalPages : 0.0;
        $previousAverageViews = $totalPages > 0 ? $previousTotalViews / $totalPages : 0.0;

        $initialEntries = [];
        foreach ($normalized as $entry) {
            $initialEntries[] = [
                'title' => $entry['title'],
                'slug' => $entry['slug'],
                'views' => $entry['views'],
                'previousViews' => $entry['previousViews'],
            ];
        }

        $rankedEntries = [];
        foreach ($normalized as $index => $entry) {
            $status = 'growing';
            $label = 'Steady traffic';

            if ($entry['views'] === 0) {
                $status = 'no-views';
                $label = 'Needs promotion';
            } elseif ($index < 3 || $entry['views'] >= $averageViews) {
                $status = 'top';
                $label = 'Top performer';
            }

            $rankedEntries[] = [
                'title' => $entry['title'],
                'slug' => $entry['slug'],
                'views' => $entry['views'],
                'previousViews' => $entry['previousViews'],
                'status' => $status,
                'label' => $label,
                'rank' => $index + 1,
            ];
        }

        $zeroViewExamples = array_slice(array_values(array_filter($initialEntries, static function (array $entry) {
            return $entry['views'] === 0;
        })), 0, 3);

        $summaryComparisons = [
            'totalViews' => [
                'current' => $totalViews,
                'previous' => $previousTotalViews,
            ],
            'averageViews' => [
                'current' => $averageViews,
                'previous' => $previousAverageViews,
            ],
            'totalPages' => [
                'current' => $totalPages,
                'previous' => $totalPages,
            ],
            'zeroViews' => [
                'current' => $zeroViewCount,
                'previous' => $previousZeroCount,
            ],
        ];

        $this->cache = [
            'totalViews' => $totalViews,
            'averageViews' => $averageViews,
            'totalPages' => $totalPages,
            'zeroViewCount' => $zeroViewCount,
            'topPages' => array_slice($initialEntries, 0, 3),
            'zeroViewExamples' => $zeroViewExamples,
            'initialEntries' => $initialEntries,
            'summaryComparisons' => $summaryComparisons,
            'lastUpdatedTimestamp' => $lastUpdated,
            'rankedEntries' => $rankedEntries,
        ];
    }
}
