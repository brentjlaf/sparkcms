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
$blogPostsFile = __DIR__ . '/../../data/blog_posts.json';
$formsFile = __DIR__ . '/../../data/forms.json';
$historyFile = __DIR__ . '/../../data/page_history.json';

$sitemapFile = realpath(__DIR__ . '/../../../sitemap.xml');

$pages = read_json_file($pagesFile);
$media = read_json_file($mediaFile);
$users = read_json_file($usersFile);
$settings = read_json_file($settingsFile);
$menus = read_json_file($menusFile);
$blogPosts = read_json_file($blogPostsFile);
$forms = read_json_file($formsFile);
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
if (!is_array($blogPosts)) {
    $blogPosts = [];
}
if (!is_array($forms)) {
    $forms = [];
}
if (!is_array($history)) {
    $history = [];
}

$views = 0;
$publishedPages = 0;
$draftPages = 0;
$scheduledPages = 0;
foreach ($pages as $p) {
    $views += $p['views'] ?? 0;
    $isPublished = !empty($p['published']);
    if ($isPublished) {
        $publishedPages++;
    } else {
        $draftPages++;
    }

    $status = strtolower((string)($p['status'] ?? ''));
    if ($status === 'scheduled') {
        $scheduledPages++;
    }
}

$averageViews = $totalPages = count($pages);
$averageViews = $totalPages > 0 ? round($views / $totalPages) : 0;

$blogStatusCounts = [
    'published' => 0,
    'draft' => 0,
    'scheduled' => 0,
];

foreach ($blogPosts as $post) {
    $status = strtolower((string)($post['status'] ?? ''));
    if (isset($blogStatusCounts[$status])) {
        $blogStatusCounts[$status]++;
    }
}

$formFieldTotals = 0;
$formRequiredFields = 0;
foreach ($forms as $form) {
    if (!empty($form['fields']) && is_array($form['fields'])) {
        foreach ($form['fields'] as $field) {
            $formFieldTotals++;
            if (!empty($field['required'])) {
                $formRequiredFields++;
            }
        }
    }
}

function dashboard_count_menu_items(array $items, int &$nestedGroups = 0): int {
    $count = 0;
    foreach ($items as $item) {
        $count++;
        if (!empty($item['children']) && is_array($item['children'])) {
            $nestedGroups++;
            $count += dashboard_count_menu_items($item['children'], $nestedGroups);
        }
    }
    return $count;
}

$menuNestedGroups = 0;
$menuItems = 0;
foreach ($menus as $menu) {
    if (!empty($menu['items']) && is_array($menu['items'])) {
        $menuItems += dashboard_count_menu_items($menu['items'], $menuNestedGroups);
    }
}

$logEntries = 0;
$lastLogTimestamp = null;
foreach ($history as $entries) {
    if (!is_array($entries)) {
        continue;
    }
    foreach ($entries as $entry) {
        $logEntries++;
        $time = isset($entry['time']) ? (int)$entry['time'] : 0;
        if ($time > 0) {
            $lastLogTimestamp = $lastLogTimestamp === null ? $time : max($lastLogTimestamp, $time);
        }
    }
}

$lastLogFormatted = $lastLogTimestamp ? date('M j, Y H:i', $lastLogTimestamp) : null;

$userRoles = [
    'admin' => 0,
    'editor' => 0,
    'other' => 0,
];
$activeUsers = 0;
$inactiveUsers = 0;
foreach ($users as $user) {
    $role = strtolower((string)($user['role'] ?? ''));
    if (isset($userRoles[$role])) {
        $userRoles[$role]++;
    } else {
        $userRoles['other']++;
    }

    if (isset($user['status']) && $user['status'] === 'active') {
        $activeUsers++;
    } else {
        $inactiveUsers++;
    }
}

usort($pages, function (array $a, array $b): int {
    $aViews = (int)($a['views'] ?? 0);
    $bViews = (int)($b['views'] ?? 0);
    return $bViews <=> $aViews;
});

$topPages = [];
foreach (array_slice($pages, 0, 5) as $page) {
    $topPages[] = [
        'title' => (string)($page['title'] ?? 'Untitled Page'),
        'slug' => (string)($page['slug'] ?? ''),
        'views' => (int)($page['views'] ?? 0),
        'published' => !empty($page['published']),
    ];
}

$settingsCount = is_array($settings) ? count($settings) : 0;
$socialProfiles = 0;
if (!empty($settings['social']) && is_array($settings['social'])) {
    foreach ($settings['social'] as $profile) {
        if (is_string($profile) && trim($profile) !== '') {
            $socialProfiles++;
        }
    }
}

$sitemapUrlCount = $publishedPages;
$sitemapLastGenerated = $sitemapFile && is_file($sitemapFile) ? date('M j, Y H:i', filemtime($sitemapFile)) : null;

$seoSummary = [
    'optimized' => 0,
    'needs_attention' => 0,
    'metadata_gaps' => 0,
];

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

$data = [
    'pages' => $totalPages,
    'media' => count($media),
    'users' => count($users),
    'views' => $views,
    'averageViews' => $averageViews,
    'pagesPublished' => $publishedPages,
    'pagesDrafts' => $draftPages,
    'pagesScheduled' => $scheduledPages,
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
    'blogsTotal' => count($blogPosts),
    'blogsPublished' => $blogStatusCounts['published'],
    'blogsDrafts' => $blogStatusCounts['draft'],
    'blogsScheduled' => $blogStatusCounts['scheduled'],
    'formsTotal' => count($forms),
    'formsFields' => $formFieldTotals,
    'formsRequiredFields' => $formRequiredFields,
    'menusTotal' => count($menus),
    'menusItems' => $menuItems,
    'menusNestedGroups' => $menuNestedGroups,
    'logsEntries' => $logEntries,
    'logsLastActivity' => $lastLogFormatted,
    'usersActive' => $activeUsers,
    'usersInactive' => $inactiveUsers,
    'usersAdmins' => $userRoles['admin'],
    'usersEditors' => $userRoles['editor'],
    'usersOtherRoles' => $userRoles['other'],
    'settingsCount' => $settingsCount,
    'settingsSocialProfiles' => $socialProfiles,
    'sitemapUrls' => $sitemapUrlCount,
    'sitemapLastGenerated' => $sitemapLastGenerated,
    'topPages' => $topPages,
];

header('Content-Type: application/json');
echo json_encode($data);
