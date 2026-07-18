<?php

namespace App\Services\Core;

use App\Interfaces\GoogleSheets\DepartmentRepositoryInterface;

class DepartmentService
{
    protected $departmentRepository;

    public function __construct(DepartmentRepositoryInterface $departmentRepository)
    {
        $this->departmentRepository = $departmentRepository;
    }

    public function getAllDepartments()
    {
        return $this->departmentRepository->fetchAll();
    }

    public function getDepartmentById($id)
    {
        return $this->departmentRepository->findById($id);
    }
    
    public function getDepartmentByCode($code)
    {
        return $this->departmentRepository->findByCode($code);
    }

    public function getDepartmentByName($name)
    {
        return $this->departmentRepository->findByName($name);
    }

    public function createDepartment(array $data)
    {
        $newId = $this->departmentRepository->generateNewId('DEP', 6);

        $mappedData = [
            'Department_ID' => $newId,
            'Department_Name' => $data['Department_Name'],
            'Department_Code' => $data['Department_Code'],
            'Manager_Employee_ID' => $data['Manager_Employee_ID'] ?? '',
            'Is_Active' => $data['Is_Active'] ?? 'TRUE',
            'Created_At' => now()->toDateTimeString(),
            'Updated_At' => now()->toDateTimeString(),
            'Created_By' => auth()->id() ?? 'SYSTEM',
            'Updated_By' => auth()->id() ?? 'SYSTEM',
            'Notes' => $data['Notes'] ?? ''
        ];

        $this->departmentRepository->create($mappedData);
        
        return $mappedData;
    }
    
    public function updateDepartment($id, array $data)
    {
        $mappedData = [
            'Updated_At' => now()->toDateTimeString(),
            'Updated_By' => auth()->id() ?? 'SYSTEM',
        ];
        
        if (isset($data['Department_Name'])) $mappedData['Department_Name'] = $data['Department_Name'];
        if (isset($data['Department_Code'])) $mappedData['Department_Code'] = $data['Department_Code'];
        if (isset($data['Manager_Employee_ID'])) $mappedData['Manager_Employee_ID'] = $data['Manager_Employee_ID'];
        if (isset($data['Is_Active'])) $mappedData['Is_Active'] = $data['Is_Active'];
        if (isset($data['Notes'])) $mappedData['Notes'] = $data['Notes'];

        return $this->departmentRepository->update($id, $mappedData);
    }

    public function deleteDepartment($id)
    {
        return $this->departmentRepository->softDelete($id);
    }
}
