<?php

namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\TransactionRepositoryInterface;
use Illuminate\Support\Collection;

class TransactionRepository extends BaseSheetRepository implements TransactionRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'FINANCE_TRANSACTION';
        $this->cacheKey = 'finance_transaction_sheet';
        $this->primaryKey = 'Transaction_ID';
    }

    public function findById(string $id)
    {
        return $this->fetchAll()->firstWhere($this->primaryKey, $id);
    }

    public function findByIdFresh($id)
    {
        return parent::findByIdFresh($id);
    }

    public function create(array $data)
    {
        return $this->append($data);
    }

    public function update($id, array $data)
    {
        return $this->updateRow($id, $data);
    }

    public function delete($id)
    {
        return $this->updateRow($id, ['Is_Active' => 'FALSE']);
    }

    public function generateNewId(string $prefix = 'TRX', int $padding = 6): string
    {
        $lockKey = $this->sheetName . '_write_lock';
        $counterKey = 'id_counter_' . $this->sheetName . '_' . $prefix;

        return \Illuminate\Support\Facades\Cache::lock($lockKey, 120)->block(15, function () use ($prefix, $padding, $counterKey) {
            $all = method_exists($this, 'fetchAllFresh') ? $this->fetchAllFresh() : $this->fetchAll();
            $maxId = 0;
            $existing = [];
            foreach ($all as $item) {
                $raw = trim((string) ($item[$this->primaryKey] ?? ''));
                $existing[strtolower($raw)] = true;
                if (preg_match('/^' . preg_quote($prefix, '/') . '-(\d+)$/i', $raw, $matches)) {
                    $maxId = max($maxId, (int) $matches[1]);
                }
            }
            $candidate = max($maxId, (int) \Illuminate\Support\Facades\Cache::get($counterKey, 0)) + 1;
            for ($attempt = 0; $attempt < 10; $attempt++, $candidate++) {
                $newId = $prefix . '-' . str_pad((string) $candidate, $padding, '0', STR_PAD_LEFT);
                if (!isset($existing[strtolower($newId)])) {
                    \Illuminate\Support\Facades\Cache::forever($counterKey, $candidate);
                    return $newId;
                }
            }
            throw new \App\Exceptions\FinancialIntegrityException('Tidak dapat mengalokasikan Transaction_ID unik dari persisted state.');
        });
    }
}
