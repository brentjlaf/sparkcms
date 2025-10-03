<?php
// File: save_page.php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/PageService.php';

require_login();

header('Content-Type: application/json; charset=UTF-8');

$service = new PageService();
$postData = $_POST;
if (!is_array($postData)) {
    $postData = [];
}
$session = isset($_SESSION) && is_array($_SESSION) ? $_SESSION : [];

$result = $service->save($postData, $session);
$status = isset($result['status']) ? (int)$result['status'] : 200;
http_response_code($status);

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
