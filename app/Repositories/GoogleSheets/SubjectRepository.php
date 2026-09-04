<?php

namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\SubjectRepositoryInterface;
use App\Support\Academic\AcademicSheetMapper;

class SubjectRepository extends BaseSheetRepository implements SubjectRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'MASTER_SUBJECT';
        $this->cacheKey = 'subjects_sheet';
        $this->primaryKey = 'Subject_ID';
    }

    public function fetchAllFresh()
    {
        return parent::fetchAllFresh()
            ->map(fn ($row) => AcademicSheetMapper::normalizeSubjectRow((array) $row))
            ->values();
    }

    public function findById(string $id)
    {
        $needle = strtolower(trim($id));

        return $this->fetchAll()->first(function ($row) use ($needle) {
            return strtolower(trim((string) ($row['Subject_ID'] ?? ''))) === $needle;
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
