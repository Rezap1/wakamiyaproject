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
        return $this->findByIdFresh($id);
    }

    public function findByEmail(string $email)
    {
        return $this->firstWhereColumn('Email', $email, true);
    }

    public function findByUsername(string $username)
    {
        return $this->firstWhereColumn('Username', $username, true);
    }

    public function create(array $data)
    {
        return $this->append($data);
    }
}
