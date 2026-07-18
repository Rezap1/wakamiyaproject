<?php

namespace App\Services\Core;

use App\Interfaces\GoogleSheets\PositionRepositoryInterface;

class PositionService
{
    protected $positionRepository;

    public function __construct(PositionRepositoryInterface $positionRepository)
    {
        $this->positionRepository = $positionRepository;
    }

    public function getAllPositions()
    {
        return $this->positionRepository->fetchAll();
    }

    public function getPositionById($id)
    {
        return $this->positionRepository->findById($id);
    }
    
    public function getPositionByCode($code)
    {
        return $this->positionRepository->findByCode($code);
    }

    public function getPositionByName($name)
    {
        return $this->positionRepository->findByName($name);
    }

    public function createPosition(array $data)
    {
        $newId = $this->positionRepository->generateNewId('POS', 6);

        $mappedData = [
            'Position_ID' => $newId,
            'Position_Name' => $data['Position_Name'],
            'Position_Code' => $data['Position_Code'],
            'Department_ID' => $data['Department_ID'],
            'Position_Level' => $data['Position_Level'],
            'Is_Active' => $data['Is_Active'] ?? 'TRUE',
            'Created_At' => now()->toDateTimeString(),
            'Updated_At' => now()->toDateTimeString(),
            'Created_By' => auth()->id() ?? 'SYSTEM',
            'Updated_By' => auth()->id() ?? 'SYSTEM',
            'Notes' => $data['Notes'] ?? ''
        ];

        $this->positionRepository->create($mappedData);
        
        return $mappedData;
    }
    
    public function updatePosition($id, array $data)
    {
        $mappedData = [
            'Updated_At' => now()->toDateTimeString(),
            'Updated_By' => auth()->id() ?? 'SYSTEM',
        ];
        
        if (isset($data['Position_Name'])) $mappedData['Position_Name'] = $data['Position_Name'];
        if (isset($data['Position_Code'])) $mappedData['Position_Code'] = $data['Position_Code'];
        if (isset($data['Department_ID'])) $mappedData['Department_ID'] = $data['Department_ID'];
        if (isset($data['Position_Level'])) $mappedData['Position_Level'] = $data['Position_Level'];
        if (isset($data['Is_Active'])) $mappedData['Is_Active'] = $data['Is_Active'];
        if (isset($data['Notes'])) $mappedData['Notes'] = $data['Notes'];

        return $this->positionRepository->update($id, $mappedData);
    }

    public function deletePosition($id)
    {
        return $this->positionRepository->softDelete($id);
    }
}
