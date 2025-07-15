<?php
// File: list-blocks.php
require_once __DIR__ . '/../CMS/includes/auth.php';
require_login();

$blocksDir = __DIR__ . '/../theme/templates/blocks';

$blocks = [];
if (is_dir($blocksDir)) {
    $paths = glob($blocksDir . '/*.php');
    if ($paths !== false) {
        $blocks = array_map('basename', $paths);
        sort($blocks, SORT_STRING);
    }
}
header('Content-Type: application/json');
echo json_encode(['blocks' => $blocks]);

