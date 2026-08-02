<?php

namespace App\Services\Core;

use App\Interfaces\GoogleSheets\EmployeeRepositoryInterface;
use App\Services\Core\EnterpriseEventService;
use Carbon\Carbon;

class EmployeeService
{
    protected $employeeRepository;
    protected $enterpriseEvent;

    public function __construct(
        EmployeeRepositoryInterface $employeeRepository,
        EnterpriseEventService $enterpriseEvent
    ) {
        $this->employeeRepository = $employeeRepository;
        $this->enterpriseEvent = $enterpriseEvent;
    }

    public function getAllEmployees()
    {
        $employees = $this->employeeRepository->fetchAll();
        return $employees->map(function ($employee) {
            $employee['Completeness_Score'] = $this->calculateCompleteness($employee);
            return $employee;
        });
    }

    public function getEmployeeById($id)
    {
        $employee = $this->employeeRepository->findById($id);
        if ($employee) {
            $employee['Completeness_Score'] = $this->calculateCompleteness($employee);
        }
        return $employee;
    }

    protected function calculateCompleteness($employee)
    {
        $fieldsToCheck = [
            'User_ID', 'Department_ID', 'Position_ID', 'Employee_Number',
            'Full_Name', 'Gender', 'Birth_Place', 'Birth_Date',
            'National_ID', 'Phone_Number', 'Email', 'Address', 
            'Join_Date', 'Employment_Status', 'Tax_Number', 'Bank_Name', 'Bank_Account_Number'
        ];
        
        $filledCount = 0;
        foreach ($fieldsToCheck as $field) {
            if (!empty($employee[$field])) {
                $filledCount++;
            }
        }
        
        return round(($filledCount / count($fieldsToCheck)) * 100);
    }
    
    public function getEmployeeByEmail($email)
    {
        return $this->employeeRepository->findByEmail($email);
    }
    
    public function getEmployeeByNationalId($nationalId)
    {
        return $this->employeeRepository->findByNationalId($nationalId);
    }

    public function createEmployee(array $data)
    {
        $newId = $this->employeeRepository->generateNewId('EMP', 6);
        $year = Carbon::now()->format('Y');
        $newEmployeeNumber = $this->employeeRepository->generateEmployeeNumber('WKM', $year, 3);
        
        $userService = app(\App\Services\Core\UserService::class);
        $user = $userService->getUserById($data['User_ID']);
        if (!$user) {
            throw new \Exception("User tidak ditemukan.");
        }

        // Prevent Duplicate Employee for same User
        $allEmployees = $this->employeeRepository->fetchAll();
        $existingUser = $allEmployees->firstWhere('User_ID', $data['User_ID']);
        if ($existingUser) {
            throw new \Exception("User ini sudah terdaftar sebagai Employee.");
        }

        if (!empty($data['National_ID'])) {
            $existingByNationalId = $this->getEmployeeByNationalId($data['National_ID']);
            if ($existingByNationalId) {
                throw new \Exception("NIK (KTP) sudah terdaftar.");
            }
        }

        $mappedData = [
            'Employee_ID' => $newId,
            'Employee_Number' => $newEmployeeNumber,
            'User_ID' => $data['User_ID'],
            'Full_Name' => $user['Full_Name'],
            'Gender' => $data['Gender'] ?? '',
            'Birth_Place' => $data['Birth_Place'] ?? '',
            'Birth_Date' => $data['Birth_Date'] ?? '',
            'National_ID' => $data['National_ID'] ?? '',
            'Phone_Number' => $user['Phone_Number'] ?? '',
            'Email' => $user['Email'],
            'Address' => $data['Address'] ?? '',
            'Department_ID' => $data['Department_ID'],
            'Position_ID' => $data['Position_ID'],
            'Join_Date' => $data['Join_Date'],
            'Employment_Status' => $data['Employment_Status'],
            'Tax_Number' => $data['Tax_Number'] ?? '',
            'Bank_Name' => $data['Bank_Name'] ?? '',
            'Bank_Account_Number' => $data['Bank_Account_Number'] ?? '',
            'Account_Holder_Name' => $data['Account_Holder_Name'] ?? '',
            'Is_Active' => $data['Is_Active'] ?? 'TRUE',
            'Created_At' => now()->toDateTimeString(),
            'Updated_At' => now()->toDateTimeString(),
            'Created_By' => auth()->id() ?? 'SYSTEM',
            'Updated_By' => auth()->id() ?? 'SYSTEM',
            'Notes' => $data['Notes'] ?? ''
        ];

        $this->employeeRepository->create($mappedData);
        
        $this->enterpriseEvent->dispatch(
            'EMPLOYEE',
            'CREATE',
            'EMPLOYEE',
            $newId,
            auth()->id() ?? 'SYSTEM',
            ['ADMINISTRATOR', 'HR'],
            [],
            $mappedData
        );

        return $mappedData;
    }
    
    public function updateEmployee($id, array $data)
    {
        $mappedData = [
            'Updated_At' => now()->toDateTimeString(),
            'Updated_By' => auth()->id() ?? 'SYSTEM',
        ];
        
        $employee = $this->getEmployeeById($id);
        if (!$employee) {
            throw new \Exception("Employee not found.");
        }

        if (isset($data['National_ID']) && !empty($data['National_ID']) && $data['National_ID'] !== $employee['National_ID']) {
            $existingByNationalId = $this->getEmployeeByNationalId($data['National_ID']);
            if ($existingByNationalId) {
                throw new \Exception("NIK (KTP) sudah terdaftar.");
            }
        }
        
        $userService = app(\App\Services\Core\UserService::class);
        $userId = $data['User_ID'] ?? $this->getEmployeeById($id)['User_ID'] ?? null;
        if ($userId) {
            $user = $userService->getUserById($userId);
            if ($user) {
                $mappedData['Full_Name'] = $user['Full_Name'];
                $mappedData['Phone_Number'] = $user['Phone_Number'] ?? '';
                $mappedData['Email'] = $user['Email'];
            }
        }
        
        if (isset($data['User_ID'])) {
            $mappedData['User_ID'] = $data['User_ID'];
        }
        
        // Map allowed fields
        $allowedFields = [
            'Gender', 'Birth_Place', 'Birth_Date', 'National_ID',
            'Address', 'Department_ID', 'Position_ID',
            'Join_Date', 'Employment_Status', 'Tax_Number', 'Bank_Name',
            'Bank_Account_Number', 'Account_Holder_Name', 'Is_Active', 'Notes'
        ];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $mappedData[$field] = $data[$field];
            }
        }

        $res = $this->employeeRepository->update($id, $mappedData);

        $this->enterpriseEvent->dispatch(
            'EMPLOYEE',
            'UPDATE',
            'EMPLOYEE',
            $id,
            auth()->id() ?? 'SYSTEM',
            ['ADMINISTRATOR', 'HR'],
            [],
            $mappedData
        );

        return $res;
    }

    public function deleteEmployee($id)
    {
        $res = $this->employeeRepository->delete($id);

        $this->enterpriseEvent->dispatch(
            'EMPLOYEE',
            'DELETE',
            'EMPLOYEE',
            $id,
            auth()->id() ?? 'SYSTEM',
            ['ADMINISTRATOR', 'HR'],
            [],
            []
        );

        return $res;
    }
}
