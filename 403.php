<?php
// File: 403.php
http_response_code(403);
$settingsFile = __DIR__ . '/CMS/data/settings.json';
$menusFile = __DIR__ . '/CMS/data/menus.json';
$settings = file_exists($settingsFile) ? json_decode(file_get_contents($settingsFile), true) : [];
$menus = file_exists($menusFile) ? json_decode(file_get_contents($menusFile), true) : [];
$scriptBase = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
if (substr($scriptBase, -4) === '/CMS') {
    $scriptBase = substr($scriptBase, 0, -4);
}
$scriptBase = rtrim($scriptBase, '/');
$page = [
    'title' => 'Restricted',
    'content' =>
        '<h1>Restricted</h1>' .
        '<p>You do not have permission to view this page.</p>' .
        '<p><a href="' . htmlspecialchars($scriptBase) . '/">Return to homepage</a>' .
        ' or <a href="' . htmlspecialchars($scriptBase) . '/CMS/login.php">log in</a> ' .
        'with an account that has access.</p>'
];
$themeBase = $scriptBase . '/theme';
$templateFile = __DIR__ . '/theme/templates/pages/errors/403.php';
ob_start();
include $templateFile;
$html = ob_get_clean();
$html = preg_replace('/<div class="drop-area"><\\/div>/', $page['content'], $html);
echo $html;
