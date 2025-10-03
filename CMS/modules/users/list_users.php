<?php
// File: list_users.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/UserService.php';
require_login();

$usersFile = __DIR__ . '/../../data/users.json';
$service = new UserService(new UserRepository($usersFile));
$users = $service->getUsers();

$clean = [];
foreach ($users as $u) {
    $clean[] = [
        'id' => $u['id'],
        'username' => $u['username'],
        'role' => $u['role'] ?? 'editor',
        'status' => $u['status'] ?? 'active',
        'created_at' => $u['created_at'] ?? 0,
        'last_login' => $u['last_login'] ?? null
    ];
}

echo json_encode($clean);
?>
