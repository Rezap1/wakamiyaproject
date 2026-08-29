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

    public function getAll() 
    { 
        $scores = $this->repository->fetchAll(); 
        return $scores->map(function($item) {
            $item['Parsed_Details'] = $this->parseEvaluationDetails($item);
            return $item;
        });
    }

    public function getById($id) 
    { 
        $score = $this->repository->findById($id); 
        if ($score) {
            $score['Parsed_Details'] = $this->parseEvaluationDetails($score);
        }
        return $score;
    }

    public function generateId() 
    { 
        return $this->repository->generateNewId('SCR', 6); 
    }

    public function parseEvaluationDetails($score): array
    {
        $detailsRaw = $score['Evaluation_Details'] ?? null;
        if (!empty($detailsRaw) && is_string($detailsRaw)) {
            $decoded = json_decode($detailsRaw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        
        $category = strtolower($score['Assessment_Category'] ?? 'general');
        return [
            'category' => $category,
            'notes' => $score['Notes'] ?? $score['Remarks'] ?? ''
        ];
    }

    public function processEvaluationDetails(array $data): array
    {
        $category = strtoupper(trim($data['Assessment_Category'] ?? 'GENERAL'));
        
        $details = ['category' => strtolower($category)];
        $notes = $data['Notes'] ?? $data['Remarks'] ?? '';
        $details['notes'] = $notes;

        $reservedKeys = [
            '_token', 'Student_ID', 'Assessment_Category', 'Date', 'Notes', 'Remarks', 
            '_method', 'Score_ID', 'Score_Value', 'Score', 'Subject_ID', 'Assessment_ID', 'Assessment_Date', 'Teacher_ID'
        ];

        foreach ($data as $key => $val) {
            if (!in_array($key, $reservedKeys) && $val !== '' && $val !== null) {
                // Parse numeric strings if they look like numbers
                if (is_numeric($val)) {
                    $details[$key] = strpos((string)$val, '.') !== false ? (float)$val : (int)$val;
                } else {
                    $details[$key] = $val;
                }
            }
        }

        // If Score_Value or Score is passed, use it. Otherwise leave it null for aspectual assessments.
        $scoreVal = null;
        if (isset($data['Score_Value']) && $data['Score_Value'] !== '') {
            $scoreVal = (float) $data['Score_Value'];
        } elseif (isset($data['Score']) && $data['Score'] !== '') {
            $scoreVal = (float) $data['Score'];
        }

        if ($category === 'GENERAL' && empty(array_diff(array_keys($details), ['category', 'notes']))) {
             if ($scoreVal === null) {
                  $scoreVal = 0.0; // Fallback
             }
             if ($scoreVal < 1 || $scoreVal > 100) {
                 throw new Exception("Nilai Ujian Bab harus berada di antara 1 dan 100.");
             }
             $details['subject_id'] = $data['Subject_ID'] ?? '';
        }

        if ($scoreVal !== null && ($scoreVal < 0 || $scoreVal > 100)) {
            throw new Exception("Nilai akhir harus berada di antara 0 dan 100.");
        }

        return [
            'category' => $category,
            'score_value' => $scoreVal,
            'evaluation_details' => json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        ];
    }

    public function create(array $data)
    {
        $this->validateDependencies($data);
        $evalResult = $this->processEvaluationDetails($data);
        
        if (!isset($data['Score_ID'])) {
            $data['Score_ID'] = $this->generateId();
        }
        
        $scoreVal = $evalResult['score_value'];
        
        $data['Assessment_Category'] = $evalResult['category'];
        $data['Score'] = $scoreVal ?? '';
        $data['Score_Value'] = $scoreVal ?? '';
        $data['Evaluation_Details'] = $evalResult['evaluation_details'];
        
        if ($scoreVal !== null) {
            $gradeResult = \App\Helpers\GradeHelper::calculate($scoreVal);
            $data['Grade'] = $gradeResult['grade'];
            $data['Status'] = $gradeResult['pass'] ? 'PASS' : 'FAIL';
        } else {
            $data['Grade'] = '';
            $data['Status'] = 'COMPLETED';
        }
        
        $data['Remarks'] = $data['Notes'] ?? ($data['Remarks'] ?? '');
        $data['Created_At'] = now()->toDateTimeString();

        $res = $this->repository->create($data);
        $this->repository->clearCache();
        
        // Notify Student & Dispatch Event
        $this->enterpriseEvent->dispatch(
            'ACADEMIC',
            'PUBLISH',
            'SCORE',
            $data['Score_ID'],
            \App\Support\ActorIdentity::required(),
            ['ACADEMIC'],
            [$data['Student_ID']],
            $data
        );
        
        return $res;
    }

    public function update($id, array $data)
    {
        $this->validateDependencies($data);
        
        $existing = $this->getById($id);
        if (!$existing) {
            throw new Exception("Record nilai tidak ditemukan.");
        }

        // Merge existing category if not passed
        if (empty($data['Assessment_Category']) && !empty($existing['Assessment_Category'])) {
            $data['Assessment_Category'] = $existing['Assessment_Category'];
        }

        $evalResult = $this->processEvaluationDetails(array_merge($existing, $data));
        $scoreVal = $evalResult['score_value'];

        $data['Assessment_Category'] = $evalResult['category'];
        $data['Score'] = $scoreVal ?? '';
        $data['Score_Value'] = $scoreVal ?? '';
        $data['Evaluation_Details'] = $evalResult['evaluation_details'];
        
        if ($scoreVal !== null) {
            $gradeResult = \App\Helpers\GradeHelper::calculate($scoreVal);
            $data['Grade'] = $gradeResult['grade'];
            $data['Status'] = $gradeResult['pass'] ? 'PASS' : 'FAIL';
        } else {
            $data['Grade'] = '';
            $data['Status'] = 'COMPLETED';
        }
        
        $data['Remarks'] = $data['Notes'] ?? ($data['Remarks'] ?? ($existing['Remarks'] ?? ''));
        $data['Updated_At'] = now()->toDateTimeString();

        $res = $this->repository->update($id, $data);
        $this->repository->clearCache();

        $studentId = $data['Student_ID'] ?? ($existing['Student_ID'] ?? null);

        $this->enterpriseEvent->dispatch(
            'ACADEMIC',
            'UPDATE',
            'SCORE',
            $id,
            \App\Support\ActorIdentity::required(),
            ['ACADEMIC'],
            $studentId ? [$studentId] : [],
            $data
        );

        return $res;
    }

    public function delete($id)
    {
        $score = $this->getById($id);
        $res = $this->repository->delete($id);
        $this->repository->clearCache();

        $studentId = $score['Student_ID'] ?? null;

        $this->enterpriseEvent->dispatch(
            'ACADEMIC',
            'DELETE',
            'SCORE',
            $id,
            \App\Support\ActorIdentity::required(),
            ['ACADEMIC'],
            $studentId ? [$studentId] : [],
            []
        );

        return $res;
    }

    protected function validateDependencies(array $data)
    {
        if (isset($data['Student_ID'])) {
            $student = $this->studentRepository->findById($data['Student_ID']);
            if (!$student) {
                throw new Exception("Siswa tidak valid.");
            }
        }
    }

    /**
     * H8.21: Get students within a teacher's scope.
     * Teacher -> Schedule -> Class_ID -> ClassEnrollment -> Student
     */
    public function getStudentsInTeacherScope($teacherId): array
    {
        $scheduleRepo = app(\App\Interfaces\GoogleSheets\ScheduleRepositoryInterface::class);
        $enrollmentRepo = app(\App\Interfaces\GoogleSheets\ClassEnrollmentRepositoryInterface::class);
        
        $schedules = $scheduleRepo->fetchAll();
        $classIds = $schedules->where('Teacher_ID', $teacherId)->pluck('Class_ID')->unique()->toArray();
        
        // Also include classes where teacher is homeroom
        $classRepo = app(\App\Interfaces\GoogleSheets\ClassRepositoryInterface::class);
        $classes = $classRepo->fetchAll();
        $homeroomClassIds = $classes->where('Homeroom_Teacher_ID', $teacherId)->pluck('Class_ID')->unique()->toArray();
        $classIds = array_unique(array_merge($classIds, $homeroomClassIds));
        
        if (empty($classIds)) {
            return [];
        }
        
        $enrollments = $enrollmentRepo->fetchAll();
        $studentIds = $enrollments->whereIn('Class_ID', $classIds)->pluck('Student_ID')->unique()->toArray();
        
        // Also include students directly assigned to these classes
        $allStudents = $this->studentRepository->fetchAll();
        $directStudentIds = $allStudents->whereIn('Class_ID', $classIds)->pluck('Student_ID')->unique()->toArray();
        $studentIds = array_unique(array_merge($studentIds, $directStudentIds));
        
        return $allStudents->whereIn('Student_ID', $studentIds)->values()->toArray();
    }

    /**
     * H8.21: Check if a student is within a teacher's scope.
     */
    public function isStudentInTeacherScope($studentId, $teacherId): bool
    {
        $scopedStudents = $this->getStudentsInTeacherScope($teacherId);
        return collect($scopedStudents)->contains('Student_ID', $studentId);
    }
}
