<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../includes/score_history.php';
require_login();

$pagesFile = __DIR__ . '/../../data/pages.json';
$pages = read_json_file($pagesFile);
if (!is_array($pages)) {
    $pages = [];
}

function seo_pluralize(int $count, string $singular, ?string $plural = null): string
{
    if ($count === 1) {
        return $singular;
    }

    return $plural ?? ($singular . 's');
}

function seo_status_label(string $status): string
{
    switch ($status) {
        case 'excellent':
            return 'Excellent';
        case 'good':
            return 'Good';
        case 'warning':
            return 'Needs Attention';
        case 'critical':
            return 'Critical';
        default:
            return ucfirst($status);
    }
}

function seo_score_gradient(string $status): string
{
    switch ($status) {
        case 'excellent':
            return 'linear-gradient(135deg, #10b981, #059669)';
        case 'good':
            return 'linear-gradient(135deg, #3b82f6, #1d4ed8)';
        case 'warning':
            return 'linear-gradient(135deg, #f59e0b, #d97706)';
        case 'critical':
            return 'linear-gradient(135deg, #ef4444, #dc2626)';
        default:
            return 'linear-gradient(135deg, #64748b, #475569)';
    }
}

function seo_score_summary(array $entry): string
{
    $issues = isset($entry['issues']) && is_array($entry['issues']) ? count($entry['issues']) : 0;
    $critical = (int) ($entry['critical_count'] ?? 0);
    $warnings = (int) ($entry['warning_count'] ?? 0);

    switch ($entry['score_status'] ?? '') {
        case 'excellent':
            if ($issues === 0) {
                return 'All SEO fundamentals look great. Keep content fresh to maintain this performance.';
            }

            return sprintf(
                'Strong SEO coverage with %d minor %s to monitor.',
                $warnings,
                seo_pluralize($warnings, 'opportunity', 'opportunities')
            );
        case 'good':
            return sprintf(
                'Healthy performance overall. Address %d outstanding %s to push this page into the excellent range.',
                $issues,
                seo_pluralize($issues, 'issue')
            );
        case 'warning':
            return sprintf(
                'Several improvements detected. Resolve %d critical %s and review the remaining warnings to protect rankings.',
                $critical,
                seo_pluralize($critical, 'item')
            );
        case 'critical':
            return sprintf(
                'This page is at risk with %d critical %s and %d additional %s. Prioritize fixes immediately.',
                $critical,
                seo_pluralize($critical, 'issue'),
                $warnings,
                seo_pluralize($warnings, 'warning')
            );
        default:
            return 'Review the detected items to improve overall SEO quality.';
    }
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

$analyzeContent = static function (string $html): array {
    $wordCount = 0;
    $h1Count = 0;
    $missingAlt = 0;
    $internalLinks = 0;

    if (trim($html) === '') {
        return [
            'word_count' => 0,
            'h1_count' => 0,
            'missing_alt' => 0,
            'internal_links' => 0,
        ];
    }

    $loaded = false;
    $dom = null;
    $libxmlPrevious = libxml_use_internal_errors(true);

    if (class_exists('DOMDocument')) {
        $dom = new DOMDocument();
        try {
            $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        } catch (Exception $exception) {
            $loaded = false;
        }
        libxml_clear_errors();
    }

    if ($loaded && $dom !== null) {
        $textContent = (string) $dom->textContent;
        if ($textContent !== '') {
            if (preg_match_all('/[\p{L}\p{N}\']+/u', $textContent, $matches)) {
                $wordCount = count($matches[0]);
            }
        }

        $h1Count = $dom->getElementsByTagName('h1')->length ?? 0;

        foreach ($dom->getElementsByTagName('img') as $img) {
            $alt = $img->getAttribute('alt');
            if ($alt === '') {
                $missingAlt++;
            }
        }

        foreach ($dom->getElementsByTagName('a') as $anchor) {
            $href = trim($anchor->getAttribute('href'));
            if ($href === '') {
                continue;
            }
            if (preg_match('/^https?:\/\//i', $href) === 1) {
                continue;
            }
            $internalLinks++;
        }
    } else {
        $textContent = trim(strip_tags($html));
        if ($textContent !== '') {
            if (preg_match_all('/[\p{L}\p{N}\']+/u', $textContent, $matches)) {
                $wordCount = count($matches[0]);
            }
        }
    }

    libxml_use_internal_errors($libxmlPrevious);

    return [
        'word_count' => $wordCount,
        'h1_count' => $h1Count,
        'missing_alt' => $missingAlt,
        'internal_links' => $internalLinks,
    ];
};

$identifierCounts = [];

foreach ($pages as $pageIndex => $page) {
    $summary['total_pages']++;

    $title = isset($page['title']) ? (string) $page['title'] : 'Untitled';
    $slug = isset($page['slug']) ? (string) $page['slug'] : '';
    $metaTitle = trim((string) ($page['meta_title'] ?? ''));
    $metaDescription = trim((string) ($page['meta_description'] ?? ''));
    $ogTitle = trim((string) ($page['og_title'] ?? ''));
    $ogDescription = trim((string) ($page['og_description'] ?? ''));
    $ogImage = trim((string) ($page['og_image'] ?? ''));
    $content = (string) ($page['content'] ?? '');
    $contentInsights = $analyzeContent($content);
    $wordCount = (int) $contentInsights['word_count'];
    $h1Count = (int) $contentInsights['h1_count'];
    $missingAltCount = (int) $contentInsights['missing_alt'];
    $internalLinkCount = (int) $contentInsights['internal_links'];

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

    if ($wordCount > 0 && $wordCount < 150) {
        $issues[] = [
            'message' => 'Content length is very short (&lt;150 words)',
            'severity' => 'critical',
        ];
    } elseif ($wordCount >= 150 && $wordCount < 300) {
        $issues[] = [
            'message' => 'Consider expanding content to at least 300 words',
            'severity' => 'warning',
        ];
    } elseif ($wordCount === 0) {
        $issues[] = [
            'message' => 'Page content missing or empty',
            'severity' => 'critical',
        ];
    }

    if ($h1Count === 0) {
        $issues[] = [
            'message' => 'Missing H1 heading',
            'severity' => 'critical',
        ];
    } elseif ($h1Count > 1) {
        $issues[] = [
            'message' => 'Multiple H1 headings detected',
            'severity' => 'warning',
        ];
    }

    if ($missingAltCount > 0) {
        $issues[] = [
            'message' => sprintf('%d image%s missing alt text', $missingAltCount, $missingAltCount === 1 ? '' : 's'),
            'severity' => 'warning',
        ];
    }

    if ($wordCount > 0 && $internalLinkCount < 2) {
        $issues[] = [
            'message' => 'Add more internal links to related content',
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

    $identifierBase = $slug !== '' ? $slug : 'page-' . ($pageIndex + 1);
    $identifierCounts[$identifierBase] = ($identifierCounts[$identifierBase] ?? 0) + 1;
    $identifier = $identifierBase;
    if ($identifierCounts[$identifierBase] > 1) {
        $identifier .= '-' . $identifierCounts[$identifierBase];
    }

    $previousScore = derive_previous_score('seo', $identifier, $score);

    $report[] = [
        'identifier' => $identifier,
        'title' => $title,
        'slug' => $slug,
        'path' => $slug !== '' ? '/' . ltrim($slug, '/') : '/',
        'meta_title' => $metaTitle,
        'meta_title_length' => $metaTitleLength,
        'meta_title_status' => $metaTitleStatus,
        'meta_description' => $metaDescription,
        'meta_description_length' => $metaDescriptionLength,
        'meta_description_status' => $metaDescriptionStatus,
        'has_social' => $hasSocialPreview,
        'issues' => $issues,
        'slug_issues' => $slugIssues,
        'score' => $score,
        'previousScore' => $previousScore,
        'score_label' => $scoreLabel,
        'score_status' => $scoreStatus,
        'critical_count' => $criticalCount,
        'warning_count' => $warningCount,
        'word_count' => $wordCount,
        'h1_count' => $h1Count,
        'missing_alt_count' => $missingAltCount,
        'internal_link_count' => $internalLinkCount,
        'last_updated' => $formatDate($page['last_modified'] ?? null),
        'last_updated_ts' => isset($page['last_modified']) && is_numeric($page['last_modified'])
            ? (int) $page['last_modified']
            : null,
    ];
}

$averageScore = $summary['total_pages'] > 0
    ? (int) round($summary['score_sum'] / $summary['total_pages'])
    : 0;

$pageEntryMap = [];
foreach ($report as $entry) {
    $pageEntryMap[$entry['identifier']] = $entry;
}

$moduleUrl = $_SERVER['PHP_SELF'] . '?module=seo';
$detailBaseUrl = $moduleUrl . '&page=';
$detailSlug = isset($_GET['page']) ? sanitize_text($_GET['page']) : null;
$selectedPage = null;

if ($detailSlug !== null && $detailSlug !== '') {
    $selectedPage = $pageEntryMap[$detailSlug] ?? null;
}

?>
<div class="seo-dashboard" id="seo" data-total-pages="<?php echo (int) $summary['total_pages']; ?>">
    <?php if ($selectedPage): ?>
        <?php
            $issueCount = isset($selectedPage['issues']) ? count($selectedPage['issues']) : 0;
            $detailSummary = seo_score_summary($selectedPage);
            $scoreGradient = seo_score_gradient($selectedPage['score_status']);
            $scoreStatusLabel = seo_status_label($selectedPage['score_status']);
            $scoreStatusHeadline = [
                'excellent' => 'Excellent Performance',
                'good' => 'Good Performance',
                'warning' => 'Needs Attention',
                'critical' => 'Critical Risk',
            ][$selectedPage['score_status']] ?? $scoreStatusLabel;

            $lastUpdatedDisplay = $selectedPage['last_updated'] ?? '—';
            $lastUpdatedTs = $selectedPage['last_updated_ts'] ?? null;
            $ageDays = null;
            if ($lastUpdatedTs !== null) {
                $ageDays = (int) floor((time() - $lastUpdatedTs) / 86400);
            }

            $currentScore = (int) ($selectedPage['score'] ?? 0);
            $previousScore = (int) ($selectedPage['previousScore'] ?? $currentScore);
            $deltaMeta = describe_score_delta($currentScore, $previousScore);

            $quickStats = [
                ['label' => 'Issues Found', 'value' => $issueCount],
                ['label' => 'Critical Issues', 'value' => (int) $selectedPage['critical_count']],
                ['label' => 'Warnings', 'value' => (int) $selectedPage['warning_count']],
                ['label' => 'Word Count', 'value' => (int) $selectedPage['word_count']],
            ];

            $onPageMetrics = [];

            $metaTitleStatus = $selectedPage['meta_title_status'];
            $metaTitleLength = (int) $selectedPage['meta_title_length'];
            $onPageMetrics[] = [
                'icon' => 'fas fa-heading',
                'label' => 'Meta Title',
                'description' => $selectedPage['meta_title'] !== ''
                    ? sprintf('%s (%d characters)', $selectedPage['meta_title'], $metaTitleLength)
                    : 'Add a concise meta title between 30 and 60 characters.',
                'status' => $metaTitleStatus,
                'badge' => $selectedPage['meta_title'] !== ''
                    ? ($metaTitleStatus === 'good' ? 'Optimized' : 'Adjust Length')
                    : 'Missing',
                'impact' => $metaTitleStatus === 'critical' ? 'high' : ($metaTitleStatus === 'warning' ? 'medium' : 'low'),
            ];

            $metaDescriptionStatus = $selectedPage['meta_description_status'];
            $metaDescriptionLength = (int) $selectedPage['meta_description_length'];
            $onPageMetrics[] = [
                'icon' => 'fas fa-file-alt',
                'label' => 'Meta Description',
                'description' => $selectedPage['meta_description'] !== ''
                    ? sprintf('%s (%d characters)', $selectedPage['meta_description'], $metaDescriptionLength)
                    : 'Write a compelling summary between 50 and 160 characters to improve click-through rates.',
                'status' => $metaDescriptionStatus,
                'badge' => $selectedPage['meta_description'] !== ''
                    ? ($metaDescriptionStatus === 'good' ? 'Optimized' : 'Adjust Length')
                    : 'Missing',
                'impact' => $metaDescriptionStatus === 'critical' ? 'high' : ($metaDescriptionStatus === 'warning' ? 'medium' : 'low'),
            ];

            $slugStatus = empty($selectedPage['slug_issues']) ? 'good' : 'warning';
            $onPageMetrics[] = [
                'icon' => 'fas fa-link',
                'label' => 'URL Slug',
                'description' => $selectedPage['slug'] !== ''
                    ? '/' . ltrim($selectedPage['slug'], '/')
                    : 'No slug assigned to this page yet.',
                'status' => $slugStatus,
                'badge' => $slugStatus === 'good' ? 'Clean' : 'Review',
                'impact' => $slugStatus === 'good' ? 'low' : 'medium',
            ];

            $contentMetrics = [];
            $wordCount = (int) $selectedPage['word_count'];
            if ($wordCount === 0) {
                $contentStatus = 'critical';
                $contentBadge = 'No Content';
                $contentDescription = 'Add meaningful body copy to help search engines understand this page.';
            } elseif ($wordCount < 150) {
                $contentStatus = 'critical';
                $contentBadge = 'Too Thin';
                $contentDescription = sprintf('%d words detected. Expand content to at least 300 words for better coverage.', $wordCount);
            } elseif ($wordCount < 300) {
                $contentStatus = 'warning';
                $contentBadge = 'Could Improve';
                $contentDescription = sprintf('%d words detected. Consider expanding the page to deepen topical relevance.', $wordCount);
            } else {
                $contentStatus = 'good';
                $contentBadge = 'Healthy';
                $contentDescription = sprintf('%d words detected. Content depth looks solid.', $wordCount);
            }
            $contentMetrics[] = [
                'icon' => 'fas fa-align-left',
                'label' => 'Content Depth',
                'description' => $contentDescription,
                'status' => $contentStatus,
                'badge' => $contentBadge,
                'impact' => $contentStatus === 'good' ? 'medium' : 'high',
            ];

            $h1Count = (int) $selectedPage['h1_count'];
            if ($h1Count === 0) {
                $headingStatus = 'critical';
                $headingBadge = 'Missing';
                $headingDescription = 'No H1 heading found. Add a single descriptive H1 to anchor the page.';
            } elseif ($h1Count === 1) {
                $headingStatus = 'good';
                $headingBadge = 'In Place';
                $headingDescription = 'Single H1 heading detected with a clear structure.';
            } else {
                $headingStatus = 'warning';
                $headingBadge = 'Multiple';
                $headingDescription = sprintf('%d H1 headings detected. Keep only one primary H1 for clarity.', $h1Count);
            }
            $contentMetrics[] = [
                'icon' => 'fas fa-heading',
                'label' => 'Heading Structure',
                'description' => $headingDescription,
                'status' => $headingStatus,
                'badge' => $headingBadge,
                'impact' => $headingStatus === 'good' ? 'medium' : 'high',
            ];

            $missingAlt = (int) $selectedPage['missing_alt_count'];
            if ($missingAlt === 0) {
                $altStatus = 'good';
                $altBadge = 'Complete';
                $altDescription = 'All images include descriptive alternative text.';
            } elseif ($missingAlt < 5) {
                $altStatus = 'warning';
                $altBadge = 'Add Alt Text';
                $altDescription = sprintf('%d image%s missing alt text. Add short descriptive text for each image.', $missingAlt, $missingAlt === 1 ? '' : 's');
            } else {
                $altStatus = 'critical';
                $altBadge = 'Missing';
                $altDescription = sprintf('%d images missing alt text. This impacts accessibility and SEO.', $missingAlt);
            }
            $contentMetrics[] = [
                'icon' => 'fas fa-image',
                'label' => 'Image Alt Text',
                'description' => $altDescription,
                'status' => $altStatus,
                'badge' => $altBadge,
                'impact' => $altStatus === 'good' ? 'medium' : 'high',
            ];

            $internalLinks = (int) $selectedPage['internal_link_count'];
            if ($internalLinks === 0) {
                $linkStatus = 'critical';
                $linkBadge = 'Add Links';
                $linkDescription = 'No internal links found. Add links to related pages to improve crawlability.';
            } elseif ($internalLinks < 3) {
                $linkStatus = 'warning';
                $linkBadge = 'Low';
                $linkDescription = sprintf('%d internal link%s detected. Add more contextual links to strengthen site architecture.', $internalLinks, $internalLinks === 1 ? '' : 's');
            } else {
                $linkStatus = 'good';
                $linkBadge = 'Healthy';
                $linkDescription = sprintf('%d internal links detected. Linking looks balanced.', $internalLinks);
            }
            $contentMetrics[] = [
                'icon' => 'fas fa-sitemap',
                'label' => 'Internal Linking',
                'description' => $linkDescription,
                'status' => $linkStatus,
                'badge' => $linkBadge,
                'impact' => $linkStatus === 'good' ? 'medium' : 'high',
            ];

            $technicalMetrics = [];
            $technicalMetrics[] = [
                'icon' => 'fas fa-share-alt',
                'label' => 'Social Preview',
                'description' => $selectedPage['has_social']
                    ? 'OG title, description, and image detected. Social sharing is ready to go.'
                    : 'Add OG title, description, and image to control how the page appears on social platforms.',
                'status' => $selectedPage['has_social'] ? 'good' : 'warning',
                'badge' => $selectedPage['has_social'] ? 'Complete' : 'Incomplete',
                'impact' => $selectedPage['has_social'] ? 'low' : 'medium',
            ];

            if ($ageDays !== null) {
                if ($ageDays <= 90) {
                    $freshnessStatus = 'good';
                    $freshnessBadge = 'Fresh';
                    $freshnessDescription = sprintf('Updated %d day%s ago.', $ageDays, $ageDays === 1 ? '' : 's');
                } elseif ($ageDays <= 180) {
                    $freshnessStatus = 'warning';
                    $freshnessBadge = 'Stale Soon';
                    $freshnessDescription = sprintf('Updated %d days ago. Plan a refresh to keep content relevant.', $ageDays);
                } else {
                    $freshnessStatus = 'warning';
                    $freshnessBadge = 'Review';
                    $freshnessDescription = sprintf('Last updated %d days ago. Consider refreshing content and metadata.', $ageDays);
                }
            } else {
                $freshnessStatus = 'warning';
                $freshnessBadge = 'Unknown';
                $freshnessDescription = 'No last updated timestamp recorded. Track edits to monitor freshness.';
            }
            $technicalMetrics[] = [
                'icon' => 'fas fa-clock',
                'label' => 'Content Freshness',
                'description' => $freshnessDescription,
                'status' => $freshnessStatus,
                'badge' => $freshnessBadge,
                'impact' => 'medium',
            ];

            $issuesBySeverity = [
                'critical' => 0,
                'warning' => 0,
            ];
            foreach ($selectedPage['issues'] as $issue) {
                $severity = $issue['severity'] ?? 'warning';
                if (isset($issuesBySeverity[$severity])) {
                    $issuesBySeverity[$severity]++;
                }
            }
        ?>
        <style>
            .seo-detail-page {
                font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                color: #0f172a;
                display: flex;
                flex-direction: column;
                gap: 24px;
            }

            .seo-detail-page .a11y-detail-actions {
                display: flex;
                gap: 12px;
                flex-wrap: wrap;
            }

            .seo-detail-page .seo-action-primary {
                background: linear-gradient(135deg, #2563eb, #7c3aed);
                color: #fff;
                border-color: transparent;
            }

            .seo-detail-page .seo-action-primary:hover,
            .seo-detail-page .seo-action-primary:focus-visible {
                background: linear-gradient(135deg, #1d4ed8, #5b21b6);
                color: #fff;
            }

            .seo-detail-page .seo-action-secondary {
                background: #f8fafc;
                color: #1f2937;
            }

            .seo-detail-page .seo-action-secondary:hover,
            .seo-detail-page .seo-action-secondary:focus-visible {
                background: #e2e8f0;
                color: #1f2937;
            }

            .seo-detail-page .seo-health-card {
                position: relative;
                overflow: hidden;
            }

            .seo-detail-page .seo-health-card::before {
                content: '';
                position: absolute;
                top: -60px;
                right: -60px;
                width: 220px;
                height: 220px;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.18);
            }

            .seo-detail-page .a11y-health-summary {
                gap: 14px;
            }

            .seo-detail-page .seo-health-summary-text {
                font-size: 15px;
                line-height: 1.6;
                color: rgba(255, 255, 255, 0.9);
            }

            .seo-detail-page .seo-score-pill {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 6px 14px;
                border-radius: 999px;
                background: rgba(15, 23, 42, 0.25);
                font-size: 12px;
                font-weight: 600;
                letter-spacing: 0.5px;
                text-transform: uppercase;
                color: #fff;
            }

            .seo-detail-page .seo-score-pill::before {
                content: '';
                display: inline-block;
                width: 8px;
                height: 8px;
                border-radius: 999px;
                background: var(--seo-pill-accent, rgba(255, 255, 255, 0.75));
            }

            .seo-detail-page .seo-score-pill.status-excellent {
                --seo-pill-accent: #22c55e;
            }

            .seo-detail-page .seo-score-pill.status-good {
                --seo-pill-accent: #38bdf8;
            }

            .seo-detail-page .seo-score-pill.status-warning {
                --seo-pill-accent: #f59e0b;
            }

            .seo-detail-page .seo-score-pill.status-critical {
                --seo-pill-accent: #ef4444;
            }

            .seo-detail-page .seo-meta {
                display: grid;
                gap: 12px;
                margin-top: 16px;
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            }

            .seo-detail-page .seo-meta div {
                background: rgba(15, 23, 42, 0.24);
                border-radius: 12px;
                padding: 12px 16px;
                display: flex;
                flex-direction: column;
                gap: 4px;
            }

            .seo-detail-page .seo-meta span {
                font-size: 12px;
                text-transform: uppercase;
                letter-spacing: 0.45px;
                opacity: 0.85;
            }

            .seo-detail-page .seo-meta strong {
                font-size: 16px;
                font-weight: 600;
                color: #fff;
            }

            .seo-detail-page .seo-meta small {
                font-size: 12px;
                color: rgba(255, 255, 255, 0.8);
            }

            .seo-detail-page .seo-metric-list {
                display: grid;
                gap: 18px;
            }

            .seo-detail-page .seo-metric {
                display: grid;
                grid-template-columns: auto 1fr auto;
                gap: 16px;
                align-items: center;
                padding-bottom: 18px;
                border-bottom: 1px solid #e2e8f0;
            }

            .seo-detail-page .seo-metric:last-child {
                border-bottom: none;
                padding-bottom: 0;
            }

            .seo-detail-page .seo-metric-icon {
                width: 44px;
                height: 44px;
                border-radius: 12px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                font-size: 18px;
                background: #e2e8f0;
                color: #1f2937;
            }

            .seo-detail-page .seo-metric-icon.status-excellent {
                background: #dcfce7;
                color: #166534;
            }

            .seo-detail-page .seo-metric-icon.status-good {
                background: #dbeafe;
                color: #1d4ed8;
            }

            .seo-detail-page .seo-metric-icon.status-warning {
                background: #fef3c7;
                color: #b45309;
            }

            .seo-detail-page .seo-metric-icon.status-critical {
                background: #fee2e2;
                color: #b91c1c;
            }

            .seo-detail-page .seo-metric-info {
                display: flex;
                flex-direction: column;
                gap: 4px;
            }

            .seo-detail-page .seo-metric-label {
                font-weight: 600;
                color: #1e293b;
            }

            .seo-detail-page .seo-metric-description {
                color: #475569;
                font-size: 13px;
            }

            .seo-detail-page .seo-badges {
                display: flex;
                gap: 10px;
                align-items: center;
                justify-content: flex-end;
                flex-wrap: wrap;
            }

            .seo-detail-page .seo-status-badge {
                padding: 6px 12px;
                border-radius: 999px;
                font-size: 12px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .seo-detail-page .seo-status-badge.status-excellent {
                background: #dcfce7;
                color: #166534;
            }

            .seo-detail-page .seo-status-badge.status-good {
                background: #dbeafe;
                color: #1d4ed8;
            }

            .seo-detail-page .seo-status-badge.status-warning {
                background: #fef3c7;
                color: #b45309;
            }

            .seo-detail-page .seo-status-badge.status-critical {
                background: #fee2e2;
                color: #b91c1c;
            }

            .seo-detail-page .seo-impact {
                padding: 4px 10px;
                border-radius: 999px;
                font-size: 11px;
                font-weight: 600;
                letter-spacing: 0.4px;
                text-transform: uppercase;
            }

            .seo-detail-page .seo-impact.high {
                background: #fee2e2;
                color: #b91c1c;
            }

            .seo-detail-page .seo-impact.medium {
                background: #fef3c7;
                color: #92400e;
            }

            .seo-detail-page .seo-impact.low {
                background: #e0f2fe;
                color: #0369a1;
            }

            .seo-detail-page .seo-issues {
                background: #fff;
                border-radius: 16px;
                padding: 24px;
                box-shadow: 0 8px 20px rgba(15, 23, 42, 0.06);
                border: 1px solid #e2e8f0;
                display: flex;
                flex-direction: column;
                gap: 20px;
            }

            .seo-detail-page .seo-issues-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                flex-wrap: wrap;
                gap: 12px;
            }

            .seo-detail-page .seo-issues-header h2 {
                margin: 0;
                font-size: 18px;
                color: #1e293b;
            }

            .seo-detail-page .seo-issue-list {
                display: grid;
                gap: 16px;
            }

            .seo-detail-page .seo-issue-card {
                border: 1px solid #e2e8f0;
                border-radius: 14px;
                padding: 16px 18px;
                display: grid;
                gap: 10px;
            }

            .seo-detail-page .seo-issue-card.severity-critical {
                border-color: #fecaca;
                background: #fef2f2;
            }

            .seo-detail-page .seo-issue-card.severity-warning {
                border-color: #fde68a;
                background: #fffbeb;
            }

            .seo-detail-page .seo-issue-title {
                font-weight: 600;
                color: #1e293b;
            }

            .seo-detail-page .seo-issue-description {
                font-size: 13px;
                color: #475569;
            }

            .seo-detail-page .seo-issue-tag {
                justify-self: flex-start;
                padding: 4px 10px;
                border-radius: 999px;
                background: #e2e8f0;
                color: #1f2937;
                font-size: 11px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.45px;
            }

            .seo-detail-page .seo-empty {
                text-align: center;
                color: #475569;
                display: grid;
                gap: 12px;
                padding: 40px 20px;
            }

            .seo-detail-page .seo-empty i {
                font-size: 40px;
                color: #10b981;
            }

            @media (max-width: 768px) {
                .seo-detail-page .a11y-health-card {
                    grid-template-columns: 1fr;
                }

                .seo-detail-page .seo-meta {
                    grid-template-columns: 1fr;
                }

                .seo-detail-page .seo-badges {
                    justify-content: flex-start;
                }
            }
        </style>
        <div class="a11y-detail-page seo-detail-page" id="seoDetailPage" data-page-slug="<?php echo htmlspecialchars($selectedPage['identifier'], ENT_QUOTES, 'UTF-8'); ?>">
            <header class="a11y-detail-header">
                <a href="<?php echo htmlspecialchars($moduleUrl, ENT_QUOTES, 'UTF-8'); ?>" class="a11y-back-link">
                    <i class="fas fa-arrow-left" aria-hidden="true"></i>
                    <span>Back to SEO Overview</span>
                </a>
                <div class="a11y-detail-actions">
                    <button type="button" class="a11y-detail-btn seo-action-primary" data-seo-action="rescan-page">
                        <i class="fas fa-rotate" aria-hidden="true"></i>
                        <span>Rescan page</span>
                    </button>
                    <a class="a11y-detail-btn seo-action-secondary" href="<?php echo htmlspecialchars($selectedPage['path'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
                        <i class="fas fa-external-link-alt" aria-hidden="true"></i>
                        <span>View live page</span>
                    </a>
                </div>
            </header>

            <section class="a11y-health-card seo-health-card" style="background: <?php echo htmlspecialchars($scoreGradient, ENT_QUOTES, 'UTF-8'); ?>;">
                <div class="a11y-health-score">
                    <div class="score-indicator score-indicator--hero">
                        <div class="a11y-health-score__value">
                            <span class="score-indicator__number"><?php echo $currentScore; ?></span><span>%</span>
                        </div>
                        <span class="score-delta <?php echo htmlspecialchars($deltaMeta['class'], ENT_QUOTES, 'UTF-8'); ?>">
                            <span aria-hidden="true"><?php echo htmlspecialchars($deltaMeta['display'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="sr-only"><?php echo htmlspecialchars($deltaMeta['srText'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </span>
                    </div>
                    <div class="a11y-health-score__label">SEO Score</div>
                    <span class="seo-score-pill status-<?php echo htmlspecialchars($selectedPage['score_status'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($scoreStatusHeadline, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="a11y-health-summary">
                    <h1><?php echo htmlspecialchars($selectedPage['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
                    <p class="a11y-health-url"><?php echo htmlspecialchars($selectedPage['slug'] !== '' ? '/' . ltrim($selectedPage['slug'], '/') : 'No slug assigned', ENT_QUOTES, 'UTF-8'); ?></p>
                    <p class="seo-health-summary-text"><?php echo htmlspecialchars($detailSummary, ENT_QUOTES, 'UTF-8'); ?></p>
                    <div class="a11y-quick-stats">
                        <?php foreach ($quickStats as $stat): ?>
                            <div class="a11y-quick-stat">
                                <div class="a11y-quick-stat__value"><?php echo htmlspecialchars((string) $stat['value'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="a11y-quick-stat__label"><?php echo htmlspecialchars($stat['label'], ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="seo-meta">
                        <div>
                            <span>Score status</span>
                            <strong><?php echo htmlspecialchars($scoreStatusLabel, ENT_QUOTES, 'UTF-8'); ?></strong>
                            <small><?php echo htmlspecialchars($scoreStatusHeadline, ENT_QUOTES, 'UTF-8'); ?></small>
                        </div>
                        <div>
                            <span>Last updated</span>
                            <strong><?php echo htmlspecialchars($lastUpdatedDisplay, ENT_QUOTES, 'UTF-8'); ?></strong>
                            <?php if ($ageDays !== null): ?>
                                <small><?php echo $ageDays === 0 ? 'Updated today' : 'Updated ' . $ageDays . ' day' . ($ageDays === 1 ? '' : 's') . ' ago'; ?></small>
                            <?php endif; ?>
                        </div>
                        <div>
                            <span>Social preview</span>
                            <strong><?php echo $selectedPage['has_social'] ? 'Complete' : 'Missing details'; ?></strong>
                            <small><?php echo $selectedPage['has_social'] ? 'OG tags ready for sharing.' : 'Add OG title, description, and image.'; ?></small>
                        </div>
                    </div>
                </div>
            </section>

            <section class="a11y-detail-grid">
                <?php if (!empty($onPageMetrics)): ?>
                    <article class="a11y-detail-card">
                        <h2>On-page SEO essentials</h2>
                        <div class="seo-metric-list">
                            <?php foreach ($onPageMetrics as $metric): ?>
                                <div class="seo-metric">
                                    <div class="seo-metric-icon status-<?php echo htmlspecialchars($metric['status'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <i class="<?php echo htmlspecialchars($metric['icon'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
                                    </div>
                                    <div class="seo-metric-info">
                                        <div class="seo-metric-label"><?php echo htmlspecialchars($metric['label'], ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div class="seo-metric-description"><?php echo htmlspecialchars($metric['description'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    </div>
                                    <div class="seo-badges">
                                        <span class="seo-status-badge status-<?php echo htmlspecialchars($metric['status'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($metric['badge'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php if (!empty($metric['impact'])): ?>
                                            <span class="seo-impact <?php echo htmlspecialchars($metric['impact'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo strtoupper(htmlspecialchars($metric['impact'], ENT_QUOTES, 'UTF-8')); ?> Impact</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </article>
                <?php endif; ?>

                <?php if (!empty($contentMetrics)): ?>
                    <article class="a11y-detail-card">
                        <h2>Content &amp; structure</h2>
                        <div class="seo-metric-list">
                            <?php foreach ($contentMetrics as $metric): ?>
                                <div class="seo-metric">
                                    <div class="seo-metric-icon status-<?php echo htmlspecialchars($metric['status'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <i class="<?php echo htmlspecialchars($metric['icon'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
                                    </div>
                                    <div class="seo-metric-info">
                                        <div class="seo-metric-label"><?php echo htmlspecialchars($metric['label'], ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div class="seo-metric-description"><?php echo htmlspecialchars($metric['description'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    </div>
                                    <div class="seo-badges">
                                        <span class="seo-status-badge status-<?php echo htmlspecialchars($metric['status'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($metric['badge'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php if (!empty($metric['impact'])): ?>
                                            <span class="seo-impact <?php echo htmlspecialchars($metric['impact'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo strtoupper(htmlspecialchars($metric['impact'], ENT_QUOTES, 'UTF-8')); ?> Impact</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </article>
                <?php endif; ?>

                <?php if (!empty($technicalMetrics)): ?>
                    <article class="a11y-detail-card">
                        <h2>Technical SEO</h2>
                        <div class="seo-metric-list">
                            <?php foreach ($technicalMetrics as $metric): ?>
                                <div class="seo-metric">
                                    <div class="seo-metric-icon status-<?php echo htmlspecialchars($metric['status'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <i class="<?php echo htmlspecialchars($metric['icon'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
                                    </div>
                                    <div class="seo-metric-info">
                                        <div class="seo-metric-label"><?php echo htmlspecialchars($metric['label'], ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div class="seo-metric-description"><?php echo htmlspecialchars($metric['description'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    </div>
                                    <div class="seo-badges">
                                        <span class="seo-status-badge status-<?php echo htmlspecialchars($metric['status'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($metric['badge'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php if (!empty($metric['impact'])): ?>
                                            <span class="seo-impact <?php echo htmlspecialchars($metric['impact'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo strtoupper(htmlspecialchars($metric['impact'], ENT_QUOTES, 'UTF-8')); ?> Impact</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </article>
                <?php endif; ?>
            </section>

            <section class="seo-issues">
                <div class="seo-issues-header">
                    <h2>Priority issues &amp; recommendations</h2>
                    <span class="seo-status-badge status-<?php echo $issueCount === 0 ? 'excellent' : ($issuesBySeverity['critical'] > 0 ? 'critical' : 'warning'); ?>"><?php echo htmlspecialchars($issueCount . ' ' . seo_pluralize($issueCount, 'issue'), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <?php if ($issueCount === 0): ?>
                    <div class="seo-empty">
                        <i class="fas fa-check-circle" aria-hidden="true"></i>
                        <p>Great work! Keep monitoring this page for future improvements.</p>
                    </div>
                <?php else: ?>
                    <div class="seo-issue-list">
                        <?php foreach ($selectedPage['issues'] as $issue): ?>
                            <?php $severity = $issue['severity'] ?? 'warning'; ?>
                            <article class="seo-issue-card severity-<?php echo htmlspecialchars($severity, ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="seo-issue-title"><?php echo htmlspecialchars($issue['message'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="seo-issue-description"><?php echo $severity === 'critical' ? 'Resolve this immediately to avoid search visibility losses.' : 'Review and fix to strengthen overall optimization.'; ?></div>
                                <span class="seo-issue-tag"><?php echo htmlspecialchars(seo_status_label($severity), ENT_QUOTES, 'UTF-8'); ?></span>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    <?php else: ?>
    <style>
        .seo-dashboard {
            font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: #0f172a;
        }
        .seo-dashboard .seo-hero {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: #fff;
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }
        .seo-dashboard .seo-hero::after {
            content: "";
            position: absolute;
            inset: auto -60px -90px auto;
            width: 260px;
            height: 260px;
            background: rgba(255,255,255,0.12);
            border-radius: 50%;
        }
        .seo-dashboard .seo-hero-content {
            position: relative;
            z-index: 1;
        }
        .seo-dashboard .seo-hero-title {
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
        .seo-dashboard .seo-sort {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1 1 220px;
        }
        .seo-dashboard .seo-sort label {
            font-size: 13px;
            font-weight: 600;
            color: #1f2937;
            white-space: nowrap;
        }
        .seo-dashboard .seo-sort select {
            flex: 1 1 auto;
            border-radius: 12px;
            border: 1px solid #dbeafe;
            padding: 10px 14px;
            font-size: 13px;
            background: #f8fafc;
            color: #1f2937;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .seo-dashboard .seo-sort select:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }
        .seo-dashboard .seo-sort-status {
            font-size: 12px;
            color: #475569;
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
            width: 64px;
            height: 64px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 600;
            font-size: 18px;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.15);
        }
        .seo-dashboard .seo-card-score.score-excellent { background: linear-gradient(135deg, #22c55e, #16a34a); }
        .seo-dashboard .seo-card-score.score-good { background: linear-gradient(135deg, #3b82f6, #2563eb); }
        .seo-dashboard .seo-card-score.score-warning { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .seo-dashboard .seo-card-score.score-critical { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .seo-dashboard .seo-card-meta {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 16px;
            align-items: flex-start;
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
        .seo-dashboard .seo-card-actions {
            margin-top: 16px;
            display: flex;
            justify-content: flex-end;
        }
        .seo-dashboard .seo-card-action-link {
            font-size: 13px;
            font-weight: 600;
            color: #4f46e5;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .seo-dashboard .seo-card-action-link:hover {
            text-decoration: underline;
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
        .seo-dashboard .seo-table-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #4f46e5;
            font-weight: 600;
            font-size: 12px;
        }
        .seo-dashboard .seo-table-link:hover {
            text-decoration: underline;
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
        .seo-dashboard .seo-status-text {
            font-size: 12px;
            font-weight: 500;
            text-transform: none;
            letter-spacing: 0;
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
            padding: 24px;
            z-index: 1050;
        }
        .seo-dashboard .seo-detail-overlay.active {
            display: flex;
        }
        .seo-dashboard .seo-detail-panel {
            background: #fff;
            border-radius: 24px;
            box-shadow: 0 30px 80px rgba(15, 23, 42, 0.22);
            width: min(960px, 100%);
            max-height: 92vh;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .seo-dashboard .seo-detail-close {
            position: absolute;
            top: 18px;
            right: 18px;
            border: none;
            background: rgba(15, 23, 42, 0.08);
            color: #0f172a;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.2s ease, transform 0.2s ease;
            z-index: 2;
        }
        .seo-dashboard .seo-detail-close:hover,
        .seo-dashboard .seo-detail-close:focus-visible {
            background: rgba(15, 23, 42, 0.14);
            transform: translateY(-1px);
            color: #111827;
        }
        .seo-dashboard .seo-detail-close i {
            font-size: 16px;
        }
        .seo-dashboard .seo-detail-scroll {
            padding: 36px 36px 28px;
            overflow-y: auto;
        }
        .seo-dashboard .seo-detail-hero {
            position: relative;
            border-radius: 20px;
            padding: 36px;
            color: #fff;
            margin-bottom: 28px;
            overflow: hidden;
            background: linear-gradient(135deg, #6366f1, #4338ca);
        }
        .seo-dashboard .seo-detail-hero::after {
            content: "";
            position: absolute;
            inset: auto -80px -80px auto;
            width: 260px;
            height: 260px;
            background: rgba(255, 255, 255, 0.12);
            border-radius: 50%;
        }
        .seo-dashboard .seo-detail-hero.status-excellent { background: linear-gradient(135deg, #10b981, #059669); }
        .seo-dashboard .seo-detail-hero.status-good { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
        .seo-dashboard .seo-detail-hero.status-warning { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .seo-dashboard .seo-detail-hero.status-critical { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .seo-dashboard .seo-detail-hero-layout {
            position: relative;
            z-index: 1;
            display: flex;
            flex-wrap: wrap;
            gap: 28px;
            align-items: flex-start;
        }
        .seo-dashboard .seo-detail-score-block {
            min-width: 180px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .seo-dashboard .seo-detail-score-value {
            font-size: 60px;
            font-weight: 700;
            line-height: 1;
            display: flex;
            align-items: baseline;
            gap: 6px;
        }
        .seo-dashboard .seo-detail-score-suffix {
            font-size: 18px;
            font-weight: 600;
            opacity: 0.85;
        }
        .seo-dashboard .seo-detail-score-delta {
            min-height: 28px;
            display: flex;
            align-items: center;
        }
        .seo-dashboard .seo-detail-score-delta .score-delta {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 600;
            background: rgba(15, 23, 42, 0.25);
            color: rgba(255, 255, 255, 0.92);
        }
        .seo-dashboard .seo-detail-score-delta .score-delta--up {
            background: rgba(34, 197, 94, 0.18);
            color: #dcfce7;
        }
        .seo-dashboard .seo-detail-score-delta .score-delta--down {
            background: rgba(239, 68, 68, 0.18);
            color: #fee2e2;
        }
        .seo-dashboard .seo-detail-score-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            background: rgba(15, 23, 42, 0.25);
        }
        .seo-dashboard .seo-detail-hero-content {
            flex: 1;
            min-width: 280px;
            display: grid;
            gap: 12px;
        }
        .seo-dashboard .seo-detail-hero-content h2 {
            font-size: 26px;
            font-weight: 700;
        }
        .seo-dashboard .seo-detail-url {
            font-family: "JetBrains Mono", "Fira Code", monospace;
            font-size: 13px;
            color: rgba(255, 255, 255, 0.82);
        }
        .seo-dashboard .seo-detail-summary {
            font-size: 15px;
            line-height: 1.7;
            color: rgba(255, 255, 255, 0.92);
            max-width: 640px;
        }
        .seo-dashboard .seo-detail-quick-stats {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        }
        .seo-dashboard .seo-detail-quick-stat {
            background: rgba(15, 23, 42, 0.25);
            border-radius: 14px;
            padding: 14px 16px;
        }
        .seo-dashboard .seo-detail-quick-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.45px;
            color: rgba(255, 255, 255, 0.75);
            display: block;
            margin-bottom: 6px;
        }
        .seo-dashboard .seo-detail-quick-value {
            font-size: 16px;
            font-weight: 600;
            color: #fff;
            display: block;
        }
        .seo-dashboard .seo-detail-section {
            margin-bottom: 32px;
        }
        .seo-dashboard .seo-detail-section h3 {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 18px;
        }
        .seo-dashboard .seo-detail-card-grid {
            display: grid;
            gap: 18px;
        }
        @media (min-width: 768px) {
            .seo-dashboard .seo-detail-card-grid {
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            }
        }
        .seo-dashboard .seo-detail-card {
            background: #fff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            padding: 20px;
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.05);
            display: grid;
            gap: 10px;
        }
        .seo-dashboard .seo-detail-card h4 {
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #475569;
        }
        .seo-dashboard .seo-detail-card p {
            font-size: 14px;
            color: #0f172a;
            line-height: 1.6;
        }
        .seo-dashboard .seo-detail-card-hint {
            font-size: 12px;
            color: #64748b;
        }
        .seo-dashboard .seo-detail-section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }
        .seo-dashboard .seo-detail-issue-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.45px;
            text-transform: uppercase;
            background: #e2e8f0;
            color: #0f172a;
        }
        .seo-dashboard .seo-detail-issue-badge.status-good { background: #dcfce7; color: #166534; }
        .seo-dashboard .seo-detail-issue-badge.status-warning { background: #fef3c7; color: #92400e; }
        .seo-dashboard .seo-detail-issue-badge.status-critical { background: #fee2e2; color: #991b1b; }
        .seo-dashboard .seo-detail-issue-grid {
            display: grid;
            gap: 16px;
        }
        .seo-dashboard .seo-detail-issue {
            border-radius: 18px;
            padding: 20px;
            border: 1px solid #e2e8f0;
            background: #fff;
            box-shadow: 0 16px 30px rgba(15, 23, 42, 0.08);
        }
        .seo-dashboard .seo-detail-issue.severity-critical {
            border-color: #fecaca;
            background: #fef2f2;
        }
        .seo-dashboard .seo-detail-issue.severity-warning {
            border-color: #fde68a;
            background: #fffbeb;
        }
        .seo-dashboard .seo-detail-issue.severity-good {
            border-color: #cbd5f5;
            background: #f8fafc;
        }
        .seo-dashboard .seo-detail-issue-title {
            font-weight: 600;
            font-size: 15px;
            color: #0f172a;
            margin-bottom: 8px;
        }
        .seo-dashboard .seo-detail-issue-text {
            font-size: 13px;
            color: #475569;
            margin-bottom: 12px;
        }
        .seo-dashboard .seo-detail-issue-tag {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.45px;
            background: rgba(15, 23, 42, 0.08);
            color: #0f172a;
        }
        .seo-dashboard .seo-detail-empty {
            text-align: center;
            padding: 48px 20px;
            border-radius: 18px;
            border: 1px dashed #cbd5f5;
            color: #475569;
            background: #f8fafc;
            display: grid;
            gap: 12px;
            justify-items: center;
        }
        .seo-dashboard .seo-detail-empty i {
            font-size: 42px;
            color: #10b981;
        }
        .seo-dashboard .seo-empty {
            background: #fff;
            border-radius: 16px;
            padding: 48px;
            text-align: center;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
        }
    </style>

    <header class="a11y-hero seo-hero">
        <div class="a11y-hero-content seo-hero-content">
            <div>
                <h1 class="a11y-hero-title seo-hero-title">SEO Dashboard</h1>
                <p class="a11y-hero-subtitle seo-lead">Monitor SEO health across your published pages. Track metadata quality, spot urgent issues, and drill into page-level recommendations.</p>
            </div>
            <div class="a11y-hero-actions seo-hero-actions">
                <span class="a11y-hero-meta seo-hero-meta">
                    <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
                    Real-time insights from your published content
                </span>
            </div>
        </div>
        <div class="a11y-overview-grid seo-overview">
            <div class="a11y-overview-card seo-overview-card">
                <div class="a11y-overview-label seo-overview-label">Total Pages</div>
                <div class="a11y-overview-value seo-overview-value"><?php echo (int) $summary['total_pages']; ?></div>
            </div>
            <div class="a11y-overview-card seo-overview-card">
                <div class="a11y-overview-label seo-overview-label">Average Score</div>
                <div class="a11y-overview-value seo-overview-value"><?php echo (int) $averageScore; ?></div>
            </div>
            <div class="a11y-overview-card seo-overview-card">
                <div class="a11y-overview-label seo-overview-label">Pages Needing Attention</div>
                <div class="a11y-overview-value seo-overview-value"><?php echo (int) $summary['attention_pages']; ?></div>
            </div>
            <div class="a11y-overview-card seo-overview-card">
                <div class="a11y-overview-label seo-overview-label">Metadata Gaps</div>
                <div class="a11y-overview-value seo-overview-value"><?php echo (int) $summary['metadata_gaps']; ?></div>
            </div>
        </div>
    </header>

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
        <div class="seo-sort" role="group" aria-label="Sort SEO results">
            <label for="seoSortSelect">Sort by</label>
            <select id="seoSortSelect">
                <option value="score-desc">Score: High to Low</option>
                <option value="score-asc">Score: Low to High</option>
                <option value="updated-desc">Last Updated: Newest First</option>
                <option value="title-asc">Title: A to Z</option>
            </select>
            <span class="seo-sort-status" id="seoSortStatus" aria-live="polite">Sorted by Highest score</span>
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
                    $scoreValue = (int) $entry['score'];
                    $previousScore = (int) ($entry['previousScore'] ?? $scoreValue);
                    $deltaMeta = describe_score_delta($scoreValue, $previousScore);

                    $data = [
                        'title' => $entry['title'],
                        'slug' => $entry['slug'],
                        'score' => $entry['score'],
                        'previousScore' => $entry['previousScore'],
                        'scoreLabel' => $entry['score_label'],
                        'scoreStatus' => $entry['score_status'],
                        'scoreStatusLabel' => seo_status_label($entry['score_status']),
                        'summary' => seo_score_summary($entry),
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
                        'wordCount' => $entry['word_count'],
                        'h1Count' => $entry['h1_count'],
                        'missingAltCount' => $entry['missing_alt_count'],
                        'internalLinkCount' => $entry['internal_link_count'],
                    ];
                    $jsonData = htmlspecialchars(json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
                ?>
                <article class="seo-card" data-status="<?php echo htmlspecialchars($entry['score_status'], ENT_QUOTES, 'UTF-8'); ?>" data-search="<?php echo htmlspecialchars(strtolower($entry['title'] . ' ' . $entry['slug'] . ' ' . $entry['meta_title']), ENT_QUOTES, 'UTF-8'); ?>" data-score="<?php echo (int) $entry['score']; ?>" data-title="<?php echo htmlspecialchars($entry['title'], ENT_QUOTES, 'UTF-8'); ?>" data-updated="<?php echo $entry['last_updated_ts'] !== null ? (int) $entry['last_updated_ts'] : 0; ?>" data-page="<?php echo $jsonData; ?>">
                    <div class="seo-card-meta">
                        <div class="score-indicator score-indicator--card">
                            <div class="seo-card-score score-<?php echo htmlspecialchars($entry['score_status'], ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="score-indicator__number"><?php echo $scoreValue; ?></span>
                            </div>
                            <span class="score-delta <?php echo htmlspecialchars($deltaMeta['class'], ENT_QUOTES, 'UTF-8'); ?>">
                                <span aria-hidden="true"><?php echo htmlspecialchars($deltaMeta['display'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="sr-only"><?php echo htmlspecialchars($deltaMeta['srText'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </span>
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
                    <div class="seo-card-actions">
                        <a class="seo-card-action-link seo-open-detail-page" href="<?php echo htmlspecialchars($detailBaseUrl . rawurlencode($entry['identifier']), ENT_QUOTES, 'UTF-8'); ?>">
                            View full report
                            <i class="fa-solid fa-arrow-right"></i>
                        </a>
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
                            $scoreValue = (int) $entry['score'];
                            $previousScore = (int) ($entry['previousScore'] ?? $scoreValue);
                            $deltaMeta = describe_score_delta($scoreValue, $previousScore);

                            $data = [
                                'title' => $entry['title'],
                                'slug' => $entry['slug'],
                                'score' => $entry['score'],
                                'previousScore' => $entry['previousScore'],
                                'scoreLabel' => $entry['score_label'],
                                'scoreStatus' => $entry['score_status'],
                                'scoreStatusLabel' => seo_status_label($entry['score_status']),
                                'summary' => seo_score_summary($entry),
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
                                'wordCount' => $entry['word_count'],
                                'h1Count' => $entry['h1_count'],
                                'missingAltCount' => $entry['missing_alt_count'],
                                'internalLinkCount' => $entry['internal_link_count'],
                            ];
                            $jsonData = htmlspecialchars(json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
                        ?>
                        <tr data-status="<?php echo htmlspecialchars($entry['score_status'], ENT_QUOTES, 'UTF-8'); ?>" data-search="<?php echo htmlspecialchars(strtolower($entry['title'] . ' ' . $entry['slug'] . ' ' . $entry['meta_title']), ENT_QUOTES, 'UTF-8'); ?>" data-score="<?php echo (int) $entry['score']; ?>" data-title="<?php echo htmlspecialchars($entry['title'], ENT_QUOTES, 'UTF-8'); ?>" data-updated="<?php echo $entry['last_updated_ts'] !== null ? (int) $entry['last_updated_ts'] : 0; ?>" data-page="<?php echo $jsonData; ?>">
                            <td>
                                <div class="seo-card-title" style="margin-bottom: 4px; font-size: 16px;">
                                    <?php echo htmlspecialchars($entry['title']); ?>
                                </div>
                                <div class="seo-card-url" style="margin-bottom: 0;">/<?php echo htmlspecialchars($entry['slug']); ?></div>
                                <div>
                                    <a class="seo-table-link seo-open-detail-page" href="<?php echo htmlspecialchars($detailBaseUrl . rawurlencode($entry['identifier']), ENT_QUOTES, 'UTF-8'); ?>">
                                        View full report
                                        <i class="fa-solid fa-arrow-right"></i>
                                    </a>
                                </div>
                            </td>
                            <td>
                                <div class="score-indicator score-indicator--table">
                                    <span class="seo-status-badge <?php echo htmlspecialchars($entry['score_status']); ?>">
                                        <span class="score-indicator__number"><?php echo $scoreValue; ?></span>
                                        <span class="seo-status-text">&bull; <?php echo htmlspecialchars($entry['score_label']); ?></span>
                                    </span>
                                    <span class="score-delta <?php echo htmlspecialchars($deltaMeta['class'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <span aria-hidden="true"><?php echo htmlspecialchars($deltaMeta['display'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span class="sr-only"><?php echo htmlspecialchars($deltaMeta['srText'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    </span>
                                </div>
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
        <div class="seo-detail-panel" role="document">
            <button type="button" class="seo-detail-close" aria-label="Close details">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
            <div class="seo-detail-scroll">
                <section class="seo-detail-hero" data-detail="hero">
                    <div class="seo-detail-hero-layout">
                        <div class="seo-detail-score-block">
                            <div class="seo-detail-score-value">
                                <span data-detail="score-value"></span>
                                <span class="seo-detail-score-suffix">/100</span>
                            </div>
                            <div class="seo-detail-score-delta" data-detail="score-delta"></div>
                            <span class="seo-detail-score-badge" data-detail="score-status-label"></span>
                        </div>
                        <div class="seo-detail-hero-content">
                            <h2 data-detail="title"></h2>
                            <p class="seo-detail-url" data-detail="url"></p>
                            <p class="seo-detail-summary" data-detail="summary"></p>
                            <div class="seo-detail-quick-stats">
                                <div class="seo-detail-quick-stat">
                                    <span class="seo-detail-quick-label">Performance</span>
                                    <span class="seo-detail-quick-value" data-detail="score-label"></span>
                                </div>
                                <div class="seo-detail-quick-stat">
                                    <span class="seo-detail-quick-label">Last updated</span>
                                    <span class="seo-detail-quick-value" data-detail="last-updated"></span>
                                </div>
                                <div class="seo-detail-quick-stat">
                                    <span class="seo-detail-quick-label">Critical issues</span>
                                    <span class="seo-detail-quick-value" data-detail="critical-count"></span>
                                </div>
                                <div class="seo-detail-quick-stat">
                                    <span class="seo-detail-quick-label">Warnings</span>
                                    <span class="seo-detail-quick-value" data-detail="warning-count"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
                <section class="seo-detail-section">
                    <h3>Metadata essentials</h3>
                    <div class="seo-detail-card-grid">
                        <article class="seo-detail-card">
                            <h4>Meta title</h4>
                            <p data-detail="meta-title"></p>
                            <span class="seo-detail-card-hint" data-detail="meta-title-length"></span>
                        </article>
                        <article class="seo-detail-card">
                            <h4>Meta description</h4>
                            <p data-detail="meta-description"></p>
                            <span class="seo-detail-card-hint" data-detail="meta-description-length"></span>
                        </article>
                        <article class="seo-detail-card">
                            <h4>Social preview</h4>
                            <p data-detail="social-status"></p>
                        </article>
                    </div>
                </section>
                <section class="seo-detail-section">
                    <h3>Content insights</h3>
                    <div class="seo-detail-card-grid">
                        <article class="seo-detail-card">
                            <h4>Word count</h4>
                            <p data-detail="word-count"></p>
                        </article>
                        <article class="seo-detail-card">
                            <h4>Heading structure</h4>
                            <p data-detail="heading-status"></p>
                        </article>
                        <article class="seo-detail-card">
                            <h4>Image alt text</h4>
                            <p data-detail="image-alt-status"></p>
                        </article>
                        <article class="seo-detail-card">
                            <h4>Internal links</h4>
                            <p data-detail="internal-link-status"></p>
                        </article>
                    </div>
                </section>
                <section class="seo-detail-section">
                    <div class="seo-detail-section-header">
                        <h3>Priority issues &amp; recommendations</h3>
                        <span class="seo-detail-issue-badge" data-detail="issue-count"></span>
                    </div>
                    <div class="seo-detail-issue-grid" data-detail="issues"></div>
                </section>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
