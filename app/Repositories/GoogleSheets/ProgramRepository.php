<?php

namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\ProgramRepositoryInterface;

class ProgramRepository extends BaseSheetRepository implements ProgramRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'MASTER_PROGRAM';
        $this->cacheKey = 'programs_sheet';
        $this->primaryKey = 'Program_ID';
    }

    public function findById(string $id)
    {
        $programs = $this->fetchAll();
        return $programs->firstWhere($this->primaryKey, $id);
    }

    public function findByCode(string $code)
    {
        $programs = $this->fetchAll();
        return $programs->firstWhere('Program_Code', $code);
    }

    public function findByName(string $name)
    {
        $programs = $this->fetchAll();
        return $programs->firstWhere('Program_Name', $name);
    }

    public function create(array $data)
    {
        return $this->append($data);
    }
}
