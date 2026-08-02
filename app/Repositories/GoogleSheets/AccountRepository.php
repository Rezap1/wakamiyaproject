<?php

namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\AccountRepositoryInterface;
use Illuminate\Support\Collection;

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

    public function generateNewId(string $prefix = 'ACC', int $padding = 6): string
    {
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
        $newId = $maxId + 1;
        return $prefix . '-' . str_pad((string)$newId, $padding, '0', STR_PAD_LEFT);
    }
}
