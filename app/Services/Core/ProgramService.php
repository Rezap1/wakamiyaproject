<?php

namespace App\Services\Core;

use App\Interfaces\GoogleSheets\ProgramRepositoryInterface;
use Exception;

class ProgramService
{
    protected $programRepository;

    public function __construct(ProgramRepositoryInterface $programRepository)
    {
        $this->programRepository = $programRepository;
    }

    public function getAllPrograms()
    {
        return $this->programRepository->fetchAll();
    }

    public function getProgramById($id)
    {
        return $this->programRepository->findById($id);
    }

    public function createProgram(array $data)
    {
        $existingByCode = $this->programRepository->findByCode($data['Program_Code']);
        if ($existingByCode) {
            throw new Exception("Kode Program sudah digunakan.");
        }

        $existingByName = $this->programRepository->findByName($data['Program_Name']);
        if ($existingByName) {
            throw new Exception("Nama Program sudah digunakan.");
        }

        $newId = $this->programRepository->generateNewId('PRG', 6);

        $mappedData = [
            'Program_ID' => $newId,
            'Program_Code' => $data['Program_Code'],
            'Program_Name' => $data['Program_Name'],
            'Program_Category' => $data['Program_Category'],
            'Description' => $data['Description'] ?? '',
            'Is_Active' => $data['Is_Active'] ?? 'TRUE',
            'Created_At' => now()->toDateTimeString(),
            'Updated_At' => now()->toDateTimeString(),
            'Created_By' => auth()->id() ?? 'SYSTEM',
            'Updated_By' => auth()->id() ?? 'SYSTEM',
            'Notes' => $data['Notes'] ?? ''
        ];

        $this->programRepository->create($mappedData);
        
        return $mappedData;
    }
    
    public function updateProgram($id, array $data)
    {
        $program = $this->getProgramById($id);
        if (!$program) {
            throw new Exception("Program tidak ditemukan.");
        }

        if (isset($data['Program_Code']) && $data['Program_Code'] !== $program['Program_Code']) {
            $existingByCode = $this->programRepository->findByCode($data['Program_Code']);
            if ($existingByCode) {
                throw new Exception("Kode Program sudah digunakan.");
            }
        }

        if (isset($data['Program_Name']) && $data['Program_Name'] !== $program['Program_Name']) {
            $existingByName = $this->programRepository->findByName($data['Program_Name']);
            if ($existingByName) {
                throw new Exception("Nama Program sudah digunakan.");
            }
        }

        $mappedData = [
            'Updated_At' => now()->toDateTimeString(),
            'Updated_By' => auth()->id() ?? 'SYSTEM',
        ];
        
        $allowedFields = [
            'Program_Code', 'Program_Name', 'Program_Category', 
            'Description', 'Is_Active', 'Notes'
        ];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $mappedData[$field] = $data[$field];
            }
        }

        return $this->programRepository->update($id, $mappedData);
    }

    public function deleteProgram($id)
    {
        return $this->programRepository->softDelete($id);
    }
}
