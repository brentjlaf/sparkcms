<?php
// File: delete_menu.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_login();

$menusFile = __DIR__ . '/../../data/menus.json';
$menus = file_exists($menusFile) ? json_decode(file_get_contents($menusFile), true) : [];
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;
$menus = array_filter($menus, function($m) use ($id) { return $m['id'] != $id; });
file_put_contents($menusFile, json_encode(array_values($menus), JSON_PRETTY_PRINT));
echo 'OK';
?>
