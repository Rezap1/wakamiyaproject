<?php

namespace App\Services\Core;

use App\Interfaces\GoogleSheets\AssignmentRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use App\Support\Academic\AssignmentStatus;

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
                throw new \Exception('Batas waktu harus setelah tanggal publikasi.');
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
        $data['Status'] = AssignmentStatus::normalize($data['Status'] ?? null) ?? AssignmentStatus::PUBLISHED;
        
        $result = $this->repository->create($data);
        $this->repository->clearCache();
        $this->verifyPersisted($data['Assignment_ID'], $data);
        
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
        $data['Status'] = AssignmentStatus::normalize($data['Status'] ?? null) ?? AssignmentStatus::PUBLISHED;
        
        $result = $this->repository->update($id, $data);
        $this->repository->clearCache();
        $this->verifyPersisted($id, $data);

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

    private function verifyPersisted(string $id, array $expected): void
    {
        // BaseSheetRepository provides a cache-free read. Keep this conditional
        // so lightweight test doubles and alternate repositories remain valid.
        if (!method_exists($this->repository, 'findByIdFresh')) {
            return;
        }

        $persisted = $this->repository->findByIdFresh($id);
        if (!$persisted) {
            throw new \RuntimeException("Assignment {$id} tidak ditemukan setelah disimpan.");
        }

        foreach (['Title', 'Class_ID', 'Teacher_ID', 'Deadline', 'Status', 'Description'] as $field) {
            if (array_key_exists($field, $expected)
                && (string) ($persisted[$field] ?? '') !== (string) ($expected[$field] ?? '')) {
                throw new \RuntimeException("Assignment {$id} gagal diverifikasi pada kolom {$field}.");
            }
        }
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
