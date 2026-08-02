<?php

namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\AcademicYearRepositoryInterface;

class AcademicYearRepository extends BaseSheetRepository implements AcademicYearRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'MASTER_ACADEMIC_YEAR';
        $this->cacheKey = 'academic_years_sheet';
        $this->primaryKey = 'Academic_Year_ID';
    }

    public function findById(string $id)
    {
        $items = $this->fetchAll();
        return $items->firstWhere($this->primaryKey, $id);
    }

    public function create(array $data)
    {
        return $this->append($data);
    }
    
    public function update($id, array $data)
    {
        return $this->updateRow($id, $data);
    }
    
    public function softDelete($id)
    {
        return $this->updateRow($id, ['Is_Active' => 'FALSE']);
    }
}
