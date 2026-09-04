<?php

namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\AcademicYearRepositoryInterface;
use App\Support\Academic\AcademicSheetMapper;

class AcademicYearRepository extends BaseSheetRepository implements AcademicYearRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'MASTER_ACADEMIC_YEAR';
        $this->cacheKey = 'academic_years_sheet';
        $this->primaryKey = 'Academic_Year_ID';
    }

    public function fetchAllFresh()
    {
        return parent::fetchAllFresh()
            ->map(fn ($row) => AcademicSheetMapper::normalizeAcademicYearRow((array) $row))
            ->filter(fn ($row) => trim((string) ($row['Academic_Year_ID'] ?? '')) !== '')
            ->values();
    }

    public function findById(string $id)
    {
        $needle = strtolower(trim($id));

        return $this->fetchAll()->first(function ($row) use ($needle) {
            return strtolower(trim((string) ($row['Academic_Year_ID'] ?? ''))) === $needle;
        });
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
