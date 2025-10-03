<?php
// File: bundle.php
// Combine theme CSS files into a single bundle.

$base = __DIR__;

$cssFiles = [
    "$base/theme/css/root.css",
    "$base/theme/css/skin.css",
    "$base/theme/css/override.css",
];
$cssOutput = "$base/theme/css/combined.css";

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

bundle($cssFiles, $cssOutput);
