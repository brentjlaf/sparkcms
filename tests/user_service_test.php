<?php
require_once __DIR__ . '/../CMS/modules/users/UserService.php';

$tempFile = tempnam(sys_get_temp_dir(), 'users');
if ($tempFile === false) {
    throw new RuntimeException('Unable to create temporary dataset.');
}

$repository = new UserRepository($tempFile);
$service = new UserService($repository);

$initialPasswordHash = password_hash('initial-secret', PASSWORD_DEFAULT);
$repository->saveUsers([
    [
        'id' => 5,
        'username' => 'alice',
        'password' => $initialPasswordHash,
        'role' => 'admin',
        'status' => 'active',
        'created_at' => 1700000000,
    ],
]);

// Updating with an empty password should retain the existing hash while applying role/status clamps.
$service->saveUser(5, 'alice', '', 'super-admin', 'disabled');
$users = $repository->loadUsers();
$alice = null;
foreach ($users as $user) {
    if ((int) $user['id'] === 5) {
        $alice = $user;
        break;
    }
}

if ($alice === null) {
    throw new RuntimeException('Updated user was not found.');
}

if ($alice['password'] !== $initialPasswordHash) {
    throw new RuntimeException('Existing password hash should be retained when an empty password is provided.');
}

if ($alice['role'] !== 'editor') {
    throw new RuntimeException('Roles should be clamped to allowed values.');
}

if ($alice['status'] !== 'active') {
    throw new RuntimeException('Statuses should be clamped to allowed values.');
}

// Creating a new user should hash the provided password (or default) and apply defaults.
$created = $service->saveUser(null, 'bob', '', 'manager', 'pending');
if (!password_verify('password', $created['password'])) {
    throw new RuntimeException('New users should receive the default password when none is provided.');
}

if ($created['role'] !== 'editor' || $created['status'] !== 'active') {
    throw new RuntimeException('New users should receive normalized role and status values.');
}

$storedUsers = $repository->loadUsers();
$bob = null;
foreach ($storedUsers as $user) {
    if ($user['username'] === 'bob') {
        $bob = $user;
        break;
    }
}

if ($bob === null) {
    throw new RuntimeException('Newly created user was not persisted.');
}

if (!password_verify('password', $bob['password'])) {
    throw new RuntimeException('Persisted default password should remain hashed.');
}

unlink($tempFile);

echo "UserService tests passed\n";
