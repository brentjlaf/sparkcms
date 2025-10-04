<?php
require_once __DIR__ . '/../../includes/template_renderer.php';

class SeoReport
{
    /** @var array<int, array<string, mixed>> */
    private array $pages;

    /** @var array<string, mixed> */
    private array $settings;

    /** @var array<int, mixed> */
    private array $menus;

    private string $scriptBase;

    private ?string $templateDir;

    /** @var callable|null */
    private $previousScoreResolver;

    private string $lastScan;

    private string $siteHost;

    /**
     * @param array<int, array<string, mixed>> $pages
     * @param array<string, mixed> $settings
     * @param array<int, mixed> $menus
     * @param callable|null $previousScoreResolver function (string $identifier, int $score): int
     */
    public function __construct(
        array $pages,
        array $settings,
        array $menus,
        string $scriptBase,
        ?string $templateDir,
        ?callable $previousScoreResolver = null,
        ?string $lastScan = null
    ) {
        $this->pages = array_values(array_filter($pages, 'is_array'));
        $this->settings = $settings;
        $this->menus = $menus;
        $this->scriptBase = rtrim($scriptBase, '/');
        $this->templateDir = $templateDir ?: null;
        $this->previousScoreResolver = $previousScoreResolver;
        $this->lastScan = $lastScan ?? date('M j, Y g:i A');

        $siteUrl = (string)($settings['site_url'] ?? ($settings['siteUrl'] ?? ''));
        $host = parse_url($siteUrl, PHP_URL_HOST);
        $this->siteHost = is_string($host) ? strtolower($host) : '';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function loadPages(string $filePath): array
    {
        $pages = read_json_file($filePath);
        if (!is_array($pages)) {
            return [];
        }

        return array_values(array_filter($pages, 'is_array'));
    }

    /**
     * @return array{pages: array<int, array<string, mixed>>, pageMap: array<string, array<string, mixed>>, stats: array<string, mixed>}
     */
    public function generateReport(): array
    {
        libxml_use_internal_errors(true);

        $analysis = [];
        foreach ($this->pages as $page) {
            $analysis[] = $this->analysePage($page);
        }

        libxml_clear_errors();

        return $this->compileReport($analysis);
    }

    /**
     * @param array<string, mixed> $page
     * @return array<string, mixed>
     */
    private function analysePage(array $page): array
    {
        $title = (string)($page['title'] ?? 'Untitled');
        $slug = (string)($page['slug'] ?? '');
        $template = (string)($page['template'] ?? '');
        $storedMetaTitle = trim((string)($page['meta_title'] ?? ($page['metaTitle'] ?? $title)));
        $storedMetaDescription = trim((string)($page['meta_description'] ?? ($page['metaDescription'] ?? '')));
        $storedMetaRobots = strtolower(trim((string)($page['meta_robots'] ?? ($page['metaRobots'] ?? ''))));

        $pageHtml = cms_build_page_html($page, $this->settings, $this->menus, $this->scriptBase, $this->templateDir);

        $doc = new DOMDocument();
        $loaded = trim($pageHtml) !== '' && $doc->loadHTML('<?xml encoding="utf-8" ?>' . $pageHtml);

        $metadata = [
            'titleTag' => '',
            'metaDescription' => '',
            'canonical' => '',
            'robots' => '',
            'openGraphCount' => 0,
            'structuredDataCount' => 0,
            'openGraphProperties' => [],
        ];

        $metrics = [
            'wordCount' => 0,
            'headings' => [
                'h1' => 0,
                'h2' => 0,
                'h3' => 0,
            ],
            'images' => 0,
            'missingAlt' => 0,
            'internalLinks' => 0,
            'externalLinks' => 0,
        ];

        if ($loaded) {
            $titleNodes = $doc->getElementsByTagName('title');
            if ($titleNodes->length > 0) {
                $metadata['titleTag'] = trim((string)$titleNodes->item(0)?->textContent);
            }

            foreach ($doc->getElementsByTagName('meta') as $meta) {
                $name = strtolower((string)$meta->getAttribute('name'));
                $property = strtolower((string)$meta->getAttribute('property'));
                $content = trim((string)$meta->getAttribute('content'));
                if ($name === 'description' && $content !== '') {
                    $metadata['metaDescription'] = $content;
                }
                if ($name === 'robots' && $content !== '') {
                    $metadata['robots'] = strtolower($content);
                }
                if (str_starts_with($property, 'og:') && $content !== '') {
                    $metadata['openGraphCount']++;
                    $metadata['openGraphProperties'][] = $property;
                }
            }

            foreach ($doc->getElementsByTagName('link') as $link) {
                $rel = strtolower((string)$link->getAttribute('rel'));
                if ($rel === 'canonical') {
                    $href = trim((string)$link->getAttribute('href'));
                    if ($href !== '') {
                        $metadata['canonical'] = $href;
                    }
                }
            }

            foreach ($doc->getElementsByTagName('script') as $script) {
                $type = strtolower((string)$script->getAttribute('type'));
                if ($type === 'application/ld+json') {
                    $metadata['structuredDataCount']++;
                }
            }

            $bodyNodes = $doc->getElementsByTagName('body');
            if ($bodyNodes->length > 0) {
                $bodyText = trim(preg_replace('/\s+/', ' ', (string)$bodyNodes->item(0)?->textContent));
                if ($bodyText !== '') {
                    $metadata['wordSample'] = substr($bodyText, 0, 120);
                    $metrics['wordCount'] = str_word_count(strip_tags($bodyText));
                }
            }

            $metrics['headings']['h1'] = $doc->getElementsByTagName('h1')->length;
            $metrics['headings']['h2'] = $doc->getElementsByTagName('h2')->length;
            $metrics['headings']['h3'] = $doc->getElementsByTagName('h3')->length;

            $images = $doc->getElementsByTagName('img');
            foreach ($images as $img) {
                $metrics['images']++;
                $alt = trim((string)$img->getAttribute('alt'));
                if ($alt === '') {
                    $metrics['missingAlt']++;
                }
            }

            $anchors = $doc->getElementsByTagName('a');
            foreach ($anchors as $anchor) {
                $href = trim((string)$anchor->getAttribute('href'));
                if ($href === '' || str_starts_with($href, 'javascript:')) {
                    continue;
                }
                if ($this->isInternalLink($href)) {
                    $metrics['internalLinks']++;
                } else {
                    $metrics['externalLinks']++;
                }
            }
        }

        return [
            'title' => $title,
            'slug' => $slug,
            'template' => $template,
            'metadata' => $metadata,
            'storedMeta' => [
                'title' => $storedMetaTitle,
                'description' => $storedMetaDescription,
                'robots' => $storedMetaRobots,
            ],
            'metrics' => $metrics,
        ];
    }

    private function isInternalLink(string $href): bool
    {
        if ($href === '' || str_starts_with($href, '#')) {
            return true;
        }

        if (str_starts_with($href, '/')) {
            return true;
        }

        $host = parse_url($href, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return false;
        }

        if ($this->siteHost === '') {
            return false;
        }

        return strtolower($host) === $this->siteHost;
    }

    /**
     * @param array<int, array<string, mixed>> $analysis
     * @return array{pages: array<int, array<string, mixed>>, pageMap: array<string, array<string, mixed>>, stats: array<string, mixed>}
     */
    private function compileReport(array $analysis): array
    {
        $totalPages = count($analysis);
        $filterCounts = [
            'all' => $totalPages,
            'critical' => 0,
            'opportunity' => 0,
            'optimized' => 0,
            'onTrack' => 0,
        ];

        $criticalIssues = 0;
        $optimizedPages = 0;
        $scoreSum = 0;
        $pageEntries = [];
        $pageEntryMap = [];

        foreach ($analysis as $index => $entry) {
            $slug = (string)$entry['slug'];
            $path = '/' . ltrim($slug, '/');
            $metadata = $entry['metadata'];
            $storedMeta = $entry['storedMeta'];
            $metrics = $entry['metrics'];

            $issues = [];
            $impactWeights = [
                'critical' => 24,
                'high' => 15,
                'medium' => 7,
                'low' => 4,
            ];

            $resolvedTitle = $metadata['titleTag'] !== '' ? $metadata['titleTag'] : $storedMeta['title'];
            $resolvedDescription = $metadata['metaDescription'] !== '' ? $metadata['metaDescription'] : $storedMeta['description'];
            $resolvedRobots = $metadata['robots'] !== '' ? $metadata['robots'] : $storedMeta['robots'];

            $titleFallback = $metadata['titleTag'] === '' && $resolvedTitle !== '';
            $descriptionFallback = $metadata['metaDescription'] === '' && $resolvedDescription !== '';
            $robotsFallback = $metadata['robots'] === '' && $resolvedRobots !== '';

            $addIssue = static function (string $impact, string $description, string $recommendation, array &$list) {
                $list[] = [
                    'impact' => $impact,
                    'description' => $description,
                    'recommendation' => $recommendation,
                ];
            };

            if ($resolvedRobots !== '' && str_contains($resolvedRobots, 'noindex')) {
                $addIssue('critical', 'Robots directives prevent indexing', 'Remove unintended noindex directives so the page can appear in search results.', $issues);
            }

            if ($metadata['titleTag'] === '') {
                $addIssue('high', 'Missing <title> tag in rendered HTML', 'Add a descriptive, unique <title> tag to clarify the page focus for search engines.', $issues);
            }

            if ($metadata['metaDescription'] === '') {
                $addIssue('medium', 'Meta description missing from output', 'Provide a compelling meta description to improve click-through rates.', $issues);
            }

            if ($metadata['canonical'] === '') {
                $addIssue('low', 'Canonical URL not declared', 'Add a canonical link to signal the preferred URL and consolidate ranking signals.', $issues);
            }

            if ($metadata['openGraphCount'] === 0) {
                $addIssue('low', 'Open Graph tags not detected', 'Add Open Graph metadata so shared links render rich previews on social platforms.', $issues);
            }

            if ($metadata['structuredDataCount'] === 0) {
                $addIssue('medium', 'Structured data missing', 'Implement Schema.org structured data to unlock rich results and enhance visibility.', $issues);
            }

            if ($metrics['wordCount'] < 250) {
                $addIssue('medium', 'Content depth is thin', 'Expand the on-page copy to at least 250 words to cover the topic comprehensively.', $issues);
            }

            if ($metrics['headings']['h1'] === 0) {
                $addIssue('medium', 'Primary heading (H1) missing', 'Ensure the page includes a single H1 headline that matches search intent.', $issues);
            }

            if ($metrics['internalLinks'] < 2) {
                $addIssue('medium', 'Internal links are limited', 'Add contextual links to related pages to strengthen internal linking.', $issues);
            }

            if ($metrics['missingAlt'] > 0) {
                $addIssue('medium', 'Images missing alt text', 'Provide descriptive alt attributes so images reinforce topical relevance and accessibility.', $issues);
            }

            $issuePreview = array_slice(array_map(static function ($issue) {
                return $issue['description'];
            }, $issues), 0, 4);
            if (empty($issuePreview)) {
                $issuePreview = ['No outstanding SEO issues detected'];
            }

            $score = 100;
            foreach ($issues as $issue) {
                $impact = $issue['impact'];
                $score -= $impactWeights[$impact] ?? 5;
            }
            if (empty($issues)) {
                $score = 99;
            }
            $score = max(0, min(100, $score));

            $previousScore = $this->resolvePreviousScore($slug, (string)$entry['title'], $index, $score);

            $optimizationLevel = $this->determineOptimizationLevel($score, $issues);
            $statusMessage = $this->describeOptimizationLevel($optimizationLevel);
            $summaryLine = $this->summariseIssues($score, $issues);

            $issueCounts = [
                'critical' => 0,
                'high' => 0,
                'medium' => 0,
                'low' => 0,
            ];
            foreach ($issues as $issue) {
                $impact = $issue['impact'];
                if (!isset($issueCounts[$impact])) {
                    $issueCounts[$impact] = 0;
                }
                $issueCounts[$impact]++;
            }

            $criticalIssues += $issueCounts['critical'] + $issueCounts['high'];
            $scoreSum += $score;

            if ($optimizationLevel === 'Optimized') {
                $optimizedPages++;
                $filterCounts['optimized']++;
            } elseif ($optimizationLevel === 'On Track') {
                $filterCounts['onTrack']++;
            } elseif ($optimizationLevel === 'Needs Work') {
                $filterCounts['opportunity']++;
            } else {
                $filterCounts['critical']++;
            }

            $pageData = [
                'title' => $entry['title'],
                'slug' => $slug,
                'url' => $path,
                'path' => $path,
                'template' => $entry['template'],
                'seoScore' => $score,
                'previousScore' => $previousScore,
                'optimizationLevel' => $optimizationLevel,
                'statusMessage' => $statusMessage,
                'summaryLine' => $summaryLine,
                'lastScanned' => $this->lastScan,
                'issues' => [
                    'preview' => $issuePreview,
                    'details' => $issues,
                    'counts' => $issueCounts,
                ],
                'metadata' => [
                    'titleTag' => $resolvedTitle,
                    'titleFallback' => $titleFallback,
                    'metaDescription' => $resolvedDescription,
                    'descriptionFallback' => $descriptionFallback,
                    'canonical' => $metadata['canonical'],
                    'robots' => $resolvedRobots,
                    'robotsFallback' => $robotsFallback,
                    'openGraphCount' => $metadata['openGraphCount'],
                    'structuredDataCount' => $metadata['structuredDataCount'],
                ],
                'metrics' => [
                    'wordCount' => $metrics['wordCount'],
                    'headings' => $metrics['headings'],
                    'images' => $metrics['images'],
                    'missingAlt' => $metrics['missingAlt'],
                    'internalLinks' => $metrics['internalLinks'],
                    'externalLinks' => $metrics['externalLinks'],
                ],
                'quickStats' => [
                    'wordCount' => $metrics['wordCount'],
                    'internalLinks' => $metrics['internalLinks'],
                    'h1' => $metrics['headings']['h1'],
                    'missingAlt' => $metrics['missingAlt'],
                ],
            ];

            $pageEntries[] = $pageData;
            $pageEntryMap[$slug] = $pageData;
        }

        $avgScore = $totalPages > 0 ? (int)round($scoreSum / $totalPages) : 0;

        $stats = [
            'totalPages' => $totalPages,
            'avgScore' => $avgScore,
            'criticalIssues' => $criticalIssues,
            'optimizedPages' => $optimizedPages,
            'filterCounts' => $filterCounts,
            'lastScan' => $this->lastScan,
        ];

        return [
            'pages' => $pageEntries,
            'pageMap' => $pageEntryMap,
            'stats' => $stats,
        ];
    }

    /**
     * @param array<int, array{impact: string, description: string, recommendation: string}> $issues
     */
    private function determineOptimizationLevel(int $score, array $issues): string
    {
        $criticalCount = 0;
        $highCount = 0;
        foreach ($issues as $issue) {
            if (($issue['impact'] ?? '') === 'critical') {
                $criticalCount++;
            }
            if (($issue['impact'] ?? '') === 'high') {
                $highCount++;
            }
        }

        if ($score >= 90 && $criticalCount === 0 && $highCount === 0) {
            return 'Optimized';
        }

        if ($score >= 75 && $criticalCount === 0) {
            return 'On Track';
        }

        if ($score >= 50) {
            return 'Needs Work';
        }

        return 'Critical';
    }

    private function describeOptimizationLevel(string $level): string
    {
        return match ($level) {
            'Optimized' => 'This page is fully optimized with only minor opportunities left.',
            'On Track' => 'Solid fundamentals are in place; address remaining items to reach full optimization.',
            'Needs Work' => 'Key SEO enhancements remain. Prioritize metadata, content depth, and linking updates.',
            default => 'Critical SEO blockers detected. Fix these items immediately to regain visibility.',
        };
    }

    /**
     * @param array<int, array{impact: string, description: string}> $issues
     */
    private function summariseIssues(int $score, array $issues): string
    {
        if (empty($issues)) {
            return sprintf('SEO score at %d%% with no outstanding issues.', $score);
        }

        $counts = [
            'critical' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
        ];
        foreach ($issues as $issue) {
            $impact = strtolower((string)($issue['impact'] ?? ''));
            if (isset($counts[$impact])) {
                $counts[$impact]++;
            }
        }

        $parts = [];
        if ($counts['critical'] > 0) {
            $parts[] = $counts['critical'] . ' critical';
        }
        if ($counts['high'] > 0) {
            $parts[] = $counts['high'] . ' high-impact';
        }
        if ($counts['medium'] > 0) {
            $parts[] = $counts['medium'] . ' medium';
        }
        if ($counts['low'] > 0) {
            $parts[] = $counts['low'] . ' low';
        }

        $issueSummary = implode(', ', $parts);
        if ($issueSummary === '') {
            $issueSummary = 'low-impact';
        }

        $issueCount = count($issues);
        return sprintf('SEO score at %d%% with %s issue%s remaining.', $score, $issueSummary, $issueCount === 1 ? '' : 's');
    }

    private function resolvePreviousScore(string $slug, string $title, int $index, int $score): int
    {
        if ($this->previousScoreResolver) {
            return (int)call_user_func($this->previousScoreResolver, $slug !== '' ? $slug : ($title !== '' ? $title : (string)$index), $score);
        }

        if (function_exists('derive_previous_score')) {
            $identifier = $slug !== '' ? $slug : ($title !== '' ? $title : (string)$index);
            return (int)derive_previous_score('seo', $identifier, $score);
        }

        return $score;
    }
}
