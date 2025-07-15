<?php
// File: delete_user.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_login();

$usersFile = __DIR__ . '/../../data/users.json';
$users = file_exists($usersFile) ? json_decode(file_get_contents($usersFile), true) : [];
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;
$users = array_filter($users, function($u) use ($id) { return $u['id'] != $id; });
file_put_contents($usersFile, json_encode(array_values($users), JSON_PRETTY_PRINT));
echo 'OK';
?>
