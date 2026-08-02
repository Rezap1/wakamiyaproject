<?php

namespace App\Services\Core;

use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Interfaces\GoogleSheets\ProgramRepositoryInterface;
use App\Interfaces\GoogleSheets\BatchRepositoryInterface;
use App\Interfaces\GoogleSheets\TeacherRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Services\Core\EnterpriseEventService;
use Exception;

class ClassService
{
    protected $classRepository;
    protected $programRepository;
    protected $batchRepository;
    protected $teacherRepository;
    protected $studentRepository;
    protected $enterpriseEvent;

    public function __construct(
        ClassRepositoryInterface $classRepository,
        ProgramRepositoryInterface $programRepository,
        BatchRepositoryInterface $batchRepository,
        TeacherRepositoryInterface $teacherRepository,
        StudentRepositoryInterface $studentRepository,
        EnterpriseEventService $enterpriseEvent
    ) {
        $this->classRepository = $classRepository;
        $this->programRepository = $programRepository;
        $this->batchRepository = $batchRepository;
        $this->teacherRepository = $teacherRepository;
        $this->studentRepository = $studentRepository;
        $this->enterpriseEvent = $enterpriseEvent;
    }

    public function getAllClasses()
    {
        return $this->classRepository->fetchAll();
    }

    public function getClassById($id)
    {
        return $this->classRepository->findById($id);
    }

    public function createClass(array $data)
    {
        $existingByCode = $this->classRepository->findByCode($data['Class_Code']);
        if ($existingByCode) {
            throw new Exception("Kode Kelas sudah digunakan.");
        }

        $program = $this->programRepository->findById($data['Program_ID']);
        if (!$program || ($program['Is_Active'] ?? 'TRUE') === 'FALSE') {
            throw new Exception("Program tidak valid atau sedang tidak aktif.");
        }

        $batch = $this->batchRepository->findById($data['Batch_ID']);
        if (!$batch || ($batch['Is_Active'] ?? 'TRUE') === 'FALSE') {
            throw new Exception("Angkatan (Batch) tidak valid atau sedang tidak aktif.");
        }

        $teacher = $this->teacherRepository->findById($data['Homeroom_Teacher_ID']);
        if (!$teacher || ($teacher['Is_Active'] ?? 'TRUE') === 'FALSE') {
            throw new Exception("Wali Kelas tidak valid atau sedang tidak aktif.");
        }

        if ($data['Capacity'] < 1) {
            throw new Exception("Kapasitas kelas minimal 1.");
        }

        $currentStudent = $data['Current_Student'] ?? 0;
        if ($currentStudent > $data['Capacity']) {
            throw new Exception("Jumlah siswa saat ini tidak boleh melebihi kapasitas kelas.");
        }

        $newId = $this->classRepository->generateNewId('CLS', 6);

        $mappedData = [
            'Class_ID' => $newId,
            'Class_Code' => $data['Class_Code'],
            'Class_Name' => $data['Class_Name'],
            'Batch_ID' => $data['Batch_ID'],
            'Program_ID' => $data['Program_ID'],
            'Homeroom_Teacher_ID' => $data['Homeroom_Teacher_ID'],
            'Capacity' => $data['Capacity'],
            'Current_Student' => $currentStudent,
            'Class_Status' => $data['Class_Status'] ?? 'Aktif',
            'Description' => $data['Description'] ?? '',
            'Is_Active' => $data['Is_Active'] ?? 'TRUE',
            'Created_At' => now()->toDateTimeString(),
            'Updated_At' => now()->toDateTimeString(),
            'Created_By' => auth()->id() ?? 'SYSTEM',
            'Updated_By' => auth()->id() ?? 'SYSTEM',
            'Notes' => $data['Notes'] ?? ''
        ];

        $this->classRepository->create($mappedData);
        
        $this->enterpriseEvent->dispatch(
            'CLASS',
            'CREATE',
            'CLASS',
            $newId,
            auth()->id() ?? 'SYSTEM',
            ['ADMINISTRATOR', 'ACADEMIC'],
            [],
            $mappedData
        );

        return $mappedData;
    }
    
    public function updateClass($id, array $data)
    {
        $class = $this->getClassById($id);
        if (!$class) {
            throw new Exception("Kelas tidak ditemukan.");
        }

        if (isset($data['Class_Code']) && $data['Class_Code'] !== $class['Class_Code']) {
            $existingByCode = $this->classRepository->findByCode($data['Class_Code']);
            if ($existingByCode) {
                throw new Exception("Kode Kelas sudah digunakan.");
            }
        }

        if (isset($data['Program_ID']) && $data['Program_ID'] !== $class['Program_ID']) {
            $program = $this->programRepository->findById($data['Program_ID']);
            if (!$program || ($program['Is_Active'] ?? 'TRUE') === 'FALSE') {
                throw new Exception("Program tidak valid atau sedang tidak aktif.");
            }
        }

        if (isset($data['Batch_ID']) && $data['Batch_ID'] !== $class['Batch_ID']) {
            $batch = $this->batchRepository->findById($data['Batch_ID']);
            if (!$batch || ($batch['Is_Active'] ?? 'TRUE') === 'FALSE') {
                throw new Exception("Angkatan (Batch) tidak valid atau sedang tidak aktif.");
            }
        }

        if (isset($data['Homeroom_Teacher_ID']) && $data['Homeroom_Teacher_ID'] !== $class['Homeroom_Teacher_ID']) {
            $teacher = $this->teacherRepository->findById($data['Homeroom_Teacher_ID']);
            if (!$teacher || ($teacher['Is_Active'] ?? 'TRUE') === 'FALSE') {
                throw new Exception("Wali Kelas tidak valid atau sedang tidak aktif.");
            }
        }

        $capacity = $data['Capacity'] ?? $class['Capacity'];
        if ($capacity < 1) {
            throw new Exception("Kapasitas kelas minimal 1.");
        }

        $currentStudent = $data['Current_Student'] ?? $class['Current_Student'];
        if ($currentStudent > $capacity) {
            throw new Exception("Jumlah siswa saat ini tidak boleh melebihi kapasitas kelas.");
        }

        $mappedData = [
            'Updated_At' => now()->toDateTimeString(),
            'Updated_By' => auth()->id() ?? 'SYSTEM',
        ];
        
        $allowedFields = [
            'Class_Code', 'Class_Name', 'Batch_ID', 'Program_ID',
            'Homeroom_Teacher_ID', 'Capacity', 'Current_Student', 
            'Description', 'Is_Active', 'Notes'
        ];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $mappedData[$field] = $data[$field];
            }
        }

        $res = $this->classRepository->update($id, $mappedData);

        $this->enterpriseEvent->dispatch(
            'CLASS',
            'UPDATE',
            'CLASS',
            $id,
            auth()->id() ?? 'SYSTEM',
            ['ADMINISTRATOR', 'ACADEMIC'],
            [],
            $mappedData
        );

        return $res;
    }

    public function deleteClass($id)
    {
        // Soft Delete Protection
        $students = $this->studentRepository->fetchAll();
        $relatedStudentsCount = $students->where('Class_ID', $id)->count();

        if ($relatedStudentsCount > 0) {
            throw new Exception("Class ini masih digunakan oleh {$relatedStudentsCount} data Siswa. Silakan ubah status menjadi Inactive.");
        }

        $res = $this->classRepository->delete($id);

        $this->enterpriseEvent->dispatch(
            'CLASS',
            'DELETE',
            'CLASS',
            $id,
            auth()->id() ?? 'SYSTEM',
            ['ADMINISTRATOR', 'ACADEMIC'],
            [],
            []
        );

        return $res;
    }
}
