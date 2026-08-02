<?php

namespace App\Services\Core;

use App\Interfaces\GoogleSheets\TeacherRepositoryInterface;
use App\Interfaces\GoogleSheets\EmployeeRepositoryInterface;
use Carbon\Carbon;
use App\Services\Core\EnterpriseEventService;
use Exception;

class TeacherService
{
    protected $teacherRepository;
    protected $employeeRepository;
    protected $userRepository;
    protected $enterpriseEvent;

    public function __construct(
        TeacherRepositoryInterface $teacherRepository, 
        EmployeeRepositoryInterface $employeeRepository,
        \App\Interfaces\GoogleSheets\UserRepositoryInterface $userRepository,
        EnterpriseEventService $enterpriseEvent
    ) {
        $this->teacherRepository = $teacherRepository;
        $this->employeeRepository = $employeeRepository;
        $this->userRepository = $userRepository;
        $this->enterpriseEvent = $enterpriseEvent;
    }

    public function getAllTeachers()
    {
        $teachers = $this->teacherRepository->fetchAll();
        return $teachers->map(function ($teacher) {
            $teacher['Completeness_Score'] = $this->calculateCompleteness($teacher);
            return $teacher;
        });
    }

    public function getTeacherById($id)
    {
        $teacher = $this->teacherRepository->findById($id);
        if ($teacher) {
            $teacher['Completeness_Score'] = $this->calculateCompleteness($teacher);
        }
        return $teacher;
    }

    protected function calculateCompleteness($teacher)
    {
        $fieldsToCheck = [
            'User_ID', 'Employee_ID', 'Teacher_Code', 'Full_Name',
            'Gender', 'Phone_Number', 'Email', 'Specialization', 
            'Hire_Date', 'Teaching_Status'
        ];
        
        $filledCount = 0;
        foreach ($fieldsToCheck as $field) {
            if (!empty($teacher[$field])) {
                $filledCount++;
            }
        }
        
        return round(($filledCount / count($fieldsToCheck)) * 100);
    }
    
    public function getTeacherByEmployeeId($employeeId)
    {
        return $this->teacherRepository->findByEmployeeId($employeeId);
    }

    public function createTeacher(array $data)
    {
        // Get User Details to ensure consistency
        $user = $this->userRepository->findById($data['User_ID']);
        if (!$user) {
            throw new Exception("User tidak ditemukan.");
        }

        // Prevent Duplicate Teacher for same User
        $allTeachers = $this->teacherRepository->fetchAll();
        $existing = $allTeachers->firstWhere('User_ID', $data['User_ID']);
        if ($existing) {
            throw new Exception("User ini sudah terdaftar sebagai Teacher.");
        }

        $newId = $this->teacherRepository->generateNewId('TCH', 6);
        $year = Carbon::now()->format('Y');
        $newTeacherCode = $this->teacherRepository->generateTeacherCode('TCH', $year, 3);

        $mappedData = [
            'Teacher_ID' => $newId,
            'User_ID' => $data['User_ID'],
            'Employee_ID' => $user['Employee_ID'] ?? '', // Map Employee_ID if user is an employee
            'Teacher_Code' => $newTeacherCode,
            'Full_Name' => $user['Full_Name'] ?? '', // Readonly from User
            'Gender' => '',       // Legacy compatibility (no gender in User by default, or you can map if available)
            'Phone_Number' => $user['Phone_Number'] ?? '', // Readonly from User
            'Email' => $user['Email'] ?? '',         // Readonly from User
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
        
        $this->enterpriseEvent->dispatch(
            'TEACHER',
            'CREATE',
            'TEACHER',
            $newId,
            auth()->id() ?? 'SYSTEM',
            ['ADMINISTRATOR', 'HR', 'ACADEMIC'],
            [],
            $mappedData
        );

        return $mappedData;
    }
    
    public function updateTeacher($id, array $data)
    {
        // Get teacher first to check Employee mapping
        $teacher = $this->getTeacherById($id);
        if (!$teacher) {
            throw new Exception("Teacher tidak ditemukan.");
        }

        // Even on update, we force consistency from the connected User
        $userId = $data['User_ID'] ?? ($teacher['User_ID'] ?? '');
        $user = $this->userRepository->findById($userId);
        if (!$user) {
            throw new Exception("User tidak ditemukan.");
        }

        $mappedData = [
            'Updated_At' => now()->toDateTimeString(),
            'Updated_By' => auth()->id() ?? 'SYSTEM',
            'Full_Name' => $user['Full_Name'] ?? '',
            'Phone_Number' => $user['Phone_Number'] ?? '',
            'Email' => $user['Email'] ?? '',
        ];
        
        // Map allowed fields that user can change
        $allowedFields = [
            'User_ID', 'Specialization', 'Hire_Date', 'Teaching_Status', 'Is_Active', 'Notes'
        ];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $mappedData[$field] = $data[$field];
            }
        }
        
        // Also update Employee_ID just in case User changed
        if (isset($user['Employee_ID']) && !empty($user['Employee_ID'])) {
            $mappedData['Employee_ID'] = $user['Employee_ID'];
        }

        $res = $this->teacherRepository->update($id, $mappedData);

        $this->enterpriseEvent->dispatch(
            'TEACHER',
            'UPDATE',
            'TEACHER',
            $id,
            auth()->id() ?? 'SYSTEM',
            ['ADMINISTRATOR', 'HR', 'ACADEMIC'],
            [],
            $mappedData
        );

        return $res;
    }

    public function deleteTeacher($id)
    {
        // Add soft delete protection if necessary in the future
        $res = $this->teacherRepository->delete($id);

        $this->enterpriseEvent->dispatch(
            'TEACHER',
            'DELETE',
            'TEACHER',
            $id,
            auth()->id() ?? 'SYSTEM',
            ['ADMINISTRATOR', 'HR', 'ACADEMIC'],
            [],
            []
        );

        return $res;
    }
}
