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

$reportService = new SeoReport(
    $pages,
    $settings,
    $menus,
    $scriptBase,
    $templateDir,
    null,
    date('M j, Y g:i A')
);

$report = $reportService->generateReport();
$pageEntries = $report['pages'];
$pageEntryMap = $report['pageMap'];
$dashboardStats = $report['stats'];

$totalPages = isset($dashboardStats['totalPages']) ? (int)$dashboardStats['totalPages'] : 0;
$avgScore = isset($dashboardStats['avgScore']) ? (int)$dashboardStats['avgScore'] : 0;
$criticalIssues = isset($dashboardStats['criticalIssues']) ? (int)$dashboardStats['criticalIssues'] : 0;
$optimizedPages = isset($dashboardStats['optimizedPages']) ? (int)$dashboardStats['optimizedPages'] : 0;
$lastScan = isset($dashboardStats['lastScan']) && $dashboardStats['lastScan'] !== ''
    ? $dashboardStats['lastScan']
    : 'Never';
$filterCounts = [
    'all' => 0,
    'critical' => 0,
    'opportunity' => 0,
    'onTrack' => 0,
    'optimized' => 0,
];
if (isset($dashboardStats['filterCounts']) && is_array($dashboardStats['filterCounts'])) {
    $filterCounts = array_merge($filterCounts, $dashboardStats['filterCounts']);
}

$moduleUrl = $_SERVER['PHP_SELF'] . '?module=seo';
$dashboardStats['moduleUrl'] = $moduleUrl;
$dashboardStats['detailBaseUrl'] = $moduleUrl . '&page=';

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
            $badgeClass = 'seo-badge--' . strtolower(str_replace(' ', '-', $selectedPage['optimizationLevel']));
        ?>
        <header class="a11y-detail-header seo-detail-header">
            <a href="<?php echo htmlspecialchars($moduleUrl, ENT_QUOTES); ?>" class="a11y-back-link seo-back-link" id="seoBackToDashboard">
                <i class="fas fa-arrow-left" aria-hidden="true"></i>
                <span>Back to Dashboard</span>
            </a>
            <div class="a11y-detail-actions seo-detail-actions">
                <button type="button" class="a11y-btn a11y-btn--ghost" data-seo-action="rescan-page">
                    <i class="fas fa-rotate" aria-hidden="true"></i>
                    <span>Rescan Page</span>
                </button>
            </div>
        </header>

        <section class="a11y-health-card seo-health-card">
            <div class="a11y-health-score seo-health-score">
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
                <span class="seo-health-score__badge <?php echo htmlspecialchars($badgeClass, ENT_QUOTES); ?>">
                    <?php echo htmlspecialchars($selectedPage['optimizationLevel']); ?>
                </span>
            </div>
            <div class="a11y-health-summary seo-health-summary">
                <h1><?php echo htmlspecialchars($selectedPage['title']); ?></h1>
                <p class="a11y-health-url"><?php echo htmlspecialchars($selectedPage['url']); ?></p>
                <p><?php echo htmlspecialchars($selectedPage['statusMessage']); ?></p>
                <p class="seo-health-overview"><?php echo htmlspecialchars($selectedPage['summaryLine']); ?></p>
                <div class="a11y-quick-stats seo-quick-stats">
                    <div class="a11y-quick-stat">
                        <div class="a11y-quick-stat__value"><?php echo (int)$selectedPage['quickStats']['wordCount']; ?></div>
                        <div class="a11y-quick-stat__label">Words</div>
                    </div>
                    <div class="a11y-quick-stat">
                        <div class="a11y-quick-stat__value"><?php echo (int)$selectedPage['quickStats']['internalLinks']; ?></div>
                        <div class="a11y-quick-stat__label">Internal Links</div>
                    </div>
                    <div class="a11y-quick-stat">
                        <div class="a11y-quick-stat__value">H1: <?php echo (int)$selectedPage['quickStats']['h1']; ?></div>
                        <div class="a11y-quick-stat__label">Primary Headings</div>
                    </div>
                    <div class="a11y-quick-stat">
                        <div class="a11y-quick-stat__value"><?php echo (int)$selectedPage['quickStats']['missingAlt']; ?></div>
                        <div class="a11y-quick-stat__label">Missing Alt</div>
                    </div>
                </div>
            </div>
        </section>

        <section class="a11y-detail-grid seo-detail-grid">
            <article class="a11y-detail-card seo-detail-card">
                <h2>Metadata health</h2>
                <ul class="seo-detail-list">
                    <li>
                        <span class="seo-detail-label">Title tag</span>
                        <span class="seo-detail-value"><?php echo $selectedPage['metadata']['titleTag'] !== '' ? 'Present' : 'Missing'; ?></span>
                        <?php if ($selectedPage['metadata']['titleFallback']): ?>
                            <span class="seo-detail-hint">Using stored title fallback</span>
                        <?php endif; ?>
                    </li>
                    <li>
                        <span class="seo-detail-label">Meta description</span>
                        <span class="seo-detail-value"><?php echo $selectedPage['metadata']['metaDescription'] !== '' ? 'Present' : 'Missing'; ?></span>
                        <?php if ($selectedPage['metadata']['descriptionFallback']): ?>
                            <span class="seo-detail-hint">Using stored description fallback</span>
                        <?php endif; ?>
                    </li>
                    <li>
                        <span class="seo-detail-label">Canonical URL</span>
                        <span class="seo-detail-value"><?php echo $selectedPage['metadata']['canonical'] !== '' ? 'Declared' : 'None'; ?></span>
                    </li>
                    <li>
                        <span class="seo-detail-label">Robots directive</span>
                        <span class="seo-detail-value"><?php echo $selectedPage['metadata']['robots'] !== '' ? htmlspecialchars($selectedPage['metadata']['robots']) : 'Indexable'; ?></span>
                        <?php if (!empty($selectedPage['metadata']['robotsFallback'])): ?>
                            <span class="seo-detail-hint">Using stored robots fallback</span>
                        <?php endif; ?>
                    </li>
                </ul>
            </article>
            <article class="a11y-detail-card seo-detail-card">
                <h2>Content & headings</h2>
                <ul class="seo-detail-list">
                    <li>
                        <span class="seo-detail-label">Word count</span>
                        <span class="seo-detail-value"><?php echo (int)$selectedPage['metrics']['wordCount']; ?></span>
                    </li>
                    <li>
                        <span class="seo-detail-label">H1 headings</span>
                        <span class="seo-detail-value"><?php echo (int)$selectedPage['metrics']['headings']['h1']; ?></span>
                    </li>
                    <li>
                        <span class="seo-detail-label">H2 headings</span>
                        <span class="seo-detail-value"><?php echo (int)$selectedPage['metrics']['headings']['h2']; ?></span>
                    </li>
                    <li>
                        <span class="seo-detail-label">H3 headings</span>
                        <span class="seo-detail-value"><?php echo (int)$selectedPage['metrics']['headings']['h3']; ?></span>
                    </li>
                </ul>
            </article>
            <article class="a11y-detail-card seo-detail-card">
                <h2>Media & sharing</h2>
                <ul class="seo-detail-list">
                    <li>
                        <span class="seo-detail-label">Images analysed</span>
                        <span class="seo-detail-value"><?php echo (int)$selectedPage['metrics']['images']; ?></span>
                    </li>
                    <li>
                        <span class="seo-detail-label">Images missing alt</span>
                        <span class="seo-detail-value"><?php echo (int)$selectedPage['metrics']['missingAlt']; ?></span>
                    </li>
                    <li>
                        <span class="seo-detail-label">Open Graph tags</span>
                        <span class="seo-detail-value"><?php echo (int)$selectedPage['metadata']['openGraphCount']; ?></span>
                    </li>
                    <li>
                        <span class="seo-detail-label">Structured data blocks</span>
                        <span class="seo-detail-value"><?php echo (int)$selectedPage['metadata']['structuredDataCount']; ?></span>
                    </li>
                </ul>
            </article>
            <article class="a11y-detail-card seo-detail-card">
                <h2>Linking overview</h2>
                <ul class="seo-detail-list">
                    <li>
                        <span class="seo-detail-label">Internal links</span>
                        <span class="seo-detail-value"><?php echo (int)$selectedPage['metrics']['internalLinks']; ?></span>
                    </li>
                    <li>
                        <span class="seo-detail-label">External links</span>
                        <span class="seo-detail-value"><?php echo (int)$selectedPage['metrics']['externalLinks']; ?></span>
                    </li>
                </ul>
            </article>
        </section>

        <?php
            $issueDetails = isset($selectedPage['issues']['details']) && is_array($selectedPage['issues']['details'])
                ? $selectedPage['issues']['details']
                : [];
            $issueDetailCount = count($issueDetails);
        ?>
        <section class="a11y-detail-issues seo-detail-issues">
            <div class="a11y-detail-issues__header">
                <h2>SEO issues</h2>
                <span id="seoIssueCount">
                    <?php echo $issueDetailCount; ?> <?php echo $issueDetailCount === 1 ? 'issue' : 'issues'; ?>
                </span>
            </div>
            <?php if ($issueDetailCount > 0): ?>
                <?php
                    $impactCounts = [
                        'critical' => 0,
                        'high' => 0,
                        'medium' => 0,
                        'low' => 0,
                    ];
                    foreach ($issueDetails as $detail) {
                        $impactKey = strtolower((string)($detail['impact'] ?? ''));
                        if (!array_key_exists($impactKey, $impactCounts)) {
                            $impactCounts[$impactKey] = 0;
                        }
                        $impactCounts[$impactKey]++;
                    }
                ?>
                <div class="a11y-severity-filters seo-severity-filters" role="group" aria-label="Filter issues by severity">
                    <button type="button" class="a11y-severity-btn seo-severity-btn active" data-seo-severity="all" aria-pressed="true" aria-label="Show all issues (<?php echo $issueDetailCount; ?>)">
                        All <span aria-hidden="true">(<?php echo $issueDetailCount; ?>)</span>
                    </button>
                    <button type="button" class="a11y-severity-btn seo-severity-btn" data-seo-severity="critical" aria-pressed="false" aria-label="Show critical issues (<?php echo $impactCounts['critical']; ?>)">
                        Critical <span aria-hidden="true">(<?php echo $impactCounts['critical']; ?>)</span>
                    </button>
                    <button type="button" class="a11y-severity-btn seo-severity-btn" data-seo-severity="high" aria-pressed="false" aria-label="Show high-impact issues (<?php echo $impactCounts['high']; ?>)">
                        High <span aria-hidden="true">(<?php echo $impactCounts['high']; ?>)</span>
                    </button>
                    <button type="button" class="a11y-severity-btn seo-severity-btn" data-seo-severity="medium" aria-pressed="false" aria-label="Show medium issues (<?php echo $impactCounts['medium']; ?>)">
                        Medium <span aria-hidden="true">(<?php echo $impactCounts['medium']; ?>)</span>
                    </button>
                    <button type="button" class="a11y-severity-btn seo-severity-btn" data-seo-severity="low" aria-pressed="false" aria-label="Show low issues (<?php echo $impactCounts['low']; ?>)">
                        Low <span aria-hidden="true">(<?php echo $impactCounts['low']; ?>)</span>
                    </button>
                </div>
                <div class="sr-only" id="seoIssueFilterStatus" role="status" aria-live="polite"></div>
                <div class="a11y-issue-list seo-issue-list">
                    <?php foreach ($issueDetails as $issue): ?>
                        <article class="a11y-issue-card seo-issue-card impact-<?php echo htmlspecialchars($issue['impact']); ?>" data-impact="<?php echo htmlspecialchars(strtolower($issue['impact'])); ?>">
                            <header>
                                <h3><?php echo htmlspecialchars($issue['description']); ?></h3>
                                <span class="a11y-impact-badge seo-impact-badge impact-<?php echo htmlspecialchars($issue['impact']); ?>"><?php echo ucfirst($issue['impact']); ?></span>
                            </header>
                            <p><?php echo htmlspecialchars($issue['recommendation']); ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
                <p class="a11y-detail-empty seo-detail-empty" id="seoNoIssuesMessage" hidden>No issues match this severity filter.</p>
            <?php else: ?>
                <p class="a11y-detail-success seo-detail-success">This page is fully optimized with no outstanding SEO issues.</p>
            <?php endif; ?>
        </section>
    </div>
<?php else: ?>
    <div class="a11y-dashboard seo-dashboard" data-last-scan="<?php echo htmlspecialchars($lastScan, ENT_QUOTES); ?>">
        <header class="a11y-hero seo-hero">
            <div class="a11y-hero-content seo-hero-content">
                <div>
                    <span class="hero-eyebrow seo-hero-eyebrow">SEO Snapshot</span>
                    <h2 class="a11y-hero-title seo-hero-title">SEO Health Dashboard</h2>
                    <p class="a11y-hero-subtitle seo-hero-subtitle">Monitor crawlable metadata, content depth, and linking strength across your site.</p>
                </div>
                <div class="a11y-hero-actions seo-hero-actions">
                    <button type="button" id="seoScanAllBtn" class="a11y-btn a11y-btn--primary" data-seo-action="scan-all">
                        <i class="fas fa-globe" aria-hidden="true"></i>
                        <span>Scan All Pages</span>
                    </button>
                    <span class="a11y-hero-meta seo-hero-meta">
                        <i class="fas fa-clock" aria-hidden="true"></i>
                        Last scan: <?php echo htmlspecialchars($lastScan); ?>
                    </span>
                </div>
            </div>
            <div class="a11y-overview-grid seo-overview-grid">
                <div class="a11y-overview-card">
                    <div class="a11y-overview-value" id="seoStatTotalPages"><?php echo $totalPages; ?></div>
                    <div class="a11y-overview-label">Total Pages</div>
                </div>
                <div class="a11y-overview-card">
                    <div class="a11y-overview-value" id="seoStatAvgScore"><?php echo $avgScore; ?>%</div>
                    <div class="a11y-overview-label">Average Score</div>
                </div>
                <div class="a11y-overview-card">
                    <div class="a11y-overview-value" id="seoStatCritical"><?php echo $criticalIssues; ?></div>
                    <div class="a11y-overview-label">Critical Issues</div>
                </div>
                <div class="a11y-overview-card">
                    <div class="a11y-overview-value" id="seoStatOptimized"><?php echo $optimizedPages; ?></div>
                    <div class="a11y-overview-label">Optimized Pages</div>
                </div>
            </div>
        </header>

        <div class="a11y-controls seo-controls">
            <label class="a11y-search seo-search" for="seoSearchInput">
                <i class="fas fa-search" aria-hidden="true"></i>
                <input type="search" id="seoSearchInput" placeholder="Search pages by title, URL, or issue" aria-label="Search SEO results">
            </label>
            <div class="a11y-filter-group seo-filter-group" role="group" aria-label="SEO filters">
                <button type="button" class="a11y-filter-btn seo-filter-btn active" data-seo-filter="all">All Pages <span class="seo-filter-count" data-count="all"><?php echo $filterCounts['all']; ?></span></button>
                <button type="button" class="a11y-filter-btn seo-filter-btn" data-seo-filter="critical">Critical <span class="seo-filter-count" data-count="critical"><?php echo $filterCounts['critical']; ?></span></button>
                <button type="button" class="a11y-filter-btn seo-filter-btn" data-seo-filter="opportunity">Needs Work <span class="seo-filter-count" data-count="opportunity"><?php echo $filterCounts['opportunity']; ?></span></button>
                <button type="button" class="a11y-filter-btn seo-filter-btn" data-seo-filter="onTrack">On Track <span class="seo-filter-count" data-count="onTrack"><?php echo $filterCounts['onTrack']; ?></span></button>
                <button type="button" class="a11y-filter-btn seo-filter-btn" data-seo-filter="optimized">Optimized <span class="seo-filter-count" data-count="optimized"><?php echo $filterCounts['optimized']; ?></span></button>
            </div>
            <div class="a11y-sort-group seo-sort-group" role="group" aria-label="Sort pages">
                <label for="seoSortSelect">Sort by</label>
                <select id="seoSortSelect">
                    <option value="score" selected>SEO score</option>
                    <option value="title">Title</option>
                    <option value="issues">Issue count</option>
                    <option value="wordCount">Word count</option>
                </select>
                <button type="button" class="a11y-sort-direction seo-sort-direction" id="seoSortDirection" data-direction="desc" aria-label="Toggle sort direction (High to low)" aria-pressed="true">
                    <i class="fas fa-sort-amount-down-alt" aria-hidden="true"></i>
                    <span class="seo-sort-direction__text" id="seoSortDirectionLabel">High to low</span>
                </button>
            </div>
            <div class="a11y-view-toggle seo-view-toggle" role="group" aria-label="Toggle layout">
                <button type="button" class="a11y-view-btn seo-view-btn active" data-seo-view="grid" aria-label="Grid view">
                    <i class="fas fa-th-large" aria-hidden="true"></i>
                </button>
                <button type="button" class="a11y-view-btn seo-view-btn" data-seo-view="table" aria-label="Table view">
                    <i class="fas fa-list" aria-hidden="true"></i>
                </button>
            </div>
        </div>

        <div class="a11y-pages-grid seo-pages-grid" id="seoPagesGrid" role="list"></div>

        <div class="a11y-table-view seo-table-view" id="seoTableView" hidden>
            <div class="a11y-table-header seo-table-header">
                <div>Page</div>
                <div>Score</div>
                <div>Optimization</div>
                <div>Issues</div>
                <div>Words</div>
                <div>Last Scanned</div>
                <div>Action</div>
            </div>
            <div id="seoTableBody" class="a11y-table-body"></div>
        </div>

        <div class="a11y-empty-state seo-empty-state" id="seoEmptyState" hidden>
            <i class="fas fa-globe" aria-hidden="true"></i>
            <h3>No pages match your filters</h3>
            <p>Try adjusting the search or changing the filter selection.</p>
        </div>
    </div>

    <div class="a11y-page-detail seo-page-detail" id="seoPageDetail" hidden role="dialog" aria-modal="true" aria-labelledby="seoDetailTitle">
        <div class="a11y-detail-content seo-detail-content">
            <button type="button" class="a11y-detail-close seo-detail-close" id="seoDetailClose" aria-label="Close SEO details">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
            <header class="a11y-detail-modal-header seo-detail-modal-header">
                <h2 id="seoDetailTitle">Page SEO Details</h2>
                <p id="seoDetailUrl" class="seo-detail-url"></p>
                <p id="seoDetailDescription" class="seo-detail-description"></p>
            </header>
            <div class="a11y-detail-modal-body seo-detail-modal-body">
                <div class="a11y-detail-badges seo-detail-badges">
                    <span class="seo-detail-score score-indicator score-indicator--badge" id="seoDetailScore"></span>
                    <span class="seo-detail-level" id="seoDetailLevel"></span>
                    <span class="seo-detail-issues" id="seoDetailIssues"></span>
                </div>
                <ul class="a11y-detail-metric-list seo-detail-metric-list" id="seoDetailMetrics"></ul>
                <div class="a11y-detail-issues-list seo-detail-issues-list">
                    <h3>Key findings</h3>
                    <ul id="seoDetailIssuesList"></ul>
                </div>
            </div>
            <footer class="a11y-detail-modal-footer seo-detail-modal-footer">
                <button type="button" class="a11y-btn a11y-btn--primary" data-seo-action="full-audit">
                    <i class="fas fa-globe" aria-hidden="true"></i>
                    <span>Full SEO Audit</span>
                </button>
            </footer>
        </div>
    </div>
<?php endif; ?>
</div>
<script>
window.__SEO_MODULE_DATA__ = <?php echo json_encode($pageEntries, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
window.__SEO_MODULE_STATS__ = <?php echo json_encode($dashboardStats, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
</script>
