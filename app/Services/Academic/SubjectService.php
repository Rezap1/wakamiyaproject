<?php

namespace App\Services\Academic;

use App\Interfaces\GoogleSheets\SubjectRepositoryInterface;

class SubjectService
{
    protected $repository;

    public function __construct(SubjectRepositoryInterface $repository)
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
        return $this->repository->generateNewId('SUB', 6);
    }

    public function validateSubject(array $data, $ignoreId = null)
    {
        $all = $this->getAll();
        
        if (isset($data['Subject_Code'])) {
            $existingCode = $all->firstWhere('Subject_Code', $data['Subject_Code']);
            if ($existingCode && $existingCode['Subject_ID'] !== $ignoreId) {
                throw new \Exception('Subject Code already exists.');
            }
        }

        if (isset($data['Subject_Name']) && isset($data['Program_ID'])) {
            $existingName = $all->first(function ($item) use ($data, $ignoreId) {
                return $item['Subject_Name'] === $data['Subject_Name'] 
                    && $item['Program_ID'] === $data['Program_ID']
                    && $item['Subject_ID'] !== $ignoreId;
            });
            if ($existingName) {
                throw new \Exception('Subject Name already exists in this Program.');
            }
        }
    }

    public function create(array $data)
    {
        $this->validateSubject($data);
        
        if (!isset($data['Subject_ID'])) {
            $data['Subject_ID'] = $this->generateId();
        }
        $data['Created_At'] = now()->toDateTimeString();
        
        $result = $this->repository->create($data);
        $this->repository->clearCache();
        return $result;
    }
    
    public function update($id, array $data)
    {
        $this->validateSubject($data, $id);
        
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
