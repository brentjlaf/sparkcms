<?php
// File: dashboard_data.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/settings.php';
require_login();

$pagesFile = __DIR__ . '/../../data/pages.json';
$mediaFile = __DIR__ . '/../../data/media.json';
$usersFile = __DIR__ . '/../../data/users.json';
$menusFile = __DIR__ . '/../../data/menus.json';
$formsFile = __DIR__ . '/../../data/forms.json';
$postsFile = __DIR__ . '/../../data/blog_posts.json';
$historyFile = __DIR__ . '/../../data/page_history.json';
$dataDirectory = __DIR__ . '/../../data';

$pages = read_json_file($pagesFile);
$media = read_json_file($mediaFile);
$users = read_json_file($usersFile);
$settings = get_site_settings();
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

function dashboard_status_label(string $status): string
{
    switch ($status) {
        case 'urgent':
            return 'Action required';
        case 'warning':
            return 'Needs attention';
        default:
            return 'On track';
    }
}

$libxmlPrevious = libxml_use_internal_errors(true);

$accessibilitySummary = [
    'accessible' => 0,
    'needs_review' => 0,
    'missing_alt' => 0,
    'issues' => 0,
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

    if (empty($issues)) {
        $accessibilitySummary['accessible']++;
    } else {
        $accessibilitySummary['needs_review']++;
    }

    $accessibilitySummary['issues'] += count($issues);
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

$pagesStatus = 'ok';
if ($totalPages === 0) {
    $pagesStatus = 'urgent';
} elseif ($pagesDraft > 0) {
    $pagesStatus = 'warning';
}
$pagesTrend = $pagesDraft > 0
    ? dashboard_format_number($pagesDraft) . ' drafts awaiting review'
    : 'All pages published';
$pagesCta = $totalPages === 0
    ? 'Create your first page'
    : ($pagesDraft > 0 ? 'Review drafts' : 'Manage pages');

$mediaCount = count($media);
$mediaStatus = $mediaCount === 0 ? 'urgent' : 'ok';
$mediaTrend = $mediaCount === 0
    ? 'Library is empty'
    : dashboard_format_bytes($mediaTotalSize) . ' stored';
$mediaCta = $mediaCount === 0 ? 'Upload media' : 'Open media library';

$postsTotal = count($posts);
$postsDraft = (int)$postsByStatus['draft'];
$postsScheduled = (int)$postsByStatus['scheduled'];
$blogsStatus = 'ok';
if ($postsTotal === 0) {
    $blogsStatus = 'urgent';
} elseif ($postsDraft > 0 || $postsScheduled > 0) {
    $blogsStatus = 'warning';
}
$blogsTrend = $postsDraft > 0
    ? dashboard_format_number($postsDraft) . ' drafts awaiting publication'
    : ($postsScheduled > 0
        ? dashboard_format_number($postsScheduled) . ' posts scheduled'
        : 'Publishing cadence on track');
$blogsCta = $postsTotal === 0 ? 'Write your first post' : ($postsDraft > 0 ? 'Publish drafts' : 'Manage posts');

$formsCount = count($forms);
$formsStatus = $formsCount === 0 ? 'urgent' : 'ok';
$formsTrend = 'Fields configured: ' . dashboard_format_number((int)$formsFields);
$formsCta = $formsCount === 0 ? 'Create a form' : 'Review submissions';

$menusCount = count($menus);
$menusStatus = $menuItems === 0 ? 'urgent' : 'ok';
$menusTrend = $menuItems === 0
    ? 'No navigation items configured'
    : dashboard_format_number((int)$menuItems) . ' navigation items live';
$menusCta = $menusCount === 0 ? 'Create a menu' : 'Manage navigation';

$usersCount = count($users);
$adminCount = (int)($usersByRole['admin'] ?? 0);
$editorCount = (int)($usersByRole['editor'] ?? 0);
$usersStatus = $adminCount === 0 ? 'urgent' : ($usersCount === 0 ? 'urgent' : 'ok');
$usersTrend = $editorCount > 0
    ? dashboard_format_number($editorCount) . ' editors collaborating'
    : 'Invite collaborators to join';
$usersCta = $adminCount === 0 ? 'Add an admin' : 'Manage team';

$analyticsStatus = $analyticsSummary['totalViews'] === 0 ? 'warning' : 'ok';
$analyticsTrend = 'Average views per page: ' . dashboard_format_number((int)$analyticsSummary['averageViews']);
$analyticsCta = $analyticsSummary['totalViews'] === 0 ? 'Set up tracking' : 'Explore analytics';

$accessibilityStatus = 'ok';
if ($accessibilitySummary['needs_review'] > 0 || $accessibilitySummary['missing_alt'] > 0) {
    $accessibilityStatus = 'warning';
}
if ($accessibilitySummary['accessible'] === 0 && ($accessibilitySummary['needs_review'] > 0 || $accessibilitySummary['missing_alt'] > 0)) {
    $accessibilityStatus = 'urgent';
}
$accessibilityTrend = $accessibilitySummary['missing_alt'] > 0
    ? dashboard_format_number($accessibilitySummary['missing_alt']) . ' images missing alt text'
    : 'Alt text coverage looks good';
$accessibilityCta = $accessibilitySummary['needs_review'] > 0 || $accessibilitySummary['missing_alt'] > 0
    ? 'Audit accessibility'
    : 'Review accessibility';

$logsStatus = $logEntries === 0 ? 'warning' : 'ok';
$logsTrend = $logsLastActivity ? 'Last activity ' . $logsLastActivity : 'No activity recorded yet';
$logsCta = 'View history';

$searchStatus = $searchIndexCount === 0 ? 'urgent' : 'ok';
$searchTrend = 'Indexed records: ' . dashboard_format_number((int)$searchIndexCount);
$searchCta = $searchIndexCount === 0 ? 'Build the search index' : 'Manage search index';

$settingsStatus = $socialCount === 0 ? 'warning' : 'ok';
$settingsTrend = $socialCount === 0
    ? 'No social links configured'
    : dashboard_format_number((int)$socialCount) . ' social links live';
$settingsCta = $socialCount === 0 ? 'Add social links' : 'Adjust settings';

$sitemapStatus = $sitemapEntries === 0 ? 'warning' : 'ok';
$sitemapTrend = $sitemapEntries === 0
    ? 'Publish pages to populate the sitemap'
    : dashboard_format_number((int)$sitemapEntries) . ' URLs ready for sitemap.xml';
$sitemapCta = $sitemapEntries === 0 ? 'Publish pages' : 'Review sitemap';

$speedStatus = 'ok';
if ($speedSummary['slow'] > 0) {
    $speedStatus = $speedSummary['slow'] >= $speedSummary['fast'] ? 'urgent' : 'warning';
} elseif ($speedSummary['monitor'] > 0) {
    $speedStatus = 'warning';
}
$speedTrend = 'Slow pages: ' . dashboard_format_number((int)$speedSummary['slow']);
$speedCta = $speedSummary['slow'] > 0 ? 'Optimise slow pages' : 'Review performance';

$importExportStatus = 'ok';
$importExportTrend = $dataFileCount > 0
    ? dashboard_format_number((int)$dataFileCount) . ' data files available'
    : 'No data files detected';
$importExportCta = 'Open import/export';

$moduleSummaries = [
    [
        'id' => 'pages',
        'module' => 'Pages',
        'primary' => dashboard_format_number($totalPages) . ' total pages',
        'secondary' => 'Published: ' . dashboard_format_number($pagesPublished) . ' • Drafts: ' . dashboard_format_number($pagesDraft),
        'status' => $pagesStatus,
        'statusLabel' => dashboard_status_label($pagesStatus),
        'trend' => $pagesTrend,
        'cta' => $pagesCta,
    ],
    [
        'id' => 'media',
        'module' => 'Media',
        'primary' => dashboard_format_number($mediaCount) . ' files',
        'secondary' => 'Library size: ' . dashboard_format_bytes($mediaTotalSize),
        'status' => $mediaStatus,
        'statusLabel' => dashboard_status_label($mediaStatus),
        'trend' => $mediaTrend,
        'cta' => $mediaCta,
    ],
    [
        'id' => 'blogs',
        'module' => 'Blogs',
        'primary' => dashboard_format_number($postsTotal) . ' posts',
        'secondary' => 'Published: ' . dashboard_format_number($postsByStatus['published']) . ' • Draft: ' . dashboard_format_number($postsByStatus['draft']) . ' • Scheduled: ' . dashboard_format_number($postsByStatus['scheduled']),
        'status' => $blogsStatus,
        'statusLabel' => dashboard_status_label($blogsStatus),
        'trend' => $blogsTrend,
        'cta' => $blogsCta,
    ],
    [
        'id' => 'forms',
        'module' => 'Forms',
        'primary' => dashboard_format_number($formsCount) . ' forms',
        'secondary' => 'Fields configured: ' . dashboard_format_number($formsFields),
        'status' => $formsStatus,
        'statusLabel' => dashboard_status_label($formsStatus),
        'trend' => $formsTrend,
        'cta' => $formsCta,
    ],
    [
        'id' => 'menus',
        'module' => 'Menus',
        'primary' => dashboard_format_number($menusCount) . ' menus',
        'secondary' => 'Navigation items: ' . dashboard_format_number($menuItems),
        'status' => $menusStatus,
        'statusLabel' => dashboard_status_label($menusStatus),
        'trend' => $menusTrend,
        'cta' => $menusCta,
    ],
    [
        'id' => 'users',
        'module' => 'Users',
        'primary' => dashboard_format_number($usersCount) . ' users',
        'secondary' => 'Admins: ' . dashboard_format_number($adminCount) . ' • Editors: ' . dashboard_format_number($editorCount),
        'status' => $usersStatus,
        'statusLabel' => dashboard_status_label($usersStatus),
        'trend' => $usersTrend,
        'cta' => $usersCta,
    ],
    [
        'id' => 'analytics',
        'module' => 'Analytics',
        'primary' => dashboard_format_number($analyticsSummary['totalViews']) . ' total views',
        'secondary' => $analyticsSummary['topPage'] ? 'Top page: ' . $analyticsSummary['topPage'] . ' (' . dashboard_format_number($analyticsSummary['topViews']) . ')' : 'No views recorded yet',
        'status' => $analyticsStatus,
        'statusLabel' => dashboard_status_label($analyticsStatus),
        'trend' => $analyticsTrend,
        'cta' => $analyticsCta,
    ],
    [
        'id' => 'accessibility',
        'module' => 'Accessibility',
        'primary' => dashboard_format_number($accessibilitySummary['accessible']) . ' compliant pages',
        'secondary' => 'Alt text issues: ' . dashboard_format_number($accessibilitySummary['missing_alt']),
        'status' => $accessibilityStatus,
        'statusLabel' => dashboard_status_label($accessibilityStatus),
        'trend' => $accessibilityTrend,
        'cta' => $accessibilityCta,
    ],
    [
        'id' => 'logs',
        'module' => 'Logs',
        'primary' => dashboard_format_number($logEntries) . ' history entries',
        'secondary' => $logsLastActivity ? 'Last activity: ' . $logsLastActivity : 'No activity recorded yet',
        'status' => $logsStatus,
        'statusLabel' => dashboard_status_label($logsStatus),
        'trend' => $logsTrend,
        'cta' => $logsCta,
    ],
    [
        'id' => 'search',
        'module' => 'Search',
        'primary' => dashboard_format_number($searchIndexCount) . ' indexed records',
        'secondary' => 'Pages: ' . dashboard_format_number($searchBreakdown['pages']) . ' • Posts: ' . dashboard_format_number($searchBreakdown['posts']) . ' • Media: ' . dashboard_format_number($searchBreakdown['media']),
        'status' => $searchStatus,
        'statusLabel' => dashboard_status_label($searchStatus),
        'trend' => $searchTrend,
        'cta' => $searchCta,
    ],
    [
        'id' => 'settings',
        'module' => 'Settings',
        'primary' => dashboard_format_number($settingsCount) . ' configuration values',
        'secondary' => 'Social profiles: ' . dashboard_format_number($socialCount),
        'status' => $settingsStatus,
        'statusLabel' => dashboard_status_label($settingsStatus),
        'trend' => $settingsTrend,
        'cta' => $settingsCta,
    ],
    [
        'id' => 'sitemap',
        'module' => 'Sitemap',
        'primary' => dashboard_format_number($sitemapEntries) . ' published URLs',
        'secondary' => 'Ready for export to sitemap.xml',
        'status' => $sitemapStatus,
        'statusLabel' => dashboard_status_label($sitemapStatus),
        'trend' => $sitemapTrend,
        'cta' => $sitemapCta,
    ],
    [
        'id' => 'speed',
        'module' => 'Speed',
        'primary' => 'Fast: ' . dashboard_format_number($speedSummary['fast']) . ' • Monitor: ' . dashboard_format_number($speedSummary['monitor']) . ' • Slow: ' . dashboard_format_number($speedSummary['slow']),
        'secondary' => $largestPage['title'] ? 'Heaviest content: ' . $largestPage['title'] : 'Content analysis based on page length',
        'status' => $speedStatus,
        'statusLabel' => dashboard_status_label($speedStatus),
        'trend' => $speedTrend,
        'cta' => $speedCta,
    ],
    [
        'id' => 'import_export',
        'module' => 'Import/Export',
        'primary' => dashboard_format_number($dataFileCount) . ' data files detected',
        'secondary' => 'Use tools to migrate or backup your site',
        'status' => $importExportStatus,
        'statusLabel' => dashboard_status_label($importExportStatus),
        'trend' => $importExportTrend,
        'cta' => $importExportCta,
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
    'accessibilityScore' => $accessibilityScore,
    'accessibilityCompliant' => $accessibilitySummary['accessible'],
    'accessibilityNeedsReview' => $accessibilitySummary['needs_review'],
    'accessibilityMissingAlt' => $accessibilitySummary['missing_alt'],
    'openAlerts' => $accessibilitySummary['needs_review'],
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
];

header('Content-Type: application/json');
echo json_encode($data);
