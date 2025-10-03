<?php
// File: modules/speed/view.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/settings.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../includes/score_history.php';
require_once __DIR__ . '/../../includes/reporting_helpers.php';
require_once __DIR__ . '/SpeedReport.php';
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

$pages = SpeedReport::loadPages($pagesFile);

$scriptBase = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
if (substr($scriptBase, -4) === '/CMS') {
    $scriptBase = substr($scriptBase, 0, -4);
}
$scriptBase = rtrim($scriptBase, '/');

$templateDir = realpath(__DIR__ . '/../../../theme/templates/pages');

$snapshotFile = __DIR__ . '/../../data/speed_snapshot.json';
$previousSnapshotRaw = read_json_file($snapshotFile);
$previousSnapshot = is_array($previousSnapshotRaw) ? $previousSnapshotRaw : [];

$reportService = new SpeedReport(
    $pages,
    $settings,
    $menus,
    $scriptBase,
    $templateDir ?: null,
    static function (string $identifier, int $score): int {
        return derive_previous_score('speed', $identifier, $score);
    },
    $previousSnapshot,
    date('M j, Y g:i A')
);

$reportPayload = $reportService->generateReport();
$report = $reportPayload['pages'];
$pageEntryMap = $reportPayload['pageMap'];
$dashboardStats = $reportPayload['stats'];

$moduleUrl = $_SERVER['PHP_SELF'] . '?module=speed';
$dashboardStats['moduleUrl'] = $moduleUrl;
$dashboardStats['detailBaseUrl'] = $moduleUrl . '&page=';

$detailSlug = isset($_GET['page']) ? sanitize_text($_GET['page']) : null;
$detailSlug = $detailSlug !== null ? trim($detailSlug) : null;

$selectedPage = null;
if ($detailSlug !== null && $detailSlug !== '') {
    $selectedPage = $pageEntryMap[$detailSlug] ?? null;
}

write_json_file($snapshotFile, $reportPayload['snapshot']);

$filterCounts = $dashboardStats['filterCounts'] ?? [
    'all' => 0,
    'slow' => 0,
    'monitor' => 0,
    'fast' => 0,
];
$heaviestPage = $dashboardStats['heaviestPage'] ?? null;

function speed_render_delta(?array $delta, string $statLabel, string $unitSingular, ?string $unitPlural = null, int $absoluteDecimals = 0, int $percentDecimals = 1): string
{
    $unitPlural = $unitPlural ?? $unitSingular . 's';
    $statLabel = trim($statLabel);

    if (!$delta || empty($delta['hasBaseline'])) {
        $srText = $statLabel . ' baseline established. No previous data available yet.';
        return '<div class="a11y-overview-delta" aria-live="polite">'
            . '<span class="speed-delta__value speed-delta__value--neutral speed-delta__value--baseline" aria-hidden="true">'
            . '<i class="fas fa-circle-dot" aria-hidden="true"></i>'
            . '<span>Baseline set</span>'
            . '</span>'
            . '<span class="sr-only">' . htmlspecialchars($srText, ENT_QUOTES, 'UTF-8') . '</span>'
            . '</div>';
    }

    $direction = $delta['direction'] ?? 'neutral';
    $directionClass = 'speed-delta__value--' . $direction;
    $absolute = (float)($delta['absolute'] ?? 0.0);
    $percentRaw = $delta['percent'];
    $percentAvailable = $percentRaw !== null;

    $percentClass = 'speed-delta__value ' . ($percentAvailable ? $directionClass : 'speed-delta__value--neutral speed-delta__value--empty');
    $absoluteClass = 'speed-delta__value ' . $directionClass;

    $icon = 'fa-minus';
    if ($direction === 'positive') {
        $icon = 'fa-arrow-trend-up';
    } elseif ($direction === 'negative') {
        $icon = 'fa-arrow-trend-down';
    }

    if (!$percentAvailable) {
        $icon = 'fa-circle-question';
    }

    $percentText = $percentAvailable
        ? report_format_change((float)$percentRaw, $percentDecimals) . '%'
        : 'No trend';
    $absoluteUnit = abs($absolute) === 1.0 ? $unitSingular : $unitPlural;
    $absoluteText = report_format_change($absolute, $absoluteDecimals) . ' ' . $absoluteUnit;

    $absNumber = number_format(abs($absolute), $absoluteDecimals, '.', '');
    $percentNumber = $percentAvailable ? number_format(abs((float)$percentRaw), $percentDecimals, '.', '') : null;

    if ($absolute == 0.0) {
        $srSummary = $statLabel . ' remained unchanged compared to the previous scan.';
    } else {
        $srSummary = $statLabel . ' ' . ($absolute > 0 ? 'increased' : 'decreased') . ' by ' . $absNumber . ' ' . $absoluteUnit . ' compared to the previous scan.';
    }

    if ($percentAvailable) {
        if ((float)$percentRaw === 0.0) {
            $srSummary .= ' There was no percentage change.';
        } else {
            $srSummary .= ' That is ' . ((float)$percentRaw > 0 ? 'up ' : 'down ') . $percentNumber . ' percent.';
        }
    } else {
        $srSummary .= ' Percentage change is not available yet.';
    }

    $html = '<div class="a11y-overview-delta" aria-live="polite">';
    $html .= '<span class="' . $percentClass . '" aria-hidden="true">';
    $html .= '<i class="fas ' . $icon . '" aria-hidden="true"></i>';
    $html .= '<span>' . htmlspecialchars($percentText, ENT_QUOTES, 'UTF-8') . '</span>';
    $html .= '</span>';
    $html .= '<span class="' . $absoluteClass . '" aria-hidden="true">';
    $html .= htmlspecialchars($absoluteText, ENT_QUOTES, 'UTF-8');
    $html .= '</span>';
    $html .= '<span class="sr-only">' . htmlspecialchars($srSummary, ENT_QUOTES, 'UTF-8') . '</span>';
    $html .= '</div>';

    return $html;
}
?>
<div class="content-section" id="performance">
<?php if ($selectedPage): ?>
    <div class="a11y-detail-page" id="speedDetailPage" data-page-slug="<?php echo htmlspecialchars($selectedPage['slug'], ENT_QUOTES); ?>">
        <?php
            $currentScore = (int) ($selectedPage['performanceScore'] ?? 0);
            $previousScore = (int) ($selectedPage['previousScore'] ?? $currentScore);
            $deltaMeta = describe_score_delta($currentScore, $previousScore);
        ?>
        <header class="a11y-detail-header">
            <a href="<?php echo htmlspecialchars($moduleUrl, ENT_QUOTES); ?>" class="a11y-back-link" id="speedBackToDashboard">
                <i class="fas fa-arrow-left" aria-hidden="true"></i>
                <span>Back to Performance Dashboard</span>
            </a>
            <div class="a11y-detail-actions">
                <button type="button" class="a11y-btn a11y-btn--ghost" data-speed-action="rescan-page">
                    <i class="fas fa-rotate" aria-hidden="true"></i>
                    <span>Rescan Page</span>
                </button>
                <button type="button" class="a11y-btn a11y-btn--secondary" data-speed-action="export-page-report">
                    <i class="fas fa-file-export" aria-hidden="true"></i>
                    <span>Export Speed Report</span>
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
                <div class="a11y-health-score__label">Performance Score</div>
                <span class="a11y-health-score__badge <?php echo htmlspecialchars($selectedPage['gradeClass']); ?>"><?php echo htmlspecialchars($selectedPage['grade']); ?></span>
            </div>
            <div class="a11y-health-summary">
                <h1><?php echo htmlspecialchars($selectedPage['title']); ?></h1>
                <p class="a11y-health-url"><?php echo htmlspecialchars($selectedPage['url']); ?></p>
                <p><?php echo htmlspecialchars($selectedPage['statusMessage']); ?></p>
                <p class="a11y-health-overview"><?php echo htmlspecialchars($selectedPage['summaryLine']); ?></p>
                <div class="a11y-quick-stats">
                    <div class="a11y-quick-stat">
                        <div class="a11y-quick-stat__value"><?php echo $selectedPage['metrics']['weightKb']; ?> KB</div>
                        <div class="a11y-quick-stat__label">Estimated Weight</div>
                    </div>
                    <div class="a11y-quick-stat">
                        <div class="a11y-quick-stat__value"><?php echo $selectedPage['metrics']['imageCount']; ?></div>
                        <div class="a11y-quick-stat__label">Images</div>
                    </div>
                    <div class="a11y-quick-stat">
                        <div class="a11y-quick-stat__value"><?php echo $selectedPage['metrics']['scriptCount']; ?></div>
                        <div class="a11y-quick-stat__label">Scripts</div>
                    </div>
                    <div class="a11y-quick-stat">
                        <div class="a11y-quick-stat__value"><?php echo $selectedPage['alerts']['critical']; ?></div>
                        <div class="a11y-quick-stat__label">Critical Alerts</div>
                    </div>
                </div>
            </div>
        </section>

        <section class="a11y-detail-grid">
            <article class="a11y-detail-card">
                <h2>Performance checkpoints</h2>
                <div class="a11y-detail-metrics">
                    <div>
                        <span class="a11y-detail-metric__label">HTML payload</span>
                        <span class="a11y-detail-metric__value"><?php echo $selectedPage['metrics']['htmlSizeKb']; ?> KB</span>
                        <span class="a11y-detail-metric__hint">Keep markup lean and remove unused sections.</span>
                    </div>
                    <div>
                        <span class="a11y-detail-metric__label">Scripts</span>
                        <span class="a11y-detail-metric__value"><?php echo $selectedPage['metrics']['scriptCount']; ?> total (<?php echo $selectedPage['metrics']['inlineScripts']; ?> inline)</span>
                        <span class="a11y-detail-metric__hint">Defer non-critical logic and audit third-party tags.</span>
                    </div>
                    <div>
                        <span class="a11y-detail-metric__label">Stylesheets</span>
                        <span class="a11y-detail-metric__value"><?php echo $selectedPage['metrics']['stylesheetCount']; ?> linked, <?php echo $selectedPage['metrics']['inlineStyles']; ?> inline</span>
                        <span class="a11y-detail-metric__hint">Inline only critical CSS, load the rest asynchronously.</span>
                    </div>
                    <div>
                        <span class="a11y-detail-metric__label">DOM nodes</span>
                        <span class="a11y-detail-metric__value"><?php echo $selectedPage['metrics']['domNodes']; ?></span>
                        <span class="a11y-detail-metric__hint">Keep below 1,500 for optimal rendering.</span>
                    </div>
                </div>
            </article>
            <article class="a11y-detail-card">
                <h2>Alert breakdown</h2>
                <ul class="a11y-violation-list">
                    <li><span>Critical</span><span><?php echo $selectedPage['alerts']['critical']; ?></span></li>
                    <li><span>Major</span><span><?php echo $selectedPage['alerts']['serious']; ?></span></li>
                    <li><span>Moderate</span><span><?php echo $selectedPage['alerts']['moderate']; ?></span></li>
                    <li><span>Minor</span><span><?php echo $selectedPage['alerts']['minor']; ?></span></li>
                </ul>
                <div class="a11y-detail-meta">
                    <div>
                        <span class="a11y-detail-meta__label">Last scanned</span>
                        <span class="a11y-detail-meta__value"><?php echo htmlspecialchars($dashboardStats['lastScan']); ?></span>
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

        <section class="a11y-detail-issues">
            <div class="a11y-detail-issues__header">
                <h2>Optimization opportunities</h2>
                <span><?php echo $selectedPage['alerts']['total']; ?> total</span>
            </div>
            <?php if (!empty($selectedPage['issues']['details'])): ?>
                <div class="a11y-issue-list">
                    <?php foreach ($selectedPage['issues']['details'] as $issue): ?>
                        <article class="a11y-issue-card impact-<?php echo htmlspecialchars($issue['impact']); ?>">
                            <header>
                                <h3><?php echo htmlspecialchars($issue['description']); ?></h3>
                                <span class="a11y-impact-badge impact-<?php echo htmlspecialchars($issue['impact']); ?>"><?php echo ucfirst($issue['impact']); ?></span>
                            </header>
                            <p><?php echo htmlspecialchars($issue['recommendation']); ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="a11y-detail-success">This page passed the automated checks with no remaining alerts.</p>
            <?php endif; ?>
        </section>
    </div>
<?php else: ?>
    <div class="a11y-dashboard" data-last-scan="<?php echo htmlspecialchars($dashboardStats['lastScan'], ENT_QUOTES); ?>">
        <header class="a11y-hero">
            <div class="a11y-hero-content">
                <div>
                    <span class="hero-eyebrow speed-hero-eyebrow">Performance Pulse</span>
                    <h2 class="a11y-hero-title">Performance &amp; Speed Dashboard</h2>
                    <p class="a11y-hero-subtitle">Track asset weight, script usage, and rendering health across every published page.</p>
                </div>
                <div class="a11y-hero-actions">
                    <button type="button" id="speedScanAllBtn" class="a11y-btn a11y-btn--primary" data-speed-action="scan-all">
                        <i class="fas fa-gauge-high" aria-hidden="true"></i>
                        <span>Run Speed Scan</span>
                    </button>
                    <?php if (!empty($heaviestPage)): ?>
                    <button type="button" class="a11y-btn a11y-btn--ghost" data-speed-action="view-heaviest" data-speed-slug="<?php echo htmlspecialchars($heaviestPage['slug'] ?? '', ENT_QUOTES); ?>" title="Estimated weight: <?php echo isset($heaviestPage['weight']) ? htmlspecialchars(number_format((float)$heaviestPage['weight'], 1), ENT_QUOTES) . ' KB' : ''; ?>">
                        <i class="fas fa-weight-hanging" aria-hidden="true"></i>
                        <span>Heaviest page: <?php echo htmlspecialchars($heaviestPage['title'] ?? ''); ?></span>
                    </button>
                    <?php endif; ?>
                    <span class="a11y-hero-meta">
                        <i class="fas fa-clock" aria-hidden="true"></i>
                        Last scan: <?php echo htmlspecialchars($dashboardStats['lastScan']); ?>
                    </span>
                    <span class="a11y-hero-meta">
                        <i class="fas fa-bolt" aria-hidden="true"></i>
                        High-performing pages: <?php echo $dashboardStats['fastPages']; ?>
                    </span>
                </div>
            </div>
            <div class="a11y-overview-grid">
                <div class="a11y-overview-card">
                    <div class="a11y-overview-value" id="speedStatTotalPages"><?php echo $dashboardStats['totalPages']; ?></div>
                    <div class="a11y-overview-label">Total Pages</div>
                    <?php echo speed_render_delta($dashboardStats['deltas']['totalPages'] ?? null, 'Total pages', 'page'); ?>
                </div>
                <div class="a11y-overview-card">
                    <div class="a11y-overview-value" id="speedStatAvgScore"><?php echo $dashboardStats['avgScore']; ?>%</div>
                    <div class="a11y-overview-label">Average Score</div>
                    <?php echo speed_render_delta($dashboardStats['deltas']['avgScore'] ?? null, 'Average score', 'point', 'points'); ?>
                </div>
                <div class="a11y-overview-card">
                    <div class="a11y-overview-value" id="speedStatCritical"><?php echo $dashboardStats['criticalAlerts']; ?></div>
                    <div class="a11y-overview-label">Critical Alerts</div>
                    <?php echo speed_render_delta($dashboardStats['deltas']['criticalAlerts'] ?? null, 'Critical alerts', 'alert'); ?>
                </div>
                <div class="a11y-overview-card">
                    <div class="a11y-overview-value" id="speedStatSlow"><?php echo $dashboardStats['slowPages']; ?></div>
                    <div class="a11y-overview-label">Pages needing attention</div>
                    <?php echo speed_render_delta($dashboardStats['deltas']['slowPages'] ?? null, 'Pages needing attention', 'page'); ?>
                </div>
            </div>
        </header>
        <div class="a11y-controls">
            <label class="a11y-search" for="speedSearchInput">
                <i class="fas fa-search" aria-hidden="true"></i>
                <input type="search" id="speedSearchInput" placeholder="Search pages by title, URL, or alert" aria-label="Search performance results">
            </label>
            <div class="a11y-filter-group" role="group" aria-label="Performance filters">
                <button type="button" class="a11y-filter-btn active" data-speed-filter="all">All Pages <span class="a11y-filter-count" data-count="all"><?php echo $filterCounts['all']; ?></span></button>
                <button type="button" class="a11y-filter-btn" data-speed-filter="slow">Slow pages <span class="a11y-filter-count" data-count="slow"><?php echo $filterCounts['slow']; ?></span></button>
                <button type="button" class="a11y-filter-btn" data-speed-filter="monitor">Needs attention <span class="a11y-filter-count" data-count="monitor"><?php echo $filterCounts['monitor']; ?></span></button>
                <button type="button" class="a11y-filter-btn" data-speed-filter="fast">Performing well <span class="a11y-filter-count" data-count="fast"><?php echo $filterCounts['fast']; ?></span></button>
            </div>
            <div class="a11y-sort-group" role="group" aria-label="Sort results">
                <label for="speedSortSelect">Sort by</label>
                <select id="speedSortSelect">
                    <option value="score" selected>Performance score</option>
                    <option value="title">Title</option>
                    <option value="alerts">Total alerts</option>
                    <option value="weight">Estimated weight</option>
                </select>
                <button type="button" class="a11y-sort-direction" id="speedSortDirection" data-direction="desc" aria-label="Toggle sort direction (High to low)" aria-pressed="true">
                    <i class="fas fa-sort-amount-down-alt" aria-hidden="true"></i>
                    <span class="a11y-sort-direction__text" id="speedSortDirectionLabel">High to low</span>
                </button>
            </div>
            <div class="a11y-view-toggle" role="group" aria-label="Toggle layout">
                <button type="button" class="a11y-view-btn active" data-speed-view="grid" aria-label="Grid view">
                    <i class="fas fa-th-large" aria-hidden="true"></i>
                </button>
                <button type="button" class="a11y-view-btn" data-speed-view="table" aria-label="Table view">
                    <i class="fas fa-list" aria-hidden="true"></i>
                </button>
            </div>
        </div>
        <div class="a11y-pages-grid" id="speedPagesGrid" role="list"></div>
        <div class="a11y-table-view" id="speedTableView" hidden>
            <div class="a11y-table-header">
                <div>Page</div>
                <div>Score</div>
                <div>Grade</div>
                <div>Alerts</div>
                <div>Est. Weight</div>
                <div>Last Scanned</div>
                <div>Action</div>
            </div>
            <div id="speedTableBody"></div>
        </div>
        <div class="a11y-empty-state" id="speedEmptyState" hidden>
            <i class="fas fa-gauge-high" aria-hidden="true"></i>
            <h3>No pages match your filters</h3>
            <p>Try adjusting the search or choosing a different performance segment.</p>
        </div>
    </div>
    <div class="a11y-page-detail" id="speedPageDetail" hidden role="dialog" aria-modal="true" aria-labelledby="speedDetailTitle">
        <div class="a11y-detail-content">
            <button type="button" class="a11y-detail-close" id="speedDetailClose" aria-label="Close performance details">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
            <header class="a11y-detail-modal-header">
                <h2 id="speedDetailTitle">Page performance details</h2>
                <p id="speedDetailUrl" class="a11y-detail-url"></p>
                <p id="speedDetailDescription" class="a11y-detail-description"></p>
            </header>
            <div class="a11y-detail-modal-body">
                <div class="a11y-detail-badges">
                    <span class="a11y-detail-score score-indicator score-indicator--badge" id="speedDetailScore"></span>
                    <span class="a11y-detail-level" id="speedDetailGrade"></span>
                    <span class="a11y-detail-violations" id="speedDetailAlerts"></span>
                </div>
                <ul class="a11y-detail-metric-list" id="speedDetailMetrics"></ul>
                <div class="a11y-detail-issues-list">
                    <h3>Key findings</h3>
                    <ul id="speedDetailIssues"></ul>
                </div>
            </div>
            <footer class="a11y-detail-modal-footer">
                <button type="button" class="a11y-btn a11y-btn--primary" data-speed-action="full-diagnose">
                    <i class="fas fa-gauge-simple-high" aria-hidden="true"></i>
                    <span>Launch detailed audit</span>
                </button>
            </footer>
        </div>
    </div>
<?php endif; ?>
</div>
<script>
window.speedDashboardData = <?php echo json_encode($report, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
window.speedDashboardStats = <?php echo json_encode($dashboardStats, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
</script>
