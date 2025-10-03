<?php
require_once __DIR__ . '/../../includes/template_renderer.php';
require_once __DIR__ . '/../../includes/reporting_helpers.php';

class SpeedReport
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

    /** @var array{totalPages: float|null, avgScore: float|null, criticalAlerts: float|null, slowPages: float|null} */
    private array $previousSnapshot;

    private string $scanTimestamp;

    private int $snapshotTimestamp;

    /**
     * @param array<int, array<string, mixed>> $pages
     * @param array<string, mixed> $settings
     * @param array<int, mixed> $menus
     * @param callable|null $previousScoreResolver function (string $identifier, int $score): int
     * @param array<string, mixed>|null $previousSnapshot
     */
    public function __construct(
        array $pages,
        array $settings,
        array $menus,
        string $scriptBase,
        ?string $templateDir,
        ?callable $previousScoreResolver = null,
        ?array $previousSnapshot = null,
        ?string $scanTimestamp = null
    ) {
        $this->pages = array_values(array_filter($pages, 'is_array'));
        $this->settings = $settings;
        $this->menus = $menus;
        $this->scriptBase = rtrim($scriptBase, '/');
        $this->templateDir = $templateDir ?: null;
        $this->previousScoreResolver = $previousScoreResolver;
        $this->scanTimestamp = $scanTimestamp ?? date('M j, Y g:i A');
        $this->snapshotTimestamp = time();
        $this->previousSnapshot = [
            'totalPages' => isset($previousSnapshot['totalPages']) ? (float) $previousSnapshot['totalPages'] : null,
            'avgScore' => isset($previousSnapshot['avgScore']) ? (float) $previousSnapshot['avgScore'] : null,
            'criticalAlerts' => isset($previousSnapshot['criticalAlerts']) ? (float) $previousSnapshot['criticalAlerts'] : null,
            'slowPages' => isset($previousSnapshot['slowPages']) ? (float) $previousSnapshot['slowPages'] : null,
        ];
    }

    /**
     * Load pages from a JSON source.
     *
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
     * @return array{pages: array<int, array<string, mixed>>, pageMap: array<string, array<string, mixed>>, stats: array<string, mixed>, snapshot: array<string, mixed>}
     */
    public function generateReport(): array
    {
        libxml_use_internal_errors(true);

        $report = [];
        $pageMap = [];

        $totalPages = 0;
        $scoreSum = 0;
        $criticalAlertsTotal = 0;
        $fastPages = 0;
        $slowPages = 0;
        $filterCounts = [
            'all' => 0,
            'slow' => 0,
            'monitor' => 0,
            'fast' => 0,
        ];
        $heaviestPage = null;
        $aggregateAlerts = [
            'critical' => 0,
            'serious' => 0,
            'moderate' => 0,
            'minor' => 0,
        ];

        foreach ($this->pages as $index => $page) {
            $entry = $this->analysePage($page, $index);
            $report[] = $entry;

            $slug = (string) ($entry['slug'] ?? '');
            if ($slug !== '') {
                $pageMap[$slug] = $entry;
            }

            $totalPages++;
            $scoreSum += (int) ($entry['performanceScore'] ?? 0);
            $alerts = $entry['alerts'] ?? ['critical' => 0, 'serious' => 0, 'moderate' => 0, 'minor' => 0, 'total' => 0];
            $criticalAlertsTotal += (int) ($alerts['critical'] ?? 0);

            foreach ($aggregateAlerts as $impact => $count) {
                $aggregateAlerts[$impact] += (int) ($alerts[$impact] ?? 0);
            }

            $category = $entry['performanceCategory'] ?? 'monitor';
            if (isset($filterCounts[$category])) {
                $filterCounts[$category]++;
            }
            $filterCounts['all']++;

            if ($category === 'fast') {
                $fastPages++;
            } elseif ($category === 'slow') {
                $slowPages++;
            }

            $metrics = $entry['metrics'] ?? [];
            $weight = isset($metrics['weightKb']) ? (float) $metrics['weightKb'] : 0.0;
            if ($heaviestPage === null || $weight > (float) ($heaviestPage['weight'] ?? 0.0)) {
                $heaviestPage = [
                    'title' => $entry['title'] ?? 'Untitled',
                    'slug' => $slug,
                    'weight' => $weight,
                    'url' => $entry['url'] ?? '',
                ];
            }
        }

        libxml_clear_errors();

        $avgScore = $totalPages > 0 ? (int) round($scoreSum / $totalPages) : 0;
        $aggregateAlerts['total'] = array_sum($aggregateAlerts);

        $stats = [
            'totalPages' => $totalPages,
            'avgScore' => $avgScore,
            'criticalAlerts' => $criticalAlertsTotal,
            'fastPages' => $fastPages,
            'slowPages' => $slowPages,
            'filterCounts' => $filterCounts,
            'lastScan' => $this->scanTimestamp,
            'heaviestPage' => $heaviestPage,
            'aggregateAlerts' => $aggregateAlerts,
            'alertsSummary' => self::summariseAlerts($aggregateAlerts),
        ];

        $stats['deltas'] = [
            'totalPages' => report_calculate_change((float) $totalPages, $this->previousSnapshot['totalPages']),
            'avgScore' => report_calculate_change((float) $avgScore, $this->previousSnapshot['avgScore']),
            'criticalAlerts' => report_calculate_change((float) $criticalAlertsTotal, $this->previousSnapshot['criticalAlerts']),
            'slowPages' => report_calculate_change((float) $slowPages, $this->previousSnapshot['slowPages']),
        ];

        $snapshot = [
            'timestamp' => $this->snapshotTimestamp,
            'totalPages' => $totalPages,
            'avgScore' => $avgScore,
            'criticalAlerts' => $criticalAlertsTotal,
            'slowPages' => $slowPages,
        ];

        return [
            'pages' => $report,
            'pageMap' => $pageMap,
            'stats' => $stats,
            'snapshot' => $snapshot,
        ];
    }

    /**
     * Map performance grade to score indicator class.
     */
    public static function mapGradeToScoreClass(string $grade): string
    {
        switch (strtoupper($grade)) {
            case 'A':
                return 'speed-score--a';
            case 'B':
                return 'speed-score--b';
            case 'C':
                return 'speed-score--c';
            default:
                return 'speed-score--d';
        }
    }

    /**
     * Map performance grade to badge class.
     */
    public static function mapGradeToBadgeClass(string $grade): string
    {
        switch (strtoupper($grade)) {
            case 'A':
                return 'grade-a';
            case 'B':
                return 'grade-b';
            case 'C':
                return 'grade-c';
            default:
                return 'grade-d';
        }
    }

    /**
     * @param array<string, mixed> $page
     *
     * @return array<string, mixed>
     */
    private function analysePage(array $page, int $index): array
    {
        $title = (string) ($page['title'] ?? 'Untitled');
        $slug = (string) ($page['slug'] ?? '');
        $path = '/' . ltrim($slug, '/');

        $pageHtml = cms_build_page_html($page, $this->settings, $this->menus, $this->scriptBase, $this->templateDir);
        $doc = new DOMDocument();
        $loaded = trim($pageHtml) !== '' && $doc->loadHTML('<?xml encoding="utf-8" ?>' . $pageHtml);

        $htmlBytes = strlen($pageHtml);
        $htmlSizeKb = round($htmlBytes / 1024, 1);
        $wordCount = str_word_count(strip_tags($pageHtml));

        $imageCount = 0;
        $scriptCount = 0;
        $inlineScriptCount = 0;
        $stylesheetCount = 0;
        $inlineStyleBlocks = 0;
        $iframeCount = 0;
        $domNodes = 0;

        if ($loaded) {
            $domNodes = $doc->getElementsByTagName('*')->length;

            $images = $doc->getElementsByTagName('img');
            $imageCount = $images->length;

            $scripts = $doc->getElementsByTagName('script');
            foreach ($scripts as $script) {
                $scriptCount++;
                if (!$script->hasAttribute('src')) {
                    $inlineScriptCount++;
                }
            }

            $links = $doc->getElementsByTagName('link');
            foreach ($links as $link) {
                if (strtolower($link->getAttribute('rel')) === 'stylesheet') {
                    $stylesheetCount++;
                }
            }

            $inlineStyleBlocks = $doc->getElementsByTagName('style')->length;
            $iframeCount = $doc->getElementsByTagName('iframe')->length;
        } else {
            $domNodes = max(0, substr_count($pageHtml, '<'));
            $lowerHtml = strtolower($pageHtml);
            $imageCount = substr_count($lowerHtml, '<img');
            $scriptCount = substr_count($lowerHtml, '<script');
            $stylesheetCount = substr_count($lowerHtml, 'rel="stylesheet"');
            $inlineScriptCount = max(0, $scriptCount - substr_count($lowerHtml, 'src='));
            $inlineStyleBlocks = substr_count($lowerHtml, '<style');
            $iframeCount = substr_count($lowerHtml, '<iframe');
        }

        $estimatedWeightKb = round($htmlSizeKb + ($imageCount * 45) + ($scriptCount * 12) + ($stylesheetCount * 8), 1);
        $avgImageWeight = $imageCount > 0 ? round($estimatedWeightKb / $imageCount, 1) : 0.0;

        $issues = [];
        $addIssue = static function (array &$issues, string $impact, string $description, string $recommendation): void {
            $issues[] = [
                'impact' => $impact,
                'description' => $description,
                'recommendation' => $recommendation,
            ];
        };

        if ($estimatedWeightKb > 900) {
            $addIssue($issues, 'critical', 'Estimated page weight exceeds 900 KB', 'Compress large assets, enable caching, and consider splitting content across lighter templates.');
        } elseif ($estimatedWeightKb > 600) {
            $addIssue($issues, 'serious', 'Estimated page weight above 600 KB', 'Minify HTML, compress imagery, and lazy-load non-critical resources.');
        } elseif ($estimatedWeightKb > 400) {
            $addIssue($issues, 'moderate', 'Estimated page weight above 400 KB', 'Audit media assets and remove unused scripts or styles to reduce payload.');
        }

        if ($imageCount > 15) {
            $addIssue($issues, 'serious', $imageCount . ' images detected', 'Use responsive image sizes, next-gen formats, and defer offscreen assets.');
        } elseif ($imageCount > 9) {
            $addIssue($issues, 'moderate', $imageCount . ' images detected', 'Review gallery content and apply lazy loading for below-the-fold imagery.');
        }

        if ($scriptCount > 7) {
            $addIssue($issues, 'serious', $scriptCount . ' scripts included', 'Bundle and defer non-critical JavaScript to shorten the main thread.');
        } elseif ($scriptCount > 4) {
            $addIssue($issues, 'moderate', $scriptCount . ' scripts included', 'Audit third-party embeds and remove unused libraries to improve speed.');
        }

        if ($stylesheetCount + $inlineStyleBlocks > 6) {
            $addIssue($issues, 'minor', 'Multiple blocking stylesheets detected', 'Combine styles where possible and inline only the critical CSS.');
        }

        if ($inlineScriptCount > 0) {
            $addIssue($issues, 'minor', $inlineScriptCount . ' inline script block(s)', 'Move inline logic into external files to improve caching and diagnostics.');
        }

        if ($domNodes > 1500) {
            $addIssue($issues, 'moderate', 'Large DOM tree with ' . $domNodes . ' nodes', 'Simplify nested layouts and remove unnecessary wrappers to speed up rendering.');
        } elseif ($domNodes > 1000) {
            $addIssue($issues, 'minor', 'DOM tree approaching heavy threshold (' . $domNodes . ' nodes)', 'Consider breaking long pages into sections and trimming unused markup.');
        }

        if ($iframeCount > 0) {
            $addIssue($issues, 'minor', $iframeCount . ' embedded frame(s)', 'Lazy-load embedded media or replace with preview placeholders to reduce startup cost.');
        }

        $alerts = $this->countAlerts($issues);

        $score = $this->calculateScore($estimatedWeightKb, $imageCount, $scriptCount, $stylesheetCount, $inlineStyleBlocks, $domNodes, $inlineScriptCount, $alerts);
        $grade = $this->mapScoreToGrade($score);

        $performanceCategory = $this->determineCategory($score, $alerts, $grade);

        $identifier = $slug !== '' ? $slug : ($title !== '' ? $title : ('page-' . $index));
        $previousScore = $this->resolvePreviousScore($identifier, $score);

        $issuePreview = array_slice(array_map(static function (array $detail): string {
            return (string) $detail['description'];
        }, $issues), 0, 4);

        if (empty($issuePreview)) {
            $issuePreview = ['No outstanding alerts'];
        }

        $alerts['total'] = array_sum([
            (int) $alerts['critical'],
            (int) $alerts['serious'],
            (int) $alerts['moderate'],
            (int) $alerts['minor'],
        ]);

        $template = (string) ($page['template'] ?? '');

        return [
            'title' => $title,
            'slug' => $slug,
            'url' => $path,
            'path' => $path,
            'template' => $template,
            'performanceScore' => $score,
            'previousScore' => $previousScore,
            'grade' => $grade,
            'gradeClass' => self::mapGradeToBadgeClass($grade),
            'scoreClass' => self::mapGradeToScoreClass($grade),
            'alerts' => $alerts,
            'alertsSummary' => self::summariseAlerts($alerts),
            'warnings' => (int) $alerts['serious'] + (int) $alerts['moderate'] + (int) $alerts['minor'],
            'lastScanned' => $this->scanTimestamp,
            'pageType' => $template !== '' ? 'Template: ' . basename($template) : 'Standard Page',
            'performanceCategory' => $performanceCategory,
            'issues' => [
                'preview' => $issuePreview,
                'details' => $issues,
            ],
            'metrics' => [
                'weightKb' => $estimatedWeightKb,
                'htmlSizeKb' => $htmlSizeKb,
                'imageCount' => $imageCount,
                'scriptCount' => $scriptCount,
                'stylesheetCount' => $stylesheetCount,
                'inlineScripts' => $inlineScriptCount,
                'inlineStyles' => $inlineStyleBlocks,
                'domNodes' => $domNodes,
                'wordCount' => $wordCount,
                'avgImageWeight' => $avgImageWeight,
                'iframeCount' => $iframeCount,
            ],
            'statusMessage' => self::describePerformanceGrade($grade),
            'summaryLine' => sprintf('Performance score %d%%. %s.', $score, self::summariseAlerts($alerts)),
        ];
    }

    /**
     * @param array<int, array{impact: string}> $issues
     * @return array{critical: int, serious: int, moderate: int, minor: int}
     */
    private function countAlerts(array $issues): array
    {
        $counts = [
            'critical' => 0,
            'serious' => 0,
            'moderate' => 0,
            'minor' => 0,
        ];

        foreach ($issues as $issue) {
            $impact = $issue['impact'] ?? 'minor';
            if (!isset($counts[$impact])) {
                $impact = 'minor';
            }
            $counts[$impact]++;
        }

        return $counts;
    }

    private function calculateScore(float $weight, int $images, int $scripts, int $stylesheets, int $inlineStyles, int $domNodes, int $inlineScripts, array $alerts): int
    {
        $score = 100;
        $score -= max(0, $weight - 300) * 0.08;
        $score -= max(0, $images - 8) * 1.5;
        $score -= max(0, $scripts - 5) * 2.5;
        $score -= max(0, ($stylesheets + $inlineStyles) - 4) * 1.2;
        $score -= max(0, $domNodes - 900) * 0.02;
        $score -= $inlineScripts * 0.5;

        $score = (int) round($score);
        $totalAlerts = array_sum([
            (int) ($alerts['critical'] ?? 0),
            (int) ($alerts['serious'] ?? 0),
            (int) ($alerts['moderate'] ?? 0),
            (int) ($alerts['minor'] ?? 0),
        ]);

        if ($totalAlerts === 0 && $score > 96) {
            $score = 98;
        }

        if ($score < 0) {
            return 0;
        }

        if ($score > 100) {
            return 100;
        }

        return $score;
    }

    private function mapScoreToGrade(int $score): string
    {
        if ($score >= 90) {
            return 'A';
        }
        if ($score >= 80) {
            return 'B';
        }
        if ($score >= 70) {
            return 'C';
        }

        return 'D';
    }

    private function determineCategory(int $score, array $alerts, string $grade): string
    {
        if (($alerts['critical'] ?? 0) > 0 || $score < 70) {
            return 'slow';
        }

        if ($score < 90 || ($alerts['serious'] ?? 0) > 0 || $grade === 'C') {
            return 'monitor';
        }

        return 'fast';
    }

    private function resolvePreviousScore(string $identifier, int $score): int
    {
        if ($this->previousScoreResolver) {
            return (int) call_user_func($this->previousScoreResolver, $identifier, $score);
        }

        return $score;
    }

    private static function describePerformanceGrade(string $grade): string
    {
        switch (strtoupper($grade)) {
            case 'A':
                return 'This page is highly optimized and should feel fast for most visitors.';
            case 'B':
                return 'Overall performance is strong with a few opportunities for speed gains.';
            case 'C':
                return 'This page needs optimization to avoid noticeable slowdowns during peak traffic.';
            default:
                return 'Heavy assets or blocking scripts are likely to cause a slow experience. Prioritize fixes soon.';
        }
    }

    /**
     * @param array{critical: int, serious: int, moderate: int, minor: int, total?: int} $alerts
     */
    private static function summariseAlerts(array $alerts): string
    {
        $parts = [];
        if (!empty($alerts['critical'])) {
            $parts[] = $alerts['critical'] . ' critical';
        }
        if (!empty($alerts['serious'])) {
            $parts[] = $alerts['serious'] . ' major';
        }
        if (!empty($alerts['moderate'])) {
            $parts[] = $alerts['moderate'] . ' moderate';
        }
        if (!empty($alerts['minor'])) {
            $parts[] = $alerts['minor'] . ' minor';
        }

        if (empty($parts)) {
            return 'No outstanding alerts detected';
        }

        $total = isset($alerts['total']) ? (int) $alerts['total'] : array_sum([
            (int) $alerts['critical'],
            (int) $alerts['serious'],
            (int) $alerts['moderate'],
            (int) $alerts['minor'],
        ]);

        return $total . ' total (' . implode(', ', $parts) . ')';
    }
}
