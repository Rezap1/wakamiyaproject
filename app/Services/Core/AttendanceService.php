<?php

namespace App\Services\Core;

use App\Interfaces\GoogleSheets\AttendanceRepositoryInterface;
use LogicException;

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
        throw new LogicException(
            'Direct attendance creation is disabled. Use an authorized Academic, request-review, or QR attendance workflow.'
        );
    }

    public function update($id, array $data)
    {
        throw new LogicException(
            'Direct attendance updates are disabled. Use an authorized Academic or request-review workflow.'
        );
    }

    public function delete($id)
    {
        throw new LogicException(
            'Direct attendance deletion is disabled. Use an authorized attendance management workflow.'
        );
    }
}
