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
        $rows = method_exists($this->repository, 'fetchAllFresh') ? $this->repository->fetchAllFresh() : $this->repository->fetchAll();
        $assets = collect($rows)
            ->where('Is_Active', '!=', 'FALSE')
            ->filter(function($acc) {
                $cat = strtoupper($acc['Account_Category'] ?? '');
                return str_contains($cat, 'ASSET') || str_contains($cat, 'ASET');
            });
            
        $configured = trim((string) config('finance.accounts.default_id', ''));
        if ($configured !== '') {
            $matched = $assets->first(fn ($acc) => ($acc['Account_ID'] ?? '') === $configured || ($acc['Account_Code'] ?? '') === $configured);
            return $matched ? $this->formatAccountRecord($matched) : null;
        }

        $candidates = $assets->filter(function($acc) {
            $name = strtolower($acc['Account_Name'] ?? '');
            return str_contains($name, 'kas') || str_contains($name, 'cash')
                || str_contains($name, 'bank') || str_contains($name, 'bsi');
        });
        return $candidates->count() === 1 ? $this->formatAccountRecord($candidates->first()) : null;
    }

    public function create(array $data)
    {
        $this->assertFinanceMutationActor();
        $code = trim($data['Account_Code'] ?? '');
        if (empty($code)) {
            throw new Exception("Kode Akun wajib diisi.");
        }

        // Validate Account Code uniqueness
        $allRows = method_exists($this->repository, 'fetchAllFresh') ? $this->repository->fetchAllFresh() : $this->repository->fetchAll();
        $all = collect($allRows)->where('Is_Active', '!=', 'FALSE');
        if ($all->contains(fn ($row) => strcasecmp(trim((string) ($row['Account_Code'] ?? '')), $code) === 0)) {
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

        if (!empty($data['Parent_Account_ID'])) {
            $parent = $this->repository->findById($data['Parent_Account_ID']);
            if (!$parent || strtoupper(trim((string) ($parent['Is_Active'] ?? 'TRUE'))) === 'FALSE') {
                throw new \App\Exceptions\FinancialIntegrityException("Parent account {$data['Parent_Account_ID']} tidak ditemukan atau tidak aktif.");
            }
        }

        $data['Account_Code'] = $code;
        $data['Account_Category'] = $category;
        $data['Normal_Balance'] = $normalBalance;
        $data['Is_Active'] = 'TRUE';
        $data['Created_By'] = \App\Support\ActorIdentity::required();
        $data['Updated_By'] = $data['Created_By'];
        $data['Created_At'] = now()->toDateTimeString();
        $data['Updated_At'] = now()->toDateTimeString();

        $res = $this->repository->create($data);
        if ($res === false || $res === null) {
            throw new Exception("Gagal menyimpan akun {$data['Account_ID']}.");
        }
        $this->repository->clearCache();
        $this->clearFinanceCaches();

        $this->dispatchEventSafe('CREATE', $data['Account_ID'], $data);

        return $this->formatAccountRecord($data);
    }

    public function update($id, array $data)
    {
        $this->assertFinanceMutationActor();
        $account = method_exists($this->repository, 'findByIdFresh') ? $this->repository->findByIdFresh($id) : $this->repository->findById($id);
        if (!$account) {
            throw new Exception("Akun tidak ditemukan.");
        }

        if (isset($data['Account_Code'])) {
            $code = trim($data['Account_Code']);
            $allRows = method_exists($this->repository, 'fetchAllFresh') ? $this->repository->fetchAllFresh() : $this->repository->fetchAll();
            $all = collect($allRows)->where('Is_Active', '!=', 'FALSE');
            $existing = $all->first(fn ($row) => strcasecmp(trim((string) ($row['Account_Code'] ?? '')), $code) === 0);
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

        if (!empty($data['Parent_Account_ID'])) {
            $parent = $this->repository->findById($data['Parent_Account_ID']);
            if (!$parent || strtoupper(trim((string) ($parent['Is_Active'] ?? 'TRUE'))) === 'FALSE') {
                throw new \App\Exceptions\FinancialIntegrityException("Parent account {$data['Parent_Account_ID']} tidak ditemukan atau tidak aktif.");
            }
        }

        $data['Updated_By'] = \App\Support\ActorIdentity::required();
        $data['Updated_At'] = now()->toDateTimeString();
        $res = $this->repository->update($id, $data);
        if ($res === false || $res === null) {
            throw new Exception("Gagal memperbarui akun {$id}.");
        }
        $this->repository->clearCache();
        $this->clearFinanceCaches();

        $this->dispatchEventSafe('UPDATE', $id, $data);

        return $this->formatAccountRecord(array_merge($account, $data));
    }

    public function delete($id)
    {
        $this->assertFinanceMutationActor();
        $account = method_exists($this->repository, 'findByIdFresh') ? $this->repository->findByIdFresh($id) : $this->repository->findById($id);
        if (!$account) {
            throw new Exception("Akun tidak ditemukan.");
        }

        $res = $this->repository->delete($id);
        if ($res === false || $res === null) {
            throw new Exception("Gagal menonaktifkan akun {$id}.");
        }
        $this->repository->clearCache();
        $this->clearFinanceCaches();

        $this->dispatchEventSafe('DELETE', $id, $account);

        return $res;
    }

    private function clearFinanceCaches(): void
    {
        Cache::forget('finance_dashboard');
        Cache::forget('dashboard_finance');
    }

    private function assertFinanceMutationActor(): string
    {
        $actor = \App\Support\ActorIdentity::required();
        $user = auth()->user();
        $role = strtoupper(trim((string) ($user->Role ?? $user->Role_Name ?? '')));
        if ($role === '' && $user && !empty($user->Role_ID)) {
            try {
                $role = strtoupper(trim((string) (app(\App\Services\Core\RoleService::class)
                    ->getRoleById($user->Role_ID)['Role_Name'] ?? '')));
            } catch (\Throwable) {
                $role = '';
            }
        }
        if (!in_array($role, ['FINANCE', 'ADMINISTRATOR', 'MASTER'], true)) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Role pengguna tidak diizinkan melakukan mutasi account.');
        }
        return $actor;
    }

    private function dispatchEventSafe(string $action, string $id, array $metadata): void
    {
        try {
            $this->enterpriseEvent->dispatch('ACCOUNT', $action, 'ACCOUNT', $id, \App\Support\ActorIdentity::required(), ['FINANCE'], [], $metadata);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Account side effect failed after primary persistence', [
                'account_id' => $id, 'action' => $action, 'exception' => get_class($e),
            ]);
        }
    }
}
