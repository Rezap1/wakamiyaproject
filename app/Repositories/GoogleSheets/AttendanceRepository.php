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
        return $this->findByIdFresh($id);
    }

    public function findByEmployeeAndSession(string $employeeId, string $sessionId)
    {
        return $this->rowsWhereColumn('Employee_ID', $employeeId)->first(function ($att) use ($sessionId) {
            return ($att['Employee_ID'] ?? '') === $employeeId &&
                   ($att['Session_ID'] ?? '') === $sessionId &&
                   strtoupper(trim($att['Is_Active'] ?? 'TRUE')) !== 'FALSE';
        });
    }

    public function findEmployeeAttendances(string $employeeId)
    {
        return $this->rowsWhereColumn('Employee_ID', $employeeId)->filter(function ($att) {
            return strtoupper(trim($att['Is_Active'] ?? 'TRUE')) !== 'FALSE';
        })->values();
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
