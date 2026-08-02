<?php

namespace App\Services\Core;

use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\ProgramRepositoryInterface;
use App\Interfaces\GoogleSheets\BatchRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Services\Core\EnterpriseEventService;
use Exception;

class StudentService
{
    protected $studentRepository;
    protected $programRepository;
    protected $batchRepository;
    protected $classRepository;
    protected $enterpriseEvent;

    public function __construct(
        StudentRepositoryInterface $studentRepository,
        ProgramRepositoryInterface $programRepository,
        BatchRepositoryInterface $batchRepository,
        ClassRepositoryInterface $classRepository,
        EnterpriseEventService $enterpriseEvent
    ) {
        $this->studentRepository = $studentRepository;
        $this->programRepository = $programRepository;
        $this->batchRepository = $batchRepository;
        $this->classRepository = $classRepository;
        $this->enterpriseEvent = $enterpriseEvent;
    }

    public function getAllStudents()
    {
        $students = $this->studentRepository->fetchAll();
        return $students->map(function ($student) {
            $student['Completeness_Score'] = $this->calculateCompleteness($student);
            return $student;
        });
    }

    public function getStudentById($id)
    {
        $student = $this->studentRepository->findById($id);
        if ($student) {
            $student['Completeness_Score'] = $this->calculateCompleteness($student);
        }
        return $student;
    }

    protected function calculateCompleteness($student)
    {
        $fieldsToCheck = [
            'User_ID', 'Program_ID', 'Batch_ID', 'Class_ID',
            'Student_Number', 'Full_Name', 'Gender', 'Birth_Place', 'Birth_Date',
            'National_ID', 'Phone_Number', 'Email', 'Address', 'Education'
        ];
        
        $filledCount = 0;
        foreach ($fieldsToCheck as $field) {
            if (!empty($student[$field])) {
                $filledCount++;
            }
        }
        
        return round(($filledCount / count($fieldsToCheck)) * 100);
    }

    public function createStudent(array $data)
    {
        $existingByNumber = $this->studentRepository->findByStudentNumber($data['Student_Number']);
        if ($existingByNumber) {
            throw new Exception("Nomor Induk Siswa sudah terdaftar.");
        }

        if (!empty($data['National_ID'])) {
            $existingByNationalId = $this->studentRepository->findByNationalId($data['National_ID']);
            if ($existingByNationalId) {
                throw new Exception("NIK (KTP) sudah terdaftar.");
            }
        }

        $program = $this->programRepository->findById($data['Program_ID']);
        if (!$program || ($program['Is_Active'] ?? 'TRUE') === 'FALSE') {
            throw new Exception("Program tidak valid atau sedang tidak aktif.");
        }

        $batch = $this->batchRepository->findById($data['Batch_ID']);
        if (!$batch || ($batch['Is_Active'] ?? 'TRUE') === 'FALSE') {
            throw new Exception("Angkatan tidak valid atau sedang tidak aktif.");
        }

        $class = $this->classRepository->findById($data['Class_ID']);
        if (!$class || ($class['Is_Active'] ?? 'TRUE') === 'FALSE') {
            throw new Exception("Kelas tidak valid atau sedang tidak aktif.");
        }

        $newId = $this->studentRepository->generateNewId('STD', 6);
        
        $userService = app(\App\Services\Core\UserService::class);
        $user = $userService->getUserById($data['User_ID']);
        if (!$user) {
            throw new Exception("User tidak ditemukan.");
        }

        $allStudents = $this->studentRepository->fetchAll();
        $existingUser = $allStudents->firstWhere('User_ID', $data['User_ID']);
        if ($existingUser) {
            throw new Exception("User ini sudah terdaftar sebagai Siswa.");
        }

        $mappedData = [
            'Student_ID' => $newId,
            'User_ID' => $data['User_ID'],
            'Student_Number' => $data['Student_Number'],
            'Registration_Date' => $data['Registration_Date'],
            'Full_Name' => $user['Full_Name'],
            'Gender' => $data['Gender'] ?? '',
            'Birth_Place' => $data['Birth_Place'] ?? '',
            'Birth_Date' => $data['Birth_Date'] ?? '',
            'National_ID' => $data['National_ID'] ?? '',
            'Phone_Number' => $user['Phone_Number'] ?? '',
            'Email' => $user['Email'] ?? '',
            'Address' => $data['Address'] ?? '',
            'Education' => $data['Education'],
            'Program_ID' => $data['Program_ID'],
            'Class_ID' => $data['Class_ID'],
            'Batch_ID' => $data['Batch_ID'],
            'Enrollment_Status' => $data['Enrollment_Status'],
            'Graduation_Status' => $data['Graduation_Status'] ?? 'Belum Lulus',
            'Is_Active' => $data['Is_Active'] ?? 'TRUE',
            'Created_At' => now()->toDateTimeString(),
            'Updated_At' => now()->toDateTimeString(),
            'Created_By' => auth()->id() ?? 'SYSTEM',
            'Updated_By' => auth()->id() ?? 'SYSTEM',
            'Notes' => $data['Notes'] ?? ''
        ];

        $this->studentRepository->create($mappedData);
        
        $this->enterpriseEvent->dispatch(
            'STUDENT',
            'CREATE',
            'STUDENT',
            $newId,
            auth()->id() ?? 'SYSTEM',
            ['ADMINISTRATOR', 'ACADEMIC'],
            [],
            $mappedData
        );

        return $mappedData;
    }
    
    public function updateStudent($id, array $data)
    {
        $student = $this->getStudentById($id);
        if (!$student) {
            throw new Exception("Data Siswa tidak ditemukan.");
        }

        if (isset($data['Student_Number']) && $data['Student_Number'] !== $student['Student_Number']) {
            $existingByNumber = $this->studentRepository->findByStudentNumber($data['Student_Number']);
            if ($existingByNumber) {
                throw new Exception("Nomor Induk Siswa sudah terdaftar.");
            }
        }

        if (isset($data['National_ID']) && !empty($data['National_ID']) && $data['National_ID'] !== $student['National_ID']) {
            $existingByNationalId = $this->studentRepository->findByNationalId($data['National_ID']);
            if ($existingByNationalId) {
                throw new Exception("NIK (KTP) sudah terdaftar.");
            }
        }

        if (isset($data['Program_ID']) && $data['Program_ID'] !== $student['Program_ID']) {
            $program = $this->programRepository->findById($data['Program_ID']);
            if (!$program || ($program['Is_Active'] ?? 'TRUE') === 'FALSE') {
                throw new Exception("Program tidak valid atau sedang tidak aktif.");
            }
        }

        if (isset($data['Batch_ID']) && $data['Batch_ID'] !== $student['Batch_ID']) {
            $batch = $this->batchRepository->findById($data['Batch_ID']);
            if (!$batch || ($batch['Is_Active'] ?? 'TRUE') === 'FALSE') {
                throw new Exception("Angkatan tidak valid atau sedang tidak aktif.");
            }
        }

        if (isset($data['Class_ID']) && $data['Class_ID'] !== $student['Class_ID']) {
            $class = $this->classRepository->findById($data['Class_ID']);
            if (!$class || ($class['Is_Active'] ?? 'TRUE') === 'FALSE') {
                throw new Exception("Kelas tidak valid atau sedang tidak aktif.");
            }
        }

        $mappedData = [
            'Updated_At' => now()->toDateTimeString(),
            'Updated_By' => auth()->id() ?? 'SYSTEM',
        ];
        
        $userService = app(\App\Services\Core\UserService::class);
        $userId = $data['User_ID'] ?? $student['User_ID'] ?? null;
        if ($userId) {
            $user = $userService->getUserById($userId);
            if ($user) {
                $mappedData['Full_Name'] = $user['Full_Name'];
                $mappedData['Phone_Number'] = $user['Phone_Number'] ?? '';
                $mappedData['Email'] = $user['Email'] ?? '';
            }
        }
        
        if (isset($data['User_ID'])) {
            $mappedData['User_ID'] = $data['User_ID'];
        }
        
        $allowedFields = [
            'Student_Number', 'Registration_Date', 'Gender',
            'Birth_Place', 'Birth_Date', 'National_ID',
            'Address', 'Education', 'Program_ID', 'Class_ID', 'Batch_ID',
            'Enrollment_Status', 'Graduation_Status', 'Is_Active', 'Notes'
        ];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $mappedData[$field] = $data[$field];
            }
        }

        $res = $this->studentRepository->update($id, $mappedData);
        
        // Phase 10.5: Generate Certificate and Academic Report on Graduation
        try {
            $newGradStatus = $mappedData['Graduation_Status'] ?? null;
            $oldGradStatus = $student['Graduation_Status'] ?? null;
            
            if ($newGradStatus && in_array(strtolower($newGradStatus), ['lulus', 'graduated', 'completed']) && strtolower($oldGradStatus) !== strtolower($newGradStatus)) {
                $program = $this->programRepository->findById($student['Program_ID']) ?? [];
                // Retrieve scores for Academic Report
                $scores = [];
                try {
                    $scoreRepo = app(\App\Interfaces\GoogleSheets\ScoreRepositoryInterface::class);
                    $scores = collect($scoreRepo->getAll())->where('Student_ID', $id)->values()->toArray();
                } catch (\Exception $e) {}

                $docAutomation = app(\App\Services\Core\DocumentAutomationService::class);
                
                // 1. Generate Certificate
                $docAutomation->generateDocument(
                    'Certificate',
                    'Student',
                    $id,
                    ['student' => $res, 'program' => $program, 'certificate' => ['Issue_Date' => now()->format('Y-m-d')]],
                    'pdf.certificate',
                    auth()->user()->email ?? 'System'
                );

                // 2. Generate Academic Report
                $docAutomation->generateDocument(
                    'AcademicReport',
                    'Student',
                    $id,
                    ['student' => $res, 'program' => $program, 'scores' => $scores],
                    'pdf.academic_report',
                    auth()->user()->email ?? 'System'
                );
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to generate Graduation Documents for Student {$id}: " . $e->getMessage());
        }

        $this->enterpriseEvent->dispatch(
            'STUDENT',
            'UPDATE',
            'STUDENT',
            $id,
            auth()->id() ?? 'SYSTEM',
            ['ADMINISTRATOR', 'ACADEMIC'],
            [],
            $mappedData
        );

        return $res;
    }

    public function deleteStudent($id)
    {
        $res = $this->studentRepository->delete($id);
        
        $this->enterpriseEvent->dispatch(
            'STUDENT',
            'DELETE',
            'STUDENT',
            $id,
            auth()->id() ?? 'SYSTEM',
            ['ADMINISTRATOR', 'ACADEMIC'],
            [],
            []
        );

        return $res;
    }
}
