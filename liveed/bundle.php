<?php
// File: bundle.php
// Combine builder CSS files into a single bundle.

$base = __DIR__;

$cssFiles = [
    "$base/css/builder-core.css",
    "$base/css/builder-history.css",
    "$base/css/builder-settings.css",
    "$base/css/builder-palette.css",
    "$base/css/builder-modal.css",
    "$base/css/builder-media.css",
    "$base/css/builder-view.css",
    "$base/css/builder-links.css",
    "$base/css/builder-a11y.css",
    "$base/css/builder-seo.css",
];

$output = "$base/builder.css";

function bundle(array $files, string $output): void
{
    $header = '/* File: ' . basename($output) . ' - merged from ' .
        implode(', ', array_map('basename', $files)) . " */\n";
    $content = $header;
    foreach ($files as $file) {
        $content .= '/* File: ' . basename($file) . " */\n";
        $content .= file_get_contents($file) . "\n";
    }
    file_put_contents($output, $content);
    echo "Bundled " . basename($output) . "\n";
}

bundle($cssFiles, $output);
