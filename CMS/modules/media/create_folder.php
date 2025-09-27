<?php
// File: create_folder.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_login();

header('Content-Type: application/json');

$folder = sanitize_text($_POST['folder'] ?? '');
if ($folder === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Folder name required']);
    exit;
}

$normalized = strtolower($folder);
$invalidNames = ['.', '..', 'con', 'prn', 'aux', 'nul', 'com1', 'com2', 'com3', 'com4', 'com5', 'com6', 'com7', 'com8', 'com9', 'lpt1', 'lpt2', 'lpt3', 'lpt4', 'lpt5', 'lpt6', 'lpt7', 'lpt8', 'lpt9'];
if (in_array($normalized, $invalidNames, true)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid folder name']);
    exit;
}

if (preg_match('/[\\\/]/', $folder)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid folder name']);
    exit;
}

$root = dirname(__DIR__, 2);
$dir = $root . '/uploads/' . basename($folder);

if (is_dir($dir)) {
    http_response_code(409);
    echo json_encode(['status' => 'error', 'message' => 'Folder already exists']);
    exit;
}

if (!@mkdir($dir, 0777, true)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Unable to create folder']);
    exit;
}

echo json_encode(['status' => 'success', 'message' => 'Folder created successfully']);
