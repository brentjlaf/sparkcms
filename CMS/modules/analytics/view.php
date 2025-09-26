<?php
// File: view.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_login();

$pagesFile = __DIR__ . '/../../data/pages.json';
$pages = read_json_file($pagesFile);
$totalViews = 0;
foreach ($pages as $p) {
    $totalViews += $p['views'] ?? 0;
}

$totalPages = count($pages);
$averageViews = $totalPages > 0 ? $totalViews / $totalPages : 0;

$sortedPages = $pages;
usort($sortedPages, function ($a, $b) {
    return ($b['views'] ?? 0) <=> ($a['views'] ?? 0);
});

$topPages = array_slice($sortedPages, 0, 3);
$topPage = $topPages[0] ?? null;

$zeroViewPages = array_values(array_filter($pages, function ($page) {
    return ($page['views'] ?? 0) === 0;
}));
$zeroViewCount = count($zeroViewPages);
$zeroViewExamples = array_slice($zeroViewPages, 0, 3);
?>
<div class="content-section" id="analytics">
    <div class="table-card">
        <div class="table-header">
            <div class="table-title">Analytics</div>
        </div>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon views"><i class="fa-solid fa-chart-line" aria-hidden="true"></i></div>
                    <div class="stat-content">
                        <div class="stat-label">Total Views</div>
                        <div class="stat-number"><?php echo number_format($totalViews); ?></div>
                        <?php if ($topPage): ?>
                        <div class="stat-subtext">Top page: <?php echo htmlspecialchars($topPage['title']); ?> (<?php echo number_format($topPage['views'] ?? 0); ?>)</div>
                        <?php else: ?>
                        <div class="stat-subtext">No traffic recorded yet</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon average"><i class="fa-solid fa-gauge-high" aria-hidden="true"></i></div>
                    <div class="stat-content">
                        <div class="stat-label">Avg. Views / Page</div>
                        <div class="stat-number"><?php echo number_format($averageViews, 1); ?></div>
                        <div class="stat-subtext">Based on <?php echo $totalPages; ?> published pages</div>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon pages"><i class="fa-regular fa-file-lines" aria-hidden="true"></i></div>
                    <div class="stat-content">
                        <div class="stat-label">Published Pages</div>
                        <div class="stat-number"><?php echo number_format($totalPages); ?></div>
                        <div class="stat-subtext">Includes static and dynamic content</div>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon low-views"><i class="fa-solid fa-arrow-trend-down" aria-hidden="true"></i></div>
                    <div class="stat-content">
                        <div class="stat-label">Unviewed Pages</div>
                        <div class="stat-number"><?php echo number_format($zeroViewCount); ?></div>
                        <div class="stat-subtext"><?php echo $zeroViewCount > 0 ? 'Great candidates for promotion' : 'Every page has traffic'; ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="insights-grid">
            <div class="insights-card">
                <div class="insights-header">
                    <div class="insights-icon highlight"><i class="fa-solid fa-ranking-star" aria-hidden="true"></i></div>
                    <div>
                        <div class="insights-title">Top Performing Pages</div>
                        <div class="insights-subtitle">Most viewed content this period</div>
                    </div>
                </div>
                <?php if (!empty($topPages)): ?>
                <ul class="insights-list">
                    <?php foreach ($topPages as $page): ?>
                        <li>
                            <div>
                                <div class="insight-title"><?php echo htmlspecialchars($page['title']); ?></div>
                                <div class="insight-subtitle"><?php echo htmlspecialchars($page['slug']); ?></div>
                            </div>
                            <div class="insight-metric"><?php echo number_format($page['views'] ?? 0); ?> views</div>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <div class="insight-empty">Traffic insights will appear once pages start receiving views.</div>
                <?php endif; ?>
            </div>
            <div class="insights-card opportunities">
                <div class="insights-header">
                    <div class="insights-icon opportunities"><i class="fa-solid fa-lightbulb" aria-hidden="true"></i></div>
                    <div>
                        <div class="insights-title">Opportunities</div>
                        <div class="insights-subtitle">Pages that need more attention</div>
                    </div>
                </div>
                <?php if ($zeroViewCount > 0): ?>
                <div class="insight-summary">You have <?php echo number_format($zeroViewCount); ?> page<?php echo $zeroViewCount === 1 ? '' : 's'; ?> without any views.</div>
                <ul class="insights-list">
                    <?php foreach ($zeroViewExamples as $page): ?>
                        <li>
                            <div>
                                <div class="insight-title"><?php echo htmlspecialchars($page['title']); ?></div>
                                <div class="insight-subtitle"><?php echo htmlspecialchars($page['slug']); ?></div>
                            </div>
                            <div class="insight-metric">0 views</div>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <div class="insight-empty">Great job! Every published page has at least one view.</div>
                <?php endif; ?>
            </div>
        </div>
        <table class="data-table" id="analyticsTable">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Slug</th>
                    <th>Views</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>
