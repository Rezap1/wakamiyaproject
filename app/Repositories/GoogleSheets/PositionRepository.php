<?php

namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\PositionRepositoryInterface;

class PositionRepository extends BaseSheetRepository implements PositionRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'MASTER_POSITION';
        $this->cacheKey = 'positions_sheet';
        $this->primaryKey = 'Position_ID';
    }

    public function findById(string $id)
    {
        $positions = $this->fetchAll();
        return $positions->firstWhere($this->primaryKey, $id);
    }

    public function findByCode(string $code)
    {
        $positions = $this->fetchAll();
        return $positions->first(function ($position) use ($code) {
            return strtolower($position['Position_Code'] ?? '') === strtolower($code);
        });
    }

    public function findByName(string $name)
    {
        $positions = $this->fetchAll();
        return $positions->first(function ($position) use ($name) {
            return strtolower($position['Position_Name'] ?? '') === strtolower($name);
        });
    }

    public function create(array $data)
    {
        return $this->append($data);
    }
}
