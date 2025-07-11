<?php
// File: list_menus.php
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$menusFile = __DIR__ . '/../../data/menus.json';
$menus = file_exists($menusFile) ? json_decode(file_get_contents($menusFile), true) : [];

echo json_encode($menus);
?>
