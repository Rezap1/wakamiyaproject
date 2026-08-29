<?php

namespace App\Services\Finance;

use App\Interfaces\GoogleSheets\AccountRepositoryInterface;
use App\Services\Core\EnterpriseEventService;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

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

    public function normalizeCategory($rawCategory): string
    {
        $cat = strtolower(trim($rawCategory ?? ''));
        if (str_contains($cat, 'asset') || str_contains($cat, 'aset')) {
            return 'ASSET';
        }
        if (str_contains($cat, 'liabil') || str_contains($cat, 'kewajiban') || str_contains($cat, 'hutang')) {
            return 'LIABILITY';
        }
        if (str_contains($cat, 'equit') || str_contains($cat, 'ekuitas') || str_contains($cat, 'modal')) {
            return 'EQUITY';
        }
        if (str_contains($cat, 'reven') || str_contains($cat, 'pendapatan') || str_contains($cat, 'income')) {
            return 'REVENUE';
        }
        if (str_contains($cat, 'expens') || str_contains($cat, 'beban') || str_contains($cat, 'biaya')) {
            return 'EXPENSE';
        }
        return 'EXPENSE';
    }

    public function getNormalBalance(string $category): string
    {
        $normalized = $this->normalizeCategory($category);
        if (in_array($normalized, ['ASSET', 'EXPENSE'])) {
            return 'DEBIT';
        }
        return 'CREDIT';
    }

    public function formatAccountRecord(array $account): array
    {
        $category = $this->normalizeCategory($account['Account_Category'] ?? '');
        $account['Account_Category'] = $category;
        $account['Normal_Balance'] = $account['Normal_Balance'] ?? $this->getNormalBalance($category);
        return $account;
    }

    public function getAll()
    {
        $accounts = collect($this->repository->fetchAll())->where('Is_Active', '!=', 'FALSE')->values();
        return $accounts->map(function ($acc) {
            return $this->formatAccountRecord($acc);
        });
    }

    public function getById($id)
    {
        $account = $this->repository->findById($id);
        if ($account) {
            return $this->formatAccountRecord($account);
        }
        return null;
    }

    public function getDefaultTransactionAccount()
    {
        $assets = collect($this->repository->fetchAll())
            ->where('Is_Active', '!=', 'FALSE')
            ->filter(function($acc) {
                $cat = strtoupper($acc['Account_Category'] ?? '');
                return str_contains($cat, 'ASSET') || str_contains($cat, 'ASET');
            });
            
        // 1. Try to find main cash account
        $cashAcc = $assets->first(function($acc) {
            $name = strtolower($acc['Account_Name'] ?? '');
            $code = $acc['Account_Code'] ?? '';
            return str_contains($name, 'kas') || str_contains($name, 'cash') || $code === '101';
        });
        
        if ($cashAcc) {
            return $this->formatAccountRecord($cashAcc);
        }
        
        // 2. Try to find main bank account
        $bankAcc = $assets->first(function($acc) {
            $name = strtolower($acc['Account_Name'] ?? '');
            $code = $acc['Account_Code'] ?? '';
            return str_contains($name, 'bank') || str_contains($name, 'bsi') || $code === '102';
        });
        
        if ($bankAcc) {
            return $this->formatAccountRecord($bankAcc);
        }
        
        // 3. Fallback to any active asset account
        $fallback = $assets->first();
        if ($fallback) {
            return $this->formatAccountRecord($fallback);
        }
        
        return null;
    }

    public function create(array $data)
    {
        $code = trim($data['Account_Code'] ?? '');
        if (empty($code)) {
            throw new Exception("Kode Akun wajib diisi.");
        }

        // Validate Account Code uniqueness
        $all = collect($this->repository->fetchAll())->where('Is_Active', '!=', 'FALSE');
        if ($all->contains('Account_Code', $code)) {
            throw new Exception("Kode Akun '{$code}' sudah terdaftar dalam sistem.");
        }

        if (empty($data['Account_ID'])) {
            $data['Account_ID'] = $this->repository->generateNewId();
        } else {
            if ($this->repository->findById($data['Account_ID'])) {
                throw new Exception("Account ID {$data['Account_ID']} sudah terdaftar.");
            }
        }

        $category = $this->normalizeCategory($data['Account_Category'] ?? '');
        $normalBalance = $this->getNormalBalance($category);

        $data['Account_Code'] = $code;
        $data['Account_Category'] = $category;
        $data['Normal_Balance'] = $normalBalance;
        $data['Is_Active'] = 'TRUE';
        $data['Created_By'] = \App\Support\ActorIdentity::required();
        $data['Created_At'] = now()->toDateTimeString();
        $data['Updated_At'] = now()->toDateTimeString();

        $res = $this->repository->create($data);
        if ($res === false || $res === null) {
            throw new Exception("Gagal menyimpan akun {$data['Account_ID']}.");
        }
        $this->repository->clearCache();
        $this->clearFinanceCaches();

        $this->enterpriseEvent->dispatch(
            'ACCOUNT',
            'CREATE',
            'ACCOUNT',
            $data['Account_ID'],
            \App\Support\ActorIdentity::required(),
            ['FINANCE'],
            [],
            $data
        );

        return $this->formatAccountRecord($data);
    }

    public function update($id, array $data)
    {
        $account = $this->repository->findById($id);
        if (!$account) {
            throw new Exception("Akun tidak ditemukan.");
        }

        if (isset($data['Account_Code'])) {
            $code = trim($data['Account_Code']);
            $all = collect($this->repository->fetchAll())->where('Is_Active', '!=', 'FALSE');
            $existing = $all->firstWhere('Account_Code', $code);
            if ($existing && ($existing['Account_ID'] ?? '') !== $id) {
                throw new Exception("Kode Akun '{$code}' sudah digunakan oleh akun lain.");
            }
            $data['Account_Code'] = $code;
        }

        if (isset($data['Account_Category'])) {
            $category = $this->normalizeCategory($data['Account_Category']);
            $data['Account_Category'] = $category;
            $data['Normal_Balance'] = $this->getNormalBalance($category);
        }

        $data['Updated_At'] = now()->toDateTimeString();
        $res = $this->repository->update($id, $data);
        if ($res === false || $res === null) {
            throw new Exception("Gagal memperbarui akun {$id}.");
        }
        $this->repository->clearCache();
        $this->clearFinanceCaches();

        $this->enterpriseEvent->dispatch(
            'ACCOUNT',
            'UPDATE',
            'ACCOUNT',
            $id,
            \App\Support\ActorIdentity::required(),
            ['FINANCE'],
            [],
            $data
        );

        return $this->formatAccountRecord(array_merge($account, $data));
    }

    public function delete($id)
    {
        $account = $this->repository->findById($id);
        if (!$account) {
            throw new Exception("Akun tidak ditemukan.");
        }

        $res = $this->repository->delete($id);
        if ($res === false || $res === null) {
            throw new Exception("Gagal menonaktifkan akun {$id}.");
        }
        $this->repository->clearCache();
        $this->clearFinanceCaches();

        $this->enterpriseEvent->dispatch(
            'ACCOUNT',
            'DELETE',
            'ACCOUNT',
            $id,
            \App\Support\ActorIdentity::required(),
            ['FINANCE'],
            [],
            $account
        );

        return $res;
    }

    private function clearFinanceCaches(): void
    {
        Cache::forget('finance_dashboard');
        Cache::forget('dashboard_finance');
    }
}
