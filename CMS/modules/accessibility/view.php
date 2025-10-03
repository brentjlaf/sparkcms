<?php
// File: modules/accessibility/view.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/settings.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../includes/score_history.php';
require_once __DIR__ . '/AccessibilityReport.php';
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

$pages = AccessibilityReport::loadPages($pagesFile);

$scriptBase = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
if (substr($scriptBase, -4) === '/CMS') {
    $scriptBase = substr($scriptBase, 0, -4);
}
$scriptBase = rtrim($scriptBase, '/');

$templateDir = realpath(__DIR__ . '/../../../theme/templates/pages');

$reportService = new AccessibilityReport(
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

$totalPages = isset($dashboardStats['totalPages']) ? (int) $dashboardStats['totalPages'] : 0;
$avgScore = isset($dashboardStats['avgScore']) ? (int) $dashboardStats['avgScore'] : 0;
$criticalIssues = isset($dashboardStats['criticalIssues']) ? (int) $dashboardStats['criticalIssues'] : 0;
$aaCompliant = isset($dashboardStats['aaCompliant']) ? (int) $dashboardStats['aaCompliant'] : 0;
$lastScan = isset($dashboardStats['lastScan']) && $dashboardStats['lastScan'] !== ''
    ? $dashboardStats['lastScan']
    : 'Never';
$filterCounts = [
    'all' => 0,
    'failing' => 0,
    'partial' => 0,
    'compliant' => 0,
];
if (isset($dashboardStats['filterCounts']) && is_array($dashboardStats['filterCounts'])) {
    $filterCounts = array_merge($filterCounts, $dashboardStats['filterCounts']);
}

$moduleUrl = $_SERVER['PHP_SELF'] . '?module=accessibility';
$dashboardStats['moduleUrl'] = $moduleUrl;
$dashboardStats['detailBaseUrl'] = $moduleUrl . '&page=';

$detailSlug = isset($_GET['page']) ? sanitize_text($_GET['page']) : null;
$detailSlug = $detailSlug !== null ? trim($detailSlug) : null;

$selectedPage = null;
if ($detailSlug !== null && $detailSlug !== '') {
    $selectedPage = $pageEntryMap[$detailSlug] ?? null;
}
?>
<div class="content-section" id="accessibility">
<?php if ($selectedPage): ?>
    <div class="a11y-detail-page" id="a11yDetailPage" data-page-slug="<?php echo htmlspecialchars($selectedPage['slug'], ENT_QUOTES); ?>">
        <?php
            $currentScore = (int) ($selectedPage['accessibilityScore'] ?? 0);
            $previousScore = (int) ($selectedPage['previousScore'] ?? $currentScore);
            $deltaMeta = describe_score_delta($currentScore, $previousScore);
        ?>
        <header class="a11y-detail-header">
            <a href="<?php echo htmlspecialchars($moduleUrl, ENT_QUOTES); ?>" class="a11y-back-link" id="a11yBackToDashboard">
                <i class="fas fa-arrow-left" aria-hidden="true"></i>
                <span>Back to Dashboard</span>
            </a>
            <div class="a11y-detail-actions">
                <button type="button" class="a11y-btn a11y-btn--ghost" data-a11y-action="rescan-page">
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
                <div class="a11y-health-score__label">Accessibility Score</div>
                <span class="a11y-health-score__badge level-<?php echo strtolower($selectedPage['wcagLevel']); ?>"><?php echo htmlspecialchars($selectedPage['wcagLevel']); ?></span>
            </div>
            <div class="a11y-health-summary">
                <h1><?php echo htmlspecialchars($selectedPage['title']); ?></h1>
                <p class="a11y-health-url"><?php echo htmlspecialchars($selectedPage['url']); ?></p>
                <p><?php echo htmlspecialchars($selectedPage['statusMessage']); ?></p>
                <p class="a11y-health-overview"><?php echo htmlspecialchars($selectedPage['summaryLine']); ?></p>
                <div class="a11y-quick-stats">
                    <div class="a11y-quick-stat">
                        <div class="a11y-quick-stat__value"><?php echo $selectedPage['metrics']['images']; ?></div>
                        <div class="a11y-quick-stat__label">Images</div>
                    </div>
                    <div class="a11y-quick-stat">
                        <div class="a11y-quick-stat__value"><?php echo $selectedPage['metrics']['missingAlt']; ?></div>
                        <div class="a11y-quick-stat__label">Missing Alt</div>
                    </div>
                    <div class="a11y-quick-stat">
                        <div class="a11y-quick-stat__value">H1: <?php echo $selectedPage['metrics']['headings']['h1']; ?></div>
                        <div class="a11y-quick-stat__label">Primary Headings</div>
                    </div>
                    <div class="a11y-quick-stat">
                        <div class="a11y-quick-stat__value"><?php echo $selectedPage['violations']['critical']; ?></div>
                        <div class="a11y-quick-stat__label">Critical Issues</div>
                    </div>
                </div>
            </div>
        </section>

        <section class="a11y-detail-grid">
            <article class="a11y-detail-card">
                <h2>Key accessibility checks</h2>
                <div class="a11y-detail-metrics">
                    <div>
                        <span class="a11y-detail-metric__label">Images analysed</span>
                        <span class="a11y-detail-metric__value"><?php echo $selectedPage['metrics']['images']; ?></span>
                        <span class="a11y-detail-metric__hint"><?php echo $selectedPage['metrics']['missingAlt'] > 0 ? $selectedPage['metrics']['missingAlt'] . ' image(s) missing alt text' : 'All images include alt text'; ?></span>
                    </div>
                    <div>
                        <span class="a11y-detail-metric__label">Headings</span>
                        <span class="a11y-detail-metric__value">H1: <?php echo $selectedPage['metrics']['headings']['h1']; ?> / H2: <?php echo $selectedPage['metrics']['headings']['h2']; ?></span>
                        <span class="a11y-detail-metric__hint">Ensure a single descriptive H1 per page.</span>
                    </div>
                    <div>
                        <span class="a11y-detail-metric__label">Generic links</span>
                        <span class="a11y-detail-metric__value"><?php echo $selectedPage['metrics']['genericLinks']; ?></span>
                        <span class="a11y-detail-metric__hint"><?php echo $selectedPage['metrics']['genericLinks'] > 0 ? 'Replace generic link text with descriptive labels.' : 'All links descriptive'; ?></span>
                    </div>
                    <div>
                        <span class="a11y-detail-metric__label">Landmarks</span>
                        <span class="a11y-detail-metric__value"><?php echo $selectedPage['metrics']['landmarks']; ?></span>
                        <span class="a11y-detail-metric__hint"><?php echo $selectedPage['metrics']['landmarks'] > 0 ? 'Structural landmarks detected' : 'Add semantic landmarks for easier navigation.'; ?></span>
                    </div>
                </div>
            </article>
            <article class="a11y-detail-card">
                <h2>WCAG violation breakdown</h2>
                <ul class="a11y-violation-list">
                    <li><span>Critical</span><span><?php echo $selectedPage['violations']['critical']; ?></span></li>
                    <li><span>Serious</span><span><?php echo $selectedPage['violations']['serious']; ?></span></li>
                    <li><span>Moderate</span><span><?php echo $selectedPage['violations']['moderate']; ?></span></li>
                    <li><span>Minor</span><span><?php echo $selectedPage['violations']['minor']; ?></span></li>
                </ul>
                <div class="a11y-detail-meta">
                    <div>
                        <span class="a11y-detail-meta__label">Last scanned</span>
                        <span class="a11y-detail-meta__value"><?php echo htmlspecialchars($selectedPage['lastScanned']); ?></span>
                    </div>
                    <div>
                        <span class="a11y-detail-meta__label">Page type</span>
                        <span class="a11y-detail-meta__value"><?php echo htmlspecialchars($selectedPage['pageType']); ?></span>
                    </div>
                    <div>
                        <span class="a11y-detail-meta__label">Warnings</span>
                        <span class="a11y-detail-meta__value"><?php echo $selectedPage['warnings']; ?></span>
                    </div>
                </div>
            </article>
        </section>

        <?php
            $issueDetails = isset($selectedPage['issues']['details']) && is_array($selectedPage['issues']['details'])
                ? $selectedPage['issues']['details']
                : [];
            $issueDetailCount = count($issueDetails);
        ?>
        <section class="a11y-detail-issues">
            <div class="a11y-detail-issues__header">
                <h2>Accessibility issues</h2>
                <span id="a11yIssueCount">
                    <?php echo $issueDetailCount; ?> <?php echo $issueDetailCount === 1 ? 'issue' : 'issues'; ?>
                </span>
            </div>
            <?php if ($issueDetailCount > 0): ?>
                <?php
                    $impactCounts = [
                        'critical' => 0,
                        'serious' => 0,
                        'moderate' => 0,
                        'minor' => 0,
                        'review' => 0,
                    ];
                    foreach ($issueDetails as $detail) {
                        $impactKey = strtolower((string)($detail['impact'] ?? ''));
                        if (!array_key_exists($impactKey, $impactCounts)) {
                            $impactCounts[$impactKey] = 0;
                        }
                        $impactCounts[$impactKey]++;
                    }
                ?>
                <div class="a11y-severity-filters" role="group" aria-label="Filter issues by severity">
                    <button type="button" class="a11y-severity-btn active" data-a11y-severity="all" aria-pressed="true" aria-label="Show all issues (<?php echo $issueDetailCount; ?>)">
                        All <span aria-hidden="true">(<?php echo $issueDetailCount; ?>)</span>
                    </button>
                    <button type="button" class="a11y-severity-btn" data-a11y-severity="critical" aria-pressed="false" aria-label="Show critical issues (<?php echo $impactCounts['critical']; ?>)">
                        Critical <span aria-hidden="true">(<?php echo $impactCounts['critical']; ?>)</span>
                    </button>
                    <button type="button" class="a11y-severity-btn" data-a11y-severity="serious" aria-pressed="false" aria-label="Show serious issues (<?php echo $impactCounts['serious']; ?>)">
                        Serious <span aria-hidden="true">(<?php echo $impactCounts['serious']; ?>)</span>
                    </button>
                    <button type="button" class="a11y-severity-btn" data-a11y-severity="moderate" aria-pressed="false" aria-label="Show moderate issues (<?php echo $impactCounts['moderate']; ?>)">
                        Moderate <span aria-hidden="true">(<?php echo $impactCounts['moderate']; ?>)</span>
                    </button>
                    <button type="button" class="a11y-severity-btn" data-a11y-severity="minor" aria-pressed="false" aria-label="Show minor issues (<?php echo $impactCounts['minor']; ?>)">
                        Minor <span aria-hidden="true">(<?php echo $impactCounts['minor']; ?>)</span>
                    </button>
                    <button type="button" class="a11y-severity-btn" data-a11y-severity="review" aria-pressed="false" aria-label="Show review issues (<?php echo $impactCounts['review']; ?>)">
                        Review <span aria-hidden="true">(<?php echo $impactCounts['review']; ?>)</span>
                    </button>
                </div>
                <div class="sr-only" id="a11yIssueFilterStatus" role="status" aria-live="polite"></div>
                <div class="a11y-issue-list">
                    <?php foreach ($issueDetails as $issue): ?>
                        <article class="a11y-issue-card impact-<?php echo htmlspecialchars($issue['impact']); ?>" data-impact="<?php echo htmlspecialchars(strtolower($issue['impact'])); ?>">
                            <header>
                                <h3><?php echo htmlspecialchars($issue['description']); ?></h3>
                                <span class="a11y-impact-badge impact-<?php echo htmlspecialchars($issue['impact']); ?>"><?php echo ucfirst($issue['impact']); ?></span>
                            </header>
                            <p><?php echo htmlspecialchars($issue['recommendation']); ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
                <p class="a11y-detail-empty" id="a11yNoIssuesMessage" hidden>No issues match this severity filter.</p>
            <?php else: ?>
                <p class="a11y-detail-success">This page passed the automated checks with no remaining issues.</p>
            <?php endif; ?>
        </section>
    </div>
<?php else: ?>
    <div class="a11y-dashboard" data-last-scan="<?php echo htmlspecialchars($lastScan, ENT_QUOTES); ?>">
        <header class="a11y-hero accessibility-hero">
            <div class="a11y-hero-content accessibility-hero-content">
                <div>
                    <span class="hero-eyebrow accessibility-hero-eyebrow">Accessibility Snapshot</span>
                    <h2 class="a11y-hero-title accessibility-hero-title">Accessibility Dashboard</h2>
                    <p class="a11y-hero-subtitle accessibility-hero-subtitle">Monitor WCAG compliance and keep your experience inclusive for every visitor.</p>
                </div>
                <div class="a11y-hero-actions accessibility-hero-actions">
                    <button type="button" id="scanAllPagesBtn" class="a11y-btn a11y-btn--primary" data-a11y-action="scan-all">
                        <i class="fas fa-universal-access" aria-hidden="true"></i>
                        <span>Scan All Pages</span>
                    </button>
                    <span class="a11y-hero-meta accessibility-hero-meta">
                        <i class="fas fa-clock" aria-hidden="true"></i>
                        Last scan: <?php echo htmlspecialchars($lastScan); ?>
                    </span>
                </div>
            </div>
            <div class="a11y-overview-grid">
                <div class="a11y-overview-card">
                    <div class="a11y-overview-value" id="a11yStatTotalPages"><?php echo $totalPages; ?></div>
                    <div class="a11y-overview-label">Total Pages</div>
                </div>
                <div class="a11y-overview-card">
                    <div class="a11y-overview-value" id="a11yStatAvgScore"><?php echo $avgScore; ?>%</div>
                    <div class="a11y-overview-label">Average Score</div>
                </div>
                <div class="a11y-overview-card">
                    <div class="a11y-overview-value" id="a11yStatCritical"><?php echo $criticalIssues; ?></div>
                    <div class="a11y-overview-label">Critical Issues</div>
                </div>
                <div class="a11y-overview-card">
                    <div class="a11y-overview-value" id="a11yStatCompliant"><?php echo $aaCompliant; ?></div>
                    <div class="a11y-overview-label">AA/AAA Compliant</div>
                </div>
            </div>
        </header>

        <div class="a11y-controls">
            <label class="a11y-search" for="a11ySearchInput">
                <i class="fas fa-search" aria-hidden="true"></i>
                <input type="search" id="a11ySearchInput" placeholder="Search pages by title, URL, or issue" aria-label="Search accessibility results">
            </label>
            <div class="a11y-filter-group" role="group" aria-label="Accessibility filters">
                <button type="button" class="a11y-filter-btn active" data-a11y-filter="all">All Pages <span class="a11y-filter-count" data-count="all"><?php echo $filterCounts['all']; ?></span></button>
                <button type="button" class="a11y-filter-btn" data-a11y-filter="failing">Critical Issues <span class="a11y-filter-count" data-count="failing"><?php echo $filterCounts['failing']; ?></span></button>
                <button type="button" class="a11y-filter-btn" data-a11y-filter="partial">Needs Work <span class="a11y-filter-count" data-count="partial"><?php echo $filterCounts['partial']; ?></span></button>
                <button type="button" class="a11y-filter-btn" data-a11y-filter="compliant">WCAG Compliant <span class="a11y-filter-count" data-count="compliant"><?php echo $filterCounts['compliant']; ?></span></button>
            </div>
            <div class="a11y-sort-group" role="group" aria-label="Sort pages">
                <label for="a11ySortSelect">Sort by</label>
                <select id="a11ySortSelect">
                    <option value="score" selected>Accessibility score</option>
                    <option value="title">Title</option>
                    <option value="violations">Total violations</option>
                    <option value="warnings">Warnings</option>
                </select>
                <button type="button" class="a11y-sort-direction" id="a11ySortDirection" data-direction="desc" aria-label="Toggle sort direction (High to low)" aria-pressed="true">
                    <i class="fas fa-sort-amount-down-alt" aria-hidden="true"></i>
                    <span class="a11y-sort-direction__text" id="a11ySortDirectionLabel">High to low</span>
                </button>
            </div>
            <div class="a11y-view-toggle" role="group" aria-label="Toggle layout">
                <button type="button" class="a11y-view-btn active" data-a11y-view="grid" aria-label="Grid view">
                    <i class="fas fa-th-large" aria-hidden="true"></i>
                </button>
                <button type="button" class="a11y-view-btn" data-a11y-view="table" aria-label="Table view">
                    <i class="fas fa-list" aria-hidden="true"></i>
                </button>
            </div>
        </div>

        <div class="a11y-pages-grid" id="a11yPagesGrid" role="list"></div>

        <div class="a11y-table-view" id="a11yTableView" hidden>
            <div class="a11y-table-header">
                <div>Page</div>
                <div>Score</div>
                <div>WCAG Level</div>
                <div>Violations</div>
                <div>Warnings</div>
                <div>Last Scanned</div>
                <div>Action</div>
            </div>
            <div id="a11yTableBody"></div>
        </div>

        <div class="a11y-empty-state" id="a11yEmptyState" hidden>
            <i class="fas fa-universal-access" aria-hidden="true"></i>
            <h3>No pages match your filters</h3>
            <p>Try adjusting the search or changing the filter selection.</p>
        </div>
    </div>

    <div class="a11y-page-detail" id="a11yPageDetail" hidden role="dialog" aria-modal="true" aria-labelledby="a11yDetailTitle">
        <div class="a11y-detail-content">
            <button type="button" class="a11y-detail-close" id="a11yDetailClose" aria-label="Close accessibility details">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
            <header class="a11y-detail-modal-header">
                <h2 id="a11yDetailTitle">Page Accessibility Details</h2>
                <p id="a11yDetailUrl" class="a11y-detail-url"></p>
                <p id="a11yDetailDescription" class="a11y-detail-description"></p>
            </header>
            <div class="a11y-detail-modal-body">
                <div class="a11y-detail-badges">
                    <span class="a11y-detail-score score-indicator score-indicator--badge" id="a11yDetailScore"></span>
                    <span class="a11y-detail-level" id="a11yDetailLevel"></span>
                    <span class="a11y-detail-violations" id="a11yDetailViolations"></span>
                </div>
                <ul class="a11y-detail-metric-list" id="a11yDetailMetrics"></ul>
                <div class="a11y-detail-issues-list">
                    <h3>Key findings</h3>
                    <ul id="a11yDetailIssues"></ul>
                </div>
            </div>
            <footer class="a11y-detail-modal-footer">
                <button type="button" class="a11y-btn a11y-btn--primary" data-a11y-action="full-audit">
                    <i class="fas fa-universal-access" aria-hidden="true"></i>
                    <span>Full Accessibility Audit</span>
                </button>
            </footer>
        </div>
    </div>
<?php endif; ?>
</div>
<script>
window.a11yDashboardData = <?php echo json_encode($pageEntries, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
window.a11yDashboardStats = <?php echo json_encode($dashboardStats, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
</script>
