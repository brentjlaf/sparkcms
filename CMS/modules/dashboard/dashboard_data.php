<?php
// File: dashboard_data.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_login();

$pagesFile = __DIR__ . '/../../data/pages.json';
$mediaFile = __DIR__ . '/../../data/media.json';
$usersFile = __DIR__ . '/../../data/users.json';
$settingsFile = __DIR__ . '/../../data/settings.json';
$menusFile = __DIR__ . '/../../data/menus.json';
$formsFile = __DIR__ . '/../../data/forms.json';
$postsFile = __DIR__ . '/../../data/blog_posts.json';
$historyFile = __DIR__ . '/../../data/page_history.json';
$dataDirectory = __DIR__ . '/../../data';

$pages = read_json_file($pagesFile);
$media = read_json_file($mediaFile);
$users = read_json_file($usersFile);
$settings = read_json_file($settingsFile);
$menus = read_json_file($menusFile);
$forms = read_json_file($formsFile);
$posts = read_json_file($postsFile);
$history = read_json_file($historyFile);

if (!is_array($pages)) {
    $pages = [];
}
if (!is_array($media)) {
    $media = [];
}
if (!is_array($users)) {
    $users = [];
}
if (!is_array($settings)) {
    $settings = [];
}
if (!is_array($menus)) {
    $menus = [];
}
if (!is_array($forms)) {
    $forms = [];
}
if (!is_array($posts)) {
    $posts = [];
}
if (!is_array($history)) {
    $history = [];
}

$views = 0;
foreach ($pages as $p) {
    $views += $p['views'] ?? 0;
}

$seoSummary = [
    'optimized' => 0,
    'needs_attention' => 0,
    'metadata_gaps' => 0,
];
$seoIssuesList = [];

$stringLength = function (string $value): int {
    if (function_exists('mb_strlen')) {
        return mb_strlen($value);
    }
    return strlen($value);
};

foreach ($pages as $page) {
    $metaTitle = trim((string)($page['meta_title'] ?? ''));
    $metaDescription = trim((string)($page['meta_description'] ?? ''));
    $ogTitle = trim((string)($page['og_title'] ?? ''));
    $ogDescription = trim((string)($page['og_description'] ?? ''));
    $ogImage = trim((string)($page['og_image'] ?? ''));

    $issues = [];

    if ($metaTitle === '') {
        $issues[] = 'meta_title_missing';
        $seoSummary['metadata_gaps']++;
    } else {
        $length = $stringLength($metaTitle);
        if ($length < 30 || $length > 60) {
            $issues[] = 'meta_title_length';
        }
    }

    if ($metaDescription === '') {
        $issues[] = 'meta_description_missing';
        $seoSummary['metadata_gaps']++;
    } else {
        $length = $stringLength($metaDescription);
        if ($length < 50 || $length > 160) {
            $issues[] = 'meta_description_length';
        }
    }

    $slug = (string)($page['slug'] ?? '');
    if ($slug === '' || !preg_match('/^[a-z0-9\-]+$/', $slug)) {
        $issues[] = 'slug_format';
    }

    if ($ogTitle === '' || $ogDescription === '' || $ogImage === '') {
        $issues[] = 'social_preview';
    }

    $pageId = isset($page['id']) ? (int)$page['id'] : null;
    $pageTitle = trim((string)($page['title'] ?? ''));
    if ($pageTitle === '') {
        $pageTitle = 'Untitled page';
    }

    $seoIssueLabels = [];
    $seoSeverityScore = 0;
    if (in_array('meta_title_missing', $issues, true)) {
        $seoIssueLabels[] = 'Missing meta title';
        $seoSeverityScore += 2;
    }
    if (in_array('meta_description_missing', $issues, true)) {
        $seoIssueLabels[] = 'Missing meta description';
        $seoSeverityScore += 2;
    }
    if (in_array('slug_format', $issues, true)) {
        $seoIssueLabels[] = 'Invalid slug formatting';
        $seoSeverityScore += 3;
    }

    if (!empty($seoIssueLabels)) {
        $seoIssuesList[] = [
            'id' => $pageId,
            'title' => $pageTitle,
            'issueTypes' => $seoIssueLabels,
            'severity' => dashboard_issue_severity($seoSeverityScore),
            'moduleTarget' => 'seo',
            'category' => 'seo',
            'actionUrl' => $pageId ? 'index.php?module=pages&action=edit&id=' . $pageId : null,
            'score' => $seoSeverityScore,
        ];
    }

    if (empty($issues)) {
        $seoSummary['optimized']++;
    } else {
        $seoSummary['needs_attention']++;
    }
}

$seoTotal = count($pages);
$seoScore = $seoTotal > 0 ? round(($seoSummary['optimized'] / $seoTotal) * 100) : 0;

$scriptBase = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
if (substr($scriptBase, -4) === '/CMS') {
    $scriptBase = substr($scriptBase, 0, -4);
}
$scriptBase = rtrim($scriptBase, '/');

$templateDir = realpath(__DIR__ . '/../../../theme/templates/pages');

function dashboard_capture_template_html(string $templateFile, array $settings, array $menus, string $scriptBase): string {
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

function dashboard_build_page_html(array $page, array $settings, array $menus, string $scriptBase, ?string $templateDir): string {
    static $templateCache = [];

    if (!$templateDir) {
        return (string)($page['content'] ?? '');
    }

    $templateName = !empty($page['template']) ? basename((string)$page['template']) : 'page.php';
    $templateFile = $templateDir . DIRECTORY_SEPARATOR . $templateName;
    if (!is_file($templateFile)) {
        return (string)($page['content'] ?? '');
    }

    if (!isset($templateCache[$templateFile])) {
        $templateCache[$templateFile] = dashboard_capture_template_html($templateFile, $settings, $menus, $scriptBase);
    }

    $templateHtml = $templateCache[$templateFile];
    $content = (string)($page['content'] ?? '');
    return str_replace('{{CONTENT}}', $content, $templateHtml);
}

function dashboard_count_menu_items(array $items): int
{
    $total = 0;
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $total++;
        if (!empty($item['children']) && is_array($item['children'])) {
            $total += dashboard_count_menu_items($item['children']);
        }
    }
    return $total;
}

function dashboard_format_bytes(int $bytes): string
{
    if ($bytes <= 0) {
        return '0 KB';
    }

    $units = ['bytes', 'KB', 'MB', 'GB'];
    $power = (int)floor(log($bytes, 1024));
    $power = max(0, min($power, count($units) - 1));
    $value = $bytes / (1024 ** $power);

    if ($power === 0) {
        return number_format($bytes) . ' ' . $units[$power];
    }

    return number_format($value, $value >= 10 ? 0 : 1) . ' ' . $units[$power];
}

function dashboard_format_number(int $value): string
{
    return number_format($value);
}

function dashboard_issue_severity(int $score): string
{
    if ($score >= 5) {
        return 'high';
    }
    if ($score >= 3) {
        return 'medium';
    }
    return 'low';
}

$libxmlPrevious = libxml_use_internal_errors(true);

$accessibilitySummary = [
    'accessible' => 0,
    'needs_review' => 0,
    'missing_alt' => 0,
    'issues' => 0,
];
$accessibilityIssuesList = [];

$genericLinkTerms = [
    'click here',
    'read more',
    'learn more',
    'here',
    'more',
    'this page',
];

foreach ($pages as $page) {
    $pageHtml = dashboard_build_page_html($page, $settings, $menus, $scriptBase, $templateDir);

    $doc = new DOMDocument();
    $loaded = trim($pageHtml) !== '' && $doc->loadHTML('<?xml encoding="utf-8" ?>' . $pageHtml);

    $missingAlt = 0;
    $genericLinks = 0;
    $landmarks = 0;
    $h1Count = 0;

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

    $pageId = isset($page['id']) ? (int)$page['id'] : null;
    $pageTitle = trim((string)($page['title'] ?? ''));
    if ($pageTitle === '') {
        $pageTitle = 'Untitled page';
    }

    $accessibilityIssueLabels = [];
    $accessibilitySeverityScore = 0;
    if ($missingAlt > 0) {
        $label = $missingAlt === 1 ? 'Image missing alt text' : $missingAlt . ' images missing alt text';
        $accessibilityIssueLabels[] = $label;
        $accessibilitySeverityScore += $missingAlt >= 5 ? 4 : 3;
    }
    if ($h1Count === 0) {
        $accessibilityIssueLabels[] = 'Missing H1 heading';
        $accessibilitySeverityScore += 3;
    } elseif ($h1Count > 1) {
        $accessibilityIssueLabels[] = $h1Count . ' H1 headings detected';
        $accessibilitySeverityScore += 2;
    }

    if (!empty($accessibilityIssueLabels)) {
        $accessibilityIssuesList[] = [
            'id' => $pageId,
            'title' => $pageTitle,
            'issueTypes' => $accessibilityIssueLabels,
            'severity' => dashboard_issue_severity($accessibilitySeverityScore),
            'moduleTarget' => 'accessibility',
            'category' => 'accessibility',
            'actionUrl' => $pageId ? 'index.php?module=pages&action=edit&id=' . $pageId : null,
            'score' => $accessibilitySeverityScore,
        ];
    }

    if (empty($issues)) {
        $accessibilitySummary['accessible']++;
    } else {
        $accessibilitySummary['needs_review']++;
    }

    $accessibilitySummary['issues'] += count($issues);
}

if (!empty($seoIssuesList)) {
    usort($seoIssuesList, function (array $a, array $b): int {
        return ($b['score'] ?? 0) <=> ($a['score'] ?? 0);
    });
    $seoIssuesList = array_map(function (array $item): array {
        unset($item['score']);
        return $item;
    }, array_slice($seoIssuesList, 0, 5));
}

if (!empty($accessibilityIssuesList)) {
    usort($accessibilityIssuesList, function (array $a, array $b): int {
        return ($b['score'] ?? 0) <=> ($a['score'] ?? 0);
    });
    $accessibilityIssuesList = array_map(function (array $item): array {
        unset($item['score']);
        return $item;
    }, array_slice($accessibilityIssuesList, 0, 5));
}

libxml_clear_errors();
libxml_use_internal_errors($libxmlPrevious);

$totalPages = count($pages);
$accessibilityScore = $totalPages > 0 ? round(($accessibilitySummary['accessible'] / $totalPages) * 100) : 0;

$pagesPublished = 0;
$pagesDraft = 0;
$largestPage = ['title' => null, 'length' => 0];
$speedSummary = [
    'fast' => 0,
    'monitor' => 0,
    'slow' => 0,
];

foreach ($pages as $page) {
    if (!empty($page['published'])) {
        $pagesPublished++;
    } else {
        $pagesDraft++;
    }

    $content = strip_tags((string)($page['content'] ?? ''));
    $length = strlen($content);
    if ($length > $largestPage['length']) {
        $largestPage = [
            'title' => (string)($page['title'] ?? ''),
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

$mediaTotalSize = 0;
foreach ($media as $item) {
    if (isset($item['size']) && is_numeric($item['size'])) {
        $mediaTotalSize += (int)$item['size'];
    }
}

$usersByRole = [];
foreach ($users as $user) {
    $role = strtolower((string)($user['role'] ?? 'unknown'));
    if ($role === '') {
        $role = 'unknown';
    }
    if (!isset($usersByRole[$role])) {
        $usersByRole[$role] = 0;
    }
    $usersByRole[$role]++;
}

$postsByStatus = [
    'published' => 0,
    'draft' => 0,
    'scheduled' => 0,
    'other' => 0,
];
foreach ($posts as $post) {
    $status = strtolower(trim((string)($post['status'] ?? '')));
    if ($status === '') {
        $status = 'other';
    }
    if (!array_key_exists($status, $postsByStatus)) {
        $status = 'other';
    }
    $postsByStatus[$status]++;
}

$formsFields = 0;
foreach ($forms as $form) {
    if (!empty($form['fields']) && is_array($form['fields'])) {
        $formsFields += count($form['fields']);
    }
}

$menuItems = 0;
foreach ($menus as $menu) {
    if (!empty($menu['items']) && is_array($menu['items'])) {
        $menuItems += dashboard_count_menu_items($menu['items']);
    }
}

$attentionItems = [];
if ($pagesDraft > 0) {
    $attentionItems[] = [
        'id' => 'pages_draft',
        'label' => 'Draft pages ready to publish',
        'count' => $pagesDraft,
        'moduleTarget' => 'pages',
        'category' => 'content',
        'actionUrl' => 'index.php?module=pages',
        'description' => 'Review drafts and publish updates when ready.',
    ];
}
if ($postsByStatus['draft'] > 0) {
    $attentionItems[] = [
        'id' => 'posts_draft',
        'label' => 'Blog drafts awaiting review',
        'count' => $postsByStatus['draft'],
        'moduleTarget' => 'blogs',
        'category' => 'content',
        'actionUrl' => 'index.php?module=blogs',
        'description' => 'Polish drafts and move them into the publishing queue.',
    ];
}
if ($postsByStatus['scheduled'] > 0) {
    $attentionItems[] = [
        'id' => 'posts_scheduled',
        'label' => 'Scheduled posts going live soon',
        'count' => $postsByStatus['scheduled'],
        'moduleTarget' => 'blogs',
        'category' => 'content',
        'actionUrl' => 'index.php?module=blogs',
        'description' => 'Confirm timing and make final edits before launch.',
    ];
}

$logEntries = 0;
$latestLogTime = null;
foreach ($history as $entries) {
    if (!is_array($entries)) {
        continue;
    }
    $logEntries += count($entries);
    foreach ($entries as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $time = isset($entry['time']) ? (int)$entry['time'] : null;
        if ($time) {
            if ($latestLogTime === null || $time > $latestLogTime) {
                $latestLogTime = $time;
            }
        }
    }
}
$logsLastActivity = $latestLogTime ? date('c', $latestLogTime) : null;

$searchBreakdown = [
    'pages' => $totalPages,
    'posts' => count($posts),
    'media' => count($media),
];
$searchIndexCount = array_sum($searchBreakdown);

$settingsCount = is_array($settings) ? count($settings) : 0;
$socialCount = (isset($settings['social']) && is_array($settings['social'])) ? count($settings['social']) : 0;

$sitemapEntries = 0;
foreach ($pages as $page) {
    if (!empty($page['published'])) {
        $sitemapEntries++;
    }
}

$topPage = null;
foreach ($pages as $page) {
    $pageViews = (int)($page['views'] ?? 0);
    if (!$topPage || $pageViews > $topPage['views']) {
        $topPage = [
            'title' => (string)($page['title'] ?? ''),
            'views' => $pageViews,
        ];
    }
}

$dataFiles = glob($dataDirectory . '/*.json');
$dataFileCount = is_array($dataFiles) ? count($dataFiles) : 0;

$analyticsSummary = [
    'totalViews' => $views,
    'averageViews' => $totalPages > 0 ? (int)round($views / $totalPages) : 0,
    'topPage' => $topPage['title'] ?? null,
    'topViews' => $topPage['views'] ?? 0,
];

$moduleSummaries = [
    [
        'id' => 'pages',
        'module' => 'Pages',
        'primary' => dashboard_format_number($totalPages) . ' total pages',
        'secondary' => 'Published: ' . dashboard_format_number($pagesPublished) . ' • Drafts: ' . dashboard_format_number($pagesDraft),
    ],
    [
        'id' => 'media',
        'module' => 'Media',
        'primary' => dashboard_format_number(count($media)) . ' files',
        'secondary' => 'Library size: ' . dashboard_format_bytes($mediaTotalSize),
    ],
    [
        'id' => 'blogs',
        'module' => 'Blogs',
        'primary' => dashboard_format_number(count($posts)) . ' posts',
        'secondary' => 'Published: ' . dashboard_format_number($postsByStatus['published']) . ' • Draft: ' . dashboard_format_number($postsByStatus['draft']) . ' • Scheduled: ' . dashboard_format_number($postsByStatus['scheduled']),
    ],
    [
        'id' => 'forms',
        'module' => 'Forms',
        'primary' => dashboard_format_number(count($forms)) . ' forms',
        'secondary' => 'Fields configured: ' . dashboard_format_number($formsFields),
    ],
    [
        'id' => 'menus',
        'module' => 'Menus',
        'primary' => dashboard_format_number(count($menus)) . ' menus',
        'secondary' => 'Navigation items: ' . dashboard_format_number($menuItems),
    ],
    [
        'id' => 'users',
        'module' => 'Users',
        'primary' => dashboard_format_number(count($users)) . ' users',
        'secondary' => 'Admins: ' . dashboard_format_number($usersByRole['admin'] ?? 0) . ' • Editors: ' . dashboard_format_number($usersByRole['editor'] ?? 0),
    ],
    [
        'id' => 'analytics',
        'module' => 'Analytics',
        'primary' => dashboard_format_number($analyticsSummary['totalViews']) . ' total views',
        'secondary' => $analyticsSummary['topPage'] ? 'Top page: ' . $analyticsSummary['topPage'] . ' (' . dashboard_format_number($analyticsSummary['topViews']) . ')' : 'No views recorded yet',
    ],
    [
        'id' => 'seo',
        'module' => 'SEO',
        'primary' => dashboard_format_number($seoSummary['optimized']) . ' optimized pages',
        'secondary' => 'Needs attention: ' . dashboard_format_number($seoSummary['needs_attention']) . ' • Metadata gaps: ' . dashboard_format_number($seoSummary['metadata_gaps']),
    ],
    [
        'id' => 'accessibility',
        'module' => 'Accessibility',
        'primary' => dashboard_format_number($accessibilitySummary['accessible']) . ' compliant pages',
        'secondary' => 'Alt text issues: ' . dashboard_format_number($accessibilitySummary['missing_alt']),
    ],
    [
        'id' => 'logs',
        'module' => 'Logs',
        'primary' => dashboard_format_number($logEntries) . ' history entries',
        'secondary' => $logsLastActivity ? 'Last activity: ' . $logsLastActivity : 'No activity recorded yet',
    ],
    [
        'id' => 'search',
        'module' => 'Search',
        'primary' => dashboard_format_number($searchIndexCount) . ' indexed records',
        'secondary' => 'Pages: ' . dashboard_format_number($searchBreakdown['pages']) . ' • Posts: ' . dashboard_format_number($searchBreakdown['posts']) . ' • Media: ' . dashboard_format_number($searchBreakdown['media']),
    ],
    [
        'id' => 'settings',
        'module' => 'Settings',
        'primary' => dashboard_format_number($settingsCount) . ' configuration values',
        'secondary' => 'Social profiles: ' . dashboard_format_number($socialCount),
    ],
    [
        'id' => 'sitemap',
        'module' => 'Sitemap',
        'primary' => dashboard_format_number($sitemapEntries) . ' published URLs',
        'secondary' => 'Ready for export to sitemap.xml',
    ],
    [
        'id' => 'speed',
        'module' => 'Speed',
        'primary' => 'Fast: ' . dashboard_format_number($speedSummary['fast']) . ' • Monitor: ' . dashboard_format_number($speedSummary['monitor']) . ' • Slow: ' . dashboard_format_number($speedSummary['slow']),
        'secondary' => $largestPage['title'] ? 'Heaviest content: ' . $largestPage['title'] : 'Content analysis based on page length',
    ],
    [
        'id' => 'import_export',
        'module' => 'Import/Export',
        'primary' => dashboard_format_number($dataFileCount) . ' data files detected',
        'secondary' => 'Use tools to migrate or backup your site',
    ],
];

$data = [
    'pages' => $totalPages,
    'pagesPublished' => $pagesPublished,
    'pagesDraft' => $pagesDraft,
    'media' => count($media),
    'mediaSize' => $mediaTotalSize,
    'users' => count($users),
    'usersAdmins' => $usersByRole['admin'] ?? 0,
    'usersEditors' => $usersByRole['editor'] ?? 0,
    'views' => $views,
    'analyticsAvgViews' => $analyticsSummary['averageViews'],
    'analyticsTopPage' => $analyticsSummary['topPage'],
    'analyticsTopViews' => $analyticsSummary['topViews'],
    'blogsTotal' => count($posts),
    'blogsPublished' => $postsByStatus['published'],
    'blogsDraft' => $postsByStatus['draft'],
    'blogsScheduled' => $postsByStatus['scheduled'],
    'formsTotal' => count($forms),
    'formsFields' => $formsFields,
    'menusCount' => count($menus),
    'menuItems' => $menuItems,
    'seoScore' => $seoScore,
    'seoOptimized' => $seoSummary['optimized'],
    'seoNeedsAttention' => $seoSummary['needs_attention'],
    'seoMetadataGaps' => $seoSummary['metadata_gaps'],
    'accessibilityScore' => $accessibilityScore,
    'accessibilityCompliant' => $accessibilitySummary['accessible'],
    'accessibilityNeedsReview' => $accessibilitySummary['needs_review'],
    'accessibilityMissingAlt' => $accessibilitySummary['missing_alt'],
    'openAlerts' => $seoSummary['needs_attention'] + $accessibilitySummary['needs_review'],
    'alertsSeo' => $seoSummary['needs_attention'],
    'alertsAccessibility' => $accessibilitySummary['needs_review'],
    'logsEntries' => $logEntries,
    'logsLastActivity' => $logsLastActivity,
    'searchIndex' => $searchIndexCount,
    'searchBreakdown' => $searchBreakdown,
    'settingsCount' => $settingsCount,
    'settingsSocialLinks' => $socialCount,
    'sitemapEntries' => $sitemapEntries,
    'speedFast' => $speedSummary['fast'],
    'speedMonitor' => $speedSummary['monitor'],
    'speedSlow' => $speedSummary['slow'],
    'speedHeaviestPage' => $largestPage['title'],
    'speedHeaviestPageLength' => $largestPage['length'],
    'dataFileCount' => $dataFileCount,
    'moduleSummaries' => $moduleSummaries,
    'seoIssues' => $seoIssuesList,
    'accessibilityIssues' => $accessibilityIssuesList,
    'attentionItems' => $attentionItems,
];

header('Content-Type: application/json');
echo json_encode($data);
