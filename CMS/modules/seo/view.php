<?php
// File: modules/seo/view.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/settings.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../includes/score_history.php';
require_once __DIR__ . '/SeoReport.php';
require_login();

$pagesFile = __DIR__ . '/../../data/pages.json';
$menusFile = __DIR__ . '/../../data/menus.json';

$settings = get_site_settings();
if (!is_array($settings)) {
    $settings = [];
}

$menus = read_json_file($menusFile);
if (!is_array($menus)) {
    $menus = [];
}

$pages = SeoReport::loadPages($pagesFile);

$scriptBase = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
if (substr($scriptBase, -4) === '/CMS') {
    $scriptBase = substr($scriptBase, 0, -4);
}
$scriptBase = rtrim($scriptBase, '/');

$templateDir = realpath(__DIR__ . '/../../../theme/templates/pages');
$lastScan = date('M j, Y g:i A');

$reportService = new SeoReport(
    $pages,
    $settings,
    $menus,
    $scriptBase,
    $templateDir,
    null,
    $lastScan
);

$report = $reportService->generateReport();
$pageEntries = $report['pages'];
$pageEntryMap = $report['pageMap'];
$dashboardStats = $report['stats'];
$lastScan = (string)($dashboardStats['lastScan'] ?? $lastScan);

$scriptPath = isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : '';
$queryParams = is_array($_GET) ? $_GET : [];
unset($queryParams['page']);
$queryParams = array_merge(['module' => 'seo'], $queryParams);
$moduleQuery = http_build_query($queryParams);
$moduleUrl = $scriptPath . ($moduleQuery !== '' ? '?' . $moduleQuery : '');
$moduleAnchorUrl = $moduleUrl . '#seo';
$dashboardStats['moduleUrl'] = $moduleUrl;
$dashboardStats['detailBaseUrl'] = $moduleUrl . (strpos($moduleUrl, '?') === false ? '?' : '&') . 'page=';

$detailSlug = isset($_GET['page']) ? sanitize_text($_GET['page']) : null;
$detailSlug = $detailSlug !== null ? trim($detailSlug) : null;

$selectedPage = null;
if ($detailSlug !== null && $detailSlug !== '') {
    $selectedPage = $pageEntryMap[$detailSlug] ?? null;
}
?>
<div class="content-section" id="seo">
<?php if ($selectedPage): ?>
    <div class="a11y-detail-page seo-detail-page" id="seoDetailPage" data-page-slug="<?php echo htmlspecialchars($selectedPage['slug'], ENT_QUOTES); ?>">
        <?php
            $currentScore = (int)($selectedPage['seoScore'] ?? 0);
            $previousScore = (int)($selectedPage['previousScore'] ?? $currentScore);
            $deltaMeta = describe_score_delta($currentScore, $previousScore);

            $impactCounts = [
                SeoReport::IMPACT_CRITICAL => 0,
                SeoReport::IMPACT_SERIOUS => 0,
                SeoReport::IMPACT_MODERATE => 0,
                SeoReport::IMPACT_MINOR => 0,
                SeoReport::IMPACT_REVIEW => 0,
            ];
            foreach ($selectedPage['issues']['details'] as $issueDetail) {
                $impact = strtolower($issueDetail['impact']);
                if (isset($impactCounts[$impact])) {
                    $impactCounts[$impact]++;
                } else {
                    $impactCounts[SeoReport::IMPACT_REVIEW]++;
                }
            }
            $issueDetailCount = array_sum($impactCounts);
        ?>
        <header class="a11y-detail-header">
            <a href="<?php echo htmlspecialchars($moduleAnchorUrl, ENT_QUOTES); ?>" class="a11y-back-link" id="seoBackToDashboard">
                <i class="fas fa-arrow-left" aria-hidden="true"></i>
                <span>Back to SEO Dashboard</span>
            </a>
            <div class="a11y-detail-actions">
                <button type="button" class="a11y-btn a11y-btn--ghost" data-seo-action="rescan-page">
                    <i class="fas fa-rotate" aria-hidden="true"></i>
                    <span>Rescan Page</span>
                </button>
            </div>
        </header>

        <section class="a11y-health-card">
            <div class="a11y-health-score">
                <div class="score-indicator score-indicator--hero">
                    <div class="a11y-health-score__value">
                        <span class="score-indicator__number"><?php echo $currentScore; ?></span><span>%</span>
                    </div>
                    <span class="score-delta <?php echo htmlspecialchars($deltaMeta['class'], ENT_QUOTES); ?>">
                        <span aria-hidden="true"><?php echo htmlspecialchars($deltaMeta['display'], ENT_QUOTES); ?></span>
                        <span class="sr-only"><?php echo htmlspecialchars($deltaMeta['srText'], ENT_QUOTES); ?></span>
                    </span>
                </div>
                <div class="a11y-health-score__label">SEO Score</div>
                <span class="a11y-health-score__badge level-<?php echo strtolower(str_replace(' ', '-', $selectedPage['optimizationLevel'])); ?>"><?php echo htmlspecialchars($selectedPage['optimizationLevel']); ?></span>
            </div>
            <div class="a11y-health-summary">
                <h1><?php echo htmlspecialchars($selectedPage['title']); ?></h1>
                <p class="a11y-health-url"><?php echo htmlspecialchars($selectedPage['url']); ?></p>
                <p><?php echo htmlspecialchars($selectedPage['statusMessage']); ?></p>
                <p class="a11y-health-overview"><?php echo htmlspecialchars($selectedPage['summaryLine']); ?></p>
                <div class="a11y-quick-stats">
                    <div class="a11y-quick-stat">
                        <div class="a11y-quick-stat__value"><?php echo $selectedPage['metrics']['wordCount']; ?></div>
                        <div class="a11y-quick-stat__label">Words</div>
                    </div>
                    <div class="a11y-quick-stat">
                        <div class="a11y-quick-stat__value"><?php echo $selectedPage['metrics']['links']['internal']; ?></div>
                        <div class="a11y-quick-stat__label">Internal Links</div>
                    </div>
                    <div class="a11y-quick-stat">
                        <div class="a11y-quick-stat__value">H1: <?php echo $selectedPage['metrics']['h1Count']; ?></div>
                        <div class="a11y-quick-stat__label">Heading Structure</div>
                    </div>
                    <div class="a11y-quick-stat">
                        <div class="a11y-quick-stat__value"><?php echo $selectedPage['metrics']['missingAlt']; ?></div>
                        <div class="a11y-quick-stat__label">Missing Alt</div>
                    </div>
                </div>
            </div>
        </section>

        <section class="a11y-detail-grid" aria-labelledby="seoMetricsHeading">
            <article class="a11y-detail-card seo-detail-card">
                <div class="seo-detail-card__header">
                    <h2 id="seoMetricsHeading">Key SEO signals</h2>
                    <p class="seo-detail-card__hint">Focus on the fundamentals that influence crawlability, relevance, and click-through rate.</p>
                </div>
                <div class="seo-detail-card__metrics">
                    <div class="seo-metric-group">
                        <h3>Metadata</h3>
                        <ul class="seo-metric-list">
                            <li><span>Title length</span><span><?php echo $selectedPage['metrics']['titleLength']; ?> characters</span></li>
                            <li><span>Meta description</span><span><?php echo $selectedPage['metrics']['metaDescriptionLength']; ?> characters</span></li>
                            <li><span>Canonical URL</span><span><?php echo $selectedPage['metrics']['hasCanonical'] ? 'Present' : 'Missing'; ?></span></li>
                        </ul>
                    </div>
                    <div class="seo-metric-group">
                        <h3>Content depth</h3>
                        <ul class="seo-metric-list">
                            <li><span>Word count</span><span><?php echo $selectedPage['metrics']['wordCount']; ?></span></li>
                            <li><span>H1 headings</span><span><?php echo $selectedPage['metrics']['h1Count']; ?></span></li>
                            <li><span>Structured data</span><span><?php echo $selectedPage['metrics']['hasStructuredData'] ? 'Detected' : 'Not detected'; ?></span></li>
                        </ul>
                    </div>
                    <div class="seo-metric-group">
                        <h3>Media &amp; sharing</h3>
                        <ul class="seo-metric-list">
                            <li><span>Images</span><span><?php echo $selectedPage['metrics']['images']; ?></span></li>
                            <li><span>Missing alt text</span><span><?php echo $selectedPage['metrics']['missingAlt']; ?></span></li>
                            <li><span>Open Graph</span><span><?php echo $selectedPage['metrics']['hasOpenGraph'] ? 'Configured' : 'Missing'; ?></span></li>
                        </ul>
                    </div>
                    <div class="seo-metric-group">
                        <h3>Linking</h3>
                        <ul class="seo-metric-list">
                            <li><span>Internal links</span><span><?php echo $selectedPage['metrics']['links']['internal']; ?></span></li>
                            <li><span>External links</span><span><?php echo $selectedPage['metrics']['links']['external']; ?></span></li>
                            <li><span>Robots directives</span><span><?php echo $selectedPage['metrics']['isNoindex'] ? 'Noindex' : 'Indexable'; ?></span></li>
                        </ul>
                    </div>
                </div>
            </article>
        </section>

        <section class="a11y-detail-issues seo-detail-issues" aria-labelledby="seoIssuesHeading">
            <header class="seo-detail-issues__header">
                <div>
                    <h2 id="seoIssuesHeading">Actionable SEO fixes</h2>
                    <p>Address these issues to strengthen relevance signals and organic performance.</p>
                </div>
                <span class="seo-detail-issues__count"><?php echo $issueDetailCount; ?> <?php echo $issueDetailCount === 1 ? 'issue' : 'issues'; ?></span>
            </header>
            <?php if ($issueDetailCount > 0): ?>
                <div class="a11y-severity-filters" role="group" aria-label="Filter issues by severity">
                    <button type="button" class="a11y-severity-btn active" data-seo-severity="all" aria-pressed="true" aria-label="Show all issues (<?php echo $issueDetailCount; ?>)">
                        All <span aria-hidden="true">(<?php echo $issueDetailCount; ?>)</span>
                    </button>
                    <button type="button" class="a11y-severity-btn" data-seo-severity="<?php echo SeoReport::IMPACT_CRITICAL; ?>" aria-pressed="false" aria-label="Show critical issues (<?php echo $impactCounts[SeoReport::IMPACT_CRITICAL]; ?>)">
                        Critical <span aria-hidden="true">(<?php echo $impactCounts[SeoReport::IMPACT_CRITICAL]; ?>)</span>
                    </button>
                    <button type="button" class="a11y-severity-btn" data-seo-severity="<?php echo SeoReport::IMPACT_SERIOUS; ?>" aria-pressed="false" aria-label="Show serious issues (<?php echo $impactCounts[SeoReport::IMPACT_SERIOUS]; ?>)">
                        Serious <span aria-hidden="true">(<?php echo $impactCounts[SeoReport::IMPACT_SERIOUS]; ?>)</span>
                    </button>
                    <button type="button" class="a11y-severity-btn" data-seo-severity="<?php echo SeoReport::IMPACT_MODERATE; ?>" aria-pressed="false" aria-label="Show moderate issues (<?php echo $impactCounts[SeoReport::IMPACT_MODERATE]; ?>)">
                        Moderate <span aria-hidden="true">(<?php echo $impactCounts[SeoReport::IMPACT_MODERATE]; ?>)</span>
                    </button>
                    <button type="button" class="a11y-severity-btn" data-seo-severity="<?php echo SeoReport::IMPACT_MINOR; ?>" aria-pressed="false" aria-label="Show minor issues (<?php echo $impactCounts[SeoReport::IMPACT_MINOR]; ?>)">
                        Minor <span aria-hidden="true">(<?php echo $impactCounts[SeoReport::IMPACT_MINOR]; ?>)</span>
                    </button>
                    <button type="button" class="a11y-severity-btn" data-seo-severity="<?php echo SeoReport::IMPACT_REVIEW; ?>" aria-pressed="false" aria-label="Show review issues (<?php echo $impactCounts[SeoReport::IMPACT_REVIEW]; ?>)">
                        Review <span aria-hidden="true">(<?php echo $impactCounts[SeoReport::IMPACT_REVIEW]; ?>)</span>
                    </button>
                </div>
                <div class="sr-only" id="seoIssueFilterStatus" role="status" aria-live="polite"></div>
                <div class="a11y-issue-list">
                    <?php foreach ($selectedPage['issues']['details'] as $issue): ?>
                        <article class="a11y-issue-card impact-<?php echo htmlspecialchars($issue['impact']); ?>" data-impact="<?php echo htmlspecialchars(strtolower($issue['impact'])); ?>">
                            <header>
                                <h3><?php echo htmlspecialchars($issue['description']); ?></h3>
                                <span class="a11y-impact-badge impact-<?php echo htmlspecialchars($issue['impact']); ?>"><?php echo ucfirst($issue['impact']); ?></span>
                            </header>
                            <p><?php echo htmlspecialchars($issue['recommendation']); ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
                <p class="a11y-detail-empty" id="seoNoIssuesMessage" hidden>No issues match this severity filter.</p>
            <?php else: ?>
                <p class="a11y-detail-success">This page passes the automated SEO checks with no outstanding issues.</p>
            <?php endif; ?>
        </section>
    </div>
<?php else: ?>
    <div class="seo-dashboard a11y-dashboard" data-last-scan="<?php echo htmlspecialchars($lastScan, ENT_QUOTES); ?>">
        <header class="seo-hero">
            <div class="seo-hero-content">
                <div>
                    <span class="hero-eyebrow seo-hero-eyebrow">Optimisation Signals</span>
                    <h2 class="seo-hero-title">SEO Optimisation Dashboard</h2>
                    <p class="seo-hero-subtitle">Measure on-page signals and prioritise fixes that improve organic visibility.</p>
                </div>
                <div class="seo-hero-actions">
                    <button type="button" id="seoScanAllPagesBtn" class="a11y-btn a11y-btn--primary" data-seo-action="scan-all">
                        <i class="fas fa-search" aria-hidden="true"></i>
                        <span>Scan All Pages</span>
                    </button>
                    <span class="seo-hero-meta">
                        <i class="fas fa-clock" aria-hidden="true"></i>
                        Last scan: <span class="seo-last-scan-value"><?php echo htmlspecialchars($lastScan); ?></span>
                    </span>
                </div>
            </div>
            <div class="seo-overview-grid">
                <div class="seo-overview-card">
                    <div class="seo-overview-value" id="seoStatTotalPages"><?php echo $totalPages; ?></div>
                    <div class="seo-overview-label">Total Pages</div>
                </div>
                <div class="seo-overview-card">
                    <div class="seo-overview-value" id="seoStatAvgScore"><?php echo $avgScore; ?>%</div>
                    <div class="seo-overview-label">Average SEO Score</div>
                </div>
                <div class="seo-overview-card">
                    <div class="seo-overview-value" id="seoStatCritical"><?php echo $criticalIssues; ?></div>
                    <div class="seo-overview-label">Critical Issues</div>
                </div>
                <div class="seo-overview-card">
                    <div class="seo-overview-value" id="seoStatOptimized"><?php echo $optimizedPages; ?></div>
                    <div class="seo-overview-label">Optimised Pages</div>
                </div>
            </div>
        </header>

        <div class="seo-controls">
            <label class="seo-search" for="seoSearchInput">
                <i class="fas fa-search" aria-hidden="true"></i>
                <input type="search" id="seoSearchInput" placeholder="Search pages by title, URL, or issue" aria-label="Search SEO results">
            </label>
            <div class="a11y-filter-group" role="group" aria-label="SEO filters">
                <button type="button" class="a11y-filter-btn active" data-seo-filter="all">All Pages <span class="a11y-filter-count" data-count="all"><?php echo $filterCounts['all']; ?></span></button>
                <button type="button" class="a11y-filter-btn" data-seo-filter="critical">Critical Issues <span class="a11y-filter-count" data-count="critical"><?php echo $filterCounts['critical']; ?></span></button>
                <button type="button" class="a11y-filter-btn" data-seo-filter="needs-work">Needs Work <span class="a11y-filter-count" data-count="needs-work"><?php echo $filterCounts['needs-work']; ?></span></button>
                <button type="button" class="a11y-filter-btn" data-seo-filter="optimized">Optimised <span class="a11y-filter-count" data-count="optimized"><?php echo $filterCounts['optimized']; ?></span></button>
            </div>
            <div class="a11y-sort-group" role="group" aria-label="Sort pages">
                <label for="seoSortSelect">Sort by</label>
                <select id="seoSortSelect" class="a11y-sort-select">
                    <option value="score" selected>SEO score</option>
                    <option value="title">Title</option>
                    <option value="issues">Total issues</option>
                </select>
                <button type="button" class="a11y-sort-direction" id="seoSortDirection" data-direction="desc" aria-label="Toggle sort direction (High to low)" aria-pressed="true">
                    <i class="fas fa-sort-amount-down-alt" aria-hidden="true"></i>
                    <span class="a11y-sort-direction__text" id="seoSortDirectionLabel">High to low</span>
                </button>
            </div>
            <div class="seo-view-toggle" role="group" aria-label="Toggle layout">
                <button type="button" class="seo-view-btn active" data-seo-view="grid" aria-label="Grid view">
                    <i class="fas fa-th-large" aria-hidden="true"></i>
                </button>
                <button type="button" class="seo-view-btn" data-seo-view="table" aria-label="Table view">
                    <i class="fas fa-table" aria-hidden="true"></i>
                </button>
            </div>
        </div>

        <section class="seo-page-grid" aria-live="polite">
            <?php foreach ($pageEntries as $page): ?>
                <article class="seo-page-card" data-page-slug="<?php echo htmlspecialchars($page['slug']); ?>" data-seo-score="<?php echo (int)$page['seoScore']; ?>" data-seo-level="<?php echo htmlspecialchars(strtolower(str_replace(' ', '-', $page['optimizationLevel'])), ENT_QUOTES); ?>" data-issues="<?php echo (int)$page['violations']['total']; ?>" tabindex="0" role="button" aria-haspopup="dialog" aria-label="View SEO summary for <?php echo htmlspecialchars($page['title']); ?>">
                    <header>
                        <div class="seo-page-score score-indicator <?php echo $page['seoScore'] >= 90 ? 'seo-score--excellent' : ($page['seoScore'] >= 75 ? 'seo-score--good' : ($page['seoScore'] >= 60 ? 'seo-score--fair' : 'seo-score--poor')); ?>">
                            <span class="score-indicator__number"><?php echo (int)$page['seoScore']; ?></span><span>%</span>
                        </div>
                        <span class="seo-level-badge level-<?php echo htmlspecialchars(strtolower(str_replace(' ', '-', $page['optimizationLevel'])), ENT_QUOTES); ?>"><?php echo htmlspecialchars($page['optimizationLevel']); ?></span>
                    </header>
                    <h3><?php echo htmlspecialchars($page['title']); ?></h3>
                    <p class="seo-page-url"><?php echo htmlspecialchars($page['url']); ?></p>
                    <p class="seo-page-summary"><?php echo htmlspecialchars($page['summaryLine']); ?></p>
                    <ul class="seo-page-metrics">
                        <li><span>Word count</span><span><?php echo $page['metrics']['wordCount']; ?></span></li>
                        <li><span>Internal links</span><span><?php echo $page['metrics']['links']['internal']; ?></span></li>
                        <li><span>Missing alt</span><span><?php echo $page['metrics']['missingAlt']; ?></span></li>
                    </ul>
                    <div class="seo-page-issues">
                        <?php foreach ($page['issues']['preview'] as $issue): ?>
                            <span class="seo-issue-chip"><?php echo htmlspecialchars($issue); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <footer>
                        <button type="button" class="seo-card-link" aria-label="See detailed SEO guidance for <?php echo htmlspecialchars($page['title']); ?>">
                            <span>See detailed SEO guidance</span>
                            <i class="fas fa-arrow-right" aria-hidden="true"></i>
                        </button>
                        <span class="sr-only">Press Enter to open SEO details for <?php echo htmlspecialchars($page['title']); ?>.</span>
                    </footer>
                </article>
            <?php endforeach; ?>
        </section>

        <section class="seo-page-table" hidden>
            <table>
                <caption class="sr-only">SEO summary table</caption>
                <thead>
                    <tr>
                        <th scope="col">Page</th>
                        <th scope="col">Score</th>
                        <th scope="col">Issues</th>
                        <th scope="col">Word count</th>
                        <th scope="col">Internal links</th>
                        <th scope="col">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pageEntries as $page): ?>
                        <tr data-page-slug="<?php echo htmlspecialchars($page['slug']); ?>" data-seo-score="<?php echo (int)$page['seoScore']; ?>" data-issues="<?php echo (int)$page['violations']['total']; ?>" tabindex="0" role="button" aria-haspopup="dialog" aria-label="View SEO summary for <?php echo htmlspecialchars($page['title']); ?>">
                            <th scope="row">
                                <div class="seo-table-title"><?php echo htmlspecialchars($page['title']); ?></div>
                                <div class="seo-table-url"><?php echo htmlspecialchars($page['url']); ?></div>
                            </th>
                            <td><?php echo (int)$page['seoScore']; ?>%</td>
                            <td><?php echo (int)$page['violations']['total']; ?></td>
                            <td><?php echo (int)$page['metrics']['wordCount']; ?></td>
                            <td><?php echo (int)$page['metrics']['links']['internal']; ?></td>
                            <td><?php echo htmlspecialchars($page['optimizationLevel']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <div class="a11y-page-detail seo-page-detail" id="seoPageDetail" hidden role="dialog" aria-modal="true" aria-labelledby="seoDetailTitle">
            <div class="a11y-detail-content">
                <button type="button" class="a11y-detail-close" id="seoDetailClose" aria-label="Close SEO details">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
                <header class="a11y-detail-modal-header">
                    <h2 id="seoDetailTitle">Page SEO Details</h2>
                    <p id="seoDetailUrl" class="a11y-detail-url"></p>
                    <p id="seoDetailDescription" class="a11y-detail-description"></p>
                </header>
                <div class="a11y-detail-modal-body">
                    <div class="a11y-detail-badges">
                        <span class="a11y-detail-score score-indicator score-indicator--badge" id="seoDetailScore"></span>
                        <span class="a11y-detail-level" id="seoDetailLevel"></span>
                        <span class="a11y-detail-violations" id="seoDetailSignals"></span>
                    </div>
                    <ul class="a11y-detail-metric-list" id="seoDetailMetrics"></ul>
                    <div class="a11y-detail-issues-list">
                        <h3>Key SEO findings</h3>
                        <ul id="seoDetailIssues"></ul>
                    </div>
                </div>
            </div>
        </div>

        <footer class="seo-dashboard-footer">
            <p>SEO data last analysed on <span class="seo-last-scan-value"><?php echo htmlspecialchars($lastScan); ?></span>. Refresh the scan whenever you publish new content or update templates.</p>
        </footer>
    </div>
<?php endif; ?>
</div>
<script>
    window.__SEO_MODULE_DATA__ = <?php echo json_encode([
        'pages' => $pageEntries,
        'stats' => $dashboardStats,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT); ?>;
</script>
