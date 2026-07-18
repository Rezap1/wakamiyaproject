<?php

namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\ClassRepositoryInterface;

class ClassRepository extends BaseSheetRepository implements ClassRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'MASTER_CLASS';
        $this->cacheKey = 'classes_sheet';
        $this->primaryKey = 'Class_ID';
    }

    public function findById(string $id)
    {
        $classes = $this->fetchAll();
        return $classes->firstWhere($this->primaryKey, $id);
    }

    public function findByCode(string $code)
    {
        $classes = $this->fetchAll();
        return $classes->firstWhere('Class_Code', $code);
    }

    public function create(array $data)
    {
        return $this->append($data);
    }
}
