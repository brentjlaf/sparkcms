<?php
// File: save-draft.php
require_once __DIR__ . '/../CMS/includes/auth.php';
require_login();

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$content = $_POST['content'] ?? '';
$timestamp = isset($_POST['timestamp']) ? intval($_POST['timestamp']) : time();

if(!$id){
    http_response_code(400);
    echo 'Invalid ID';
    exit;
}

$dir = __DIR__ . '/../CMS/data/drafts';
if(!is_dir($dir)){
    mkdir($dir, 0755, true);
}
$file = $dir . '/page-' . $id . '.json';
$data = ['content'=>$content,'timestamp'=>$timestamp];
file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));

echo 'OK';
