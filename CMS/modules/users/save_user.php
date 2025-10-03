<?php
// File: save_user.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/UserService.php';
require_login();

$usersFile = __DIR__ . '/../../data/users.json';
$service = new UserService(new UserRepository($usersFile));

$id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
$username = sanitize_text($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');
$role = sanitize_text($_POST['role'] ?? 'editor');
$status = sanitize_text($_POST['status'] ?? 'active');
try {
    $service->saveUser($id, $username, $password, $role, $status);
    echo 'OK';
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo $e->getMessage();
}
?>
