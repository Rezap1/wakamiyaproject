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
        return collect($this->repository->fetchAll())
            ->where('Is_Active', '!=', 'FALSE')
            ->map(function ($transaction) {
                $normalizedType = $this->normalizeTypeOrNull($transaction['Type'] ?? '');
                if ($normalizedType !== null) {
                    $transaction['Type'] = $normalizedType;
                }

                if (isset($transaction['Amount'])) {
                    $transaction['Amount'] = (float) $transaction['Amount'];
                }

                return $transaction;
            })
            ->values();
    }

    public function getById($id)
    {
        return $this->repository->findById($id);
    }

    public function create(array $data)
    {
        $data = $this->normalizeTransactionData($data);

        if (empty($data['Transaction_ID'])) {
            $data['Transaction_ID'] = $this->repository->generateNewId();
        } else {
            if ($this->repository->findById($data['Transaction_ID'])) {
                throw new Exception("Transaction ID {$data['Transaction_ID']} sudah terdaftar.");
            }
        }
        

        $data['Is_Active'] = 'TRUE';
        $data['Created_By'] = \App\Support\ActorIdentity::required();
        $data['Created_At'] = now()->toDateTimeString();
        $data['Updated_At'] = now()->toDateTimeString();

        $res = $this->repository->create($data);
        if (!$res) {
            throw new Exception("Gagal menyimpan transaksi {$data['Transaction_ID']}.");
        }
        $this->repository->clearCache();

        $this->enterpriseEvent->dispatch('FINANCE', 'CREATE', 'TRANSACTION', $data['Transaction_ID'], \App\Support\ActorIdentity::required(), ['FINANCE'], [], $data);

        return $res;
    }

    public function update($id, array $data)
    {
        $transaction = $this->repository->findById($id);
        if (!$transaction) {
            throw new Exception("Transaksi tidak ditemukan.");
        }

        $data = $this->normalizeTransactionData($data, false);

        $data['Updated_At'] = now()->toDateTimeString();
        $res = $this->repository->update($id, $data);
        if (!$res) {
            throw new Exception("Gagal memperbarui transaksi {$id}.");
        }
        $this->repository->clearCache();

        $this->enterpriseEvent->dispatch('FINANCE', 'UPDATE', 'TRANSACTION', $id, \App\Support\ActorIdentity::required(), ['FINANCE'], [], $data);

        return $res;
    }

    private function normalizeTransactionData(array $data, bool $isCreate = true): array
    {
        if (array_key_exists('Type', $data)) {
            $data['Type'] = $this->normalizeType($data['Type']);
        }

        if (array_key_exists('Category', $data)) {
            $data['Category'] = trim((string) $data['Category']);
            if ($data['Category'] === '') {
                throw new Exception('Nama pemasukan/pengeluaran wajib diisi.');
            }
        } elseif ($isCreate) {
            throw new Exception('Nama pemasukan/pengeluaran wajib diisi.');
        }

        foreach (['Account_ID', 'Reference_Type', 'Reference_ID', 'Description'] as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }

        if (array_key_exists('Amount', $data)) {
            $amount = (float) $data['Amount'];
            if ($amount < 0) {
                throw new Exception('Nominal transaksi tidak boleh negatif.');
            }
            $data['Amount'] = $amount;
        }

        return $data;
    }

    private function normalizeType($type): string
    {
        $value = strtolower(trim((string) $type));

        $incomeAliases = ['income', 'pemasukan', 'masuk', 'revenue', 'pendapatan'];
        $expenseAliases = ['expense', 'pengeluaran', 'keluar', 'cost', 'biaya', 'beban'];

        if (in_array($value, $incomeAliases, true)) {
            return 'Income';
        }

        if (in_array($value, $expenseAliases, true)) {
            return 'Expense';
        }

        throw new Exception('Tipe transaksi harus Pemasukan atau Pengeluaran.');
    }

    private function normalizeTypeOrNull($type): ?string
    {
        try {
            return $this->normalizeType($type);
        } catch (Exception $e) {
            return null;
        }
    }

    public function delete($id)
    {
        $transaction = $this->repository->findById($id);
        if (!$transaction) {
            throw new Exception("Transaksi tidak ditemukan.");
        }

        $res = $this->repository->delete($id);
        if (!$res) {
            throw new Exception("Gagal membatalkan transaksi {$id}.");
        }
        $this->repository->clearCache();

        $this->enterpriseEvent->dispatch('FINANCE', 'DELETE', 'TRANSACTION', $id, \App\Support\ActorIdentity::required(), ['FINANCE'], [], []);

        return $res;
    }
}
