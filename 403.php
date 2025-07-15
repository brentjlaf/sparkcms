<?php
// File: 403.php
http_response_code(403);
$settingsFile = __DIR__ . '/CMS/data/settings.json';
$menusFile = __DIR__ . '/CMS/data/menus.json';
require_once __DIR__ . '/CMS/includes/data.php';
$settings = read_json($settingsFile);
$menus = read_json($menusFile);
$scriptBase = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
if (substr($scriptBase, -4) === '/CMS') {
    $scriptBase = substr($scriptBase, 0, -4);
}
$scriptBase = rtrim($scriptBase, '/');
$page = ['title' => 'Restricted', 'content' => '<h1>Restricted</h1>'];
$themeBase = $scriptBase . '/theme';
$templateFile = __DIR__ . '/theme/templates/pages/errors/403.php';
ob_start();
include $templateFile;
$html = ob_get_clean();
$html = preg_replace('/<div class="drop-area"><\\/div>/', $page['content'], $html);
echo $html;
