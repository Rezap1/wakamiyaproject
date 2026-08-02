<?php

namespace App\Services\Academic;

use App\Interfaces\GoogleSheets\AssessmentRepositoryInterface;
use App\Interfaces\GoogleSheets\ProgramRepositoryInterface;
use App\Interfaces\GoogleSheets\BatchRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Interfaces\GoogleSheets\TeacherRepositoryInterface;
use Exception;

class AssessmentService
{
    protected $assessmentRepository;
    protected $programRepository;
    protected $batchRepository;
    protected $classRepository;
    protected $teacherRepository;

    public function __construct(
        AssessmentRepositoryInterface $assessmentRepository,
        ProgramRepositoryInterface $programRepository,
        BatchRepositoryInterface $batchRepository,
        ClassRepositoryInterface $classRepository,
        TeacherRepositoryInterface $teacherRepository
    ) {
        $this->assessmentRepository = $assessmentRepository;
        $this->programRepository = $programRepository;
        $this->batchRepository = $batchRepository;
        $this->classRepository = $classRepository;
        $this->teacherRepository = $teacherRepository;
    }

    public function getAll()
    {
        return $this->assessmentRepository->getAll();
    }

    public function getById($id)
    {
        return $this->assessmentRepository->getById($id);
    }

    public function create(array $data)
    {
        $this->validateDependencies($data);
        return $this->assessmentRepository->create($data);
    }

    public function update($id, array $data)
    {
        $this->validateDependencies($data);
        return $this->assessmentRepository->update($id, $data);
    }

    public function delete($id)
    {
        return $this->assessmentRepository->delete($id);
    }

    protected function validateDependencies(array $data)
    {
        if (isset($data['Program_ID'])) {
            $program = $this->programRepository->findById($data['Program_ID']);
            if (!$program || ($program['Is_Active'] ?? 'TRUE') === 'FALSE') {
                throw new Exception("Program tidak valid atau sedang tidak aktif.");
            }
        }
        if (isset($data['Batch_ID'])) {
            $batch = $this->batchRepository->findById($data['Batch_ID']);
            if (!$batch || ($batch['Is_Active'] ?? 'TRUE') === 'FALSE') {
                throw new Exception("Angkatan tidak valid atau sedang tidak aktif.");
            }
        }
        if (isset($data['Class_ID'])) {
            $class = $this->classRepository->findById($data['Class_ID']);
            if (!$class || ($class['Is_Active'] ?? 'TRUE') === 'FALSE') {
                throw new Exception("Kelas tidak valid atau sedang tidak aktif.");
            }
        }
        if (isset($data['Teacher_ID'])) {
            $teacher = $this->teacherRepository->findById($data['Teacher_ID']);
            if (!$teacher || ($teacher['Is_Active'] ?? 'TRUE') === 'FALSE') {
                throw new Exception("Guru tidak valid atau sedang tidak aktif.");
            }
        }
    }
}