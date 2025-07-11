<?php
// File: load-block.php
require_once __DIR__ . '/../CMS/includes/auth.php';
require_login();

$block = isset($_GET['file']) ? basename($_GET['file']) : '';
$blockPath = realpath(__DIR__ . '/../theme/templates/blocks/' . $block);
$base = realpath(__DIR__ . '/../theme/templates/blocks');
if ($blockPath && strpos($blockPath, $base) === 0 && file_exists($blockPath)) {
    readfile($blockPath);
}
