<?php

namespace App\Services\Core;

use App\Interfaces\GoogleSheets\AttendanceRepositoryInterface;

class AttendanceService
{
    protected $repository;

    public function __construct(AttendanceRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function getAll()
    {
        return $this->repository->fetchAll();
    }

    public function getById($id)
    {
        return $this->repository->findById($id);
    }

    public function getEmployeeAttendanceBySession(string $employeeId, string $sessionId)
    {
        $all = collect($this->getAll());
        return $all->first(function ($att) use ($employeeId, $sessionId) {
            return ($att['Employee_ID'] ?? '') === $employeeId && 
                   ($att['Session_ID'] ?? '') === $sessionId &&
                   strtoupper(trim($att['Is_Active'] ?? 'TRUE')) !== 'FALSE';
        });
    }

    public function getEmployeeAttendances(string $employeeId)
    {
        $all = collect($this->getAll());
        return $all->filter(function ($att) use ($employeeId) {
            return ($att['Employee_ID'] ?? '') === $employeeId &&
                   strtoupper(trim($att['Is_Active'] ?? 'TRUE')) !== 'FALSE';
        })->values();
    }

    public function generateId()
    {
        return $this->repository->generateNewId('ATT', 6);
    }

    public function create(array $data)
    {
        if (!isset($data['Attendance_ID'])) {
            $data['Attendance_ID'] = $this->generateId();
        }
        $data['Created_At'] = now()->toDateTimeString();
        return $this->repository->create($data);
    }
    
    public function update($id, array $data)
    {
        $data['Updated_At'] = now()->toDateTimeString();
        return $this->repository->update($id, $data);
    }
    
    public function delete($id)
    {
        return $this->repository->delete($id);
    }
}
