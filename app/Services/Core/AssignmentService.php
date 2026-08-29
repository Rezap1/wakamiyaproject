<?php

namespace App\Services\Core;

use App\Interfaces\GoogleSheets\AssignmentRepositoryInterface;
use Illuminate\Support\Facades\Auth;

class AssignmentService
{
    protected $repository;
    protected $enterpriseEvent;

    public function __construct(AssignmentRepositoryInterface $repository, EnterpriseEventService $enterpriseEvent)
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
        return $this->repository->generateNewId('ASN', 6);
    }

    public function validateAssignment(array $data)
    {
        if (isset($data['Publish_Date']) && isset($data['Deadline'])) {
            if (strtotime($data['Publish_Date']) >= strtotime($data['Deadline'])) {
                throw new \Exception('Deadline must be greater than Publish Date.');
            }
        }
    }

    public function create(array $data)
    {
        $this->validateAssignment($data);
        if (!isset($data['Assignment_ID'])) {
            $data['Assignment_ID'] = $this->generateId();
        }
        $data['Created_At'] = now()->toDateTimeString();
        $data['Status'] = !empty($data['Status']) ? $data['Status'] : 'Published';
        
        $result = $this->repository->create($data);
        $this->repository->clearCache();
        
        $this->enterpriseEvent->dispatch(
            'ACADEMIC',
            'CREATE',
            'ASSESSMENT',
            $data['Assignment_ID'],
            Auth::id(),
            ['ACADEMIC'],
            [],
            $data
        );

        return $result;
    }
    
    public function update($id, array $data)
    {
        $this->validateAssignment($data);
        $data['Updated_At'] = now()->toDateTimeString();
        if (isset($data['Status']) && empty($data['Status'])) {
            $data['Status'] = 'Published';
        }
        
        $result = $this->repository->update($id, $data);
        $this->repository->clearCache();

        $this->enterpriseEvent->dispatch(
            'ACADEMIC',
            'UPDATE',
            'ASSESSMENT',
            $id,
            Auth::id(),
            ['ACADEMIC'],
            [],
            $data
        );

        return $result;
    }
    
    public function delete($id)
    {
        $result = $this->repository->delete($id);
        $this->repository->clearCache();

        $this->enterpriseEvent->dispatch(
            'ACADEMIC',
            'DELETE',
            'ASSESSMENT',
            $id,
            Auth::id(),
            ['ACADEMIC'],
            [],
            []
        );

        return $result;
    }
}
