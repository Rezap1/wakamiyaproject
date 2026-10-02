<?php

namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\AccountRepositoryInterface;

class AccountRepository extends BaseSheetRepository implements AccountRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'MASTER_ACCOUNT';
        $this->cacheKey = 'master_account_sheet';
        $this->primaryKey = 'Account_ID';
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

    public function generateNewId(string $prefix = 'ACC', int $padding = 6): string
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
