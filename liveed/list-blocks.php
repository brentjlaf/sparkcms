<?php
// File: list-blocks.php
require_once __DIR__ . '/../CMS/includes/auth.php';
require_login();

$blocksDir = __DIR__ . '/../theme/templates/blocks';
$blocks = [];
if (is_dir($blocksDir)) {
    foreach (scandir($blocksDir) as $f) {
        if (substr($f, -4) === '.php') {
            $blocks[] = $f;
        }
    }
}
header('Content-Type: application/json');
echo json_encode(['blocks' => $blocks]);

