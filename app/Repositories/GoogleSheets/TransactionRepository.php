<?php

namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\TransactionRepositoryInterface;

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
        return $this->findByIdFresh($id);
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
        $quotedPrefix = preg_quote($prefix, '/');
        $next = $this->allocateNextSequence(
            strtolower($this->sheetName.':'.$this->primaryKey.':'.$prefix),
            $this->primaryKey,
            static fn (string $value): ?int => preg_match('/^'.$quotedPrefix.'-(\d+)$/i', $value, $matches)
                ? (int) $matches[1]
                : null
        );

        return $prefix.'-'.str_pad((string) $next, $padding, '0', STR_PAD_LEFT);
    }
}
