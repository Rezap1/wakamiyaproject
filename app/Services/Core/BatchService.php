<?php

namespace App\Services\Core;

use App\Interfaces\GoogleSheets\BatchRepositoryInterface;
use App\Interfaces\GoogleSheets\ProgramRepositoryInterface;
use Exception;

class BatchService
{
    protected $batchRepository;
    protected $programRepository;

    public function __construct(
        BatchRepositoryInterface $batchRepository,
        ProgramRepositoryInterface $programRepository
    ) {
        $this->batchRepository = $batchRepository;
        $this->programRepository = $programRepository;
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
            'Created_By' => auth()->id() ?? 'SYSTEM',
            'Updated_By' => auth()->id() ?? 'SYSTEM',
            'Notes' => $data['Notes'] ?? ''
        ];

        $this->batchRepository->create($mappedData);
        
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
            'Updated_By' => auth()->id() ?? 'SYSTEM',
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

        return $this->batchRepository->update($id, $mappedData);
    }

    public function deleteBatch($id)
    {
        return $this->batchRepository->softDelete($id);
    }
}
