<?php
require_once __DIR__ . '/../../includes/template_renderer.php';

class AccessibilityReport
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

    /** @var string[] */
    private array $genericLinkTerms;

    private string $lastScan;

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
        $this->genericLinkTerms = [
            'click here',
            'read more',
            'learn more',
            'here',
            'more',
            'this page',
        ];
        $this->lastScan = $lastScan ?? date('M j, Y g:i A');
    }

    /**
     * Load and normalise pages from a JSON data file.
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

    public function getLastScan(): string
    {
        return $this->lastScan;
    }

    /**
     * Generate the accessibility report for all pages.
     *
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

        $pageHtml = cms_build_page_html($page, $this->settings, $this->menus, $this->scriptBase, $this->templateDir);

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
            $imageCount = $this->countImages($doc, $missingAlt);
            $headings['h1'] = $doc->getElementsByTagName('h1')->length;
            $headings['h2'] = $doc->getElementsByTagName('h2')->length;
            $genericLinks = $this->countGenericLinks($doc);
            $landmarks = $this->countLandmarks($doc);
        }

        $issues = $this->summariseIssues($missingAlt, $headings['h1'], $genericLinks, $landmarks);

        return [
            'title' => $title,
            'slug' => $slug,
            'template' => $template,
            'image_count' => $imageCount,
            'missing_alt' => $missingAlt,
            'headings' => $headings,
            'generic_links' => $genericLinks,
            'landmarks' => $landmarks,
            'issues' => $issues,
        ];
    }

    private function countImages(DOMDocument $doc, int &$missingAlt): int
    {
        $images = $doc->getElementsByTagName('img');
        $missingAlt = 0;
        foreach ($images as $img) {
            $alt = trim((string)$img->getAttribute('alt'));
            if ($alt === '') {
                $missingAlt++;
            }
        }

        return $images->length;
    }

    private function countGenericLinks(DOMDocument $doc): int
    {
        $genericLinks = 0;
        $anchors = $doc->getElementsByTagName('a');
        foreach ($anchors as $anchor) {
            $text = strtolower(trim((string)$anchor->textContent));
            if ($text === '') {
                continue;
            }
            foreach ($this->genericLinkTerms as $term) {
                if ($text === $term) {
                    $genericLinks++;
                    break;
                }
            }
        }

        return $genericLinks;
    }

    private function countLandmarks(DOMDocument $doc): int
    {
        $landmarkTags = ['main', 'nav', 'header', 'footer'];
        $landmarks = 0;
        foreach ($landmarkTags as $tag) {
            $landmarks += $doc->getElementsByTagName($tag)->length;
        }

        return $landmarks;
    }

    /**
     * @return string[]
     */
    private function summariseIssues(int $missingAlt, int $h1Count, int $genericLinks, int $landmarks): array
    {
        $issues = [];

        if ($missingAlt > 0) {
            $issues[] = sprintf('%d image%s missing alt text', $missingAlt, $missingAlt === 1 ? ' is' : 's are');
        }

        if ($h1Count === 0) {
            $issues[] = 'No H1 heading found';
        } elseif ($h1Count > 1) {
            $issues[] = 'Multiple H1 headings detected';
        }

        if ($genericLinks > 0) {
            $issues[] = sprintf('%d link%s use generic text', $genericLinks, $genericLinks === 1 ? '' : 's');
        }

        if ($landmarks === 0) {
            $issues[] = 'Add landmark elements (main, nav, header, footer)';
        }

        return $issues;
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
            'failing' => 0,
            'partial' => 0,
            'compliant' => 0,
        ];
        $criticalIssues = 0;
        $aaCompliant = 0;
        $scoreSum = 0;
        $pageEntries = [];
        $pageEntryMap = [];

        foreach ($analysis as $index => $entry) {
            $slug = (string)$entry['slug'];
            $path = '/' . ltrim($slug, '/');
            $critical = (int)$entry['missing_alt'];
            $serious = ($entry['headings']['h1'] === 0 || $entry['headings']['h1'] > 1) ? 1 : 0;
            $moderate = $entry['generic_links'] > 0 ? 1 : 0;
            $minor = $entry['landmarks'] === 0 ? 1 : 0;
            $violationsTotal = $critical + $serious + $moderate + $minor;

            $warnings = ($entry['generic_links'] > 0 ? $entry['generic_links'] : 0) + ($entry['landmarks'] === 0 ? 1 : 0);

            $score = 100;
            $score -= $critical * 15;
            $score -= $serious * 12;
            $score -= $moderate * 8;
            $score -= $minor * 5;
            if ($violationsTotal === 0) {
                $score = 98;
            }
            $score = max(0, min(100, $score));

            if ($violationsTotal === 0) {
                $wcagLevel = 'AAA';
            } elseif ($critical === 0 && $serious <= 1 && $score >= 80) {
                $wcagLevel = 'AA';
            } elseif ($score >= 60) {
                $wcagLevel = 'Partial';
            } else {
                $wcagLevel = 'Failing';
            }

            if (in_array($wcagLevel, ['AA', 'AAA'], true)) {
                $aaCompliant++;
                $filterCounts['compliant']++;
            }

            if ($wcagLevel === 'Partial') {
                $filterCounts['partial']++;
            }

            if ($wcagLevel === 'Failing' || $critical > 0 || $score < 60) {
                $filterCounts['failing']++;
            }

            $criticalIssues += $critical;
            $scoreSum += $score;

            $issueDetails = [];
            foreach ($entry['issues'] as $issueText) {
                $detail = $this->classifyIssueDetail($issueText);
                $issueDetails[] = [
                    'description' => $issueText,
                    'impact' => $detail['impact'],
                    'recommendation' => $detail['recommendation'],
                ];
            }

            $issuePreview = array_slice(array_map(static function ($detail) {
                return $detail['description'];
            }, $issueDetails), 0, 4);

            if (empty($issuePreview)) {
                $issuePreview = ['No outstanding issues'];
            }

            $violations = [
                'critical' => $critical,
                'serious' => $serious,
                'moderate' => $moderate,
                'minor' => $minor,
                'total' => $violationsTotal,
            ];

            $previousScore = $this->resolvePreviousScore($slug, (string)$entry['title'], $index, $score);

            $pageData = [
                'title' => $entry['title'],
                'slug' => $slug,
                'url' => $path,
                'path' => $path,
                'template' => $entry['template'],
                'accessibilityScore' => $score,
                'previousScore' => $previousScore,
                'wcagLevel' => $wcagLevel,
                'violations' => $violations,
                'warnings' => $warnings,
                'lastScanned' => $this->lastScan,
                'pageType' => !empty($entry['template']) ? 'Template: ' . basename((string)$entry['template']) : 'Standard Page',
                'compliance' => $wcagLevel === 'Failing' ? 'Failing' : ($wcagLevel === 'Partial' ? 'Needs Work' : 'Compliant'),
                'issues' => [
                    'preview' => $issuePreview,
                    'details' => $issueDetails,
                ],
                'metrics' => [
                    'images' => $entry['image_count'],
                    'missingAlt' => $entry['missing_alt'],
                    'headings' => $entry['headings'],
                    'genericLinks' => $entry['generic_links'],
                    'landmarks' => $entry['landmarks'],
                ],
            ];

            $pageData['statusMessage'] = $this->describeWcagLevel($wcagLevel);
            $pageData['summaryLine'] = sprintf(
                'Current accessibility score: %d%%. %s.',
                $score,
                $this->summariseViolations($violations)
            );

            $pageEntries[] = $pageData;
            $pageEntryMap[$slug] = $pageData;
        }

        $avgScore = $totalPages > 0 ? (int)round($scoreSum / $totalPages) : 0;

        $stats = [
            'totalPages' => $totalPages,
            'avgScore' => $avgScore,
            'criticalIssues' => $criticalIssues,
            'aaCompliant' => $aaCompliant,
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
     * @param array<string, int> $violations
     */
    private function summariseViolations(array $violations): string
    {
        $parts = [];
        if (!empty($violations['critical'])) {
            $parts[] = $violations['critical'] . ' critical';
        }
        if (!empty($violations['serious'])) {
            $parts[] = $violations['serious'] . ' serious';
        }
        if (!empty($violations['moderate'])) {
            $parts[] = $violations['moderate'] . ' moderate';
        }
        if (!empty($violations['minor'])) {
            $parts[] = $violations['minor'] . ' minor';
        }

        if (empty($parts)) {
            return 'No outstanding violations detected';
        }

        return implode(', ', $parts) . ' issue' . ($violations['total'] === 1 ? '' : 's');
    }

    /**
     * @return array{impact: string, recommendation: string}
     */
    private function classifyIssueDetail(string $issue): array
    {
        $lower = strtolower($issue);

        if (strpos($lower, 'alt') !== false) {
            return [
                'impact' => 'critical',
                'recommendation' => 'Provide descriptive alternative text for all meaningful images to support screen reader users.',
            ];
        }

        if (strpos($lower, 'h1') !== false) {
            return [
                'impact' => 'serious',
                'recommendation' => 'Ensure each page uses a single, descriptive H1 heading to clarify document structure.',
            ];
        }

        if (strpos($lower, 'link') !== false) {
            return [
                'impact' => 'moderate',
                'recommendation' => 'Replace generic link labels with meaningful descriptions of the target destination.',
            ];
        }

        if (strpos($lower, 'landmark') !== false) {
            return [
                'impact' => 'minor',
                'recommendation' => 'Add structural landmarks such as <main>, <nav>, <header>, or <footer> for assistive navigation.',
            ];
        }

        return [
            'impact' => 'review',
            'recommendation' => 'Review this issue to ensure it aligns with WCAG 2.1 AA expectations.',
        ];
    }

    private function describeWcagLevel(string $level): string
    {
        return match ($level) {
            'AAA' => 'This page exceeds WCAG AA standards with no detected blocking issues.',
            'AA' => 'This page meets WCAG AA accessibility requirements but still has areas to refine.',
            'Partial' => 'This page has partial WCAG compliance and should be prioritized for remediation.',
            default => 'This page has critical accessibility blockers that need immediate attention.',
        };
    }

    private function resolvePreviousScore(string $slug, string $title, int $index, int $score): int
    {
        if ($this->previousScoreResolver) {
            return (int)call_user_func($this->previousScoreResolver, $slug !== '' ? $slug : ($title !== '' ? $title : (string)$index), $score);
        }

        if (function_exists('derive_previous_score')) {
            $identifier = $slug !== '' ? $slug : ($title !== '' ? $title : (string)$index);
            return (int)derive_previous_score('accessibility', $identifier, $score);
        }

        return $score;
    }
}
