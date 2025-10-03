<?php
// Shared helpers for rendering theme templates into HTML strings.
if (!function_exists('cms_capture_template_html')) {
    /**
     * Capture the rendered HTML of a template with placeholder content.
     */
    function cms_capture_template_html(string $templateFile, array $settings, array $menus, string $scriptBase): string
    {
        $page = ['content' => '{{CONTENT}}'];
        $themeBase = $scriptBase . '/theme';

        ob_start();
        include $templateFile;
        $html = ob_get_clean();

        $html = preg_replace('/<div class="drop-area"><\\/div>/', '{{CONTENT}}', $html, 1);
        if (strpos($html, '{{CONTENT}}') === false) {
            $html .= '{{CONTENT}}';
        }

        $html = preg_replace('#<templateSetting[^>]*>.*?<\\/templateSetting>#si', '', $html);
        $html = preg_replace('#<div class="block-controls"[^>]*>.*?<\\/div>#si', '', $html);
        $html = str_replace('draggable="true"', '', $html);
        $html = preg_replace('#\\sdata-ts="[^"]*"#i', '', $html);
        $html = preg_replace('#\\sdata-(?:blockid|template|original|active|custom_[A-Za-z0-9_-]+)="[^"]*"#i', '', $html);

        return $html;
    }
}

if (!function_exists('cms_build_page_html')) {
    /**
     * Build a full HTML page by merging content into a cached template skeleton.
     */
    function cms_build_page_html(array $page, array $settings, array $menus, string $scriptBase, ?string $templateDir): string
    {
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
            $templateCache[$templateFile] = cms_capture_template_html($templateFile, $settings, $menus, $scriptBase);
        }

        $templateHtml = $templateCache[$templateFile];
        $content = (string)($page['content'] ?? '');

        return str_replace('{{CONTENT}}', $content, $templateHtml);
    }
}
