<?php
// File: delete_user.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/UserService.php';
require_login();

$usersFile = __DIR__ . '/../../data/users.json';
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;
$service = new UserService(new UserRepository($usersFile));
$service->deleteUser($id);
echo 'OK';
?>
