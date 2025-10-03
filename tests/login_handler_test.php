<?php
require_once __DIR__ . '/../CMS/includes/auth.php';

// Ensure a clean session state for the test run.
$_SESSION = [];

// Provide a controlled dataset so the login handler inspects an inactive account.
global $users;
$users = [
    [
        'id' => 99,
        'username' => 'inactive-user',
        'password' => password_hash('secret-pass', PASSWORD_DEFAULT),
        'role' => 'admin',
        'status' => 'inactive',
        'created_at' => time(),
        'last_login' => null,
    ],
];

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'username' => 'inactive-user',
    'password' => 'secret-pass',
];

ob_start();
require __DIR__ . '/../CMS/login.php';
$output = ob_get_clean();

if (isset($_SESSION['user'])) {
    throw new RuntimeException('Inactive users should not receive an authenticated session.');
}

if (strpos($output, 'Invalid credentials') === false) {
    throw new RuntimeException('Inactive accounts should receive the generic invalid credentials response.');
}

session_unset();
session_destroy();

$_SERVER['REQUEST_METHOD'] = 'GET';
$_POST = [];

// The regression passed if no exception was thrown.
echo "Login handler inactive user regression test passed\n";
