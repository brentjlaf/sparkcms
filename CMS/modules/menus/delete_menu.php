<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$menusFile = __DIR__ . '/../../data/menus.json';
$menus = file_exists($menusFile) ? json_decode(file_get_contents($menusFile), true) : [];
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$menus = array_filter($menus, function($m) use ($id) { return $m['id'] != $id; });
file_put_contents($menusFile, json_encode(array_values($menus), JSON_PRETTY_PRINT));
echo 'OK';
?>
