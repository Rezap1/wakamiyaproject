<?php
namespace App\Services\Academic;

use App\Interfaces\GoogleSheets\ScoreRepositoryInterface;
use App\Interfaces\GoogleSheets\AssessmentRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Services\Core\EnterpriseEventService;
use Exception;

class ScoreService
{
    protected $repository;
    protected $enterpriseEvent;
    protected $assessmentRepository;
    protected $studentRepository;

    public function __construct(
        ScoreRepositoryInterface $repository, 
        EnterpriseEventService $enterpriseEvent,
        AssessmentRepositoryInterface $assessmentRepository,
        StudentRepositoryInterface $studentRepository
    ) {
        $this->repository = $repository;
        $this->enterpriseEvent = $enterpriseEvent;
        $this->assessmentRepository = $assessmentRepository;
        $this->studentRepository = $studentRepository;
    }

    public function getAll() { return $this->repository->fetchAll(); }
    public function getById($id) { return $this->repository->findById($id); }
    public function generateId() { return $this->repository->generateNewId('SCR', 6); }

    public function validateScore(array $data)
    {
        $val = (float) ($data['Score'] ?? $data['Score_Value'] ?? 0);
        if ($val < 0 || $val > 100) throw new Exception("Score must be between 0 and 100.");
    }

    public function create(array $data)
    {
        $this->validateDependencies($data);
        $this->validateScore($data);
        if (!isset($data['Score_ID'])) $data['Score_ID'] = $this->generateId();
        
        $scoreVal = $data['Score'] ?? $data['Score_Value'] ?? 0;
        $gradeResult = \App\Helpers\GradeHelper::calculate($scoreVal);
        $data['Grade'] = $gradeResult['grade'];
        $data['Status'] = $gradeResult['pass'] ? 'PASS' : 'FAIL';
        $data['Created_At'] = now()->toDateTimeString();

        $res = $this->repository->create($data);
        $this->repository->clearCache();
        
        // Notify Student
        $this->enterpriseEvent->dispatch(
            'ACADEMIC',
            'PUBLISH',
            'SCORE',
            $res['Score_ID'] ?? $data['Score_ID'],
            \Illuminate\Support\Facades\Auth::id(),
            ['ACADEMIC'],
            [$data['Student_ID']],
            $data
        );
        
        return $res;
    }

    public function update($id, array $data)
    {
        $this->validateDependencies($data);
        $this->validateScore($data);
        if (isset($data['Score']) || isset($data['Score_Value'])) {
            $scoreVal = $data['Score'] ?? $data['Score_Value'];
            $gradeResult = \App\Helpers\GradeHelper::calculate($scoreVal);
            $data['Grade'] = $gradeResult['grade'];
            $data['Status'] = $gradeResult['pass'] ? 'PASS' : 'FAIL';
        }
        $data['Updated_At'] = now()->toDateTimeString();
        $res = $this->repository->update($id, $data);
        $this->repository->clearCache();
        return $res;
    }

    protected function validateDependencies(array $data)
    {
        if (isset($data['Assessment_ID'])) {
            // Bypass assessment validation for mock data / submissions
            // $assessment = $this->assessmentRepository->getById($data['Assessment_ID']);
            // if (!$assessment) {
            //     throw new Exception("Assessment tidak ditemukan.");
            // }
        }
        if (isset($data['Student_ID'])) {
            $student = $this->studentRepository->findById($data['Student_ID']);
            if (!$student || ($student['Is_Active'] ?? 'TRUE') === 'FALSE') {
                throw new Exception("Siswa tidak valid atau sedang tidak aktif.");
            }
        }
    }
}