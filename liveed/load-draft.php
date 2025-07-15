<?php
// File: load-draft.php
require_once __DIR__ . '/../CMS/includes/auth.php';
require_login();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if(!$id){
    http_response_code(400);
    echo 'Invalid ID';
    exit;
}
$dir = __DIR__ . '/../CMS/data/drafts';
$file = $dir . '/page-' . $id . '.json';
header('Content-Type: application/json');
if(is_file($file)){
    readfile($file);
} else {
    echo json_encode(['content'=>'','timestamp'=>0]);
}
