<?php
// File: 404.php
http_response_code(404);
$settingsFile = __DIR__ . '/CMS/data/settings.json';
$menusFile = __DIR__ . '/CMS/data/menus.json';
require_once __DIR__ . '/CMS/includes/data.php';
$settings = read_json_file($settingsFile);
$menus = read_json_file($menusFile);
$scriptBase = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
if (substr($scriptBase, -4) === '/CMS') {
    $scriptBase = substr($scriptBase, 0, -4);
}
$scriptBase = rtrim($scriptBase, '/');
$page = [
    'title' => 'Page Not Found',
    'content' =>
        '<h1>Page Not Found</h1>' .
        '<p>The page you are looking for might have been moved or deleted.</p>' .
        '<p><a href="' . htmlspecialchars($scriptBase) . '/">Return to homepage</a>' .
        ' or use the site search to find what you are looking for.</p>'
];
$themeBase = $scriptBase . '/theme';
$templateFile = __DIR__ . '/theme/templates/pages/errors/404.php';
ob_start();
include $templateFile;
$html = ob_get_clean();
$html = preg_replace('/<div class="drop-area"><\\/div>/', $page['content'], $html);
echo $html;
