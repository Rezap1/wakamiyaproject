<?php

namespace App\Services\Core;

use App\Interfaces\GoogleSheets\BatchRepositoryInterface;
use App\Interfaces\GoogleSheets\ProgramRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Services\Core\EnterpriseEventService;
use Exception;

class BatchService
{
    protected $batchRepository;
    protected $programRepository;
    protected $studentRepository;
    protected $classRepository;
    protected $enterpriseEvent;

    public function __construct(
        BatchRepositoryInterface $batchRepository,
        ProgramRepositoryInterface $programRepository,
        StudentRepositoryInterface $studentRepository,
        ClassRepositoryInterface $classRepository,
        EnterpriseEventService $enterpriseEvent
    ) {
        $this->batchRepository = $batchRepository;
        $this->programRepository = $programRepository;
        $this->studentRepository = $studentRepository;
        $this->classRepository = $classRepository;
        $this->enterpriseEvent = $enterpriseEvent;
    }

    public function getAllBatches()
    {
        return $this->batchRepository->fetchAll();
    }

    public function getBatchById($id)
    {
        return $this->batchRepository->findById($id);
    }

    public function createBatch(array $data)
    {
        $existingByCode = $this->batchRepository->findByCode($data['Batch_Code']);
        if ($existingByCode) {
            throw new Exception("Kode Batch sudah digunakan.");
        }

        $existingByName = $this->batchRepository->findByName($data['Batch_Name']);
        if ($existingByName) {
            throw new Exception("Nama Batch sudah digunakan.");
        }

        $program = $this->programRepository->findById($data['Program_ID']);
        if (!$program || ($program['Is_Active'] ?? 'TRUE') === 'FALSE') {
            throw new Exception("Program tidak valid atau sedang tidak aktif.");
        }

        if (strtotime($data['End_Date']) < strtotime($data['Start_Date'])) {
            throw new Exception("Tanggal Selesai tidak boleh lebih kecil dari Tanggal Mulai.");
        }

        $newId = $this->batchRepository->generateNewId('BAT', 6);

        $mappedData = [
            'Batch_ID' => $newId,
            'Batch_Code' => $data['Batch_Code'],
            'Batch_Name' => $data['Batch_Name'],
            'Start_Date' => $data['Start_Date'],
            'End_Date' => $data['End_Date'],
            'Program_ID' => $data['Program_ID'],
            'Batch_Status' => $data['Batch_Status'] ?? 'Berlangsung',
            'Description' => $data['Description'] ?? '',
            'Is_Active' => $data['Is_Active'] ?? 'TRUE',
            'Created_At' => now()->toDateTimeString(),
            'Updated_At' => now()->toDateTimeString(),
            'Created_By' => \App\Support\ActorIdentity::required(),
            'Updated_By' => \App\Support\ActorIdentity::required(),
            'Notes' => $data['Notes'] ?? ''
        ];

        $this->batchRepository->create($mappedData);
        
        $this->enterpriseEvent->dispatch(
            'BATCH',
            'CREATE',
            'BATCH',
            $newId,
            \App\Support\ActorIdentity::required(),
            ['ADMINISTRATOR', 'ACADEMIC'],
            [],
            $mappedData
        );

        return $mappedData;
    }
    
    public function updateBatch($id, array $data)
    {
        $batch = $this->getBatchById($id);
        if (!$batch) {
            throw new Exception("Batch tidak ditemukan.");
        }

        if (isset($data['Batch_Code']) && $data['Batch_Code'] !== $batch['Batch_Code']) {
            $existingByCode = $this->batchRepository->findByCode($data['Batch_Code']);
            if ($existingByCode) {
                throw new Exception("Kode Batch sudah digunakan.");
            }
        }

        if (isset($data['Batch_Name']) && $data['Batch_Name'] !== $batch['Batch_Name']) {
            $existingByName = $this->batchRepository->findByName($data['Batch_Name']);
            if ($existingByName) {
                throw new Exception("Nama Batch sudah digunakan.");
            }
        }

        if (isset($data['Program_ID']) && $data['Program_ID'] !== $batch['Program_ID']) {
            $program = $this->programRepository->findById($data['Program_ID']);
            if (!$program || ($program['Is_Active'] ?? 'TRUE') === 'FALSE') {
                throw new Exception("Program tidak valid atau sedang tidak aktif.");
            }
        }

        $startDate = $data['Start_Date'] ?? $batch['Start_Date'];
        $endDate = $data['End_Date'] ?? $batch['End_Date'];
        if (strtotime($endDate) < strtotime($startDate)) {
            throw new Exception("Tanggal Selesai tidak boleh lebih kecil dari Tanggal Mulai.");
        }

        $mappedData = [
            'Updated_At' => now()->toDateTimeString(),
            'Updated_By' => \App\Support\ActorIdentity::required(),
        ];
        
        $allowedFields = [
            'Batch_Code', 'Batch_Name', 'Start_Date', 'End_Date',
            'Program_ID', 'Batch_Status', 'Description', 'Is_Active', 'Notes'
        ];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $mappedData[$field] = $data[$field];
            }
        }

        $res = $this->batchRepository->update($id, $mappedData);

        $this->enterpriseEvent->dispatch(
            'BATCH',
            'UPDATE',
            'BATCH',
            $id,
            \App\Support\ActorIdentity::required(),
            ['ADMINISTRATOR', 'ACADEMIC'],
            [],
            $mappedData
        );

        return $res;
    }

    public function deleteBatch($id)
    {
        // Soft Delete Protection
        $students = collect($this->studentRepository->fetchAll());
        $relatedStudentsCount = $students->where('Batch_ID', $id)->filter(function($s) {
            return ($s['Is_Active'] ?? 'TRUE') !== 'FALSE';
        })->count();

        if ($relatedStudentsCount > 0) {
            throw new Exception("Batch tidak dapat dihapus karena masih digunakan oleh siswa.");
        }
        
        $classes = collect($this->classRepository->fetchAll());
        $relatedClassesCount = $classes->where('Batch_ID', $id)->filter(function($c) {
            return ($c['Is_Active'] ?? 'TRUE') !== 'FALSE';
        })->count();
        
        if ($relatedClassesCount > 0) {
            throw new Exception("Batch tidak dapat dihapus karena masih memiliki kelas aktif.");
        }

        $res = $this->batchRepository->delete($id);

        $this->enterpriseEvent->dispatch(
            'BATCH',
            'DELETE',
            'BATCH',
            $id,
            \App\Support\ActorIdentity::required(),
            ['ADMINISTRATOR', 'ACADEMIC'],
            [],
            []
        );

        return $res;
    }
}
