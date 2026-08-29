<?php

namespace App\Services\Core;

use App\Interfaces\GoogleSheets\DepartmentRepositoryInterface;
use App\Interfaces\GoogleSheets\EmployeeRepositoryInterface;
use App\Services\Core\EnterpriseEventService;
use Exception;

class DepartmentService
{
    protected $departmentRepository;
    protected $employeeRepository;
    protected $enterpriseEvent;

    public function __construct(
        DepartmentRepositoryInterface $departmentRepository, 
        EmployeeRepositoryInterface $employeeRepository,
        EnterpriseEventService $enterpriseEvent
    ) {
        $this->departmentRepository = $departmentRepository;
        $this->employeeRepository = $employeeRepository;
        $this->enterpriseEvent = $enterpriseEvent;
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
            'Created_By' => \App\Support\ActorIdentity::required(),
            'Updated_By' => \App\Support\ActorIdentity::required(),
            'Notes' => $data['Notes'] ?? ''
        ];

        $this->departmentRepository->create($mappedData);
        
        $this->enterpriseEvent->dispatch(
            'DEPARTMENT',
            'CREATE',
            'DEPARTMENT',
            $newId,
            \App\Support\ActorIdentity::required(),
            ['ADMINISTRATOR', 'HR'],
            [],
            $mappedData
        );

        return $mappedData;
    }
    
    public function updateDepartment($id, array $data)
    {
        $mappedData = [
            'Updated_At' => now()->toDateTimeString(),
            'Updated_By' => \App\Support\ActorIdentity::required(),
        ];
        
        if (isset($data['Department_Name'])) $mappedData['Department_Name'] = $data['Department_Name'];
        if (isset($data['Department_Code'])) $mappedData['Department_Code'] = $data['Department_Code'];
        if (isset($data['Manager_Employee_ID'])) $mappedData['Manager_Employee_ID'] = $data['Manager_Employee_ID'];
        if (isset($data['Is_Active'])) $mappedData['Is_Active'] = $data['Is_Active'];
        if (isset($data['Notes'])) $mappedData['Notes'] = $data['Notes'];

        $res = $this->departmentRepository->update($id, $mappedData);

        $this->enterpriseEvent->dispatch(
            'DEPARTMENT',
            'UPDATE',
            'DEPARTMENT',
            $id,
            \App\Support\ActorIdentity::required(),
            ['ADMINISTRATOR', 'HR'],
            [],
            $mappedData
        );

        return $res;
    }

    public function deleteDepartment($id)
    {
        // Soft Delete Protection
        $employees = $this->employeeRepository->fetchAll();
        $relatedEmployeesCount = $employees->where('Department_ID', $id)->count();

        if ($relatedEmployeesCount > 0) {
            throw new Exception("Department ini masih digunakan oleh {$relatedEmployeesCount} data Pegawai. Silakan ubah status menjadi Inactive.");
        }

        $res = $this->departmentRepository->delete($id);

        $this->enterpriseEvent->dispatch(
            'DEPARTMENT',
            'DELETE',
            'DEPARTMENT',
            $id,
            \App\Support\ActorIdentity::required(),
            ['ADMINISTRATOR', 'HR'],
            [],
            []
        );

        return $res;
    }
}
