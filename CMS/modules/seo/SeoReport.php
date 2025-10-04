<?php
require_once __DIR__ . '/../../includes/template_renderer.php';

class SeoReport
{
    public const IMPACT_CRITICAL = 'critical';
    public const IMPACT_SERIOUS = 'serious';
    public const IMPACT_MODERATE = 'moderate';
    public const IMPACT_MINOR = 'minor';
    public const IMPACT_REVIEW = 'review';

    public const OPTIMISATION_OPTIMISED = 'Optimised';
    public const OPTIMISATION_NEEDS_WORK = 'Needs Improvement';
    public const OPTIMISATION_CRITICAL = 'Critical';

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
    }

    /**
     * Load and normalise pages from a JSON data file.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function loadPages(string $filePath): array
    {
        if (!function_exists('read_json_file')) {
            require_once __DIR__ . '/../../includes/data.php';
        }

        $pages = read_json_file($filePath);
        if (!is_array($pages)) {
            return [];
        }

        return array_values(array_filter($pages, 'is_array'));
    }

    /**
     * Generate the SEO report for all pages.
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
     * Classify an issue description into an impact level with guidance.
     *
     * @return array{impact: string, recommendation: string}
     */
    public function classifyIssue(string $issue): array
    {
        $lower = strtolower($issue);

        if (strpos($lower, 'title') !== false) {
            return [
                'impact' => strpos($lower, 'missing') !== false ? self::IMPACT_CRITICAL : self::IMPACT_SERIOUS,
                'recommendation' => 'Craft a descriptive title tag between 30-65 characters that reflects the page intent and primary keyword.',
            ];
        }

        if (strpos($lower, 'meta description') !== false) {
            return [
                'impact' => strpos($lower, 'missing') !== false ? self::IMPACT_SERIOUS : self::IMPACT_MODERATE,
                'recommendation' => 'Add a unique meta description of 70-160 characters highlighting the core value proposition.',
            ];
        }

        if (strpos($lower, 'h1') !== false) {
            return [
                'impact' => self::IMPACT_SERIOUS,
                'recommendation' => 'Use a single H1 heading that summarises the page topic and includes the target keyword.',
            ];
        }

        if (strpos($lower, 'word count') !== false) {
            return [
                'impact' => self::IMPACT_MODERATE,
                'recommendation' => 'Expand the on-page content to at least 300 words to provide sufficient context for search engines.',
            ];
        }

        if (strpos($lower, 'canonical') !== false) {
            return [
                'impact' => self::IMPACT_MODERATE,
                'recommendation' => 'Add a canonical URL to signal the preferred version of this content and avoid duplicate issues.',
            ];
        }

        if (strpos($lower, 'open graph') !== false || strpos($lower, 'social preview') !== false) {
            return [
                'impact' => self::IMPACT_MINOR,
                'recommendation' => 'Include Open Graph tags (og:title, og:description, og:image) to improve social sharing and click-through rates.',
            ];
        }

        if (strpos($lower, 'structured data') !== false || strpos($lower, 'schema') !== false) {
            return [
                'impact' => self::IMPACT_MINOR,
                'recommendation' => 'Add structured data markup (e.g., JSON-LD) to qualify for rich results and enhanced listings.',
            ];
        }

        if (strpos($lower, 'alt text') !== false) {
            return [
                'impact' => self::IMPACT_MODERATE,
                'recommendation' => 'Provide descriptive alternative text for images to support accessibility and image search visibility.',
            ];
        }

        if (strpos($lower, 'internal link') !== false) {
            return [
                'impact' => self::IMPACT_MODERATE,
                'recommendation' => 'Add internal links to relevant pages to improve crawlability and distribute authority.',
            ];
        }

        if (strpos($lower, 'noindex') !== false) {
            return [
                'impact' => self::IMPACT_CRITICAL,
                'recommendation' => 'Remove the noindex directive unless this page should be hidden from search engines.',
            ];
        }

        return [
            'impact' => self::IMPACT_MINOR,
            'recommendation' => 'Review this recommendation to ensure the page follows on-page SEO best practices.',
        ];
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

        $metrics = [
            'title' => '',
            'titleLength' => 0,
            'metaDescription' => '',
            'metaDescriptionLength' => 0,
            'h1Count' => 0,
            'wordCount' => 0,
            'images' => 0,
            'missingAlt' => 0,
            'links' => ['internal' => 0, 'external' => 0],
            'hasCanonical' => false,
            'hasCanonicalSetting' => false,
            'hasStructuredData' => false,
            'hasOpenGraph' => false,
            'isNoindex' => false,
        ];

        if ($loaded) {
            $this->populateHeadMetrics($doc, $metrics);
            $metrics['h1Count'] = $doc->getElementsByTagName('h1')->length;
            $this->countImages($doc, $metrics);
            $metrics['links'] = $this->countLinks($doc);
            $metrics['wordCount'] = $this->extractWordCount($pageHtml);
        } else {
            $metrics['wordCount'] = $this->extractWordCount($pageHtml);
        }

        $canonicalSetting = trim((string)($page['canonical_url'] ?? ''));
        if ($canonicalSetting !== '') {
            $metrics['hasCanonicalSetting'] = true;
        }

        $this->applyFallbackMetadata($page, $metrics);

        $issues = [];
        $violations = [
            self::IMPACT_CRITICAL => 0,
            self::IMPACT_SERIOUS => 0,
            self::IMPACT_MODERATE => 0,
            self::IMPACT_MINOR => 0,
            'total' => 0,
        ];

        $this->evaluateIssues($page, $metrics, $issues, $violations);

        $score = $this->calculateScore($violations);

        return [
            'title' => $title,
            'slug' => $slug,
            'template' => $template,
            'metrics' => $metrics,
            'issues' => $issues,
            'violations' => $violations,
            'score' => $score,
        ];
    }

    /**
     * @param DOMDocument $doc
     * @param array<string, mixed> $metrics
     */
    private function populateHeadMetrics(DOMDocument $doc, array &$metrics): void
    {
        $titles = $doc->getElementsByTagName('title');
        if ($titles->length > 0) {
            $metrics['title'] = trim((string)$titles->item(0)->textContent);
            $metrics['titleLength'] = $this->seoStrlen($metrics['title']);
        }

        $metaTags = $doc->getElementsByTagName('meta');
        foreach ($metaTags as $meta) {
            $name = strtolower(trim((string)$meta->getAttribute('name')));
            $property = strtolower(trim((string)$meta->getAttribute('property')));
            if ($name === 'description') {
                $metrics['metaDescription'] = trim((string)$meta->getAttribute('content'));
                $metrics['metaDescriptionLength'] = $this->seoStrlen($metrics['metaDescription']);
            }
            if ($name === 'robots' && stripos((string)$meta->getAttribute('content'), 'noindex') !== false) {
                $metrics['isNoindex'] = true;
            }
            if (strpos($property, 'og:') === 0) {
                $metrics['hasOpenGraph'] = true;
            }
        }

        $linkTags = $doc->getElementsByTagName('link');
        foreach ($linkTags as $link) {
            $relRaw = strtolower(trim((string)$link->getAttribute('rel')));
            if ($relRaw === '') {
                continue;
            }

            $relTokens = preg_split('/\s+/', $relRaw) ?: [];
            if (in_array('canonical', $relTokens, true) && trim((string)$link->getAttribute('href')) !== '') {
                $metrics['hasCanonical'] = true;
            }
        }

        $scripts = $doc->getElementsByTagName('script');
        foreach ($scripts as $script) {
            $type = strtolower(trim((string)$script->getAttribute('type')));
            if ($type === 'application/ld+json' && trim((string)$script->textContent) !== '') {
                $metrics['hasStructuredData'] = true;
                break;
            }
        }

        if (!$metrics['hasOpenGraph']) {
            foreach ($metaTags as $meta) {
                if (strtolower(trim((string)$meta->getAttribute('property'))) === 'og:title') {
                    $metrics['hasOpenGraph'] = true;
                    break;
                }
            }
        }
    }

    /**
     * @param DOMDocument $doc
     * @param array<string, mixed> $metrics
     */
    private function countImages(DOMDocument $doc, array &$metrics): void
    {
        $images = $doc->getElementsByTagName('img');
        $metrics['images'] = $images->length;
        $metrics['missingAlt'] = 0;
        foreach ($images as $img) {
            $alt = trim((string)$img->getAttribute('alt'));
            if ($alt === '') {
                $metrics['missingAlt']++;
            }
        }
    }

    private function countLinks(DOMDocument $doc): array
    {
        $internal = 0;
        $external = 0;

        $anchors = $doc->getElementsByTagName('a');
        foreach ($anchors as $anchor) {
            $href = trim((string)$anchor->getAttribute('href'));
            if ($href === '' || strpos($href, '#') === 0 || stripos($href, 'javascript:') === 0) {
                continue;
            }
            if (preg_match('#^https?://#i', $href)) {
                $external++;
            } else {
                $internal++;
            }
        }

        return ['internal' => $internal, 'external' => $external];
    }

    private function extractWordCount(string $html): int
    {
        $clean = preg_replace('#<(script|style|noscript|template)[^>]*>.*?<\\/\\1>#si', ' ', $html);
        if ($clean === null) {
            $clean = $html;
        }
        $text = strip_tags($clean);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5);
        $text = preg_replace('/\s+/u', ' ', $text);
        if ($text === null) {
            $text = '';
        }
        $text = trim($text);
        if ($text === '') {
            return 0;
        }

        return str_word_count($text);
    }

    /**
     * @param array<string, mixed> $page
     * @param array<string, mixed> $metrics
     */
    private function applyFallbackMetadata(array $page, array &$metrics): void
    {
        if ($metrics['metaDescriptionLength'] === 0) {
            $fallbackDescription = trim((string)($page['meta_description'] ?? ''));
            if ($fallbackDescription !== '') {
                $metrics['metaDescription'] = $fallbackDescription;
                $metrics['metaDescriptionLength'] = $this->seoStrlen($fallbackDescription);
            }
        }

        if ($metrics['titleLength'] === 0) {
            $fallbackTitle = trim((string)($page['meta_title'] ?? ''));
            if ($fallbackTitle === '') {
                $fallbackTitle = trim((string)($page['title'] ?? ''));
            }
            if ($fallbackTitle !== '') {
                $metrics['title'] = $fallbackTitle;
                $metrics['titleLength'] = $this->seoStrlen($fallbackTitle);
            }
        }
    }

    /**
     * @param array<string, mixed> $page
     * @param array<string, mixed> $metrics
     * @param string[] $issues
     * @param array<string, int> $violations
     */
    private function evaluateIssues(array $page, array &$metrics, array &$issues, array &$violations): void
    {
        $addIssue = static function (string $description, string $impact) use (&$issues, &$violations): void {
            if (!isset($violations[$impact])) {
                $violations[$impact] = 0;
            }
            $violations[$impact]++;
            $violations['total']++;
            $issues[] = $description;
        };

        if ($metrics['titleLength'] === 0) {
            $addIssue('Page title is missing', self::IMPACT_CRITICAL);
        } else {
            if ($metrics['titleLength'] < 30 || $metrics['titleLength'] > 65) {
                $addIssue('Page title length is outside the recommended 30-65 characters', self::IMPACT_SERIOUS);
            }
        }

        if ($metrics['metaDescriptionLength'] === 0) {
            $addIssue('Meta description is missing', self::IMPACT_SERIOUS);
        } elseif ($metrics['metaDescriptionLength'] < 70 || $metrics['metaDescriptionLength'] > 160) {
            $addIssue('Meta description length should be between 70-160 characters', self::IMPACT_MODERATE);
        }

        if ($metrics['h1Count'] === 0) {
            $addIssue('No H1 heading found on the page', self::IMPACT_SERIOUS);
        } elseif ($metrics['h1Count'] > 1) {
            $addIssue('Multiple H1 headings detected', self::IMPACT_MODERATE);
        }

        if ($metrics['wordCount'] < 150) {
            $addIssue('Word count is below 150 words', self::IMPACT_SERIOUS);
        } elseif ($metrics['wordCount'] < 300) {
            $addIssue('Word count is below 300 words', self::IMPACT_MODERATE);
        }

        if ($metrics['links']['internal'] < 3) {
            $addIssue('Add more internal links to related content', self::IMPACT_MODERATE);
        }

        if ($metrics['missingAlt'] > 0) {
            $addIssue(sprintf('%d image%s missing alt text', $metrics['missingAlt'], $metrics['missingAlt'] === 1 ? ' is' : 's are'), self::IMPACT_MODERATE);
        }

        if (!$metrics['hasCanonical']) {
            if ($metrics['hasCanonicalSetting']) {
                $addIssue('Canonical URL saved but canonical link tag is missing', self::IMPACT_MODERATE);
            } else {
                $addIssue('Canonical URL tag is missing', self::IMPACT_MODERATE);
            }
        }

        if (!$metrics['hasOpenGraph']) {
            $addIssue('Open Graph tags missing for social sharing', self::IMPACT_MINOR);
        }

        if (!$metrics['hasStructuredData']) {
            $addIssue('Structured data markup not detected', self::IMPACT_MINOR);
        }

        if ($metrics['isNoindex']) {
            $addIssue('Robots meta tag blocks indexing (noindex)', self::IMPACT_CRITICAL);
        }
    }

    /**
     * @param array<string, int> $violations
     */
    private function calculateScore(array $violations): int
    {
        $score = 100;
        $score -= ($violations[self::IMPACT_CRITICAL] ?? 0) * 18;
        $score -= ($violations[self::IMPACT_SERIOUS] ?? 0) * 12;
        $score -= ($violations[self::IMPACT_MODERATE] ?? 0) * 7;
        $score -= ($violations[self::IMPACT_MINOR] ?? 0) * 4;

        if (($violations['total'] ?? 0) === 0) {
            $score = 98;
        }

        return max(0, min(100, (int)round($score)));
    }

    /**
     * @param array<int, array<string, mixed>> $analysis
     * @return array{pages: array<int, array<string, mixed>>, pageMap: array<string, array<string, mixed>>, stats: array<string, mixed>}
     */
    private function compileReport(array $analysis): array
    {
        $pageEntries = [];
        $pageEntryMap = [];

        $totalPages = count($analysis);
        $scoreSum = 0;
        $criticalIssues = 0;
        $optimizedPages = 0;
        $needsWork = 0;

        $filterCounts = [
            'all' => $totalPages,
            'critical' => 0,
            'needs-work' => 0,
            'optimized' => 0,
        ];

        foreach ($analysis as $index => $entry) {
            $slug = (string)($entry['slug'] ?? '');
            $path = '/' . ltrim($slug, '/');
            $violations = $entry['violations'];
            $score = (int)$entry['score'];
            $scoreSum += $score;
            $criticalIssues += (int)($violations[self::IMPACT_CRITICAL] ?? 0);

            $optimizationLevel = self::OPTIMISATION_CRITICAL;
            if ($score >= 90 && ($violations[self::IMPACT_CRITICAL] ?? 0) === 0) {
                $optimizationLevel = self::OPTIMISATION_OPTIMISED;
                $optimizedPages++;
                $filterCounts['optimized']++;
            } elseif ($score >= 60) {
                $optimizationLevel = self::OPTIMISATION_NEEDS_WORK;
                $needsWork++;
                $filterCounts['needs-work']++;
            } else {
                $filterCounts['critical']++;
            }

            $issueDetails = [];
            foreach ($entry['issues'] as $issueText) {
                $detail = $this->classifyIssue($issueText);
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
                $issuePreview = ['No outstanding SEO issues'];
            }

            $previousScore = $this->resolvePreviousScore($slug, (string)($entry['title'] ?? ''), $index, $score);

            $pageData = [
                'title' => $entry['title'],
                'slug' => $slug,
                'url' => $path,
                'path' => $path,
                'template' => $entry['template'],
                'seoScore' => $score,
                'previousScore' => $previousScore,
                'optimizationLevel' => $optimizationLevel,
                'violations' => $violations,
                'warnings' => ($violations[self::IMPACT_MODERATE] ?? 0) + ($violations[self::IMPACT_MINOR] ?? 0),
                'lastScanned' => $this->lastScan,
                'pageType' => !empty($entry['template']) ? 'Template: ' . basename((string)$entry['template']) : 'Standard Page',
                'statusMessage' => $this->describeSeoHealth($score, (int)($violations[self::IMPACT_CRITICAL] ?? 0)),
                'summaryLine' => sprintf('SEO health score: %d%%. %s.', $score, $this->summariseViolations($violations)),
                'issues' => [
                    'preview' => $issuePreview,
                    'details' => $issueDetails,
                ],
                'metrics' => $entry['metrics'],
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
            'needsWork' => $needsWork,
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
        if (!empty($violations[self::IMPACT_CRITICAL])) {
            $parts[] = $violations[self::IMPACT_CRITICAL] . ' critical';
        }
        if (!empty($violations[self::IMPACT_SERIOUS])) {
            $parts[] = $violations[self::IMPACT_SERIOUS] . ' serious';
        }
        if (!empty($violations[self::IMPACT_MODERATE])) {
            $parts[] = $violations[self::IMPACT_MODERATE] . ' moderate';
        }
        if (!empty($violations[self::IMPACT_MINOR])) {
            $parts[] = $violations[self::IMPACT_MINOR] . ' minor';
        }

        if (empty($parts)) {
            return 'No outstanding SEO issues detected';
        }

        return implode(', ', $parts) . ' issue' . (($violations['total'] ?? 0) === 1 ? '' : 's');
    }

    private function describeSeoHealth(int $score, int $criticalIssues): string
    {
        if ($score >= 90 && $criticalIssues === 0) {
            return 'This page is fully optimised for search with only minor enhancement opportunities.';
        }
        if ($score >= 75) {
            return 'This page performs well for SEO, but targeted improvements could boost visibility further.';
        }
        if ($score >= 55) {
            return 'This page has noticeable SEO gaps that should be addressed to stay competitive.';
        }

        return 'This page has critical SEO blockers that may prevent it from ranking effectively.';
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

    private function seoStrlen(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    }
}
