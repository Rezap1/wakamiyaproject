<?php

namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\UserRepositoryInterface;

class UserRepository extends BaseSheetRepository implements UserRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'MASTER_USER';
        $this->cacheKey = 'users_sheet';
        $this->primaryKey = 'User_ID';
    }

    public function findById(string $id)
    {
        $users = $this->fetchAll();
        return $users->firstWhere($this->primaryKey, $id);
    }

    public function findByEmail(string $email)
    {
        $users = $this->fetchAll();
        return $users->first(function ($user) use ($email) {
            return strtolower($user['Email'] ?? '') === strtolower($email);
        });
    }

    public function findByUsername(string $username)
    {
        $users = $this->fetchAll();
        return $users->first(function ($user) use ($username) {
            return strtolower($user['Username'] ?? '') === strtolower($username);
        });
    }

    public function create(array $data)
    {
        return $this->append($data);
    }
}
