<?php
// File: modules/seo/view.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_login();

$pagesFile = __DIR__ . '/../../data/pages.json';
$pages = read_json_file($pagesFile);

$report = [];
$summary = [
    'optimized' => 0,
    'needs_attention' => 0,
    'metadata_gaps' => 0,
];

foreach ($pages as $page) {
    $title = $page['title'] ?? 'Untitled';
    $slug = $page['slug'] ?? '';

    $metaTitle = trim($page['meta_title'] ?? '');
    $metaDescription = trim($page['meta_description'] ?? '');
    $ogTitle = trim($page['og_title'] ?? '');
    $ogDescription = trim($page['og_description'] ?? '');
    $ogImage = trim($page['og_image'] ?? '');

    $metaTitleLength = $metaTitle !== '' ? mb_strlen($metaTitle) : 0;
    $metaDescriptionLength = $metaDescription !== '' ? mb_strlen($metaDescription) : 0;

    $issues = [];
    $metaTitleStatus = 'good';
    $metaDescriptionStatus = 'good';

    if ($metaTitle === '') {
        $issues[] = 'Meta title missing';
        $metaTitleStatus = 'critical';
        $summary['metadata_gaps']++;
    } elseif ($metaTitleLength < 30 || $metaTitleLength > 60) {
        $issues[] = 'Meta title length outside 30-60 characters';
        $metaTitleStatus = 'warning';
    }

    if ($metaDescription === '') {
        $issues[] = 'Meta description missing';
        $metaDescriptionStatus = 'critical';
        $summary['metadata_gaps']++;
    } elseif ($metaDescriptionLength < 50 || $metaDescriptionLength > 160) {
        $issues[] = 'Meta description length outside 50-160 characters';
        $metaDescriptionStatus = 'warning';
    }

    if ($slug === '' || !preg_match('/^[a-z0-9\-]+$/', $slug)) {
        $issues[] = 'Slug should use lowercase letters, numbers, and dashes only';
    }

    $hasSocialPreview = $ogTitle !== '' && $ogDescription !== '' && $ogImage !== '';
    if (!$hasSocialPreview) {
        $issues[] = 'Social preview incomplete (requires OG title, description, and image)';
    }

    if (empty($issues)) {
        $summary['optimized']++;
    } else {
        $summary['needs_attention']++;
    }

    $report[] = [
        'title' => $title,
        'slug' => $slug,
        'meta_title' => $metaTitle,
        'meta_title_length' => $metaTitleLength,
        'meta_title_status' => $metaTitleStatus,
        'meta_description' => $metaDescription,
        'meta_description_length' => $metaDescriptionLength,
        'meta_description_status' => $metaDescriptionStatus,
        'has_social' => $hasSocialPreview,
        'issues' => $issues,
    ];
}
?>
<div class="content-section" id="seo">
    <div class="table-card">
        <div class="table-header">
            <div class="table-title">SEO Overview</div>
            <div class="table-actions">
                <input type="text" id="seoSearch" class="table-search" placeholder="Filter pages..." aria-label="Filter SEO rows">
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card" data-seo-filter="all">
                <div class="stat-header">
                    <div class="stat-icon seo">🔎</div>
                    <div class="stat-content">
                        <div class="stat-label">Total Pages</div>
                        <div class="stat-number"><?php echo count($report); ?></div>
                    </div>
                </div>
            </div>
            <div class="stat-card" data-seo-filter="optimized">
                <div class="stat-header">
                    <div class="stat-icon seo">✅</div>
                    <div class="stat-content">
                        <div class="stat-label">Optimized</div>
                        <div class="stat-number"><?php echo $summary['optimized']; ?></div>
                    </div>
                </div>
            </div>
            <div class="stat-card" data-seo-filter="attention">
                <div class="stat-header">
                    <div class="stat-icon seo">⚠️</div>
                    <div class="stat-content">
                        <div class="stat-label">Needs Attention</div>
                        <div class="stat-number"><?php echo $summary['needs_attention']; ?></div>
                    </div>
                </div>
            </div>
            <div class="stat-card" data-seo-filter="metadata">
                <div class="stat-header">
                    <div class="stat-icon seo">📝</div>
                    <div class="stat-content">
                        <div class="stat-label">Metadata Gaps</div>
                        <div class="stat-number"><?php echo $summary['metadata_gaps']; ?></div>
                    </div>
                </div>
            </div>
        </div>

        <table class="data-table" id="seoTable">
            <thead>
                <tr>
                    <th>Page</th>
                    <th>Meta Title</th>
                    <th>Meta Description</th>
                    <th>Slug</th>
                    <th>Social Preview</th>
                    <th>Issues</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report as $entry): ?>
                    <?php
                        $statuses = [];
                        if (empty($entry['issues'])) {
                            $statuses[] = 'optimized';
                        } else {
                            $statuses[] = 'attention';
                        }
                        if ($entry['meta_title'] === '' || $entry['meta_description'] === '') {
                            $statuses[] = 'metadata';
                        }
                        $rowStatus = implode(' ', $statuses);
                    ?>
                    <tr data-status="<?php echo htmlspecialchars($rowStatus); ?>">
                        <td>
                            <div class="cell-title"><?php echo htmlspecialchars($entry['title']); ?></div>
                            <div class="cell-subtext">/<?php echo htmlspecialchars($entry['slug']); ?></div>
                        </td>
                        <td>
                            <?php if ($entry['meta_title'] !== ''): ?>
                                <div><?php echo htmlspecialchars($entry['meta_title']); ?></div>
                                <span class="status-badge status-<?php echo htmlspecialchars($entry['meta_title_status']); ?>">
                                    <?php echo $entry['meta_title_length']; ?> chars
                                </span>
                            <?php else: ?>
                                <span class="status-badge status-critical">Missing</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($entry['meta_description'] !== ''): ?>
                                <div class="cell-muted"><?php echo htmlspecialchars($entry['meta_description']); ?></div>
                                <span class="status-badge status-<?php echo htmlspecialchars($entry['meta_description_status']); ?>">
                                    <?php echo $entry['meta_description_length']; ?> chars
                                </span>
                            <?php else: ?>
                                <span class="status-badge status-critical">Missing</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <code>/<?php echo htmlspecialchars($entry['slug']); ?></code>
                        </td>
                        <td>
                            <?php if ($entry['has_social']): ?>
                                <span class="status-badge status-good">Complete</span>
                            <?php else: ?>
                                <span class="status-badge status-warning">Incomplete</span>
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
