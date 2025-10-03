<?php
// File: UserService.php
// Provides business logic for managing users.

require_once __DIR__ . '/UserRepository.php';

class UserService
{
    /** @var UserRepository */
    private $repository;

    /** @var array<int,string> */
    private $allowedRoles = ['admin', 'editor'];

    /** @var array<int,string> */
    private $allowedStatuses = ['active', 'inactive'];

    public function __construct(UserRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Retrieve all users with normalized defaults.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getUsers()
    {
        $users = $this->repository->loadUsers();
        foreach ($users as &$user) {
            $user['role'] = $this->normalizeRole($user['role'] ?? null);
            $user['status'] = $this->normalizeStatus($user['status'] ?? null);
            if (!isset($user['created_at'])) {
                $user['created_at'] = 0;
            }
            if (!array_key_exists('last_login', $user)) {
                $user['last_login'] = null;
            }
        }
        unset($user);

        return $users;
    }

    /**
     * Save (create or update) a user record.
     *
     * @param int|null $id
     * @param string $username
     * @param string $password
     * @param string $role
     * @param string $status
     * @return array<string,mixed> Saved user data
     */
    public function saveUser($id, $username, $password, $role, $status)
    {
        $username = trim((string) $username);
        if ($username === '') {
            throw new InvalidArgumentException('Missing username');
        }

        $password = trim((string) $password);

        $role = $this->normalizeRole($role);
        $status = $this->normalizeStatus($status);
        $users = $this->repository->loadUsers();

        $id = $id !== null && $id !== '' ? (int) $id : null;
        $savedUser = null;

        if ($id !== null) {
            foreach ($users as &$user) {
                if ((int) ($user['id'] ?? 0) === $id) {
                    $user['username'] = $username;
                    if ($password !== '') {
                        $user['password'] = password_hash($password, PASSWORD_DEFAULT);
                    }
                    $user['role'] = $role;
                    $user['status'] = $status;
                    if (!isset($user['created_at'])) {
                        $user['created_at'] = time();
                    }
                    if (!array_key_exists('last_login', $user)) {
                        $user['last_login'] = null;
                    }
                    $savedUser = $user;
                    break;
                }
            }
            unset($user);

            if ($savedUser === null) {
                throw new InvalidArgumentException('User not found.');
            }
        } else {
            if ($password === '') {
                throw new InvalidArgumentException('Missing password');
            }

            $newUser = [
                'id' => $this->repository->getNextId($users),
                'username' => $username,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'role' => $role,
                'status' => $status,
                'created_at' => time(),
                'last_login' => null,
            ];
            $users[] = $newUser;
            $savedUser = $newUser;
        }

        $this->repository->saveUsers($users);
        return $savedUser;
    }

    /**
     * Delete a user record by its identifier.
     *
     * @param int $id
     * @return void
     */
    public function deleteUser($id)
    {
        $id = (int) $id;
        $users = $this->repository->loadUsers();
        $filtered = array_filter($users, function ($user) use ($id) {
            return (int) ($user['id'] ?? 0) !== $id;
        });
        $this->repository->saveUsers(array_values($filtered));
    }

    private function normalizeRole($role)
    {
        $role = is_string($role) ? trim($role) : '';
        if (!in_array($role, $this->allowedRoles, true)) {
            return 'editor';
        }
        return $role;
    }

    private function normalizeStatus($status)
    {
        $status = is_string($status) ? trim($status) : '';
        if (!in_array($status, $this->allowedStatuses, true)) {
            return 'active';
        }
        return $status;
    }
}
