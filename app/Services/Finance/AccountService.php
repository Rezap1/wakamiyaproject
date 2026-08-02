<?php

namespace App\Services\Finance;

use App\Interfaces\GoogleSheets\AccountRepositoryInterface;
use App\Services\Core\EnterpriseEventService;
use Exception;
use Illuminate\Support\Facades\Auth;

class AccountService
{
    protected $repository;
    protected $enterpriseEvent;

    public function __construct(
        AccountRepositoryInterface $repository,
        EnterpriseEventService $enterpriseEvent
    ) {
        $this->repository = $repository;
        $this->enterpriseEvent = $enterpriseEvent;
    }

    public function getAll()
    {
        return collect($this->repository->fetchAll())->where('Is_Active', '!=', 'FALSE')->values();
    }

    public function getById($id)
    {
        return $this->repository->findById($id);
    }

    public function create(array $data)
    {
        if (empty($data['Account_ID'])) {
            $data['Account_ID'] = $this->repository->generateNewId();
        } else {
            if ($this->repository->findById($data['Account_ID'])) {
                throw new Exception("Account ID {$data['Account_ID']} sudah terdaftar.");
            }
        }
        
        $data['Is_Active'] = 'TRUE';
        $data['Created_By'] = Auth::id() ?? 'SYSTEM';
        $data['Created_At'] = now()->toDateTimeString();
        $data['Updated_At'] = now()->toDateTimeString();

        $res = $this->repository->create($data);
        $this->repository->clearCache();

        $this->enterpriseEvent->dispatch(
            'ACCOUNT',
            'CREATE',
            'ACCOUNT',
            $data['Account_ID'],
            Auth::id(),
            ['FINANCE'],
            [],
            $data
        );

        return $res;
    }

    public function update($id, array $data)
    {
        $account = $this->repository->findById($id);
        if (!$account) {
            throw new Exception("Akun tidak ditemukan.");
        }

        $data['Updated_At'] = now()->toDateTimeString();
        $res = $this->repository->update($id, $data);
        $this->repository->clearCache();

        $this->enterpriseEvent->dispatch(
            'ACCOUNT',
            'UPDATE',
            'ACCOUNT',
            $id,
            Auth::id(),
            ['FINANCE'],
            [],
            $data
        );

        return $res;
    }

    public function delete($id)
    {
        $account = $this->repository->findById($id);
        if (!$account) {
            throw new Exception("Akun tidak ditemukan.");
        }

        $res = $this->repository->delete($id);
        $this->repository->clearCache();

        $this->enterpriseEvent->dispatch(
            'ACCOUNT',
            'DELETE',
            'ACCOUNT',
            $id,
            Auth::id(),
            ['FINANCE'],
            [],
            $account
        );

        return $res;
    }
}
