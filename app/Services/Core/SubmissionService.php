<?php

namespace App\Services\Core;

use App\Interfaces\GoogleSheets\SubmissionRepositoryInterface;

class SubmissionService
{
    protected $repository;

    public function __construct(SubmissionRepositoryInterface $repository)
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
        return $this->repository->generateNewId('SBM', 6);
    }

    public function validateSubmission(array $data, $isUpdate = false)
    {
        // One submission per student per assignment
        if (!$isUpdate) {
            $existing = $this->getAll()->first(function($item) use ($data) {
                return $item['Student_ID'] === ($data['Student_ID'] ?? '') && $item['Assignment_ID'] === ($data['Assignment_ID'] ?? '');
            });
            if ($existing) {
                throw new \Exception('Student has already submitted this assignment.');
            }
        }
    }

    public function create(array $data)
    {
        $this->validateSubmission($data);
        if (!isset($data['Submission_ID'])) {
            $data['Submission_ID'] = $this->generateId();
        }
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
