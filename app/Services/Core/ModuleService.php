<?php

namespace App\Services\Core;

use App\Interfaces\GoogleSheets\ModuleRepositoryInterface;
use App\Services\Core\EnterpriseEventService;

class ModuleService
{
    protected $moduleRepository;
    protected $enterpriseEvent;

    public function __construct(
        ModuleRepositoryInterface $moduleRepository,
        EnterpriseEventService $enterpriseEvent
    ) {
        $this->moduleRepository = $moduleRepository;
        $this->enterpriseEvent = $enterpriseEvent;
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
        
        $this->enterpriseEvent->dispatch(
            'MODULE',
            'CREATE',
            'MODULE',
            $newId,
            auth()->id() ?? 'SYSTEM',
            ['ADMINISTRATOR'],
            [],
            $mappedData
        );

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

        $res = $this->moduleRepository->update($id, $mappedData);

        $this->enterpriseEvent->dispatch(
            'MODULE',
            'UPDATE',
            'MODULE',
            $id,
            auth()->id() ?? 'SYSTEM',
            ['ADMINISTRATOR'],
            [],
            $mappedData
        );

        return $res;
    }

    public function deleteModule($id)
    {
        $res = $this->moduleRepository->delete($id);

        $this->enterpriseEvent->dispatch(
            'MODULE',
            'DELETE',
            'MODULE',
            $id,
            auth()->id() ?? 'SYSTEM',
            ['ADMINISTRATOR'],
            [],
            []
        );

        return $res;
    }
}
