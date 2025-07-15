<?php
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

$settingsFile = __DIR__ . '/../CMS/data/settings.json';
$settings = file_exists($settingsFile) ? json_decode(file_get_contents($settingsFile), true) : [];
$menusFile = __DIR__ . '/../CMS/data/menus.json';
$menus = file_exists($menusFile) ? json_decode(file_get_contents($menusFile), true) : [];

$scriptBase = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
if (substr($scriptBase, -7) === '/liveed') {
    $scriptBase = substr($scriptBase, 0, -7);
}
$themeBase = $scriptBase . '/theme';

$template = !empty($page['template']) ? $page['template'] : 'page.php';
$templateFile = realpath(__DIR__ . '/../theme/templates/pages/' . $template);
if (!$templateFile || !file_exists($templateFile)) {
    http_response_code(500);
    echo 'Template not found';
    exit;
}

ob_start();
include $templateFile;
$html = ob_get_clean();
$html = preg_replace('/<div class="drop-area"><\\/div>/', $page['content'], $html);
$html = preg_replace('#<templateSetting[^>]*>.*?</templateSetting>#si', '', $html);
$html = preg_replace('#<div class="block-controls"[^>]*>.*?</div>#si', '', $html);
$html = str_replace('draggable="true"', '', $html);
$html = preg_replace('#\sdata-ts="[^"]*"#i', '', $html);

$headInject = "<link rel=\"stylesheet\" href=\"{$scriptBase}/theme/css/root.css\">" .
    "<link rel=\"stylesheet\" href=\"{$scriptBase}/theme/css/combined.css\">" .
    "<link rel=\"stylesheet\" href=\"{$scriptBase}/theme/css/override.css\">";
$html = preg_replace('/<head>/', '<head>' . $headInject, $html, 1);

echo $html;
