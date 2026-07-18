<?php

namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\MatchingRepositoryInterface;

class MatchingRepository extends BaseSheetRepository implements MatchingRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'MATCHING';
        $this->primaryKey = 'Matching_ID';
        $this->cacheKey = 'matchings_sheet';
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
        $matchings = parent::fetchAll();
        return $matchings->firstWhere($this->primaryKey, $id);
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
     * Generate new ID for Matching (Format: MTC000001)
     * @return string
     */
    public function generateNewId(string $prefix = 'MTC', int $padding = 6): string
    {
        return parent::generateNewId($prefix, $padding);
    }
}
