<?php
// File: UserRepository.php
// Repository for managing persistence of user records.

require_once __DIR__ . '/../../includes/data.php';

class UserRepository
{
    /** @var string */
    private $usersFile;

    public function __construct($usersFile)
    {
        $this->usersFile = $usersFile;
    }

    /**
     * Load all user records from storage.
     *
     * @return array<int,array<string,mixed>>
     */
    public function loadUsers()
    {
        $users = read_json_file($this->usersFile);
        return is_array($users) ? array_values($users) : [];
    }

    /**
     * Persist user records to storage.
     *
     * @param array<int,array<string,mixed>> $users
     * @return void
     */
    public function saveUsers(array $users)
    {
        write_json_file($this->usersFile, array_values($users));
    }

    /**
     * Generate the next sequential identifier.
     *
     * @param array<int,array<string,mixed>>|null $users
     * @return int
     */
    public function getNextId(?array $users = null)
    {
        if ($users === null) {
            $users = $this->loadUsers();
        }

        $maxId = 0;
        foreach ($users as $user) {
            if (isset($user['id']) && $user['id'] > $maxId) {
                $maxId = (int) $user['id'];
            }
        }

        return $maxId + 1;
    }
}
