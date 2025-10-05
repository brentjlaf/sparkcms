<?php
// File: auth.php
session_start();
require_once __DIR__ . '/data.php';
require_once __DIR__ . '/settings.php';

ensure_site_timezone();

// Path to users.json
$usersFile = __DIR__ . '/../data/users.json';
if (!file_exists($usersFile)) {
    file_put_contents(
        $usersFile,
        json_encode([
            [
                'id' => 1,
                'username' => 'admin',
                'password' => password_hash('password', PASSWORD_DEFAULT),
                'role' => 'admin',
                'status' => 'active',
                'created_at' => time(),
                'last_login' => null
            ]
        ], JSON_PRETTY_PRINT)
    );
}
$users = read_json_file($usersFile);

function find_user($username) {
    global $users;
    foreach ($users as $user) {
        if (strtolower($user['username']) === strtolower($username)) {
            return $user;
        }
    }
    return null;
}

function require_login() {
    if (!isset($_SESSION['user'])) {
        header('Location: login.php');
        exit;
    }
}

function is_logged_in() {
    return isset($_SESSION['user']);
}
?>
