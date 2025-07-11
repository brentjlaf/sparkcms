<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$folder = trim($_POST['folder'] ?? '');
if ($folder === '') {
    echo json_encode(['status' => 'error', 'message' => 'Folder name required']);
    exit;
}
$root = dirname(__DIR__, 2);
$dir = $root . '/uploads/' . basename($folder);
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

echo json_encode(['status' => 'success']);
