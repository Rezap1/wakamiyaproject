<?php

namespace App\Services\Core;

use App\Interfaces\GoogleSheets\EmployeeRepositoryInterface;
use Carbon\Carbon;

class EmployeeService
{
    protected $employeeRepository;

    public function __construct(EmployeeRepositoryInterface $employeeRepository)
    {
        $this->employeeRepository = $employeeRepository;
    }

    public function getAllEmployees()
    {
        return $this->employeeRepository->fetchAll();
    }

    public function getEmployeeById($id)
    {
        return $this->employeeRepository->findById($id);
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

        $mappedData = [
            'Employee_ID' => $newId,
            'Employee_Number' => $newEmployeeNumber,
            'Full_Name' => $data['Full_Name'],
            'Gender' => $data['Gender'],
            'Birth_Place' => $data['Birth_Place'] ?? '',
            'Birth_Date' => $data['Birth_Date'] ?? '',
            'National_ID' => $data['National_ID'] ?? '',
            'Phone_Number' => $data['Phone_Number'],
            'Email' => $data['Email'],
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
        
        return $mappedData;
    }
    
    public function updateEmployee($id, array $data)
    {
        $mappedData = [
            'Updated_At' => now()->toDateTimeString(),
            'Updated_By' => auth()->id() ?? 'SYSTEM',
        ];
        
        // Map allowed fields
        $allowedFields = [
            'Full_Name', 'Gender', 'Birth_Place', 'Birth_Date', 'National_ID',
            'Phone_Number', 'Email', 'Address', 'Department_ID', 'Position_ID',
            'Join_Date', 'Employment_Status', 'Tax_Number', 'Bank_Name',
            'Bank_Account_Number', 'Account_Holder_Name', 'Is_Active', 'Notes'
        ];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $mappedData[$field] = $data[$field];
            }
        }

        return $this->employeeRepository->update($id, $mappedData);
    }

    public function deleteEmployee($id)
    {
        return $this->employeeRepository->softDelete($id);
    }
}
