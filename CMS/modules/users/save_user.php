<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$usersFile = __DIR__ . '/../../data/users.json';
$users = file_exists($usersFile) ? json_decode(file_get_contents($usersFile), true) : [];

$id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$role = trim($_POST['role'] ?? 'editor');
$status = trim($_POST['status'] ?? 'active');

if ($username === '') {
    http_response_code(400);
    echo 'Missing username';
    exit;
}

if ($id) {
    foreach ($users as &$u) {
        if ($u['id'] == $id) {
            $u['username'] = $username;
            if ($password !== '') {
                $u['password'] = password_hash($password, PASSWORD_DEFAULT);
            }
            $u['role'] = $role;
            $u['status'] = $status;
            break;
        }
    }
    unset($u);
} else {
    $id = 1;
    foreach ($users as $u) {
        if ($u['id'] >= $id) $id = $u['id'] + 1;
    }
    $users[] = [
        'id' => $id,
        'username' => $username,
        'password' => password_hash($password !== '' ? $password : 'password', PASSWORD_DEFAULT),
        'role' => $role,
        'status' => $status,
        'created_at' => time(),
        'last_login' => null
    ];
}

file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));
echo 'OK';
?>
