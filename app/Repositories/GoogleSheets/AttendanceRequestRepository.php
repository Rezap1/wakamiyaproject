<?php

namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\AttendanceRequestRepositoryInterface;

class AttendanceRequestRepository extends BaseSheetRepository implements AttendanceRequestRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'MASTER_ATTENDANCE_REQUEST';
        $this->cacheKey = 'attendance_requests_sheet';
        $this->primaryKey = 'Request_ID';
    }

    public function findById(string $id)
    {
        $items = $this->fetchAll();
        return $items->firstWhere($this->primaryKey, $id);
    }

    public function findByStudent(string $studentId)
    {
        $items = $this->fetchAll();
        return $items->filter(function ($item) use ($studentId) {
            return ($item['Student_ID'] ?? '') === $studentId;
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
}
