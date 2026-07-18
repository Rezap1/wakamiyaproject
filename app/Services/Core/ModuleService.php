<?php

namespace App\Services\Core;

use App\Interfaces\GoogleSheets\ModuleRepositoryInterface;

class ModuleService
{
    protected $moduleRepository;

    public function __construct(ModuleRepositoryInterface $moduleRepository)
    {
        $this->moduleRepository = $moduleRepository;
    }

    public function getAllModules()
    {
        return $this->moduleRepository->fetchAll();
    }

    public function getModuleById($id)
    {
        return $this->moduleRepository->findById($id);
    }
    
    public function getModuleByCode($code)
    {
        return $this->moduleRepository->findByCode($code);
    }

    public function getModuleByName($name)
    {
        return $this->moduleRepository->findByName($name);
    }

    public function createModule(array $data)
    {
        $newId = $this->moduleRepository->generateNewId('MOD', 6);

        $mappedData = [
            'Module_ID' => $newId,
            'Module_Name' => $data['Module_Name'],
            'Module_Code' => $data['Module_Code'],
            'Module_Group' => $data['Module_Group'],
            'Module_Order' => $data['Module_Order'],
            'Is_Active' => $data['Is_Active'] ?? 'TRUE',
            'Created_At' => now()->toDateTimeString(),
            'Updated_At' => now()->toDateTimeString(),
            'Created_By' => auth()->id() ?? 'SYSTEM',
            'Updated_By' => auth()->id() ?? 'SYSTEM',
            'Notes' => $data['Notes'] ?? ''
        ];

        $this->moduleRepository->create($mappedData);
        
        return $mappedData;
    }
    
    public function updateModule($id, array $data)
    {
        $mappedData = [
            'Updated_At' => now()->toDateTimeString(),
            'Updated_By' => auth()->id() ?? 'SYSTEM',
        ];
        
        if (isset($data['Module_Name'])) $mappedData['Module_Name'] = $data['Module_Name'];
        if (isset($data['Module_Code'])) $mappedData['Module_Code'] = $data['Module_Code'];
        if (isset($data['Module_Group'])) $mappedData['Module_Group'] = $data['Module_Group'];
        if (isset($data['Module_Order'])) $mappedData['Module_Order'] = $data['Module_Order'];
        if (isset($data['Is_Active'])) $mappedData['Is_Active'] = $data['Is_Active'];
        if (isset($data['Notes'])) $mappedData['Notes'] = $data['Notes'];

        return $this->moduleRepository->update($id, $mappedData);
    }

    public function deleteModule($id)
    {
        return $this->moduleRepository->softDelete($id);
    }
}
