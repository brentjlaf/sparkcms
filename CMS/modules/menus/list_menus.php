<?php
// File: list_menus.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_login();

$menusFile = __DIR__ . '/../../data/menus.json';
$menus = read_json_file($menusFile);

if (!is_array($menus)) {
    $menus = [];
}

$lastUpdatedTimestamp = is_file($menusFile) ? filemtime($menusFile) : null;

$response = [
    'menus' => $menus,
    'lastUpdated' => $lastUpdatedTimestamp ? date(DATE_ATOM, $lastUpdatedTimestamp) : null,
];

echo json_encode($response);
?>
