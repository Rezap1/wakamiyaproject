<?php

namespace App\Services\Finance;

use App\Interfaces\GoogleSheets\TransactionRepositoryInterface;
use App\Interfaces\GoogleSheets\AccountRepositoryInterface;
use App\Services\Core\EnterpriseEventService;
use Exception;
use Illuminate\Support\Facades\Auth;

class TransactionService
{
    protected $repository;
    protected $accountRepository;
    protected $enterpriseEvent;

    public function __construct(
        TransactionRepositoryInterface $repository,
        AccountRepositoryInterface $accountRepository,
        EnterpriseEventService $enterpriseEvent
    ) {
        $this->repository = $repository;
        $this->accountRepository = $accountRepository;
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
        if (empty($data['Transaction_ID'])) {
            $data['Transaction_ID'] = $this->repository->generateNewId();
        } else {
            if ($this->repository->findById($data['Transaction_ID'])) {
                throw new Exception("Transaction ID {$data['Transaction_ID']} sudah terdaftar.");
            }
        }
        

        $data['Is_Active'] = 'TRUE';
        $data['Created_By'] = Auth::id() ?? 'SYSTEM';
        $data['Created_At'] = now()->toDateTimeString();
        $data['Updated_At'] = now()->toDateTimeString();

        $res = $this->repository->create($data);
        $this->repository->clearCache();

        $this->enterpriseEvent->dispatch('FINANCE', 'CREATE', 'TRANSACTION', $res['Transaction_ID'] ?? $data['Transaction_ID'], Auth::id() ?? 'SYSTEM', ['FINANCE'], [], $data);

        return $res;
    }

    public function update($id, array $data)
    {
        $transaction = $this->repository->findById($id);
        if (!$transaction) {
            throw new Exception("Transaksi tidak ditemukan.");
        }



        $data['Updated_At'] = now()->toDateTimeString();
        $res = $this->repository->update($id, $data);
        $this->repository->clearCache();

        $this->enterpriseEvent->dispatch('FINANCE', 'UPDATE', 'TRANSACTION', $id, Auth::id() ?? 'SYSTEM', ['FINANCE'], [], $data);

        return $res;
    }

    public function delete($id)
    {
        $transaction = $this->repository->findById($id);
        if (!$transaction) {
            throw new Exception("Transaksi tidak ditemukan.");
        }

        $res = $this->repository->delete($id);
        $this->repository->clearCache();

        $this->enterpriseEvent->dispatch('FINANCE', 'DELETE', 'TRANSACTION', $id, Auth::id() ?? 'SYSTEM', ['FINANCE'], [], []);

        return $res;
    }
}
