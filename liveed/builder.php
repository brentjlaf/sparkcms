<?php
// File: builder.php
require_once __DIR__ . '/../CMS/includes/auth.php';
require_login();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$pagesFile = __DIR__ . '/../CMS/data/pages.json';
$pages = file_exists($pagesFile) ? json_decode(file_get_contents($pagesFile), true) : [];
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
$settingsFile = __DIR__ . '/../CMS/data/settings.json';
$settings = file_exists($settingsFile) ? json_decode(file_get_contents($settingsFile), true) : [];
$menusFile = __DIR__ . '/../CMS/data/menus.json';
$menus = file_exists($menusFile) ? json_decode(file_get_contents($menusFile), true) : [];

// Render the theme page template with a canvas placeholder
$templateFile = realpath(__DIR__ . '/../theme/templates/pages/page.php');
ob_start();
include $templateFile;
$themeHtml = ob_get_clean();
$canvasContent = $page['content'] ?: '<div class="canvas-placeholder">Drag blocks here</div>';
$themeHtml = preg_replace('/<mwPageArea[^>]*><\\/mwPageArea>/', '<div id="canvas" class="canvas">' . $canvasContent . '</div>', $themeHtml);

$headInject = "<link rel=\"stylesheet\" href=\"{$scriptBase}/liveed/builder.css\">" .
    "<link rel=\"stylesheet\" href=\"https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css\"/>";
$themeHtml = preg_replace('/<head>/', '<head>' . $headInject, $themeHtml, 1);

$builderHeader = '<header class="builder-header"><span class="title">Editing: ' . htmlspecialchars($page['title']) . '</span><button id="saveBtn" class="builder-btn">Save</button><span id="saveStatus" class="save-status"></span></header>';
$builderStart = '<div class="builder"><aside class="block-palette">' . $builderHeader . '<h2>Blocks</h2><input type="text" class="palette-search" placeholder="Search blocks"><div class="palette-items"></div></aside><main class="canvas-container">';
$builderEnd = '</main><div id="settingsPanel" class="settings-panel"><div class="settings-header"><span class="title">Settings</span><button type="button" class="close-btn">&times;</button></div><div class="settings-content"></div></div></div>' .
    '<script>window.builderPageId = ' . json_encode($page['id']) . ';window.builderBase = ' . json_encode($scriptBase) . ';</script>' .
    '<script type="module" src="' . $scriptBase . '/liveed/builder.js"></script>';

$themeHtml = preg_replace('/<body([^>]*)>/', '<body$1>' . $builderStart, $themeHtml, 1);
$themeHtml = preg_replace('/<\/body>/', $builderEnd . '</body>', $themeHtml, 1);

echo $themeHtml;
