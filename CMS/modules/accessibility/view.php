<?php
// File: modules/accessibility/view.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_login();

$pagesFile = __DIR__ . '/../../data/pages.json';
$pages = read_json_file($pagesFile);

libxml_use_internal_errors(true);

$report = [];
$summary = [
    'accessible' => 0,
    'needs_review' => 0,
    'missing_alt' => 0,
];

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
    $content = $page['content'] ?? '';

    $doc = new DOMDocument();
    $loaded = $content !== '' && $doc->loadHTML('<?xml encoding="utf-8" ?>' . $content);

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
?>
<div class="content-section" id="accessibility">
    <div class="table-card">
        <div class="table-header">
            <div class="table-title">Accessibility Insights</div>
            <div class="table-actions">
                <input type="text" id="a11ySearch" class="table-search" placeholder="Filter pages..." aria-label="Filter accessibility rows">
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card" data-a11y-filter="all">
                <div class="stat-header">
                    <div class="stat-icon accessibility">♿</div>
                    <div class="stat-content">
                        <div class="stat-label">Total Pages</div>
                        <div class="stat-number"><?php echo count($report); ?></div>
                    </div>
                </div>
            </div>
            <div class="stat-card" data-a11y-filter="accessible">
                <div class="stat-header">
                    <div class="stat-icon accessibility">✅</div>
                    <div class="stat-content">
                        <div class="stat-label">No Issues</div>
                        <div class="stat-number"><?php echo $summary['accessible']; ?></div>
                    </div>
                </div>
            </div>
            <div class="stat-card" data-a11y-filter="review">
                <div class="stat-header">
                    <div class="stat-icon accessibility">⚠️</div>
                    <div class="stat-content">
                        <div class="stat-label">Needs Review</div>
                        <div class="stat-number"><?php echo $summary['needs_review']; ?></div>
                    </div>
                </div>
            </div>
            <div class="stat-card" data-a11y-filter="alt">
                <div class="stat-header">
                    <div class="stat-icon accessibility">🖼️</div>
                    <div class="stat-content">
                        <div class="stat-label">Missing Alt Text</div>
                        <div class="stat-number"><?php echo $summary['missing_alt']; ?></div>
                    </div>
                </div>
            </div>
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
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report as $entry): ?>
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
                    ?>
                    <tr data-status="<?php echo htmlspecialchars($rowStatus); ?>">
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
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
