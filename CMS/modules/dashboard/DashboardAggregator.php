<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/settings.php';
require_once __DIR__ . '/../../includes/template_renderer.php';
require_once __DIR__ . '/../analytics/AnalyticsService.php';
require_once __DIR__ . '/../blogs/BlogRepository.php';
require_once __DIR__ . '/../forms/FormRepository.php';
require_once __DIR__ . '/../forms/FormAnalytics.php';
require_once __DIR__ . '/../events/EventsService.php';
require_once __DIR__ . '/../calendar/CalendarRepository.php';
require_once __DIR__ . '/../users/UserService.php';

/**
 * Immutable value object containing a snapshot of dashboard metrics.
 */
final class DashboardSnapshot
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(private array $payload)
    {
    }

    public function getPagesCount(): int
    {
        return (int) ($this->payload['pages'] ?? 0);
    }

    public function getMediaLibraryBytes(): int
    {
        return (int) ($this->payload['mediaSize'] ?? 0);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getModuleSummaries(): array
    {
        $modules = $this->payload['moduleSummaries'] ?? [];
        return is_array($modules) ? $modules : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->payload;
    }
}

final class DashboardAggregator
{
    private string $dataDirectory;

    /** @var array<string, mixed> */
    private array $settings;

    private AnalyticsService $analyticsService;

    private FormRepository $formRepository;

    private FormAnalytics $formAnalytics;

    private EventsService $eventsService;

    private CalendarRepository $calendarRepository;

    private UserService $userService;

    private BlogRepository $blogRepository;

    /** @var array<int, array<string, mixed>> */
    private array $pages = [];

    /** @var array<int, array<string, mixed>> */
    private array $media = [];

    /** @var array<int, array<string, mixed>> */
    private array $users = [];

    /** @var array<int, array<string, mixed>> */
    private array $menus = [];

    /** @var array<int, array<string, mixed>> */
    private array $forms = [];

    /** @var array<int, array<string, mixed>> */
    private array $posts = [];

    /** @var array<string, array<int, array<string, mixed>>> */
    private array $history = [];

    /** @var array<int, array<string, mixed>> */
    private array $events = [];

    /** @var array<int, array<string, mixed>> */
    private array $eventOrders = [];

    /** @var array<int, array<string, mixed>> */
    private array $calendarEvents = [];

    /** @var array<int, array<string, mixed>> */
    private array $calendarCategories = [];

    private string $scriptBase;

    private ?string $templateDir;

    /**
     * @param array<string, mixed>|null $settingsOverride
     */
    public function __construct(string $dataDirectory, string $scriptBase, ?string $templateDir = null, ?array $settingsOverride = null)
    {
        $this->dataDirectory = rtrim($dataDirectory, DIRECTORY_SEPARATOR);
        $this->scriptBase = rtrim($scriptBase, '/');
        $this->templateDir = $templateDir ?: null;
        $this->settings = $settingsOverride ?? get_site_settings();

        $this->initializeModuleServices();
        $this->loadDatasets();
    }

    public function aggregate(): DashboardSnapshot
    {
        $slugCounts = $this->countSlugs($this->pages);

        $analysis = $this->analysePages($this->pages, $this->settings, $this->menus, $this->scriptBase, $this->templateDir);
        $seoSummary = $analysis['seo'];
        $accessibilitySummary = $analysis['accessibility'];
        $largestPage = $analysis['largest'];
        $speedSummary = $analysis['speed'];

        $pagesPublished = $analysis['pagesPublished'];
        $pagesDraft = $analysis['pagesDraft'];

        $seoSummary = $this->applySlugDuplicates($seoSummary, $slugCounts);

        $analyticsData = $this->analyticsService->getDashboardData();
        $analyticsSummary = $this->buildAnalyticsSummary($analyticsData, $this->pages);
        $views = $analyticsSummary['totalViews'];

        $mediaTotalSize = $this->calculateMediaBytes($this->media);
        $usersByRole = $this->groupUsersByRole($this->users);
        $postsByStatus = $this->groupPostsByStatus($this->posts);
        $formsFields = $this->countFormFields($this->forms);
        $formsDashboard = $this->formAnalytics->getDashboardContext();
        $menuItems = $this->countMenuItems($this->menus);
        $logStats = $this->summariseLogs($this->history);
        $searchBreakdown = $this->buildSearchBreakdown($this->pages, $this->posts, $this->media);
        $settingsSummary = $this->summariseSettings($this->settings);
        $sitemapEntries = $this->countPublishedPages($this->pages);
        $eventsOverview = $this->eventsService->getOverview();
        $eventsSummary = $this->summariseEvents($this->events, $this->eventOrders, $eventsOverview);
        $calendarMetrics = CalendarRepository::computeMetrics($this->calendarEvents, $this->calendarCategories);
        $calendarSummary = $this->summariseCalendar($calendarMetrics);

        $moduleSummaries = $this->buildModuleSummaries(
            count($this->pages),
            $pagesPublished,
            $pagesDraft,
            $mediaTotalSize,
            $usersByRole,
            $views,
            $analyticsSummary,
            $this->posts,
            $postsByStatus,
            $this->forms,
            $formsFields,
            $formsDashboard,
            $this->menus,
            $menuItems,
            $logStats,
            $searchBreakdown,
            $settingsSummary,
            $sitemapEntries,
            $speedSummary,
            $largestPage,
            $eventsSummary,
            $eventsOverview,
            $calendarSummary,
            $accessibilitySummary,
            $seoSummary
        );

        $statusSortedModules = $this->sortModuleSummaries($moduleSummaries);

        $snapshot = [
            'pages' => count($this->pages),
            'pagesPublished' => $pagesPublished,
            'pagesDraft' => $pagesDraft,
            'media' => count($this->media),
            'mediaSize' => $mediaTotalSize,
            'users' => count($this->users),
            'usersAdmins' => $usersByRole['admin'] ?? 0,
            'usersEditors' => $usersByRole['editor'] ?? 0,
            'views' => $views,
            'analyticsAvgViews' => $analyticsSummary['averageViews'],
            'analyticsTopPage' => $analyticsSummary['topPage'],
            'analyticsTopViews' => $analyticsSummary['topViews'],
            'analyticsZeroViews' => (int) ($analyticsData['zeroViewCount'] ?? 0),
            'blogsTotal' => count($this->posts),
            'blogsPublished' => $postsByStatus['published'],
            'blogsDraft' => $postsByStatus['draft'],
            'blogsScheduled' => $postsByStatus['scheduled'],
            'eventsTotal' => $eventsSummary['total'],
            'eventsPublished' => $eventsSummary['published'],
            'eventsUpcoming' => $eventsSummary['upcoming'],
            'eventsTicketsSold' => $eventsSummary['ticketsSold'],
            'eventsRevenue' => $eventsSummary['revenue'],
            'eventsPendingOrders' => $eventsSummary['pendingOrders'],
            'formsTotal' => (int) ($formsDashboard['totalForms'] ?? count($this->forms)),
            'formsFields' => $formsFields,
            'formsTotalSubmissions' => (int) ($formsDashboard['totalSubmissions'] ?? 0),
            'formsRecentSubmissions' => (int) ($formsDashboard['recentSubmissions'] ?? 0),
            'formsActive' => (int) ($formsDashboard['activeForms'] ?? 0),
            'formsLastSubmissionTimestamp' => $formsDashboard['lastSubmissionTimestamp'] ?? null,
            'formsLastSubmissionLabel' => $formsDashboard['lastSubmissionLabel'] ?? 'No submissions yet',
            'menusCount' => count($this->menus),
            'menuItems' => $menuItems,
            'calendarTotal' => $calendarSummary['total'],
            'calendarUpcoming' => $calendarSummary['upcoming'],
            'calendarRecurring' => $calendarSummary['recurring'],
            'calendarCategories' => $calendarSummary['categoryCount'],
            'calendarNextEvent' => $calendarSummary['nextEvent'],
            'accessibilityScore' => $analysis['accessibilityScore'],
            'accessibilityCompliant' => $accessibilitySummary['accessible'],
            'accessibilityNeedsReview' => $accessibilitySummary['needs_review'],
            'accessibilityMissingAlt' => $accessibilitySummary['missing_alt'],
            'openAlerts' => $accessibilitySummary['needs_review'],
            'alertsAccessibility' => $accessibilitySummary['needs_review'],
            'logsEntries' => $logStats['count'],
            'logsLastActivity' => $logStats['lastActivity'],
            'searchIndex' => $searchBreakdown['pages'] + $searchBreakdown['posts'] + $searchBreakdown['media'],
            'searchBreakdown' => $searchBreakdown,
            'settingsCount' => $settingsSummary['count'],
            'settingsSocialLinks' => $settingsSummary['socialCount'],
            'sitemapEntries' => $sitemapEntries,
            'speedFast' => $speedSummary['fast'],
            'speedMonitor' => $speedSummary['monitor'],
            'speedSlow' => $speedSummary['slow'],
            'speedHeaviestPage' => $largestPage['title'],
            'speedHeaviestPageLength' => $largestPage['length'],
            'eventsCurrency' => $eventsSummary['currency'],
            'seoOptimised' => $seoSummary['optimised'],
            'seoMissingTitle' => $seoSummary['missing_title'],
            'seoMissingDescription' => $seoSummary['missing_description'],
            'seoDescriptionLengthIssues' => $seoSummary['description_length'],
            'seoDuplicateSlugs' => $seoSummary['duplicate_slugs'],
            'seoIssues' => $seoSummary['issues'],
            'moduleSummaries' => $statusSortedModules,
            'generatedAt' => gmdate(DATE_ATOM),
        ];

        return new DashboardSnapshot($snapshot);
    }

    private function initializeModuleServices(): void
    {
        $pagesFile = $this->dataDirectory . DIRECTORY_SEPARATOR . 'pages.json';
        $this->analyticsService = new AnalyticsService($pagesFile);

        $formsFile = $this->dataDirectory . DIRECTORY_SEPARATOR . 'forms.json';
        $submissionsFile = $this->dataDirectory . DIRECTORY_SEPARATOR . 'form_submissions.json';
        $this->formRepository = new FormRepository($formsFile, $submissionsFile);
        $this->formAnalytics = new FormAnalytics($this->formRepository);

        $eventsFile = $this->dataDirectory . DIRECTORY_SEPARATOR . 'events.json';
        $ordersFile = $this->dataDirectory . DIRECTORY_SEPARATOR . 'event_orders.json';
        $categoriesFile = $this->dataDirectory . DIRECTORY_SEPARATOR . 'event_categories.json';
        $eventsRepository = new EventsRepository($eventsFile, $ordersFile, $categoriesFile);
        $this->eventsService = new EventsService($eventsRepository);

        $calendarEventsFile = $this->dataDirectory . DIRECTORY_SEPARATOR . 'calendar_events.json';
        $calendarCategoriesFile = $this->dataDirectory . DIRECTORY_SEPARATOR . 'calendar_categories.json';
        $this->calendarRepository = new CalendarRepository($calendarEventsFile, $calendarCategoriesFile);

        $userRepository = new UserRepository($this->dataDirectory . DIRECTORY_SEPARATOR . 'users.json');
        $this->userService = new UserService($userRepository);

        $postsFile = $this->dataDirectory . DIRECTORY_SEPARATOR . 'blog_posts.json';
        $this->blogRepository = new BlogRepository($postsFile);
    }

    private function loadDatasets(): void
    {
        $this->pages = $this->loadCollection('pages.json');
        $this->media = $this->loadCollection('media.json');
        $this->users = $this->userService->getUsers();
        $this->menus = $this->loadCollection('menus.json');
        $this->forms = $this->formRepository->getForms();
        $this->posts = $this->blogRepository->readPosts();
        $this->history = $this->loadAssociativeCollection('page_history.json');
        $this->events = $this->eventsService->getEventsData();
        $this->eventOrders = $this->eventsService->getOrdersData();
        $this->calendarEvents = $this->calendarRepository->getEvents();
        $this->calendarCategories = $this->calendarRepository->getCategories();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadCollection(string $file): array
    {
        $data = read_json_file($this->dataDirectory . DIRECTORY_SEPARATOR . $file);
        if (!is_array($data)) {
            return [];
        }

        $items = array_values(array_filter($data, 'is_array'));
        return array_map(
            /** @param array<string, mixed> $item */
            static function (array $item): array {
                return $item;
            },
            $items
        );
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function loadAssociativeCollection(string $file): array
    {
        $data = read_json_file($this->dataDirectory . DIRECTORY_SEPARATOR . $file);
        if (!is_array($data)) {
            return [];
        }

        $result = [];
        foreach ($data as $key => $value) {
            if (!is_array($value)) {
                continue;
            }
            $result[(string) $key] = array_values(array_filter($value, 'is_array'));
        }

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $pages
     */
    private function calculateTotalViews(array $pages): int
    {
        $views = 0;
        foreach ($pages as $page) {
            $views += (int) ($page['views'] ?? 0);
        }

        return $views;
    }

    /**
     * @param array<int, array<string, mixed>> $pages
     * @return array<string, int>
     */
    private function countSlugs(array $pages): array
    {
        $counts = [];
        foreach ($pages as $page) {
            $slug = strtolower(trim((string) ($page['slug'] ?? '')));
            if ($slug === '') {
                continue;
            }
            $counts[$slug] = ($counts[$slug] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @param array<int, array<string, mixed>> $pages
     * @param array<string, mixed> $settings
     * @param array<int, array<string, mixed>> $menus
     * @return array{
     *     seo: array<string, int>,
     *     accessibility: array<string, int>,
     *     largest: array{title: ?string, length: int},
     *     speed: array{fast: int, monitor: int, slow: int},
     *     pagesPublished: int,
     *     pagesDraft: int,
     *     accessibilityScore: int
     * }
     */
    private function analysePages(array $pages, array $settings, array $menus, string $scriptBase, ?string $templateDir): array
    {
        $libxmlPrevious = libxml_use_internal_errors(true);

        $seoSummary = [
            'optimised' => 0,
            'missing_title' => 0,
            'missing_description' => 0,
            'long_title' => 0,
            'description_length' => 0,
            'duplicate_slugs' => 0,
            'issues' => 0,
        ];

        $accessibilitySummary = [
            'accessible' => 0,
            'needs_review' => 0,
            'missing_alt' => 0,
            'issues' => 0,
        ];

        $largestPage = ['title' => null, 'length' => 0];
        $speedSummary = ['fast' => 0, 'monitor' => 0, 'slow' => 0];
        $pagesPublished = 0;
        $pagesDraft = 0;

        foreach ($pages as $page) {
            if (!empty($page['published'])) {
                $pagesPublished++;
            } else {
                $pagesDraft++;
            }

            $pageHtml = cms_build_page_html($page, $settings, $menus, $scriptBase, $templateDir);
            $doc = new DOMDocument();
            $loaded = trim($pageHtml) !== '' && $doc->loadHTML('<?xml encoding="utf-8" ?>' . $pageHtml);

            $missingAlt = 0;
            $genericLinks = 0;
            $landmarks = 0;
            $h1Count = 0;
            $seoIssues = 0;

            if ($loaded) {
                $images = $doc->getElementsByTagName('img');
                foreach ($images as $img) {
                    $alt = trim($img->getAttribute('alt'));
                    if ($alt === '') {
                        $missingAlt++;
                    }
                }

                $h1Count = $doc->getElementsByTagName('h1')->length;

                $anchors = $doc->getElementsByTagName('a');
                foreach ($anchors as $anchor) {
                    $text = strtolower(trim($anchor->textContent));
                    if ($text !== '' && in_array($text, $this->getGenericLinkTerms(), true)) {
                        $genericLinks++;
                    }
                }

                foreach (['main', 'nav', 'header', 'footer'] as $tag) {
                    $landmarks += $doc->getElementsByTagName($tag)->length;
                }
            }

            $issues = [];
            if ($missingAlt > 0) {
                $issues[] = 'missing_alt';
                $accessibilitySummary['missing_alt'] += $missingAlt;
            }
            if ($h1Count === 0 || $h1Count > 1) {
                $issues[] = 'h1_count';
            }
            if ($genericLinks > 0) {
                $issues[] = 'generic_links';
            }
            if ($landmarks === 0) {
                $issues[] = 'landmarks';
            }

            if (empty($issues)) {
                $accessibilitySummary['accessible']++;
            } else {
                $accessibilitySummary['needs_review']++;
            }
            $accessibilitySummary['issues'] += count($issues);

            $metaTitle = trim((string) ($page['meta_title'] ?? ''));
            if ($metaTitle === '') {
                $seoSummary['missing_title']++;
                $seoIssues++;
            } else {
                $titleLength = $this->stringLength($metaTitle);
                if ($titleLength > 60) {
                    $seoSummary['long_title']++;
                    $seoIssues++;
                }
            }

            $metaDescription = trim((string) ($page['meta_description'] ?? ''));
            if ($metaDescription === '') {
                $seoSummary['missing_description']++;
                $seoIssues++;
            } else {
                $descriptionLength = $this->stringLength($metaDescription);
                if ($descriptionLength < 50 || $descriptionLength > 160) {
                    $seoSummary['description_length']++;
                    $seoIssues++;
                }
            }

            if ($seoIssues === 0) {
                $seoSummary['optimised']++;
            }
            $seoSummary['issues'] += $seoIssues;

            $content = strip_tags((string) ($page['content'] ?? ''));
            $length = strlen($content);
            if ($length > $largestPage['length']) {
                $largestPage = [
                    'title' => (string) ($page['title'] ?? ''),
                    'length' => $length,
                ];
            }

            if ($length < 5000) {
                $speedSummary['fast']++;
            } elseif ($length < 15000) {
                $speedSummary['monitor']++;
            } else {
                $speedSummary['slow']++;
            }
        }

        libxml_clear_errors();
        libxml_use_internal_errors($libxmlPrevious);

        $totalPages = count($pages);
        $accessibilityScore = $totalPages > 0 ? (int) round(($accessibilitySummary['accessible'] / $totalPages) * 100) : 0;

        return [
            'seo' => $seoSummary,
            'accessibility' => $accessibilitySummary,
            'largest' => $largestPage,
            'speed' => $speedSummary,
            'pagesPublished' => $pagesPublished,
            'pagesDraft' => $pagesDraft,
            'accessibilityScore' => $accessibilityScore,
        ];
    }

    /**
     * @return string[]
     */
    private function getGenericLinkTerms(): array
    {
        return [
            'click here',
            'read more',
            'learn more',
            'here',
            'more',
            'this page',
        ];
    }

    /**
     * @param array<string, int> $slugCounts
     * @param array<string, int> $seoSummary
     * @return array<string, int>
     */
    private function applySlugDuplicates(array $seoSummary, array $slugCounts): array
    {
        foreach ($slugCounts as $slug => $count) {
            if ($count > 1) {
                $seoSummary['duplicate_slugs'] += $count - 1;
                $seoSummary['issues'] += $count - 1;
            }
        }

        return $seoSummary;
    }

    /**
     * @param array<int, array<string, mixed>> $media
     */
    private function calculateMediaBytes(array $media): int
    {
        $total = 0;
        foreach ($media as $item) {
            if (isset($item['size']) && is_numeric($item['size'])) {
                $total += (int) $item['size'];
            }
        }
        return $total;
    }

    /**
     * @param array<int, array<string, mixed>> $users
     * @return array<string, int>
     */
    private function groupUsersByRole(array $users): array
    {
        $grouped = [];
        foreach ($users as $user) {
            $role = strtolower((string) ($user['role'] ?? 'unknown'));
            if ($role === '') {
                $role = 'unknown';
            }
            $grouped[$role] = ($grouped[$role] ?? 0) + 1;
        }
        return $grouped;
    }

    /**
     * @param array<int, array<string, mixed>> $posts
     * @return array{published: int, draft: int, scheduled: int, other: int}
     */
    private function groupPostsByStatus(array $posts): array
    {
        $statuses = [
            'published' => 0,
            'draft' => 0,
            'scheduled' => 0,
            'other' => 0,
        ];

        foreach ($posts as $post) {
            $status = strtolower(trim((string) ($post['status'] ?? '')));
            if ($status === '' || !array_key_exists($status, $statuses)) {
                $status = 'other';
            }
            $statuses[$status]++;
        }

        return $statuses;
    }

    /**
     * @param array<int, array<string, mixed>> $forms
     */
    private function countFormFields(array $forms): int
    {
        $count = 0;
        foreach ($forms as $form) {
            if (!empty($form['fields']) && is_array($form['fields'])) {
                $count += count($form['fields']);
            }
        }
        return $count;
    }

    /**
     * @param array<int, array<string, mixed>> $menus
     */
    private function countMenuItems(array $menus): int
    {
        $total = 0;
        foreach ($menus as $menu) {
            if (!empty($menu['items']) && is_array($menu['items'])) {
                $total += $this->countMenuItemRecursive($menu['items']);
            }
        }
        return $total;
    }

    /**
     * @param array<int, mixed> $items
     */
    private function countMenuItemRecursive(array $items): int
    {
        $total = 0;
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $total++;
            if (!empty($item['children']) && is_array($item['children'])) {
                $total += $this->countMenuItemRecursive($item['children']);
            }
        }
        return $total;
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $history
     * @return array{count: int, lastActivity: ?string}
     */
    private function summariseLogs(array $history): array
    {
        $count = 0;
        $latest = null;
        foreach ($history as $entries) {
            $count += count($entries);
            foreach ($entries as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $time = isset($entry['time']) ? (int) $entry['time'] : null;
                if ($time) {
                    if ($latest === null || $time > $latest) {
                        $latest = $time;
                    }
                }
            }
        }

        return [
            'count' => $count,
            'lastActivity' => $latest ? date('c', $latest) : null,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $pages
     * @param array<int, array<string, mixed>> $posts
     * @param array<int, array<string, mixed>> $media
     * @return array{pages: int, posts: int, media: int}
     */
    private function buildSearchBreakdown(array $pages, array $posts, array $media): array
    {
        return [
            'pages' => count($pages),
            'posts' => count($posts),
            'media' => count($media),
        ];
    }

    /**
     * @param array<string, mixed> $settings
     * @return array{count: int, socialCount: int}
     */
    private function summariseSettings(array $settings): array
    {
        $count = is_array($settings) ? count($settings) : 0;
        $socialCount = (isset($settings['social']) && is_array($settings['social'])) ? count($settings['social']) : 0;

        return [
            'count' => $count,
            'socialCount' => $socialCount,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $pages
     */
    private function countPublishedPages(array $pages): int
    {
        $count = 0;
        foreach ($pages as $page) {
            if (!empty($page['published'])) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * @param array<int, array<string, mixed>> $pages
     * @return array{title: ?string, views: int}|null
     */
    private function findTopPage(array $pages): ?array
    {
        $top = null;
        foreach ($pages as $page) {
            $views = (int) ($page['views'] ?? 0);
            if (!$top || $views > $top['views']) {
                $top = [
                    'title' => (string) ($page['title'] ?? ''),
                    'views' => $views,
                ];
            }
        }
        return $top;
    }

    /**
     * @param array<string, mixed> $analyticsData
     * @param array<int, array<string, mixed>> $pages
     * @return array{totalViews: int, averageViews: int, topPage: ?string, topViews: int}
     */
    private function buildAnalyticsSummary(array $analyticsData, array $pages): array
    {
        $totalPages = count($pages);
        $totalViews = (int) round((float) ($analyticsData['totalViews'] ?? 0));
        $averageValue = $analyticsData['averageViews'] ?? null;
        $averageViews = is_numeric($averageValue)
            ? (int) round((float) $averageValue)
            : ($totalPages > 0 ? (int) round($totalViews / $totalPages) : 0);

        $topPageTitle = null;
        $topViews = 0;
        $topPages = $analyticsData['topPages'] ?? null;
        if (is_array($topPages) && isset($topPages[0]) && is_array($topPages[0])) {
            $first = $topPages[0];
            $title = isset($first['title']) ? trim((string) $first['title']) : '';
            $slug = isset($first['slug']) ? trim((string) $first['slug']) : '';
            $topPageTitle = $title !== '' ? $title : ($slug !== '' ? $slug : null);
            $topViews = (int) round((float) ($first['views'] ?? 0));
        }

        if ($topPageTitle === null && $totalPages > 0) {
            $top = $this->findTopPage($pages);
            if ($top !== null) {
                $title = isset($top['title']) ? trim((string) $top['title']) : '';
                $topPageTitle = $title !== '' ? $title : null;
                $topViews = (int) ($top['views'] ?? 0);
            }
        }

        if ($totalViews === 0 && $totalPages > 0) {
            $totalViews = $this->calculateTotalViews($pages);
            if ($totalPages > 0) {
                $averageViews = (int) round($totalViews / $totalPages);
            }
        }

        return [
            'totalViews' => $totalViews,
            'averageViews' => $averageViews,
            'topPage' => $topPageTitle,
            'topViews' => $topViews,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $events
     * @param array<int, array<string, mixed>> $orders
     * @param array<string, mixed>|null $overview
     * @return array{
     *     total: int,
     *     published: int,
     *     upcoming: int,
     *     ticketsSold: int,
     *     revenue: float,
     *     pendingOrders: int,
     *     currency: string
     * }
     */
    private function summariseEvents(array $events, array $orders, ?array $overview = null): array
    {
        $stats = is_array($overview['stats'] ?? null) ? $overview['stats'] : null;

        $total = $stats !== null ? (int) ($stats['total_events'] ?? count($events)) : count($events);
        $published = 0;
        $upcoming = 0;
        $ticketsSold = 0;
        $revenue = 0.0;
        $pendingOrders = 0;
        $currency = 'USD';

        $now = time();
        foreach ($events as $event) {
            $status = strtolower(trim((string) ($event['status'] ?? 'draft')));
            if ($status === 'published') {
                $published++;
            }
            $start = isset($event['start']) ? strtotime((string) $event['start']) : false;
            if ($status === 'published' && $start !== false && $start >= $now) {
                $upcoming++;
            }
        }

        foreach ($orders as $order) {
            $status = strtolower(trim((string) ($order['status'] ?? 'paid')));
            $amount = isset($order['amount']) ? (float) $order['amount'] : 0.0;
            if (!empty($order['currency']) && is_string($order['currency'])) {
                $orderCurrency = strtoupper(trim($order['currency']));
                if ($orderCurrency !== '') {
                    $currency = $orderCurrency;
                }
            }

            $tickets = 0;
            if (!empty($order['tickets']) && is_array($order['tickets'])) {
                foreach ($order['tickets'] as $ticket) {
                    if (!is_array($ticket)) {
                        continue;
                    }
                    $tickets += max(0, (int) ($ticket['quantity'] ?? 0));
                }
            }

            if ($status === 'refunded') {
                continue;
            }

            if (in_array($status, ['pending', 'awaiting-payment'], true)) {
                $pendingOrders++;
            }

            $ticketsSold += $tickets;
            $revenue += $amount;
        }

        if ($stats !== null) {
            if (isset($stats['total_tickets_sold'])) {
                $ticketsSold = (int) $stats['total_tickets_sold'];
            }
            if (isset($stats['total_revenue'])) {
                $revenue = (float) $stats['total_revenue'];
            }
        }

        return [
            'total' => $total,
            'published' => $published,
            'upcoming' => $upcoming,
            'ticketsSold' => $ticketsSold,
            'revenue' => $revenue,
            'pendingOrders' => $pendingOrders,
            'currency' => $currency,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $calendarEvents
     * @param array<int, array<string, mixed>> $calendarCategories
     * @return array{
     *     total: int,
     *     upcoming: int,
     *     recurring: int,
     *     categoryCount: int,
     *     nextEvent: ?array{title: string, time: string}
     * }
     */
    private function summariseCalendar(array $metrics): array
    {
        $total = (int) ($metrics['total_events'] ?? 0);
        $upcoming = (int) ($metrics['upcoming_count'] ?? 0);
        $recurring = (int) ($metrics['recurring_count'] ?? 0);
        $categoryCount = (int) ($metrics['category_count'] ?? 0);

        $nextEvent = null;
        if (isset($metrics['next_event']) && is_array($metrics['next_event'])) {
            $next = $metrics['next_event'];
            $title = isset($next['title']) ? trim((string) $next['title']) : '';
            if ($title === '') {
                $title = 'Untitled event';
            }
            $time = isset($next['start_date'])
                ? (string) $next['start_date']
                : (isset($next['time']) ? (string) $next['time'] : '');
            if ($time !== '') {
                $nextEvent = [
                    'title' => $title,
                    'time' => $time,
                ];
            }
        }

        return [
            'total' => $total,
            'upcoming' => $upcoming,
            'recurring' => $recurring,
            'categoryCount' => $categoryCount,
            'nextEvent' => $nextEvent,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $moduleSummaries
     * @return array<int, array<string, mixed>>
     */
    private function sortModuleSummaries(array $moduleSummaries): array
    {
        $statusPriority = ['urgent' => 0, 'warning' => 1, 'ok' => 2];
        usort(
            $moduleSummaries,
            static function (array $a, array $b) use ($statusPriority): int {
                $statusA = strtolower((string) ($a['status'] ?? 'ok'));
                $statusB = strtolower((string) ($b['status'] ?? 'ok'));
                $priorityA = $statusPriority[$statusA] ?? $statusPriority['ok'];
                $priorityB = $statusPriority[$statusB] ?? $statusPriority['ok'];

                if ($priorityA === $priorityB) {
                    return strcasecmp((string) ($a['module'] ?? ''), (string) ($b['module'] ?? ''));
                }

                return $priorityA <=> $priorityB;
            }
        );

        return $moduleSummaries;
    }

    /**
     * @param array<string, int> $usersByRole
     * @param array<string, int> $postsByStatus
     * @param array<int, array<string, mixed>> $forms
     * @param array<string, mixed> $formsDashboard
     * @param array<int, array<string, mixed>> $menus
     * @param array{count: int, lastActivity: ?string} $logStats
     * @param array{pages: int, posts: int, media: int} $searchBreakdown
     * @param array{count: int, socialCount: int} $settingsSummary
     * @param array{fast: int, monitor: int, slow: int} $speedSummary
     * @param array{title: ?string, length: int} $largestPage
     * @param array<string, mixed> $eventsSummary
     * @param array<string, mixed> $eventsOverview
     * @param array<string, mixed> $calendarSummary
     * @param array<string, int> $accessibilitySummary
     * @param array<string, int> $seoSummary
     * @return array<int, array<string, mixed>>
     */
    private function buildModuleSummaries(
        int $totalPages,
        int $pagesPublished,
        int $pagesDraft,
        int $mediaTotalSize,
        array $usersByRole,
        int $views,
        array $analyticsSummary,
        array $posts,
        array $postsByStatus,
        array $forms,
        int $formsFields,
        array $formsDashboard,
        array $menus,
        int $menuItems,
        array $logStats,
        array $searchBreakdown,
        array $settingsSummary,
        int $sitemapEntries,
        array $speedSummary,
        array $largestPage,
        array $eventsSummary,
        array $eventsOverview,
        array $calendarSummary,
        array $accessibilitySummary,
        array $seoSummary
    ): array {
        $pagesStatus = $pagesDraft > 0 ? 'warning' : 'ok';
        $pagesTrend = $pagesDraft > 0
            ? $pagesDraft . ' drafts awaiting review'
            : 'All pages published';
        $pagesCta = $pagesDraft > 0 ? 'Review drafts' : 'Open pages';

        $mediaStatus = $mediaTotalSize > 200 * 1024 * 1024 ? 'warning' : 'ok';
        $mediaTrend = 'Library size: ' . $this->formatBytes($mediaTotalSize);
        $mediaCta = $mediaTotalSize > 0 ? 'Manage media' : 'Upload media';

        $usersStatus = ($usersByRole['admin'] ?? 0) === 0 ? 'warning' : 'ok';
        $usersTrend = 'Admins: ' . $this->formatNumber($usersByRole['admin'] ?? 0)
            . ' • Editors: ' . $this->formatNumber($usersByRole['editor'] ?? 0);
        $usersCta = ($usersByRole['admin'] ?? 0) === 0 ? 'Invite admin' : 'Manage team';

        $analyticsStatus = $views === 0 ? 'warning' : 'ok';
        $analyticsTrend = $views > 0
            ? 'Top page: ' . ($analyticsSummary['topPage'] ?? 'Unknown')
            : 'No traffic recorded yet';
        $analyticsCta = $views === 0 ? 'Promote content' : 'Open analytics';

        $blogsStatus = $postsByStatus['draft'] > 0 ? 'warning' : 'ok';
        $blogsTrend = 'Drafts: ' . $this->formatNumber($postsByStatus['draft'])
            . ' • Scheduled: ' . $this->formatNumber($postsByStatus['scheduled']);
        $blogsCta = $postsByStatus['draft'] > 0 ? 'Publish posts' : 'Write post';

        $eventsStatus = 'ok';
        if ($eventsSummary['total'] === 0) {
            $eventsStatus = 'urgent';
        } elseif ($eventsSummary['published'] === 0 || $eventsSummary['upcoming'] === 0 || $eventsSummary['pendingOrders'] > 0) {
            $eventsStatus = 'warning';
        }
        $eventsSecondary = 'Upcoming: ' . $this->formatNumber($eventsSummary['upcoming'])
            . ' • Tickets sold: ' . $this->formatNumber($eventsSummary['ticketsSold']);
        $upcomingEvents = isset($eventsOverview['upcoming']) && is_array($eventsOverview['upcoming'])
            ? $eventsOverview['upcoming']
            : [];
        if (!empty($upcomingEvents) && is_array($upcomingEvents[0])) {
            $firstUpcoming = $upcomingEvents[0];
            $title = isset($firstUpcoming['title']) ? trim((string) $firstUpcoming['title']) : '';
            $start = isset($firstUpcoming['start']) ? (string) $firstUpcoming['start'] : '';
            if ($title !== '') {
                $summary = $title;
                if ($start !== '') {
                    $timestamp = strtotime($start);
                    if ($timestamp !== false) {
                        $summary .= ' (' . date('M j', $timestamp) . ')';
                    }
                }
                $eventsSecondary .= ' • Next: ' . $summary;
            }
        }
        $eventsTrend = $eventsSummary['pendingOrders'] > 0
            ? 'Pending orders: ' . $this->formatNumber($eventsSummary['pendingOrders'])
            : ($eventsSummary['revenue'] > 0
                ? 'Revenue: ' . $this->formatCurrency($eventsSummary['revenue'], $eventsSummary['currency'])
                : 'No ticket sales yet');
        $eventsCta = $eventsSummary['total'] === 0
            ? 'Create an event'
            : ($eventsSummary['pendingOrders'] > 0 ? 'Review event orders' : 'Open events');

        $calendarStatus = 'ok';
        if ($calendarSummary['total'] === 0) {
            $calendarStatus = 'urgent';
        } elseif ($calendarSummary['upcoming'] === 0) {
            $calendarStatus = 'warning';
        }
        $calendarSecondary = 'Upcoming: ' . $this->formatNumber($calendarSummary['upcoming'])
            . ' • Categories: ' . $this->formatNumber($calendarSummary['categoryCount']);
        if ($calendarSummary['nextEvent']) {
            $nextEventTime = isset($calendarSummary['nextEvent']['time'])
                ? strtotime((string) $calendarSummary['nextEvent']['time'])
                : false;
            if ($nextEventTime !== false) {
                $calendarTrend = 'Next: ' . $calendarSummary['nextEvent']['title']
                    . ' (' . date('M j', $nextEventTime) . ')';
            } else {
                $calendarTrend = 'Next: ' . $calendarSummary['nextEvent']['title'];
            }
        } else {
            $calendarTrend = 'No upcoming entries scheduled';
        }
        $calendarCta = $calendarSummary['total'] === 0 ? 'Add calendar event' : 'Open calendar';

        $formsTotal = (int) ($formsDashboard['totalForms'] ?? count($forms));
        $formsStatus = $formsTotal === 0 ? 'warning' : 'ok';
        $totalSubmissions = (int) ($formsDashboard['totalSubmissions'] ?? 0);
        $activeForms = (int) ($formsDashboard['activeForms'] ?? 0);
        $recentSubmissions = (int) ($formsDashboard['recentSubmissions'] ?? 0);
        $lastSubmissionLabel = isset($formsDashboard['lastSubmissionLabel'])
            ? (string) $formsDashboard['lastSubmissionLabel']
            : 'No submissions yet';

        $formsSecondary = 'Submissions: ' . $this->formatNumber($totalSubmissions)
            . ' • Active: ' . $this->formatNumber($activeForms);
        if ($formsFields > 0) {
            $formsSecondary .= ' • Fields: ' . $this->formatNumber($formsFields);
        }

        $formsTrend = $recentSubmissions > 0
            ? 'Last 30 days: ' . $this->formatNumber($recentSubmissions)
            : $lastSubmissionLabel;
        $formsCta = $formsTotal === 0 ? 'Create form' : 'View forms';

        $menusStatus = $menuItems === 0 ? 'warning' : 'ok';
        $menusTrend = $menuItems === 0
            ? 'No navigation items configured'
            : $this->formatNumber($menuItems) . ' navigation links';
        $menusCta = $menuItems === 0 ? 'Build menus' : 'Edit menus';

        $logsStatus = 'ok';
        $logsTrend = $logStats['lastActivity'] ? 'Last change ' . date('M j, g:i A', strtotime($logStats['lastActivity'])) : 'No edits logged yet';
        $logsCta = 'View history';

        $searchIndexed = $searchBreakdown['pages'] + $searchBreakdown['posts'] + $searchBreakdown['media'];
        $searchStatus = $searchIndexed === 0 ? 'warning' : 'ok';
        $searchTrend = $searchIndexed === 0
            ? 'Search index empty'
            : 'Indexed entries: ' . $this->formatNumber($searchIndexed);
        $searchCta = $searchIndexed === 0 ? 'Index content' : 'Manage search';

        $settingsStatus = $settingsSummary['socialCount'] === 0 ? 'warning' : 'ok';
        $settingsTrend = $settingsSummary['socialCount'] === 0
            ? 'No social links configured'
            : $this->formatNumber((int) $settingsSummary['socialCount']) . ' social links live';
        $settingsCta = $settingsSummary['socialCount'] === 0 ? 'Add social links' : 'Adjust settings';

        $sitemapStatus = $sitemapEntries === 0 ? 'warning' : 'ok';
        $sitemapTrend = $sitemapEntries === 0
            ? 'Publish pages to populate the sitemap'
            : $this->formatNumber((int) $sitemapEntries) . ' URLs ready for sitemap.xml';
        $sitemapCta = $sitemapEntries === 0 ? 'Publish pages' : 'Review sitemap';

        $speedStatus = 'ok';
        if ($speedSummary['slow'] > 0) {
            $speedStatus = $speedSummary['slow'] >= $speedSummary['fast'] ? 'urgent' : 'warning';
        } elseif ($speedSummary['monitor'] > 0) {
            $speedStatus = 'warning';
        }
        $speedTrend = 'Slow pages: ' . $this->formatNumber((int) $speedSummary['slow']);
        $speedCta = $speedSummary['slow'] > 0 ? 'Optimise slow pages' : 'Review performance';

        $seoStatus = 'ok';
        if ($seoSummary['missing_title'] > 0 || $seoSummary['missing_description'] > 0 || $seoSummary['duplicate_slugs'] > 0) {
            $seoStatus = 'urgent';
        } elseif ($seoSummary['long_title'] > 0 || $seoSummary['description_length'] > 0) {
            $seoStatus = 'warning';
        }
        $seoTrend = 'Meta descriptions within best practice range';
        if ($seoSummary['duplicate_slugs'] > 0) {
            $seoTrend = 'Duplicate slugs detected: ' . $this->formatNumber((int) $seoSummary['duplicate_slugs']);
        } elseif ($seoSummary['missing_description'] > 0 || $seoSummary['missing_title'] > 0) {
            $seoTrend = $this->formatNumber((int) ($seoSummary['missing_title'] + $seoSummary['missing_description'])) . ' meta fields missing';
        } elseif ($seoSummary['long_title'] > 0 || $seoSummary['description_length'] > 0) {
            $seoTrend = 'Metadata length alerts: ' . $this->formatNumber((int) ($seoSummary['long_title'] + $seoSummary['description_length']));
        }
        $seoCta = $seoStatus === 'urgent' ? 'Fix SEO issues' : 'Review SEO settings';

        $accessibilityStatus = $accessibilitySummary['needs_review'] > 0 ? 'warning' : 'ok';
        if ($accessibilitySummary['missing_alt'] > 0 && $accessibilitySummary['needs_review'] >= $accessibilitySummary['accessible']) {
            $accessibilityStatus = 'urgent';
        }
        $accessibilityTrend = 'Alt text issues: ' . $this->formatNumber((int) $accessibilitySummary['missing_alt']);
        $accessibilityCta = $accessibilityStatus === 'urgent' ? 'Fix accessibility issues' : 'Review accessibility';

        return [
            [
                'id' => 'pages',
                'module' => 'Pages',
                'primary' => $this->formatNumber($totalPages) . ' total pages',
                'secondary' => 'Published: ' . $this->formatNumber($pagesPublished) . ' • Drafts: ' . $this->formatNumber($pagesDraft),
                'status' => $pagesStatus,
                'statusLabel' => $this->statusLabel($pagesStatus),
                'trend' => $pagesTrend,
                'cta' => $pagesCta,
            ],
            [
                'id' => 'media',
                'module' => 'Media',
                'primary' => $this->formatNumber(count($this->media)) . ' assets in library',
                'secondary' => 'Library usage ' . $this->formatBytes($mediaTotalSize),
                'status' => $mediaStatus,
                'statusLabel' => $this->statusLabel($mediaStatus),
                'trend' => $mediaTrend,
                'cta' => $mediaCta,
            ],
            [
                'id' => 'users',
                'module' => 'Users',
                'primary' => $this->formatNumber(count($this->users)) . ' active team members',
                'secondary' => $usersTrend,
                'status' => $usersStatus,
                'statusLabel' => $this->statusLabel($usersStatus),
                'trend' => $usersTrend,
                'cta' => $usersCta,
            ],
            [
                'id' => 'analytics',
                'module' => 'Analytics',
                'primary' => 'Total views: ' . $this->formatNumber($analyticsSummary['totalViews']),
                'secondary' => 'Average per page: ' . $this->formatNumber($analyticsSummary['averageViews']),
                'status' => $analyticsStatus,
                'statusLabel' => $this->statusLabel($analyticsStatus),
                'trend' => $analyticsTrend,
                'cta' => $analyticsCta,
            ],
            [
                'id' => 'blogs',
                'module' => 'Blog',
                'primary' => $this->formatNumber(count($posts)) . ' total posts',
                'secondary' => 'Published: ' . $this->formatNumber($postsByStatus['published']) . ' • Drafts: ' . $this->formatNumber($postsByStatus['draft']),
                'status' => $blogsStatus,
                'statusLabel' => $this->statusLabel($blogsStatus),
                'trend' => $blogsTrend,
                'cta' => $blogsCta,
            ],
            [
                'id' => 'forms',
                'module' => 'Forms',
                'primary' => $this->formatNumber($formsTotal) . ' active forms',
                'secondary' => $formsSecondary,
                'status' => $formsStatus,
                'statusLabel' => $this->statusLabel($formsStatus),
                'trend' => $formsTrend,
                'cta' => $formsCta,
            ],
            [
                'id' => 'menus',
                'module' => 'Menus',
                'primary' => $this->formatNumber(count($menus)) . ' menus configured',
                'secondary' => $menusTrend,
                'status' => $menusStatus,
                'statusLabel' => $this->statusLabel($menusStatus),
                'trend' => $menusTrend,
                'cta' => $menusCta,
            ],
            [
                'id' => 'logs',
                'module' => 'Logs',
                'primary' => $this->formatNumber($logStats['count']) . ' total entries',
                'secondary' => $logStats['lastActivity'] ? 'Last edit: ' . date('M j, g:i A', strtotime($logStats['lastActivity'])) : 'No edits yet',
                'status' => $logsStatus,
                'statusLabel' => $this->statusLabel($logsStatus),
                'trend' => $logsTrend,
                'cta' => $logsCta,
            ],
            [
                'id' => 'search',
                'module' => 'Search',
                'primary' => $this->formatNumber($searchIndexed) . ' indexed items',
                'secondary' => 'Pages: ' . $this->formatNumber($searchBreakdown['pages']) . ' • Posts: ' . $this->formatNumber($searchBreakdown['posts']),
                'status' => $searchStatus,
                'statusLabel' => $this->statusLabel($searchStatus),
                'trend' => $searchTrend,
                'cta' => $searchCta,
            ],
            [
                'id' => 'settings',
                'module' => 'Settings',
                'primary' => $this->formatNumber($settingsSummary['count']) . ' configuration items',
                'secondary' => $settingsTrend,
                'status' => $settingsStatus,
                'statusLabel' => $this->statusLabel($settingsStatus),
                'trend' => $settingsTrend,
                'cta' => $settingsCta,
            ],
            [
                'id' => 'sitemap',
                'module' => 'Sitemap',
                'primary' => $this->formatNumber($sitemapEntries) . ' URLs in sitemap',
                'secondary' => $sitemapTrend,
                'status' => $sitemapStatus,
                'statusLabel' => $this->statusLabel($sitemapStatus),
                'trend' => $sitemapTrend,
                'cta' => $sitemapCta,
            ],
            [
                'id' => 'speed',
                'module' => 'Speed',
                'primary' => 'Fast: ' . $this->formatNumber($speedSummary['fast']) . ' • Monitor: ' . $this->formatNumber($speedSummary['monitor']) . ' • Slow: ' . $this->formatNumber($speedSummary['slow']),
                'secondary' => $largestPage['title'] ? 'Heaviest content: ' . $largestPage['title'] : 'Content analysis based on page length',
                'status' => $speedStatus,
                'statusLabel' => $this->statusLabel($speedStatus),
                'trend' => $speedTrend,
                'cta' => $speedCta,
            ],
            [
                'id' => 'events',
                'module' => 'Events',
                'primary' => $this->formatNumber($eventsSummary['total']) . ' total events',
                'secondary' => $eventsSecondary,
                'status' => $eventsStatus,
                'statusLabel' => $this->statusLabel($eventsStatus),
                'trend' => $eventsTrend,
                'cta' => $eventsCta,
            ],
            [
                'id' => 'calendar',
                'module' => 'Calendar',
                'primary' => $this->formatNumber($calendarSummary['total']) . ' scheduled entries',
                'secondary' => $calendarSecondary,
                'status' => $calendarStatus,
                'statusLabel' => $this->statusLabel($calendarStatus),
                'trend' => $calendarTrend,
                'cta' => $calendarCta,
            ],
            [
                'id' => 'seo',
                'module' => 'SEO',
                'primary' => $this->formatNumber($seoSummary['optimised']) . ' pages optimised',
                'secondary' => $seoTrend,
                'status' => $seoStatus,
                'statusLabel' => $this->statusLabel($seoStatus),
                'trend' => $seoTrend,
                'cta' => $seoCta,
            ],
            [
                'id' => 'accessibility',
                'module' => 'Accessibility',
                'primary' => $this->formatNumber($accessibilitySummary['accessible']) . ' pages compliant',
                'secondary' => 'Needs review: ' . $this->formatNumber($accessibilitySummary['needs_review']),
                'status' => $accessibilityStatus,
                'statusLabel' => $this->statusLabel($accessibilityStatus),
                'trend' => $accessibilityTrend,
                'cta' => $accessibilityCta,
            ],
        ];
    }

    private function stringLength(string $value): int
    {
        return function_exists('mb_strlen') ? (int) mb_strlen($value) : strlen($value);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 KB';
        }

        $units = ['bytes', 'KB', 'MB', 'GB'];
        $power = (int) floor(log($bytes, 1024));
        $power = max(0, min($power, count($units) - 1));
        $value = $bytes / (1024 ** $power);

        if ($power === 0) {
            return number_format($bytes) . ' ' . $units[$power];
        }

        return number_format($value, $value >= 10 ? 0 : 1) . ' ' . $units[$power];
    }

    private function formatNumber(int $value): string
    {
        return number_format($value);
    }

    private function currencySymbol(string $currency): string
    {
        $upper = strtoupper(trim($currency));
        if ($upper === '') {
            return '$';
        }

        $map = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'AUD' => 'A$',
            'CAD' => 'C$',
            'JPY' => '¥',
            'NZD' => 'NZ$',
        ];

        return $map[$upper] ?? ($upper . ' ');
    }

    private function formatCurrency(float $amount, string $currency = 'USD'): string
    {
        $symbol = $this->currencySymbol($currency);
        $formatted = number_format($amount, 2);

        return $symbol . $formatted;
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'urgent' => 'Action required',
            'warning' => 'Needs attention',
            default => 'On track',
        };
    }
}
