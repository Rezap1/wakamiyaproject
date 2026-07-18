<?php

namespace App\Services\Core;

use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\ProgramRepositoryInterface;
use App\Interfaces\GoogleSheets\BatchRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use Exception;

class StudentService
{
    protected $studentRepository;
    protected $programRepository;
    protected $batchRepository;
    protected $classRepository;

    public function __construct(
        StudentRepositoryInterface $studentRepository,
        ProgramRepositoryInterface $programRepository,
        BatchRepositoryInterface $batchRepository,
        ClassRepositoryInterface $classRepository
    ) {
        $this->studentRepository = $studentRepository;
        $this->programRepository = $programRepository;
        $this->batchRepository = $batchRepository;
        $this->classRepository = $classRepository;
    }

    public function getAllStudents()
    {
        return $this->studentRepository->fetchAll();
    }

    public function getStudentById($id)
    {
        return $this->studentRepository->findById($id);
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

        $mappedData = [
            'Student_ID' => $newId,
            'Student_Number' => $data['Student_Number'],
            'Registration_Date' => $data['Registration_Date'],
            'Full_Name' => $data['Full_Name'],
            'Gender' => $data['Gender'],
            'Birth_Place' => $data['Birth_Place'] ?? '',
            'Birth_Date' => $data['Birth_Date'] ?? '',
            'National_ID' => $data['National_ID'] ?? '',
            'Phone_Number' => $data['Phone_Number'] ?? '',
            'Email' => $data['Email'] ?? '',
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
        
        $allowedFields = [
            'Student_Number', 'Registration_Date', 'Full_Name', 'Gender',
            'Birth_Place', 'Birth_Date', 'National_ID', 'Phone_Number', 'Email',
            'Address', 'Education', 'Program_ID', 'Class_ID', 'Batch_ID',
            'Enrollment_Status', 'Graduation_Status', 'Is_Active', 'Notes'
        ];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $mappedData[$field] = $data[$field];
            }
        }

        return $this->studentRepository->update($id, $mappedData);
    }

    public function deleteStudent($id)
    {
        return $this->studentRepository->softDelete($id);
    }
}
