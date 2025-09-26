<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_login();

$pagesFile = __DIR__ . '/../../data/pages.json';
$pages = read_json_file($pagesFile);
if (!is_array($pages)) {
    $pages = [];
}

$stringLength = function (string $value): int {
    if (function_exists('mb_strlen')) {
        return mb_strlen($value);
    }
    return strlen($value);
};

$report = [];
$summary = [
    'total_pages' => 0,
    'score_sum' => 0,
    'critical_issues' => 0,
    'metadata_gaps' => 0,
    'attention_pages' => 0,
    'status_counts' => [
        'critical' => 0,
        'warning' => 0,
        'good' => 0,
        'excellent' => 0,
    ],
];

$formatDate = static function ($timestamp): ?string {
    if (is_numeric($timestamp) && $timestamp > 0) {
        return date('M j, Y', (int) $timestamp);
    }
    return null;
};

foreach ($pages as $page) {
    $summary['total_pages']++;

    $title = isset($page['title']) ? (string) $page['title'] : 'Untitled';
    $slug = isset($page['slug']) ? (string) $page['slug'] : '';
    $metaTitle = trim((string) ($page['meta_title'] ?? ''));
    $metaDescription = trim((string) ($page['meta_description'] ?? ''));
    $ogTitle = trim((string) ($page['og_title'] ?? ''));
    $ogDescription = trim((string) ($page['og_description'] ?? ''));
    $ogImage = trim((string) ($page['og_image'] ?? ''));

    $metaTitleLength = $metaTitle !== '' ? $stringLength($metaTitle) : 0;
    $metaDescriptionLength = $metaDescription !== '' ? $stringLength($metaDescription) : 0;

    $issues = [];
    $metaTitleStatus = 'good';
    $metaDescriptionStatus = 'good';

    if ($metaTitle === '') {
        $issues[] = [
            'message' => 'Meta title missing',
            'severity' => 'critical',
        ];
        $metaTitleStatus = 'critical';
        $summary['metadata_gaps']++;
    } elseif ($metaTitleLength < 30 || $metaTitleLength > 60) {
        $issues[] = [
            'message' => 'Meta title length outside 30-60 characters',
            'severity' => 'warning',
        ];
        $metaTitleStatus = 'warning';
    }

    if ($metaDescription === '') {
        $issues[] = [
            'message' => 'Meta description missing',
            'severity' => 'critical',
        ];
        $metaDescriptionStatus = 'critical';
        $summary['metadata_gaps']++;
    } elseif ($metaDescriptionLength < 50 || $metaDescriptionLength > 160) {
        $issues[] = [
            'message' => 'Meta description length outside 50-160 characters',
            'severity' => 'warning',
        ];
        $metaDescriptionStatus = 'warning';
    }

    $slugIssues = [];
    if ($slug === '') {
        $slugIssues[] = 'Slug is missing';
    }
    if ($slug !== '' && !preg_match('/^[a-z0-9\-]+$/', $slug)) {
        $slugIssues[] = 'Slug should use lowercase letters, numbers, and dashes only';
    }
    foreach ($slugIssues as $slugIssue) {
        $issues[] = [
            'message' => $slugIssue,
            'severity' => 'warning',
        ];
    }

    $hasSocialPreview = $ogTitle !== '' && $ogDescription !== '' && $ogImage !== '';
    if (!$hasSocialPreview) {
        $issues[] = [
            'message' => 'Social preview incomplete (requires OG title, description, and image)',
            'severity' => 'warning',
        ];
    }

    $criticalCount = 0;
    $warningCount = 0;
    foreach ($issues as $issue) {
        if ($issue['severity'] === 'critical') {
            $criticalCount++;
        } else {
            $warningCount++;
        }
    }

    $summary['critical_issues'] += $criticalCount;

    $score = 100 - ($criticalCount * 30) - ($warningCount * 15);
    if ($score < 0) {
        $score = 0;
    }
    if ($score > 100) {
        $score = 100;
    }

    if ($score >= 90) {
        $scoreLabel = 'Excellent';
        $scoreStatus = 'excellent';
    } elseif ($score >= 75) {
        $scoreLabel = 'Good';
        $scoreStatus = 'good';
    } elseif ($score >= 55) {
        $scoreLabel = 'Needs Attention';
        $scoreStatus = 'warning';
    } else {
        $scoreLabel = 'Critical';
        $scoreStatus = 'critical';
    }

    $summary['status_counts'][$scoreStatus]++;
    if ($scoreStatus === 'critical' || $scoreStatus === 'warning') {
        $summary['attention_pages']++;
    }

    $summary['score_sum'] += $score;

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
        'score' => $score,
        'score_label' => $scoreLabel,
        'score_status' => $scoreStatus,
        'critical_count' => $criticalCount,
        'warning_count' => $warningCount,
        'last_updated' => $formatDate($page['last_modified'] ?? null),
    ];
}

$averageScore = $summary['total_pages'] > 0
    ? (int) round($summary['score_sum'] / $summary['total_pages'])
    : 0;

?>
<div class="seo-dashboard" id="seo" data-total-pages="<?php echo (int) $summary['total_pages']; ?>">
    <style>
        .seo-dashboard {
            font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: #0f172a;
        }
        .seo-dashboard .seo-header {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: #fff;
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }
        .seo-dashboard .seo-header::after {
            content: "";
            position: absolute;
            inset: auto -60px -90px auto;
            width: 260px;
            height: 260px;
            background: rgba(255,255,255,0.12);
            border-radius: 50%;
        }
        .seo-dashboard .seo-header-content {
            position: relative;
            z-index: 1;
        }
        .seo-dashboard h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 12px;
        }
        .seo-dashboard p.seo-lead {
            font-size: 16px;
            max-width: 720px;
            color: rgba(255,255,255,0.9);
        }
        .seo-dashboard .seo-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 18px;
            margin-top: 28px;
        }
        .seo-dashboard .seo-overview-card {
            background: rgba(17, 24, 39, 0.25);
            border-radius: 14px;
            padding: 20px;
            backdrop-filter: blur(8px);
        }
        .seo-dashboard .seo-overview-value {
            font-size: 28px;
            font-weight: 600;
        }
        .seo-dashboard .seo-overview-label {
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: rgba(255,255,255,0.75);
        }
        .seo-dashboard .seo-controls {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 15px 30px rgba(15, 23, 42, 0.08);
            padding: 24px 28px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            gap: 18px;
            align-items: center;
        }
        .seo-dashboard .seo-search {
            position: relative;
            flex: 1 1 280px;
        }
        .seo-dashboard .seo-search input {
            width: 100%;
            padding: 12px 40px 12px 14px;
            border-radius: 12px;
            border: 1px solid #dbeafe;
            font-size: 14px;
            transition: border-color 0.2s ease;
        }
        .seo-dashboard .seo-search input:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }
        .seo-dashboard .seo-search i {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
        }
        .seo-dashboard .seo-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .seo-dashboard .seo-filter-btn {
            border-radius: 999px;
            border: 1px solid #e2e8f0;
            padding: 8px 18px;
            background: #fff;
            color: #475569;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .seo-dashboard .seo-filter-btn:hover,
        .seo-dashboard .seo-filter-btn.active {
            border-color: #6366f1;
            background: #eef2ff;
            color: #312e81;
        }
        .seo-dashboard .seo-view-toggle {
            display: inline-flex;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
        }
        .seo-dashboard .seo-view-btn {
            border: none;
            background: #fff;
            padding: 8px 14px;
            color: #475569;
            cursor: pointer;
            transition: background 0.2s ease;
        }
        .seo-dashboard .seo-view-btn.active {
            background: #6366f1;
            color: #fff;
        }
        .seo-dashboard .seo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 22px;
        }
        .seo-dashboard .seo-card {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.08);
            padding: 24px;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            border: 1px solid transparent;
        }
        .seo-dashboard .seo-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 32px rgba(15, 23, 42, 0.12);
        }
        .seo-dashboard .seo-card-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .seo-dashboard .seo-card-url {
            font-family: "JetBrains Mono", "Fira Code", monospace;
            font-size: 12px;
            color: #64748b;
            margin-bottom: 18px;
        }
        .seo-dashboard .seo-card-score {
            position: absolute;
            top: 22px;
            right: 24px;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 600;
            font-size: 16px;
        }
        .seo-dashboard .seo-card-score.score-excellent { background: linear-gradient(135deg, #22c55e, #16a34a); }
        .seo-dashboard .seo-card-score.score-good { background: linear-gradient(135deg, #3b82f6, #2563eb); }
        .seo-dashboard .seo-card-score.score-warning { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .seo-dashboard .seo-card-score.score-critical { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .seo-dashboard .seo-card-meta {
            position: relative;
            padding-right: 70px;
        }
        .seo-dashboard .seo-card-stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 18px;
        }
        .seo-dashboard .seo-card-stat-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #94a3b8;
        }
        .seo-dashboard .seo-card-stat-value {
            font-size: 18px;
            font-weight: 600;
            color: #1e293b;
        }
        .seo-dashboard .seo-card-issues {
            background: #f8fafc;
            padding: 16px;
            border-radius: 12px;
        }
        .seo-dashboard .seo-card-issues strong {
            display: block;
            margin-bottom: 10px;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #475569;
        }
        .seo-dashboard .seo-card-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .seo-dashboard .seo-tag {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            padding: 6px 10px;
            border-radius: 999px;
        }
        .seo-dashboard .seo-tag.critical { background: #fee2e2; color: #b91c1c; }
        .seo-dashboard .seo-tag.warning { background: #fef3c7; color: #92400e; }
        .seo-dashboard .seo-tag.good { background: #dcfce7; color: #166534; }
        .seo-dashboard .seo-table-wrapper {
            margin-top: 30px;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.08);
            overflow: hidden;
            display: none;
        }
        .seo-dashboard .seo-table-wrapper.active {
            display: block;
        }
        .seo-dashboard table {
            width: 100%;
            border-collapse: collapse;
        }
        .seo-dashboard thead {
            background: #f8fafc;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.4px;
            color: #64748b;
        }
        .seo-dashboard th,
        .seo-dashboard td {
            padding: 18px 22px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            vertical-align: top;
            font-size: 14px;
        }
        .seo-dashboard tbody tr {
            cursor: pointer;
        }
        .seo-dashboard tbody tr:hover {
            background: #f8fafc;
        }
        .seo-dashboard .seo-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
        }
        .seo-dashboard .seo-status-badge.good { background: #dcfce7; color: #166534; }
        .seo-dashboard .seo-status-badge.warning { background: #fef3c7; color: #92400e; }
        .seo-dashboard .seo-status-badge.critical { background: #fee2e2; color: #b91c1c; }
        .seo-dashboard .seo-status-badge.neutral { background: #e2e8f0; color: #334155; }
        .seo-dashboard .seo-detail-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.55);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1050;
            padding: 24px;
        }
        .seo-dashboard .seo-detail-overlay.active {
            display: flex;
        }
        .seo-dashboard .seo-detail {
            background: #fff;
            border-radius: 20px;
            max-width: 860px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            box-shadow: 0 30px 60px rgba(15, 23, 42, 0.18);
        }
        .seo-dashboard .seo-detail-header {
            padding: 32px;
            border-bottom: 1px solid #e2e8f0;
        }
        .seo-dashboard .seo-detail-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 6px;
        }
        .seo-dashboard .seo-detail-url {
            font-family: "JetBrains Mono", "Fira Code", monospace;
            font-size: 13px;
            color: #64748b;
        }
        .seo-dashboard .seo-detail-score {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-top: 24px;
        }
        .seo-dashboard .seo-detail-score-circle {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 24px;
            font-weight: 700;
        }
        .seo-dashboard .seo-detail-score-circle.score-excellent { background: linear-gradient(135deg, #22c55e, #16a34a); }
        .seo-dashboard .seo-detail-score-circle.score-good { background: linear-gradient(135deg, #3b82f6, #2563eb); }
        .seo-dashboard .seo-detail-score-circle.score-warning { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .seo-dashboard .seo-detail-score-circle.score-critical { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .seo-dashboard .seo-detail-score-text {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .seo-dashboard .seo-detail-score-label {
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.4px;
            color: #64748b;
        }
        .seo-dashboard .seo-detail-score-value {
            font-size: 20px;
            font-weight: 600;
        }
        .seo-dashboard .seo-detail-body {
            padding: 32px;
            display: grid;
            gap: 28px;
        }
        .seo-dashboard .seo-detail-section h3 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 12px;
        }
        .seo-dashboard .seo-detail-grid {
            display: grid;
            gap: 18px;
        }
        @media (min-width: 768px) {
            .seo-dashboard .seo-detail-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        .seo-dashboard .seo-detail-card {
            background: #f8fafc;
            border-radius: 16px;
            padding: 18px;
        }
        .seo-dashboard .seo-detail-card strong {
            display: block;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 8px;
            color: #475569;
        }
        .seo-dashboard .seo-issue-list {
            list-style: none;
            display: grid;
            gap: 10px;
        }
        .seo-dashboard .seo-issue-item {
            display: flex;
            gap: 10px;
            align-items: flex-start;
        }
        .seo-dashboard .seo-issue-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-top: 6px;
        }
        .seo-dashboard .seo-issue-dot.critical { background: #ef4444; }
        .seo-dashboard .seo-issue-dot.warning { background: #f97316; }
        .seo-dashboard .seo-issue-dot.good { background: #22c55e; }
        .seo-dashboard .seo-detail-close {
            position: absolute;
            top: 20px;
            right: 20px;
            border: none;
            background: transparent;
            font-size: 28px;
            color: #475569;
            cursor: pointer;
        }
        .seo-dashboard .seo-empty {
            background: #fff;
            border-radius: 16px;
            padding: 48px;
            text-align: center;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
        }
    </style>

    <div class="seo-header">
        <div class="seo-header-content">
            <h1>SEO Dashboard</h1>
            <p class="seo-lead">Monitor SEO health across your published pages. Track metadata quality, spot urgent issues, and drill into page-level recommendations.</p>
            <div class="seo-overview">
                <div class="seo-overview-card">
                    <div class="seo-overview-value"><?php echo (int) $summary['total_pages']; ?></div>
                    <div class="seo-overview-label">Total Pages</div>
                </div>
                <div class="seo-overview-card">
                    <div class="seo-overview-value"><?php echo (int) $averageScore; ?></div>
                    <div class="seo-overview-label">Average Score</div>
                </div>
                <div class="seo-overview-card">
                    <div class="seo-overview-value"><?php echo (int) $summary['attention_pages']; ?></div>
                    <div class="seo-overview-label">Pages Needing Attention</div>
                </div>
                <div class="seo-overview-card">
                    <div class="seo-overview-value"><?php echo (int) $summary['metadata_gaps']; ?></div>
                    <div class="seo-overview-label">Metadata Gaps</div>
                </div>
            </div>
        </div>
    </div>

    <div class="seo-controls">
        <div class="seo-search">
            <input type="text" id="seoSearchInput" placeholder="Search pages by title, slug, or metadata..." aria-label="Search SEO pages">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        </div>
        <div class="seo-filters" role="group" aria-label="Filter pages by status">
            <button type="button" class="seo-filter-btn active" data-filter="all">All Pages</button>
            <button type="button" class="seo-filter-btn" data-filter="critical">Critical</button>
            <button type="button" class="seo-filter-btn" data-filter="warning">Needs Attention</button>
            <button type="button" class="seo-filter-btn" data-filter="good">Performing Well</button>
        </div>
        <div class="seo-view-toggle" role="group" aria-label="Toggle SEO view">
            <button type="button" class="seo-view-btn active" data-view="grid"><i class="fa-solid fa-grip"></i></button>
            <button type="button" class="seo-view-btn" data-view="table"><i class="fa-solid fa-list"></i></button>
        </div>
    </div>

    <?php if (empty($report)): ?>
        <div class="seo-empty">
            <h2>No pages to analyze yet</h2>
            <p>Add or publish pages to begin generating SEO insights.</p>
        </div>
    <?php else: ?>
        <div class="seo-grid" id="seoGrid">
            <?php foreach ($report as $index => $entry): ?>
                <?php
                    $data = [
                        'title' => $entry['title'],
                        'slug' => $entry['slug'],
                        'score' => $entry['score'],
                        'scoreLabel' => $entry['score_label'],
                        'scoreStatus' => $entry['score_status'],
                        'metaTitle' => $entry['meta_title'],
                        'metaTitleLength' => $entry['meta_title_length'],
                        'metaTitleStatus' => $entry['meta_title_status'],
                        'metaDescription' => $entry['meta_description'],
                        'metaDescriptionLength' => $entry['meta_description_length'],
                        'metaDescriptionStatus' => $entry['meta_description_status'],
                        'hasSocial' => $entry['has_social'],
                        'issues' => $entry['issues'],
                        'lastUpdated' => $entry['last_updated'],
                        'criticalCount' => $entry['critical_count'],
                        'warningCount' => $entry['warning_count'],
                    ];
                    $jsonData = htmlspecialchars(json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
                ?>
                <article class="seo-card" data-status="<?php echo htmlspecialchars($entry['score_status'], ENT_QUOTES, 'UTF-8'); ?>" data-search="<?php echo htmlspecialchars(strtolower($entry['title'] . ' ' . $entry['slug'] . ' ' . $entry['meta_title']), ENT_QUOTES, 'UTF-8'); ?>" data-page="<?php echo $jsonData; ?>">
                    <div class="seo-card-meta">
                        <div class="seo-card-score score-<?php echo htmlspecialchars($entry['score_status'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo (int) $entry['score']; ?>
                        </div>
                        <h2 class="seo-card-title"><?php echo htmlspecialchars($entry['title']); ?></h2>
                        <div class="seo-card-url">/<?php echo htmlspecialchars($entry['slug']); ?></div>
                        <div class="seo-card-stats">
                            <div>
                                <div class="seo-card-stat-label">Meta Title</div>
                                <div class="seo-card-stat-value"><?php echo $entry['meta_title_length'] > 0 ? (int) $entry['meta_title_length'] . ' chars' : '—'; ?></div>
                            </div>
                            <div>
                                <div class="seo-card-stat-label">Meta Description</div>
                                <div class="seo-card-stat-value"><?php echo $entry['meta_description_length'] > 0 ? (int) $entry['meta_description_length'] . ' chars' : '—'; ?></div>
                            </div>
                            <div>
                                <div class="seo-card-stat-label">Social Preview</div>
                                <div class="seo-card-stat-value"><?php echo $entry['has_social'] ? 'Complete' : 'Incomplete'; ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="seo-card-issues">
                        <strong>Issues</strong>
                        <div class="seo-card-tags">
                            <?php if (empty($entry['issues'])): ?>
                                <span class="seo-tag good">Optimized</span>
                            <?php else: ?>
                                <?php foreach ($entry['issues'] as $issue): ?>
                                    <span class="seo-tag <?php echo htmlspecialchars($issue['severity']); ?>"><?php echo htmlspecialchars($issue['message']); ?></span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="seo-table-wrapper" id="seoTableWrapper">
            <table>
                <thead>
                    <tr>
                        <th>Page</th>
                        <th>Score</th>
                        <th>Meta Title</th>
                        <th>Meta Description</th>
                        <th>Social Preview</th>
                        <th>Critical Issues</th>
                        <th>Warnings</th>
                        <th>Last Updated</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report as $entry): ?>
                        <?php
                            $data = [
                                'title' => $entry['title'],
                                'slug' => $entry['slug'],
                                'score' => $entry['score'],
                                'scoreLabel' => $entry['score_label'],
                                'scoreStatus' => $entry['score_status'],
                                'metaTitle' => $entry['meta_title'],
                                'metaTitleLength' => $entry['meta_title_length'],
                                'metaTitleStatus' => $entry['meta_title_status'],
                                'metaDescription' => $entry['meta_description'],
                                'metaDescriptionLength' => $entry['meta_description_length'],
                                'metaDescriptionStatus' => $entry['meta_description_status'],
                                'hasSocial' => $entry['has_social'],
                                'issues' => $entry['issues'],
                                'lastUpdated' => $entry['last_updated'],
                                'criticalCount' => $entry['critical_count'],
                                'warningCount' => $entry['warning_count'],
                            ];
                            $jsonData = htmlspecialchars(json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
                        ?>
                        <tr data-status="<?php echo htmlspecialchars($entry['score_status'], ENT_QUOTES, 'UTF-8'); ?>" data-search="<?php echo htmlspecialchars(strtolower($entry['title'] . ' ' . $entry['slug'] . ' ' . $entry['meta_title']), ENT_QUOTES, 'UTF-8'); ?>" data-page="<?php echo $jsonData; ?>">
                            <td>
                                <div class="seo-card-title" style="margin-bottom: 4px; font-size: 16px;">
                                    <?php echo htmlspecialchars($entry['title']); ?>
                                </div>
                                <div class="seo-card-url" style="margin-bottom: 0;">/<?php echo htmlspecialchars($entry['slug']); ?></div>
                            </td>
                            <td>
                                <span class="seo-status-badge <?php echo htmlspecialchars($entry['score_status']); ?>"><?php echo (int) $entry['score']; ?> &bull; <?php echo htmlspecialchars($entry['score_label']); ?></span>
                            </td>
                            <td>
                                <?php if ($entry['meta_title'] !== ''): ?>
                                    <div><?php echo htmlspecialchars($entry['meta_title']); ?></div>
                                    <div class="seo-card-url" style="font-size: 12px; margin-top: 6px;">Length: <?php echo (int) $entry['meta_title_length']; ?> chars</div>
                                <?php else: ?>
                                    <span class="seo-status-badge critical">Missing</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($entry['meta_description'] !== ''): ?>
                                    <div><?php echo htmlspecialchars($entry['meta_description']); ?></div>
                                    <div class="seo-card-url" style="font-size: 12px; margin-top: 6px;">Length: <?php echo (int) $entry['meta_description_length']; ?> chars</div>
                                <?php else: ?>
                                    <span class="seo-status-badge critical">Missing</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($entry['has_social']): ?>
                                    <span class="seo-status-badge good">Complete</span>
                                <?php else: ?>
                                    <span class="seo-status-badge warning">Incomplete</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo (int) $entry['critical_count']; ?></td>
                            <td><?php echo (int) $entry['warning_count']; ?></td>
                            <td><?php echo $entry['last_updated'] !== null ? htmlspecialchars($entry['last_updated']) : '—'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <div class="seo-detail-overlay" id="seoDetail" role="dialog" aria-modal="true" aria-hidden="true">
        <div class="seo-detail">
            <button type="button" class="seo-detail-close" aria-label="Close details">&times;</button>
            <div class="seo-detail-header">
                <div class="seo-detail-title" data-detail="title"></div>
                <div class="seo-detail-url" data-detail="url"></div>
                <div class="seo-detail-score">
                    <div class="seo-detail-score-circle" data-detail="score-circle"></div>
                    <div class="seo-detail-score-text">
                        <span class="seo-detail-score-label">SEO Score</span>
                        <span class="seo-detail-score-value" data-detail="score"></span>
                        <span class="seo-card-url" style="margin: 0;" data-detail="score-label"></span>
                    </div>
                </div>
            </div>
            <div class="seo-detail-body">
                <section class="seo-detail-section">
                    <h3>Metadata Overview</h3>
                    <div class="seo-detail-grid">
                        <div class="seo-detail-card">
                            <strong>Meta Title</strong>
                            <div data-detail="meta-title"></div>
                            <div class="seo-card-url" data-detail="meta-title-length"></div>
                        </div>
                        <div class="seo-detail-card">
                            <strong>Meta Description</strong>
                            <div data-detail="meta-description"></div>
                            <div class="seo-card-url" data-detail="meta-description-length"></div>
                        </div>
                        <div class="seo-detail-card">
                            <strong>Social Preview</strong>
                            <div data-detail="social-status"></div>
                        </div>
                        <div class="seo-detail-card">
                            <strong>Last Updated</strong>
                            <div data-detail="last-updated"></div>
                        </div>
                    </div>
                </section>
                <section class="seo-detail-section">
                    <h3>Issues & Recommendations</h3>
                    <ul class="seo-issue-list" data-detail="issues"></ul>
                </section>
            </div>
        </div>
    </div>
</div>
