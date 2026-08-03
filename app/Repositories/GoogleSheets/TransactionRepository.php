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

        return \Illuminate\Support\Facades\Cache::lock($lockKey, 10)->block(5, function () use ($prefix, $padding, $counterKey) {
            if (!\Illuminate\Support\Facades\Cache::has($counterKey)) {
                $all = $this->fetchAll();
                $maxId = 0;
                foreach ($all as $item) {
                    if (preg_match('/^' . $prefix . '-(\d+)$/', $item[$this->primaryKey] ?? '', $matches)) {
                        $num = (int)$matches[1];
                        if ($num > $maxId) {
                            $maxId = $num;
                        }
                    }
                }
                \Illuminate\Support\Facades\Cache::forever($counterKey, $maxId);
            }
            
            $newId = \Illuminate\Support\Facades\Cache::increment($counterKey);
            return $prefix . '-' . str_pad((string)$newId, $padding, '0', STR_PAD_LEFT);
        });
    }
}
