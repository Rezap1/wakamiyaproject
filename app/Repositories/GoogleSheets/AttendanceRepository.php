<?php

namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\AttendanceRepositoryInterface;

class AttendanceRepository extends BaseSheetRepository implements AttendanceRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'MASTER_ATTENDANCE';
        $this->cacheKey = 'attendances_sheet';
        $this->primaryKey = 'Attendance_ID';
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
