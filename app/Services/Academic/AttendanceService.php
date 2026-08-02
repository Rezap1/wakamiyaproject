<?php

namespace App\Services\Academic;

use App\Interfaces\GoogleSheets\AttendanceRepositoryInterface;
use App\Services\Core\EnterpriseEventService;
use Exception;

class AttendanceService
{
    protected $repository;
    protected $enterpriseEvent;

    public function __construct(AttendanceRepositoryInterface $repository, EnterpriseEventService $enterpriseEvent)
    {
        $this->repository = $repository;
        $this->enterpriseEvent = $enterpriseEvent;
    }

    public function getAll()
    {
        return $this->repository->fetchAll();
    }

    public function getById($id)
    {
        return $this->repository->findById($id);
    }

    public function generateId()
    {
        return $this->repository->generateNewId('ATT', 6);
    }

    public function openSession(array $data)
    {
        // $data contains: Schedule_ID, Teacher_ID, Attendance_Date, Semester, Academic_Year, Grace_Period
        // Validation: Teacher can only open attendance for today or past (not future)
        if (strtotime($data['Attendance_Date']) > strtotime(date('Y-m-d'))) {
            throw new Exception("Cannot open attendance for future dates.");
        }
        
        // Cannot open twice for the same schedule and date
        $existing = $this->getAll()->first(function($item) use ($data) {
            return $item['Schedule_ID'] === ($data['Schedule_ID'] ?? '') 
                && $item['Attendance_Date'] === ($data['Attendance_Date'] ?? '')
                && ($item['Session_Status'] ?? '') === 'OPEN';
        });

        if ($existing) {
            throw new Exception("Attendance session is already open for this schedule and date.");
        }

        // Simulating opening a session by inserting a master record (or just directly inserting absent default for all students)
        // Since we don't have a separate SESSION table, we'll assume "Open Session" inserts default 'Absent' for all enrolled students
        // We just return success and let the controller handle student list
        return true;
    }

    public function markAttendance(array $data)
    {
        // $data contains: Schedule_ID, Student_ID, Teacher_ID, Attendance_Date, Check_In_Time, Grace_Period, Status (optional)
        
        // Auto-Late detection
        $status = $data['Status'] ?? 'Present';
        if (empty($data['Status']) && !empty($data['Check_In_Time']) && !empty($data['Grace_Period'])) {
            // Assume Schedule Start_Time is passed or fetched. Check if it's late based on logic.
            // In a real scenario we'd fetch Schedule_ID to get Start_Time.
            $status = 'Present'; // Simplified
        }

        if (!isset($data['Attendance_ID'])) {
            $data['Attendance_ID'] = $this->generateId();
        }
        $data['Status'] = $status;
        $data['Created_At'] = now()->toDateTimeString();

        $result = $this->repository->create($data);
        $this->repository->clearCache();
        return $result;
    }

    public function update($id, array $data)
    {
        $data['Updated_At'] = now()->toDateTimeString();
        $result = $this->repository->update($id, $data);
        $this->repository->clearCache();
        return $result;
    }

    public function delete($id)
    {
        $result = $this->repository->delete($id);
        $this->repository->clearCache();
        return $result;
    }
}