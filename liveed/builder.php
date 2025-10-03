<?php
// File: builder.php
require_once __DIR__ . '/../CMS/includes/auth.php';
require_once __DIR__ . '/../CMS/includes/data.php';
require_once __DIR__ . '/../CMS/includes/settings.php';
require_login();

if (!function_exists('render_partial')) {
    function render_partial(string $path, array $data = []): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        include $path;
        return ob_get_clean();
    }
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$pagesFile = __DIR__ . '/../CMS/data/pages.json';
$pages = read_json_file($pagesFile);
$page = null;
foreach ($pages as $p) {
    if ((int)$p['id'] === $id) { $page = $p; break; }
}
if (!$page) {
    http_response_code(404);
    echo 'Page not found';
    exit;
}

$scriptBase = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
if (substr($scriptBase, -7) === '/liveed') {
    $scriptBase = substr($scriptBase, 0, -7);
}
$themeBase = $scriptBase . '/theme';

// Load settings and menus for the theme template
$settings = get_site_settings();
$menusFile = __DIR__ . '/../CMS/data/menus.json';
$menus = read_json_file($menusFile);

// Render the theme page template with a canvas placeholder
$templateFile = realpath(__DIR__ . '/../theme/templates/pages/page.php');
ob_start();
include $templateFile;
$themeHtml = ob_get_clean();
$placeholderText = !empty($settings['canvas_placeholder'])
    ? htmlspecialchars($settings['canvas_placeholder'])
    : 'Drag blocks from the palette to start building your page';
$canvasContent = $page['content'] ?: '<div class="canvas-placeholder">' . $placeholderText . '</div>';
$dropAreaHook = '<div class="drop-area" id="liveed-canvas-placeholder"></div>';
if (strpos($themeHtml, $dropAreaHook) !== false) {
    $themeHtml = str_replace($dropAreaHook, '<div id="canvas" class="canvas">' . $canvasContent . '</div>', $themeHtml);
} else {
    $themeHtml = str_replace('<div class="drop-area"></div>', '<div id="canvas" class="canvas">' . $canvasContent . '</div>', $themeHtml);
}

$cssFiles = [
    'builder-core.css',
    'builder-history.css',
    'builder-settings.css',
    'builder-palette.css',
    'builder-modal.css',
    'builder-media.css',
    'builder-view.css',
];
$headInject = '';
foreach ($cssFiles as $css) {
    $headInject .= '<link rel="stylesheet" href="' . htmlspecialchars($scriptBase, ENT_QUOTES, 'UTF-8') . '/liveed/css/' . $css . '">' . "\n";
}
$headInject .= '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">' . "\n";

$headHook = '<!-- liveed-head -->';
if (strpos($themeHtml, $headHook) !== false) {
    $themeHtml = str_replace($headHook, $headInject, $themeHtml);
} else {
    $headPos = strpos($themeHtml, '<head>');
    if ($headPos !== false) {
        $themeHtml = substr_replace($themeHtml, '<head>' . $headInject, $headPos, strlen('<head>'));
    }
}

$lastSavedLabel = date('Y-m-d H:i', (int) $page['last_modified']);
$paletteHtml = render_partial(__DIR__ . '/templates/palette.php', [
    'pageTitle' => $page['title'],
    'lastSavedLabel' => $lastSavedLabel,
]);

$builderStart = render_partial(__DIR__ . '/templates/builder-start.php', [
    'paletteHtml' => $paletteHtml,
]);

$modalsHtml = render_partial(__DIR__ . '/templates/modals.php');

$builderEnd = render_partial(__DIR__ . '/templates/builder-end.php', [
    'modalsHtml' => $modalsHtml,
    'scriptBase' => $scriptBase,
    'pageId' => $page['id'],
    'pageSlug' => $page['slug'],
    'pageLastModified' => $page['last_modified'],
]);

$builderStartHook = '<!-- liveed-builder:start -->';
if (strpos($themeHtml, $builderStartHook) !== false) {
    $themeHtml = str_replace($builderStartHook, $builderStart, $themeHtml);
} else {
    $bodyPos = strpos($themeHtml, '<body');
    if ($bodyPos !== false) {
        $bodyClose = strpos($themeHtml, '>', $bodyPos);
        if ($bodyClose !== false) {
            $insertPos = $bodyClose + 1;
            $themeHtml = substr_replace($themeHtml, $builderStart, $insertPos, 0);
        }
    }
}

$builderEndHook = '<!-- liveed-builder:end -->';
if (strpos($themeHtml, $builderEndHook) !== false) {
    $themeHtml = str_replace($builderEndHook, $builderEnd, $themeHtml);
} else {
    $endPos = strripos($themeHtml, '</body>');
    if ($endPos !== false) {
        $themeHtml = substr_replace($themeHtml, $builderEnd, $endPos, 0);
    }
}

echo $themeHtml;
