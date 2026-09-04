<?php

namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\ScheduleRepositoryInterface;
use App\Support\Academic\AcademicSheetMapper;

class ScheduleRepository extends BaseSheetRepository implements ScheduleRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'MASTER_SCHEDULE';
        $this->cacheKey = 'schedules_sheet';
        $this->primaryKey = 'Schedule_ID';
    }

    public function fetchAllFresh()
    {
        return parent::fetchAllFresh()
            ->map(fn ($row) => AcademicSheetMapper::normalizeScheduleRow((array) $row))
            ->values();
    }

    public function findById(string $id)
    {
        $needle = strtolower(trim($id));

        return $this->fetchAll()->first(function ($row) use ($needle) {
            return strtolower(trim((string) ($row['Schedule_ID'] ?? ''))) === $needle;
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
