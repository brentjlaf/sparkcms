<?php
// File: 404.php
http_response_code(404);
$settingsFile = __DIR__ . '/CMS/data/settings.json';
$menusFile = __DIR__ . '/CMS/data/menus.json';
$settings = file_exists($settingsFile) ? json_decode(file_get_contents($settingsFile), true) : [];
$menus = file_exists($menusFile) ? json_decode(file_get_contents($menusFile), true) : [];
$scriptBase = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
if (substr($scriptBase, -4) === '/CMS') {
    $scriptBase = substr($scriptBase, 0, -4);
}
$scriptBase = rtrim($scriptBase, '/');
$page = ['title' => 'Page Not Found', 'content' => '<h1>Page Not Found</h1>'];
$themeBase = $scriptBase . '/theme';
$templateFile = __DIR__ . '/theme/templates/pages/errors/404.php';
ob_start();
include $templateFile;
$html = ob_get_clean();
$html = preg_replace('/<mwPageArea[^>]*><\\/mwPageArea>/', $page['content'], $html);
echo $html;
