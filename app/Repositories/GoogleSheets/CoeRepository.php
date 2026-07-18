<?php

namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\CoeRepositoryInterface;

class CoeRepository extends BaseSheetRepository implements CoeRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'COE';
        $this->primaryKey = 'COE_ID';
        $this->cacheKey = 'coes_sheet';
    }

    /**
     * @param bool $forceRefresh
     * @return array
     */
    public function fetchAll(bool $forceRefresh = false): array
    {
        if ($forceRefresh) {
            $this->clearCache();
        }
        return parent::fetchAll()->toArray();
    }

    /**
     * @param string $id
     * @return array|null
     */
    public function findById(string $id): ?array
    {
        $coes = parent::fetchAll();
        return $coes->firstWhere($this->primaryKey, $id);
    }

    /**
     * @param array $data
     * @return bool
     */
    public function create(array $data): bool
    {
        $this->append($data);
        return true;
    }

    /**
     * @param string $id
     * @param array $data
     * @return bool
     */
    public function update(string $id, array $data): bool
    {
        parent::update($id, $data);
        return true;
    }

    /**
     * @param string $id
     * @param string $deletedBy
     * @return mixed
     */
    public function softDelete(string $id, string $deletedBy = 'system')
    {
        return parent::update($id, [
            'Is_Active' => 'FALSE',
            'Updated_By' => $deletedBy,
            'Updated_At' => now()->toDateTimeString()
        ]);
    }

    /**
     * Generate new ID for COE (Format: COE000001)
     * @return string
     */
    public function generateNewId(string $prefix = 'COE', int $padding = 6): string
    {
        return parent::generateNewId($prefix, $padding);
    }
}
