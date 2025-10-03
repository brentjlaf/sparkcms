<?php
// File: dashboard_data.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/settings.php';
require_once __DIR__ . '/../../includes/template_renderer.php';
require_login();

$pagesFile = __DIR__ . '/../../data/pages.json';
$mediaFile = __DIR__ . '/../../data/media.json';
$usersFile = __DIR__ . '/../../data/users.json';
$menusFile = __DIR__ . '/../../data/menus.json';
$formsFile = __DIR__ . '/../../data/forms.json';
$postsFile = __DIR__ . '/../../data/blog_posts.json';
$historyFile = __DIR__ . '/../../data/page_history.json';
$eventsFile = __DIR__ . '/../../data/events.json';
$eventOrdersFile = __DIR__ . '/../../data/event_orders.json';
$calendarEventsFile = __DIR__ . '/../../data/calendar_events.json';
$calendarCategoriesFile = __DIR__ . '/../../data/calendar_categories.json';
$commerceFile = __DIR__ . '/../../data/commerce.json';
$dataDirectory = __DIR__ . '/../../data';

$pages = read_json_file($pagesFile);
$media = read_json_file($mediaFile);
$users = read_json_file($usersFile);
$settings = get_site_settings();
$menus = read_json_file($menusFile);
$forms = read_json_file($formsFile);
$posts = read_json_file($postsFile);
$history = read_json_file($historyFile);
$events = read_json_file($eventsFile);
$eventOrders = read_json_file($eventOrdersFile);
$calendarEvents = read_json_file($calendarEventsFile);
$calendarCategories = read_json_file($calendarCategoriesFile);
$commerce = read_json_file($commerceFile);

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
if (!is_array($events)) {
    $events = [];
}
if (!is_array($eventOrders)) {
    $eventOrders = [];
}
if (!is_array($calendarEvents)) {
    $calendarEvents = [];
}
if (!is_array($calendarCategories)) {
    $calendarCategories = [];
}
if (!is_array($commerce)) {
    $commerce = [];
}

$events = array_values(array_filter($events, 'is_array'));
$eventOrders = array_values(array_filter($eventOrders, 'is_array'));
$calendarEvents = array_values(array_filter($calendarEvents, 'is_array'));
$calendarCategories = array_values(array_filter($calendarCategories, 'is_array'));

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

function dashboard_currency_symbol(string $currency): string
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

function dashboard_format_currency(float $amount, string $currency = 'USD'): string
{
    $symbol = dashboard_currency_symbol($currency);
    $formatted = number_format($amount, 2);

    return $symbol . $formatted;
}

function dashboard_strlen(string $value): int
{
    if (function_exists('mb_strlen')) {
        return (int)mb_strlen($value);
    }

    return strlen($value);
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

$seoSummary = [
    'optimised' => 0,
    'missing_title' => 0,
    'missing_description' => 0,
    'long_title' => 0,
    'description_length' => 0,
    'duplicate_slugs' => 0,
    'issues' => 0,
];

$slugCounts = [];
foreach ($pages as $page) {
    $slug = strtolower(trim((string)($page['slug'] ?? '')));
    if ($slug === '') {
        continue;
    }
    if (!isset($slugCounts[$slug])) {
        $slugCounts[$slug] = 0;
    }
    $slugCounts[$slug]++;
}

$genericLinkTerms = [
    'click here',
    'read more',
    'learn more',
    'here',
    'more',
    'this page',
];

foreach ($pages as $page) {
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

    $metaTitle = trim((string)($page['meta_title'] ?? ''));
    if ($metaTitle === '') {
        $seoSummary['missing_title']++;
        $seoIssues++;
    } else {
        $titleLength = dashboard_strlen($metaTitle);
        if ($titleLength > 60) {
            $seoSummary['long_title']++;
            $seoIssues++;
        }
    }

    $metaDescription = trim((string)($page['meta_description'] ?? ''));
    if ($metaDescription === '') {
        $seoSummary['missing_description']++;
        $seoIssues++;
    } else {
        $descriptionLength = dashboard_strlen($metaDescription);
        if ($descriptionLength < 50 || $descriptionLength > 160) {
            $seoSummary['description_length']++;
            $seoIssues++;
        }
    }

    if ($seoIssues === 0) {
        $seoSummary['optimised']++;
    }

    $seoSummary['issues'] += $seoIssues;
}

libxml_clear_errors();
libxml_use_internal_errors($libxmlPrevious);

foreach ($slugCounts as $slug => $count) {
    if ($count > 1) {
        $seoSummary['duplicate_slugs'] += $count - 1;
        $seoSummary['issues'] += $count - 1;
    }
}

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

$eventsTotal = count($events);
$eventsPublished = 0;
$eventsUpcoming = 0;
$eventsTicketsSold = 0;
$eventsRevenue = 0.0;
$eventsPendingOrders = 0;
$eventsCurrency = 'USD';

$commerceSettings = [];
if (isset($commerce['settings']) && is_array($commerce['settings'])) {
    $commerceSettings = $commerce['settings'];
}
if (!empty($commerceSettings['currency'])) {
    $eventsCurrency = strtoupper((string)$commerceSettings['currency']);
}

$now = time();
foreach ($events as $event) {
    $status = strtolower(trim((string)($event['status'] ?? 'draft')));
    if ($status === 'published') {
        $eventsPublished++;
    }

    $start = isset($event['start']) ? strtotime((string)$event['start']) : false;
    if ($status === 'published' && $start !== false && $start >= $now) {
        $eventsUpcoming++;
    }
}

foreach ($eventOrders as $order) {
    $status = strtolower(trim((string)($order['status'] ?? 'paid')));
    $amount = isset($order['amount']) ? (float)$order['amount'] : 0.0;
    $tickets = 0;
    if (!empty($order['tickets']) && is_array($order['tickets'])) {
        foreach ($order['tickets'] as $ticket) {
            if (!is_array($ticket)) {
                continue;
            }
            $tickets += max(0, (int)($ticket['quantity'] ?? 0));
        }
    }

    if ($status === 'refunded') {
        continue;
    }

    $eventsTicketsSold += $tickets;
    $eventsRevenue += $amount;

    if ($status !== 'paid' && $status !== 'completed') {
        $eventsPendingOrders++;
    }
}

$calendarTotal = count($calendarEvents);
$calendarUpcoming = 0;
$calendarRecurring = 0;
$calendarNextEvent = null;
foreach ($calendarEvents as $calendarEvent) {
    $startDateRaw = isset($calendarEvent['start_date']) ? (string)$calendarEvent['start_date'] : '';
    $startDate = $startDateRaw !== '' ? strtotime($startDateRaw) : false;
    if ($startDate !== false && $startDate >= $now) {
        $calendarUpcoming++;
        if ($calendarNextEvent === null || $startDate < $calendarNextEvent['time']) {
            $calendarNextEvent = [
                'title' => (string)($calendarEvent['title'] ?? ''),
                'time' => $startDate,
            ];
        }
    }

    $interval = strtolower(trim((string)($calendarEvent['recurring_interval'] ?? 'none')));
    if ($interval !== '' && $interval !== 'none') {
        $calendarRecurring++;
    }
}
$calendarCategoryCount = count($calendarCategories);

$commerceSummary = isset($commerce['summary']) && is_array($commerce['summary']) ? $commerce['summary'] : [];
$commerceCatalog = isset($commerce['catalog']) && is_array($commerce['catalog'])
    ? array_values(array_filter($commerce['catalog'], 'is_array'))
    : [];
$commerceOrdersList = isset($commerce['orders']) && is_array($commerce['orders'])
    ? array_values(array_filter($commerce['orders'], 'is_array'))
    : [];

$commerceCurrency = $eventsCurrency;
if (!empty($commerceSettings['currency'])) {
    $commerceCurrency = strtoupper((string)$commerceSettings['currency']);
}
$lowInventoryThreshold = isset($commerceSettings['low_inventory_threshold'])
    ? (int)$commerceSettings['low_inventory_threshold']
    : 10;

$commerceRevenue = isset($commerceSummary['total_revenue']) ? (float)$commerceSummary['total_revenue'] : 0.0;
$commerceOrders = isset($commerceSummary['orders']) ? (int)$commerceSummary['orders'] : count($commerceOrdersList);
$commerceAverageOrder = isset($commerceSummary['average_order_value']) ? (float)$commerceSummary['average_order_value'] : 0.0;
$commerceConversionRate = isset($commerceSummary['conversion_rate']) ? (float)$commerceSummary['conversion_rate'] : 0.0;
$commerceRefundRate = isset($commerceSummary['refund_rate']) ? (float)$commerceSummary['refund_rate'] : 0.0;

$commerceLowStock = 0;
foreach ($commerceCatalog as $product) {
    $inventory = isset($product['inventory']) ? (int)$product['inventory'] : 0;
    if ($inventory <= $lowInventoryThreshold) {
        $commerceLowStock++;
    }
}

$commercePendingOrders = 0;
$commerceRefundRequests = 0;
foreach ($commerceOrdersList as $order) {
    $status = strtolower(trim((string)($order['status'] ?? '')));
    if ($status === '') {
        continue;
    }
    if (strpos($status, 'pending') !== false || strpos($status, 'processing') !== false || strpos($status, 'awaiting') !== false) {
        $commercePendingOrders++;
    }
    if (strpos($status, 'refund') !== false) {
        $commerceRefundRequests++;
    }
}

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

$seoStatus = 'ok';
if ($seoSummary['missing_title'] > 0 || $seoSummary['missing_description'] > 0 || $seoSummary['duplicate_slugs'] > 0) {
    $seoStatus = 'urgent';
} elseif ($seoSummary['long_title'] > 0 || $seoSummary['description_length'] > 0) {
    $seoStatus = 'warning';
}
$seoTrend = 'Meta descriptions within best practice range';
if ($seoSummary['duplicate_slugs'] > 0) {
    $seoTrend = 'Duplicate slugs detected: ' . dashboard_format_number((int)$seoSummary['duplicate_slugs']);
} elseif ($seoSummary['missing_description'] > 0 || $seoSummary['missing_title'] > 0) {
    $seoTrend = dashboard_format_number((int)($seoSummary['missing_title'] + $seoSummary['missing_description'])) . ' meta fields missing';
} elseif ($seoSummary['long_title'] > 0 || $seoSummary['description_length'] > 0) {
    $seoTrend = 'Metadata length alerts: ' . dashboard_format_number((int)($seoSummary['long_title'] + $seoSummary['description_length']));
}
$seoCta = $seoStatus === 'urgent' ? 'Fix SEO issues' : 'Review SEO settings';

$eventsStatus = 'ok';
if ($eventsTotal === 0) {
    $eventsStatus = 'urgent';
} elseif ($eventsPublished === 0 || $eventsUpcoming === 0 || $eventsPendingOrders > 0) {
    $eventsStatus = 'warning';
}
$eventsSecondary = 'Upcoming: ' . dashboard_format_number($eventsUpcoming) . ' • Tickets sold: ' . dashboard_format_number($eventsTicketsSold);
$eventsTrend = $eventsPendingOrders > 0
    ? 'Pending orders: ' . dashboard_format_number($eventsPendingOrders)
    : ($eventsRevenue > 0
        ? 'Revenue: ' . dashboard_format_currency($eventsRevenue, $eventsCurrency)
        : 'No ticket sales yet');
$eventsCta = $eventsTotal === 0
    ? 'Create an event'
    : ($eventsPendingOrders > 0 ? 'Review event orders' : 'Open events');

$calendarStatus = 'ok';
if ($calendarTotal === 0) {
    $calendarStatus = 'urgent';
} elseif ($calendarUpcoming === 0) {
    $calendarStatus = 'warning';
}
$calendarSecondary = 'Upcoming: ' . dashboard_format_number($calendarUpcoming) . ' • Categories: ' . dashboard_format_number($calendarCategoryCount);
if ($calendarNextEvent) {
    $calendarNextTitle = trim((string)($calendarNextEvent['title'] ?? ''));
    if ($calendarNextTitle === '') {
        $calendarNextTitle = 'Untitled event';
    }
    $calendarTrend = 'Next: ' . $calendarNextTitle . ' (' . date('M j', $calendarNextEvent['time']) . ')';
} else {
    $calendarTrend = 'No upcoming entries scheduled';
}
$calendarCta = $calendarTotal === 0 ? 'Add calendar event' : 'Open calendar';

$commerceStatus = 'ok';
$storefrontStatus = strtolower(trim((string)($commerceSettings['storefront_status'] ?? '')));
if ($commerceOrders === 0 && $commerceRevenue <= 0.0) {
    $commerceStatus = 'urgent';
}
if ($commerceLowStock > 0 || $commercePendingOrders > 0 || $commerceRefundRequests > 0) {
    $commerceStatus = $commerceStatus === 'urgent' ? 'urgent' : 'warning';
}
if ($storefrontStatus !== '' && !in_array($storefrontStatus, ['live', 'enabled'], true)) {
    $commerceStatus = in_array($storefrontStatus, ['paused', 'offline', 'maintenance'], true) ? 'urgent' : 'warning';
}
$commerceSecondary = 'Orders: ' . dashboard_format_number($commerceOrders) . ' • Avg order: ' . dashboard_format_currency($commerceAverageOrder, $commerceCurrency);
$commerceTrendParts = [];
if ($commerceLowStock > 0) {
    $commerceTrendParts[] = 'Low stock: ' . dashboard_format_number($commerceLowStock);
}
if ($commercePendingOrders > 0) {
    $commerceTrendParts[] = 'Pending: ' . dashboard_format_number($commercePendingOrders);
}
if ($commerceRefundRequests > 0) {
    $commerceTrendParts[] = 'Refunds: ' . dashboard_format_number($commerceRefundRequests);
}
$commerceTrend = $commerceTrendParts ? implode(' • ', $commerceTrendParts) : 'Store operating normally';
$commerceCta = 'Open commerce';
if ($commerceStatus === 'urgent' && $commerceOrders === 0 && $commerceRevenue <= 0.0) {
    $commerceCta = 'Launch store';
} elseif ($commerceLowStock > 0) {
    $commerceCta = 'Review inventory';
} elseif ($commercePendingOrders > 0) {
    $commerceCta = 'Manage orders';
}

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
        'id' => 'events',
        'module' => 'Events',
        'primary' => dashboard_format_number($eventsTotal) . ' events',
        'secondary' => $eventsSecondary,
        'status' => $eventsStatus,
        'statusLabel' => dashboard_status_label($eventsStatus),
        'trend' => $eventsTrend,
        'cta' => $eventsCta,
    ],
    [
        'id' => 'commerce',
        'module' => 'Commerce',
        'primary' => dashboard_format_currency($commerceRevenue, $commerceCurrency) . ' revenue',
        'secondary' => $commerceSecondary,
        'status' => $commerceStatus,
        'statusLabel' => dashboard_status_label($commerceStatus),
        'trend' => $commerceTrend,
        'cta' => $commerceCta,
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
        'id' => 'calendar',
        'module' => 'Calendar',
        'primary' => dashboard_format_number($calendarTotal) . ' calendar entries',
        'secondary' => $calendarSecondary,
        'status' => $calendarStatus,
        'statusLabel' => dashboard_status_label($calendarStatus),
        'trend' => $calendarTrend,
        'cta' => $calendarCta,
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
        'id' => 'seo',
        'module' => 'SEO',
        'primary' => dashboard_format_number($seoSummary['optimised']) . ' pages optimised',
        'secondary' => 'Meta issues: ' . dashboard_format_number((int)$seoSummary['issues']) . ' • Duplicate slugs: ' . dashboard_format_number((int)$seoSummary['duplicate_slugs']),
        'status' => $seoStatus,
        'statusLabel' => dashboard_status_label($seoStatus),
        'trend' => $seoTrend,
        'cta' => $seoCta,
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

$statusPriority = [
    'urgent' => 0,
    'warning' => 1,
    'ok' => 2,
];

usort($moduleSummaries, function (array $a, array $b) use ($statusPriority): int {
    $statusA = strtolower((string)($a['status'] ?? 'ok'));
    $statusB = strtolower((string)($b['status'] ?? 'ok'));
    $priorityA = $statusPriority[$statusA] ?? $statusPriority['ok'];
    $priorityB = $statusPriority[$statusB] ?? $statusPriority['ok'];

    if ($priorityA === $priorityB) {
        return strcasecmp((string)($a['module'] ?? ''), (string)($b['module'] ?? ''));
    }

    return $priorityA <=> $priorityB;
});

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
    'eventsTotal' => $eventsTotal,
    'eventsPublished' => $eventsPublished,
    'eventsUpcoming' => $eventsUpcoming,
    'eventsTicketsSold' => $eventsTicketsSold,
    'eventsRevenue' => $eventsRevenue,
    'eventsPendingOrders' => $eventsPendingOrders,
    'formsTotal' => count($forms),
    'formsFields' => $formsFields,
    'menusCount' => count($menus),
    'menuItems' => $menuItems,
    'calendarTotal' => $calendarTotal,
    'calendarUpcoming' => $calendarUpcoming,
    'calendarRecurring' => $calendarRecurring,
    'calendarCategories' => $calendarCategoryCount,
    'calendarNextEvent' => $calendarNextEvent
        ? [
            'title' => trim((string)($calendarNextEvent['title'] ?? '')),
            'time' => date('c', $calendarNextEvent['time']),
        ]
        : null,
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
    'commerceRevenue' => $commerceRevenue,
    'commerceOrders' => $commerceOrders,
    'commerceAverageOrder' => $commerceAverageOrder,
    'commerceConversionRate' => $commerceConversionRate,
    'commerceRefundRate' => $commerceRefundRate,
    'commerceLowStock' => $commerceLowStock,
    'commercePendingOrders' => $commercePendingOrders,
    'commerceRefundRequests' => $commerceRefundRequests,
    'commerceStorefrontStatus' => $storefrontStatus,
    'commerceCurrency' => $commerceCurrency,
    'eventsCurrency' => $eventsCurrency,
    'seoOptimised' => $seoSummary['optimised'],
    'seoMissingTitle' => $seoSummary['missing_title'],
    'seoMissingDescription' => $seoSummary['missing_description'],
    'seoDescriptionLengthIssues' => $seoSummary['description_length'],
    'seoDuplicateSlugs' => $seoSummary['duplicate_slugs'],
    'seoIssues' => $seoSummary['issues'],
    'moduleSummaries' => $moduleSummaries,
    'generatedAt' => gmdate(DATE_ATOM),
];

header('Content-Type: application/json');
echo json_encode($data);
