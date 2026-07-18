<?php

namespace App\Services\Core;

use App\Interfaces\GoogleSheets\TeacherRepositoryInterface;
use App\Interfaces\GoogleSheets\EmployeeRepositoryInterface;
use Carbon\Carbon;
use Exception;

class TeacherService
{
    protected $teacherRepository;
    protected $employeeRepository;

    public function __construct(TeacherRepositoryInterface $teacherRepository, EmployeeRepositoryInterface $employeeRepository)
    {
        $this->teacherRepository = $teacherRepository;
        $this->employeeRepository = $employeeRepository;
    }

    public function getAllTeachers()
    {
        return $this->teacherRepository->fetchAll();
    }

    public function getTeacherById($id)
    {
        return $this->teacherRepository->findById($id);
    }
    
    public function getTeacherByEmployeeId($employeeId)
    {
        return $this->teacherRepository->findByEmployeeId($employeeId);
    }

    public function createTeacher(array $data)
    {
        // Get Employee Details to ensure consistency
        $employee = $this->employeeRepository->findById($data['Employee_ID']);
        if (!$employee) {
            throw new Exception("Employee tidak ditemukan.");
        }

        $newId = $this->teacherRepository->generateNewId('TCH', 6);
        $year = Carbon::now()->format('Y');
        $newTeacherCode = $this->teacherRepository->generateTeacherCode('TCH', $year, 3);

        $mappedData = [
            'Teacher_ID' => $newId,
            'Employee_ID' => $data['Employee_ID'],
            'Teacher_Code' => $newTeacherCode,
            'Full_Name' => $employee['Full_Name'], // Readonly from Employee
            'Gender' => $employee['Gender'],       // Readonly from Employee
            'Phone_Number' => $employee['Phone_Number'], // Readonly from Employee
            'Email' => $employee['Email'],         // Readonly from Employee
            'Specialization' => $data['Specialization'],
            'Hire_Date' => $data['Hire_Date'],
            'Teaching_Status' => $data['Teaching_Status'],
            'Is_Active' => $data['Is_Active'] ?? 'TRUE',
            'Created_At' => now()->toDateTimeString(),
            'Updated_At' => now()->toDateTimeString(),
            'Created_By' => auth()->id() ?? 'SYSTEM',
            'Updated_By' => auth()->id() ?? 'SYSTEM',
            'Notes' => $data['Notes'] ?? ''
        ];

        $this->teacherRepository->create($mappedData);
        
        return $mappedData;
    }
    
    public function updateTeacher($id, array $data)
    {
        // Get teacher first to check Employee mapping
        $teacher = $this->getTeacherById($id);
        if (!$teacher) {
            throw new Exception("Teacher tidak ditemukan.");
        }

        // Even on update, we force consistency from the connected Employee
        $employeeId = $data['Employee_ID'] ?? $teacher['Employee_ID'];
        $employee = $this->employeeRepository->findById($employeeId);
        if (!$employee) {
            throw new Exception("Employee tidak ditemukan.");
        }

        $mappedData = [
            'Updated_At' => now()->toDateTimeString(),
            'Updated_By' => auth()->id() ?? 'SYSTEM',
            'Full_Name' => $employee['Full_Name'],
            'Gender' => $employee['Gender'],
            'Phone_Number' => $employee['Phone_Number'],
            'Email' => $employee['Email'],
        ];
        
        // Map allowed fields that user can change
        $allowedFields = [
            'Employee_ID', 'Specialization', 'Hire_Date', 'Teaching_Status', 'Is_Active', 'Notes'
        ];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $mappedData[$field] = $data[$field];
            }
        }

        return $this->teacherRepository->update($id, $mappedData);
    }

    public function deleteTeacher($id)
    {
        return $this->teacherRepository->softDelete($id);
    }
}
