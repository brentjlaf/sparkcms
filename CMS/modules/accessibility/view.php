<?php
// File: modules/accessibility/view.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_login();

$pagesFile = __DIR__ . '/../../data/pages.json';
$pages = read_json_file($pagesFile);
$settingsFile = __DIR__ . '/../../data/settings.json';
$settings = read_json_file($settingsFile);
$menusFile = __DIR__ . '/../../data/menus.json';
$menus = read_json_file($menusFile);

$scriptBase = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
if (substr($scriptBase, -4) === '/CMS') {
    $scriptBase = substr($scriptBase, 0, -4);
}
$scriptBase = rtrim($scriptBase, '/');

$templateDir = realpath(__DIR__ . '/../../../theme/templates/pages');

function capture_template_html(string $templateFile, array $settings, array $menus, string $scriptBase): string {
    $page = ['content' => '{{CONTENT}}'];
    $themeBase = $scriptBase . '/theme';
    ob_start();
    include $templateFile;
    $html = ob_get_clean();
    $html = preg_replace('/<div class="drop-area"><\/div>/', '{{CONTENT}}', $html, 1);
    if (strpos($html, '{{CONTENT}}') === false) {
        $html .= '{{CONTENT}}';
    }
    $html = preg_replace('#<templateSetting[^>]*>.*?</templateSetting>#si', '', $html);
    $html = preg_replace('#<div class="block-controls"[^>]*>.*?</div>#si', '', $html);
    $html = str_replace('draggable="true"', '', $html);
    $html = preg_replace('#\sdata-ts="[^"]*"#i', '', $html);
    $html = preg_replace('#\sdata-(?:blockid|template|original|active|custom_[A-Za-z0-9_-]+)="[^"]*"#i', '', $html);
    return $html;
}

function build_page_html(array $page, array $settings, array $menus, string $scriptBase, ?string $templateDir): string {
    static $templateCache = [];

    if (!$templateDir) {
        return (string)($page['content'] ?? '');
    }

    $templateName = !empty($page['template']) ? basename($page['template']) : 'page.php';
    $templateFile = $templateDir . DIRECTORY_SEPARATOR . $templateName;
    if (!is_file($templateFile)) {
        return (string)($page['content'] ?? '');
    }

    if (!isset($templateCache[$templateFile])) {
        $templateCache[$templateFile] = capture_template_html($templateFile, $settings, $menus, $scriptBase);
    }

    $templateHtml = $templateCache[$templateFile];
    $content = (string)($page['content'] ?? '');
    return str_replace('{{CONTENT}}', $content, $templateHtml);
}

libxml_use_internal_errors(true);

$report = [];
$summary = [
    'accessible' => 0,
    'needs_review' => 0,
    'missing_alt' => 0,
];
$issueCount = 0;

$genericLinkTerms = [
    'click here',
    'read more',
    'learn more',
    'here',
    'more',
    'this page',
];

foreach ($pages as $page) {
    $title = $page['title'] ?? 'Untitled';
    $slug = $page['slug'] ?? '';
    $pageHtml = build_page_html($page, $settings, $menus, $scriptBase, $templateDir);

    $doc = new DOMDocument();
    $loaded = trim($pageHtml) !== '' && $doc->loadHTML('<?xml encoding="utf-8" ?>' . $pageHtml);

    $imageCount = 0;
    $missingAlt = 0;
    $headings = [
        'h1' => 0,
        'h2' => 0,
    ];
    $genericLinks = 0;
    $landmarks = 0;

    if ($loaded) {
        $images = $doc->getElementsByTagName('img');
        $imageCount = $images->length;
        foreach ($images as $img) {
            $alt = trim($img->getAttribute('alt'));
            if ($alt === '') {
                $missingAlt++;
            }
        }

        $h1s = $doc->getElementsByTagName('h1');
        $headings['h1'] = $h1s->length;
        $h2s = $doc->getElementsByTagName('h2');
        $headings['h2'] = $h2s->length;

        $anchors = $doc->getElementsByTagName('a');
        foreach ($anchors as $anchor) {
            $text = strtolower(trim($anchor->textContent));
            if ($text !== '') {
                foreach ($genericLinkTerms as $term) {
                    if ($text === $term) {
                        $genericLinks++;
                        break;
                    }
                }
            }
        }

        $landmarkTags = ['main', 'nav', 'header', 'footer'];
        foreach ($landmarkTags as $tag) {
            $landmarks += $doc->getElementsByTagName($tag)->length;
        }
    }

    $issues = [];

    if ($missingAlt > 0) {
        $issues[] = sprintf('%d images missing alt text', $missingAlt);
        $summary['missing_alt'] += $missingAlt;
    }

    if ($headings['h1'] === 0) {
        $issues[] = 'No H1 heading found';
    } elseif ($headings['h1'] > 1) {
        $issues[] = 'Multiple H1 headings detected';
    }

    if ($genericLinks > 0) {
        $issues[] = sprintf('%d link(s) use generic text', $genericLinks);
    }

    if ($landmarks === 0) {
        $issues[] = 'Consider adding landmark elements (main, nav, header, footer)';
    }

    if (empty($issues)) {
        $summary['accessible']++;
    } else {
        $summary['needs_review']++;
    }

    $issueCount += count($issues);
    $report[] = [
        'title' => $title,
        'slug' => $slug,
        'image_count' => $imageCount,
        'missing_alt' => $missingAlt,
        'headings' => $headings,
        'generic_links' => $genericLinks,
        'landmarks' => $landmarks,
        'issues' => $issues,
    ];
}

$totalPages = count($report);
$avgCompliance = $totalPages > 0 ? round(($summary['accessible'] / $totalPages) * 100) : 0;
$criticalIssues = $issueCount;
$accessibleRate = $avgCompliance;
$lastScan = date('M j, Y g:i A');

libxml_clear_errors();
?>
<div class="content-section" id="accessibility">
    <div class="a11y-hero">
        <div class="a11y-hero-header">
            <div>
                <h2 class="a11y-hero-title">Accessibility Dashboard</h2>
                <p class="a11y-hero-subtitle">Monitor WCAG compliance and ensure every experience is inclusive.</p>
            </div>
            <div class="a11y-hero-actions">
                <button type="button" id="scanAllPagesBtn" class="a11y-scan-btn">
                    <span class="a11y-scan-icon" aria-hidden="true"><i class="fa-solid fa-arrows-rotate"></i></span>
                    <span class="a11y-scan-label">Scan All Pages</span>
                </button>
                <div class="a11y-hero-meta">Last scan: <?php echo htmlspecialchars($lastScan); ?></div>
            </div>
        </div>
        <div class="a11y-hero-stats">
            <button class="a11y-stat-card active" data-a11y-filter="all">
                <div class="a11y-stat-icon"><i class="fa-solid fa-universal-access" aria-hidden="true"></i></div>
                <div>
                    <div class="a11y-stat-value"><?php echo $totalPages; ?></div>
                    <div class="a11y-stat-label">Total Pages</div>
                </div>
                <span class="a11y-stat-chip">View all</span>
            </button>
            <button class="a11y-stat-card" data-a11y-filter="accessible">
                <div class="a11y-stat-icon success"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></div>
                <div>
                    <div class="a11y-stat-value"><?php echo $summary['accessible']; ?></div>
                    <div class="a11y-stat-label">AA Compliant</div>
                </div>
                <span class="a11y-stat-chip"><?php echo $accessibleRate; ?>% pass</span>
            </button>
            <button class="a11y-stat-card" data-a11y-filter="review">
                <div class="a11y-stat-icon warning"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></div>
                <div>
                    <div class="a11y-stat-value"><?php echo $summary['needs_review']; ?></div>
                    <div class="a11y-stat-label">Needs Review</div>
                </div>
                <span class="a11y-stat-chip">Focus first</span>
            </button>
            <button class="a11y-stat-card" data-a11y-filter="alt">
                <div class="a11y-stat-icon critical"><i class="fa-solid fa-image" aria-hidden="true"></i></div>
                <div>
                    <div class="a11y-stat-value"><?php echo $summary['missing_alt']; ?></div>
                    <div class="a11y-stat-label">Alt Text Missing</div>
                </div>
                <span class="a11y-stat-chip">High impact</span>
            </button>
        </div>
        <div class="a11y-hero-overview">
            <div class="a11y-overview-item">
                <div class="a11y-overview-label">Average Compliance</div>
                <div class="a11y-overview-value"><?php echo $avgCompliance; ?>%</div>
            </div>
            <div class="a11y-overview-item">
                <div class="a11y-overview-label">Critical Issues Detected</div>
                <div class="a11y-overview-value"><?php echo $criticalIssues; ?></div>
            </div>
            <div class="a11y-overview-item">
                <div class="a11y-overview-label">Reports Generated</div>
                <div class="a11y-overview-value"><?php echo $totalPages; ?></div>
            </div>
        </div>
    </div>

    <div class="table-card">
        <div class="table-header">
            <div>
                <div class="table-title">Accessibility Insights</div>
                <p class="table-subtitle">Filter pages and prioritize fixes to improve overall compliance.</p>
            </div>
            <div class="table-actions">
                <input type="text" id="a11ySearch" class="table-search" placeholder="Search by page, issue, or URL" aria-label="Filter accessibility rows">
            </div>
        </div>

        <div class="a11y-filter-bar">
            <button class="a11y-filter-pill active" data-a11y-filter="all">All Pages <span><?php echo $totalPages; ?></span></button>
            <button class="a11y-filter-pill" data-a11y-filter="review">Needs Review <span><?php echo $summary['needs_review']; ?></span></button>
            <button class="a11y-filter-pill" data-a11y-filter="alt">Missing Alt Text <span><?php echo $summary['missing_alt']; ?></span></button>
            <button class="a11y-filter-pill" data-a11y-filter="accessible">WCAG Compliant <span><?php echo $summary['accessible']; ?></span></button>
        </div>

        <table class="data-table" id="accessibilityTable">
            <thead>
                <tr>
                    <th>Page</th>
                    <th>Images</th>
                    <th>Headings</th>
                    <th>Links</th>
                    <th>Landmarks</th>
                    <th>Issues</th>
                    <th class="a11y-action-header">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report as $index => $entry): ?>
                    <?php
                        $statuses = [];
                        if (empty($entry['issues'])) {
                            $statuses[] = 'accessible';
                        } else {
                            $statuses[] = 'review';
                        }
                        if ($entry['missing_alt'] > 0) {
                            $statuses[] = 'alt';
                        }
                        $rowStatus = implode(' ', $statuses);
                        $detailId = 'a11y-details-' . $index;
                    ?>
                    <tr class="a11y-summary-row" data-status="<?php echo htmlspecialchars($rowStatus); ?>" data-expanded="false">
                        <td>
                            <div class="cell-title"><?php echo htmlspecialchars($entry['title']); ?></div>
                            <div class="cell-subtext">/<?php echo htmlspecialchars($entry['slug']); ?></div>
                        </td>
                        <td>
                            <div><?php echo $entry['image_count']; ?> total</div>
                            <?php if ($entry['missing_alt'] > 0): ?>
                                <span class="status-badge status-critical"><?php echo $entry['missing_alt']; ?> missing alt</span>
                            <?php else: ?>
                                <span class="status-badge status-good">All described</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div>H1: <?php echo $entry['headings']['h1']; ?></div>
                            <div>H2: <?php echo $entry['headings']['h2']; ?></div>
                        </td>
                        <td>
                            <?php if ($entry['generic_links'] > 0): ?>
                                <span class="status-badge status-warning"><?php echo $entry['generic_links']; ?> generic</span>
                            <?php else: ?>
                                <span class="status-badge status-good">Descriptive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($entry['landmarks'] > 0): ?>
                                <span class="status-badge status-good"><?php echo $entry['landmarks']; ?> landmark(s)</span>
                            <?php else: ?>
                                <span class="status-badge status-warning">None detected</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($entry['issues'])): ?>
                                <ul class="issue-list">
                                    <?php foreach ($entry['issues'] as $issue): ?>
                                        <li><?php echo htmlspecialchars($issue); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <span class="issue-none">No outstanding issues</span>
                            <?php endif; ?>
                        </td>
                        <td class="a11y-action-cell">
                            <button type="button" class="a11y-detail-btn" aria-expanded="false" aria-controls="<?php echo htmlspecialchars($detailId); ?>">
                                <span class="a11y-detail-icon" aria-hidden="true"><i class="fa-solid fa-chart-pie"></i></span>
                                <span>View details</span>
                            </button>
                        </td>
                    </tr>
                    <tr id="<?php echo htmlspecialchars($detailId); ?>" class="a11y-detail-row" data-status="<?php echo htmlspecialchars($rowStatus); ?>" hidden>
                        <td colspan="7">
                            <div class="a11y-detail-card">
                                <div class="a11y-detail-meta">
                                    <div>
                                        <span class="a11y-detail-label">Page URL</span>
                                        <span class="a11y-detail-value">/<?php echo htmlspecialchars($entry['slug']); ?></span>
                                    </div>
                                    <div>
                                        <span class="a11y-detail-label">Last scanned</span>
                                        <span class="a11y-detail-value"><?php echo htmlspecialchars($lastScan); ?></span>
                                    </div>
                                    <div>
                                        <span class="a11y-detail-label">Landmarks</span>
                                        <span class="a11y-detail-value"><?php echo $entry['landmarks']; ?></span>
                                    </div>
                                </div>
                                <div class="a11y-detail-grid">
                                    <div class="a11y-detail-metric">
                                        <span class="a11y-detail-label">Images</span>
                                        <span class="a11y-detail-value"><?php echo $entry['image_count']; ?></span>
                                        <span class="a11y-detail-hint"><?php echo $entry['missing_alt'] > 0 ? $entry['missing_alt'] . ' missing alt' : 'All images described'; ?></span>
                                    </div>
                                    <div class="a11y-detail-metric">
                                        <span class="a11y-detail-label">Headings</span>
                                        <span class="a11y-detail-value">H1: <?php echo $entry['headings']['h1']; ?> / H2: <?php echo $entry['headings']['h2']; ?></span>
                                        <span class="a11y-detail-hint">Ensure a single descriptive H1</span>
                                    </div>
                                    <div class="a11y-detail-metric">
                                        <span class="a11y-detail-label">Links</span>
                                        <span class="a11y-detail-value"><?php echo $entry['generic_links']; ?> generic</span>
                                        <span class="a11y-detail-hint"><?php echo $entry['generic_links'] > 0 ? 'Improve link text clarity' : 'All links descriptive'; ?></span>
                                    </div>
                                </div>
                                <div class="a11y-detail-issues">
                                    <h4>Issues detected</h4>
                                    <?php if (!empty($entry['issues'])): ?>
                                        <ul>
                                            <?php foreach ($entry['issues'] as $issue): ?>
                                                <li><?php echo htmlspecialchars($issue); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <p class="a11y-detail-success">This page meets the current automated checks.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
