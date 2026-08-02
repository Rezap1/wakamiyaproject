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
