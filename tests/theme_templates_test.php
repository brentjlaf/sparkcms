<?php
$root = dirname(__DIR__);
require $root . '/CMS/includes/template_renderer.php';

$settings = json_decode(file_get_contents($root . '/CMS/data/settings.json'), true);
$menus = json_decode(file_get_contents($root . '/CMS/data/menus.json'), true);

if (!is_array($settings) || !is_array($menus)) {
    throw new RuntimeException('Failed to load settings or menus data.');
}

$templatesDir = realpath($root . '/theme/templates/pages');
if ($templatesDir === false) {
    throw new RuntimeException('Theme templates directory not found.');
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($templatesDir, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }

    $templateFile = $file->getPathname();
    $relativePath = substr($templateFile, strlen($root) + 1);

    $htmlFirst = cms_capture_template_html($templateFile, $settings, $menus, '');
    $htmlSecond = cms_capture_template_html($templateFile, $settings, $menus, '');

    if ($htmlFirst === '' || $htmlSecond === '') {
        throw new RuntimeException("Template {$relativePath} rendered empty output.");
    }

    if (strpos($htmlFirst, '{{CONTENT}}') === false || strpos($htmlSecond, '{{CONTENT}}') === false) {
        throw new RuntimeException("Template {$relativePath} is missing the content placeholder.");
    }
}

echo "Theme template rendering tests passed\n";
