<?php
// File: view.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/analytics.php';
require_login();

$pagesFile = __DIR__ . '/../../data/pages.json';
$pages = read_json_file($pagesFile);

$totalViews = 0;
$lastUpdatedTimestamp = 0;
foreach ($pages as $p) {
    $totalViews += (int) ($p['views'] ?? 0);
    $modified = isset($p['last_modified']) ? (int) $p['last_modified'] : 0;
    if ($modified > $lastUpdatedTimestamp) {
        $lastUpdatedTimestamp = $modified;
    }
}

$totalPages = count($pages);
$averageViews = $totalPages > 0 ? $totalViews / $totalPages : 0;

$sortedPages = $pages;
usort($sortedPages, function ($a, $b) {
    return ($b['views'] ?? 0) <=> ($a['views'] ?? 0);
});

$topPages = array_slice($sortedPages, 0, 3);
$zeroViewPages = array_values(array_filter($pages, static function ($page) {
    return (int) ($page['views'] ?? 0) === 0;
}));
$zeroViewCount = count($zeroViewPages);
$zeroViewExamples = array_slice($zeroViewPages, 0, 3);

$initialEntries = [];
$previousTotalViews = 0;
$previousZeroViewCount = 0;
foreach ($sortedPages as $page) {
    $views = (int) ($page['views'] ?? 0);
    $slug = isset($page['slug']) ? (string) $page['slug'] : '';
    $previousViews = analytics_previous_views($slug, $views);

    $initialEntries[] = [
        'title' => isset($page['title']) ? (string) $page['title'] : 'Untitled',
        'slug' => $slug,
        'views' => $views,
        'previousViews' => $previousViews,
    ];

    $previousTotalViews += $previousViews;
    if ($previousViews === 0) {
        $previousZeroViewCount++;
    }
}

$lastUpdatedDisplay = $lastUpdatedTimestamp > 0
    ? date('M j, Y g:i a', $lastUpdatedTimestamp)
    : null;

$previousAverageViews = $totalPages > 0 ? $previousTotalViews / $totalPages : 0;

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
        'previous' => $previousZeroViewCount,
    ],
];
?>
<link rel="stylesheet" href="modules/analytics/analytics.css">
<div class="content-section" id="analytics">
    <div class="analytics-dashboard">
        <header class="a11y-hero analytics-hero">
            <div class="a11y-hero-content analytics-hero-content">
                <div class="analytics-hero-text">
                    <h2 class="a11y-hero-title analytics-hero-title">Analytics Dashboard</h2>
                    <p class="a11y-hero-subtitle analytics-hero-subtitle">Monitor traffic trends, understand what resonates, and uncover pages that need promotion.</p>
                </div>
                <div class="a11y-hero-actions analytics-hero-actions">
                    <button type="button" class="analytics-btn analytics-btn--primary" data-analytics-action="refresh" data-loading-text="Refreshing&hellip;">
                        <i class="fa-solid fa-rotate" aria-hidden="true"></i>
                        <span class="analytics-btn__text">Refresh data</span>
                    </button>
                    <button type="button" class="analytics-btn analytics-btn--ghost" data-analytics-action="export" data-loading-text="Exporting&hellip;">
                        <i class="fa-solid fa-download" aria-hidden="true"></i>
                        <span class="analytics-btn__text">Export CSV</span>
                    </button>
                    <span class="a11y-hero-meta analytics-hero-meta" id="analyticsLastUpdated" data-timestamp="<?php echo $lastUpdatedTimestamp > 0 ? htmlspecialchars(date(DATE_ATOM, $lastUpdatedTimestamp), ENT_QUOTES) : ''; ?>">
                        <?php echo $lastUpdatedDisplay
                            ? 'Data refreshed ' . htmlspecialchars($lastUpdatedDisplay, ENT_QUOTES)
                            : 'Data refreshed moments ago'; ?>
                    </span>
                </div>
            </div>
            <div class="a11y-overview-grid analytics-overview-grid">
                <div class="a11y-overview-card analytics-overview-card">
                    <div class="a11y-overview-value analytics-overview-value" id="analyticsTotalViews" data-value="<?php echo (int) $totalViews; ?>"><?php echo number_format($totalViews); ?></div>
                    <div class="a11y-overview-label analytics-overview-label">Total Views</div>
                    <div class="analytics-overview-delta analytics-overview-delta--neutral" id="analyticsTotalViewsDelta" aria-live="polite">
                        <i class="fa-solid fa-minus analytics-overview-delta__icon" aria-hidden="true"></i>
                        <span class="analytics-overview-delta__text">No change vs previous</span>
                        <span class="sr-only analytics-overview-delta__sr">Comparison to the previous period will update shortly.</span>
                    </div>
                    <?php if (!empty($topPages)):
                        $topPage = $topPages[0]; ?>
                        <div class="analytics-overview-hint">Top page: <?php echo htmlspecialchars($topPage['title'] ?? 'Untitled', ENT_QUOTES); ?> (<?php echo number_format((int) ($topPage['views'] ?? 0)); ?>)</div>
                    <?php else: ?>
                        <div class="analytics-overview-hint">Traffic insights will appear as data arrives.</div>
                    <?php endif; ?>
                </div>
                <div class="a11y-overview-card analytics-overview-card">
                    <div class="a11y-overview-value analytics-overview-value" id="analyticsAverageViews" data-value="<?php echo $averageViews; ?>"><?php echo number_format($averageViews, 1); ?></div>
                    <div class="a11y-overview-label analytics-overview-label">Avg. views per page</div>
                    <div class="analytics-overview-delta analytics-overview-delta--neutral" id="analyticsAverageViewsDelta" aria-live="polite">
                        <i class="fa-solid fa-minus analytics-overview-delta__icon" aria-hidden="true"></i>
                        <span class="analytics-overview-delta__text">No change vs previous</span>
                        <span class="sr-only analytics-overview-delta__sr">Comparison to the previous period will update shortly.</span>
                    </div>
                    <div class="analytics-overview-hint">Based on <?php echo number_format($totalPages); ?> published pages</div>
                </div>
                <div class="a11y-overview-card analytics-overview-card">
                    <div class="a11y-overview-value analytics-overview-value" id="analyticsTotalPages" data-value="<?php echo $totalPages; ?>"><?php echo number_format($totalPages); ?></div>
                    <div class="a11y-overview-label analytics-overview-label">Published pages</div>
                    <div class="analytics-overview-delta analytics-overview-delta--neutral" id="analyticsTotalPagesDelta" aria-live="polite">
                        <i class="fa-solid fa-minus analytics-overview-delta__icon" aria-hidden="true"></i>
                        <span class="analytics-overview-delta__text">No change vs previous</span>
                        <span class="sr-only analytics-overview-delta__sr">Comparison to the previous period will update shortly.</span>
                    </div>
                    <div class="analytics-overview-hint">Includes static and dynamic content</div>
                </div>
                <div class="a11y-overview-card analytics-overview-card">
                    <div class="a11y-overview-value analytics-overview-value" id="analyticsZeroPages" data-value="<?php echo $zeroViewCount; ?>"><?php echo number_format($zeroViewCount); ?></div>
                    <div class="a11y-overview-label analytics-overview-label">Pages with no views</div>
                    <div class="analytics-overview-delta analytics-overview-delta--neutral" id="analyticsZeroPagesDelta" aria-live="polite">
                        <i class="fa-solid fa-minus analytics-overview-delta__icon" aria-hidden="true"></i>
                        <span class="analytics-overview-delta__text">No change vs previous</span>
                        <span class="sr-only analytics-overview-delta__sr">Comparison to the previous period will update shortly.</span>
                    </div>
                    <div class="analytics-overview-hint"><?php echo $zeroViewCount > 0 ? 'Great candidates for internal promotion.' : 'Every published page has traffic.'; ?></div>
                </div>
            </div>
        </header>

        <section class="analytics-insights">
            <article class="analytics-insight-card">
                <header class="analytics-insight-header">
                    <div class="analytics-insight-icon">
                        <i class="fa-solid fa-ranking-star" aria-hidden="true"></i>
                    </div>
                    <div>
                        <h3 class="analytics-insight-title">Top performers</h3>
                        <p class="analytics-insight-subtitle">Highest viewed content across your site</p>
                    </div>
                </header>
                <?php if (!empty($topPages)): ?>
                    <ul class="analytics-insight-list" id="analyticsTopList">
                        <?php foreach ($topPages as $page): ?>
                            <li>
                                <div>
                                    <span class="analytics-insight-item-title"><?php echo htmlspecialchars($page['title'] ?? 'Untitled', ENT_QUOTES); ?></span>
                                    <span class="analytics-insight-item-slug"><?php echo htmlspecialchars($page['slug'] ?? '', ENT_QUOTES); ?></span>
                                </div>
                                <span class="analytics-insight-metric"><?php echo number_format((int) ($page['views'] ?? 0)); ?> views</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <p class="analytics-insight-empty" id="analyticsTopEmpty" hidden>Traffic insights will appear once pages start receiving views.</p>
                <?php else: ?>
                    <ul class="analytics-insight-list" id="analyticsTopList" hidden></ul>
                    <p class="analytics-insight-empty" id="analyticsTopEmpty">Traffic insights will appear once pages start receiving views.</p>
                <?php endif; ?>
            </article>
            <article class="analytics-insight-card analytics-insight-card--secondary">
                <header class="analytics-insight-header">
                    <div class="analytics-insight-icon">
                        <i class="fa-solid fa-lightbulb" aria-hidden="true"></i>
                    </div>
                    <div>
                        <h3 class="analytics-insight-title">Opportunities</h3>
                        <p class="analytics-insight-subtitle">Pages that could use extra attention</p>
                    </div>
                </header>
                <p class="analytics-insight-summary" id="analyticsZeroSummary"><?php echo $zeroViewCount > 0
                    ? 'You have ' . number_format($zeroViewCount) . ' page' . ($zeroViewCount === 1 ? '' : 's') . ' with no recorded views.'
                    : 'Great job! Every published page has at least one view.'; ?></p>
                <?php if ($zeroViewCount > 0): ?>
                    <ul class="analytics-insight-list" id="analyticsZeroList">
                        <?php foreach ($zeroViewExamples as $page): ?>
                            <li>
                                <div>
                                    <span class="analytics-insight-item-title"><?php echo htmlspecialchars($page['title'] ?? 'Untitled', ENT_QUOTES); ?></span>
                                    <span class="analytics-insight-item-slug"><?php echo htmlspecialchars($page['slug'] ?? '', ENT_QUOTES); ?></span>
                                </div>
                                <span class="analytics-insight-metric">0 views</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <p class="analytics-insight-empty" id="analyticsZeroEmpty" hidden>Great job! Every published page has at least one view.</p>
                <?php else: ?>
                    <ul class="analytics-insight-list" id="analyticsZeroList" hidden></ul>
                    <p class="analytics-insight-empty" id="analyticsZeroEmpty">Great job! Every published page has at least one view.</p>
                <?php endif; ?>
            </article>
        </section>

        <section class="analytics-controls" aria-label="Analytics controls">
            <label class="analytics-search" for="analyticsSearchInput">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input type="search" id="analyticsSearchInput" placeholder="Search pages by title or slug" aria-label="Search analytics results">
            </label>
            <div class="analytics-filter-group" role="group" aria-label="Filter analytics results">
                <button type="button" class="analytics-filter-btn active" data-analytics-filter="all">All pages <span class="analytics-filter-count" data-analytics-count="all"><?php echo count($initialEntries); ?></span></button>
                <button type="button" class="analytics-filter-btn" data-analytics-filter="top">Top performers <span class="analytics-filter-count" data-analytics-count="top">0</span></button>
                <button type="button" class="analytics-filter-btn" data-analytics-filter="growing">Steady traffic <span class="analytics-filter-count" data-analytics-count="growing">0</span></button>
                <button type="button" class="analytics-filter-btn" data-analytics-filter="no-views">Needs promotion <span class="analytics-filter-count" data-analytics-count="no-views">0</span></button>
            </div>
            <div class="analytics-view-toggle" role="group" aria-label="Toggle analytics layout">
                <button type="button" class="analytics-view-btn active" data-analytics-view="grid" aria-label="Grid view"><i class="fa-solid fa-grip" aria-hidden="true"></i></button>
                <button type="button" class="analytics-view-btn" data-analytics-view="table" aria-label="Table view"><i class="fa-solid fa-table" aria-hidden="true"></i></button>
            </div>
        </section>

        <div class="analytics-grid" id="analyticsGrid" role="list"></div>

        <div class="analytics-table" id="analyticsTableView" hidden>
            <table class="analytics-table__table">
                <thead>
                    <tr>
                        <th scope="col" aria-sort="none">
                            <button type="button" class="analytics-table__sort" data-analytics-sort="title" data-analytics-sort-label="Title">
                                <span class="analytics-table__sort-label">Title</span>
                                <span class="analytics-table__sort-indicator" aria-hidden="true"></span>
                            </button>
                        </th>
                        <th scope="col" aria-sort="none">
                            <button type="button" class="analytics-table__sort" data-analytics-sort="slug" data-analytics-sort-label="Slug">
                                <span class="analytics-table__sort-label">Slug</span>
                                <span class="analytics-table__sort-indicator" aria-hidden="true"></span>
                            </button>
                        </th>
                        <th scope="col" aria-sort="descending">
                            <button type="button" class="analytics-table__sort" data-analytics-sort="views" data-analytics-sort-label="Views">
                                <span class="analytics-table__sort-label">Views</span>
                                <span class="analytics-table__sort-indicator" aria-hidden="true"></span>
                            </button>
                        </th>
                    </tr>
                </thead>
                <tbody id="analyticsTableBody"></tbody>
            </table>
        </div>

        <div class="analytics-empty-state" id="analyticsEmptyState" hidden>
            <i class="fa-regular fa-chart-bar" aria-hidden="true"></i>
            <p>No pages match your current filters. Adjust the filters or clear the search to see results.</p>
        </div>
    </div>
</div>
<script>
    window.analyticsInitialEntries = <?php echo json_encode($initialEntries, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    window.analyticsInitialMeta = <?php echo json_encode([
        'lastUpdated' => $lastUpdatedDisplay ? 'Data refreshed ' . $lastUpdatedDisplay : 'Data refreshed moments ago',
        'lastUpdatedIso' => $lastUpdatedTimestamp > 0 ? date(DATE_ATOM, $lastUpdatedTimestamp) : null,
        'summary' => $summaryComparisons,
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
</script>
