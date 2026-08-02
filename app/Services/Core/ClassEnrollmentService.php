<?php

namespace App\Services\Core;

use App\Interfaces\GoogleSheets\ClassEnrollmentRepositoryInterface;

class ClassEnrollmentService
{
    protected $repository;

    public function __construct(ClassEnrollmentRepositoryInterface $repository)
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
        return $this->repository->generateNewId('ENR', 6);
    }

    public function create(array $data)
    {
        if (!isset($data['Enrollment_ID'])) {
            $data['Enrollment_ID'] = $this->generateId();
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
