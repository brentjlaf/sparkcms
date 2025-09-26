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

$pages = read_json_file($pagesFile);
$media = read_json_file($mediaFile);
$users = read_json_file($usersFile);
$settings = read_json_file($settingsFile);
$menus = read_json_file($menusFile);

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

$views = 0;
foreach ($pages as $p) {
    $views += $p['views'] ?? 0;
}

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
];

header('Content-Type: application/json');
echo json_encode($data);
