<?php
// File: list_menus.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_login();

$menusFile = __DIR__ . '/../../data/menus.json';
$menus = read_json_file($menusFile);

echo json_encode($menus);
?>
