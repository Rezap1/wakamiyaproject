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
        if (!in_array($category, ['GENERAL', 'SPORTS', 'LANGUAGE'])) {
            $category = 'GENERAL';
        }

        $details = ['category' => strtolower($category)];
        $notes = $data['Notes'] ?? $data['Remarks'] ?? '';

        if ($category === 'SPORTS') {
            $distance = (float) ($data['running_distance'] ?? 0);
            $time = (float) ($data['running_time'] ?? 0);
            $pushUp = (int) ($data['push_up'] ?? 0);
            $sitUp = (int) ($data['sit_up'] ?? 0);

            if ($distance < 0 || $time < 0 || $pushUp < 0 || $sitUp < 0) {
                throw new Exception("Metrik evaluasi olahraga tidak valid (harus angka non-negatif).");
            }

            $details['running_distance'] = $distance;
            $details['running_time'] = $time;
            $details['push_up'] = $pushUp;
            $details['sit_up'] = $sitUp;
            $details['notes'] = $notes;

            $scoreVal = (float) ($data['Score_Value'] ?? $data['Score'] ?? 0);
            if ($scoreVal <= 0) {
                $distScore = min(100, ($distance / 5) * 100 * 0.3);
                $pushScore = min(100, ($pushUp / 30) * 100 * 0.35);
                $sitScore = min(100, ($sitUp / 30) * 100 * 0.35);
                $scoreVal = round($distScore + $pushScore + $sitScore);
                if ($scoreVal > 100) $scoreVal = 100;
            }

        } elseif ($category === 'LANGUAGE') {
            $speaking = (float) ($data['speaking'] ?? 0);
            $writing = (float) ($data['writing'] ?? 0);
            $listening = (float) ($data['listening'] ?? 0);
            $reading = (float) ($data['reading'] ?? 0);
            $ethics = (float) ($data['ethics'] ?? 0);
            $motivation = (float) ($data['motivation'] ?? 0);
            $attendance = (float) ($data['attendance'] ?? 0);

            $rubrics = [
                'speaking' => $speaking, 
                'writing' => $writing, 
                'listening' => $listening, 
                'reading' => $reading, 
                'ethics' => $ethics, 
                'motivation' => $motivation, 
                'attendance' => $attendance
            ];

            foreach ($rubrics as $key => $val) {
                if ($val < 0 || $val > 100) {
                    throw new Exception("Rubrik bahasa '{$key}' harus berada dalam rentang 0-100.");
                }
                $details[$key] = $val;
            }

            $details['notes'] = $notes;
            $scoreVal = round(array_sum($rubrics) / count($rubrics));

        } else {
            // GENERAL ACADEMIC SCORE
            $scoreVal = (float) ($data['Score_Value'] ?? $data['Score'] ?? 0);
            $details['subject_id'] = $data['Subject_ID'] ?? '';
            $details['notes'] = $notes;
        }

        if ($scoreVal < 0 || $scoreVal > 100) {
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
        $gradeResult = \App\Helpers\GradeHelper::calculate($scoreVal);
        
        $data['Assessment_Category'] = $evalResult['category'];
        $data['Score'] = $scoreVal;
        $data['Score_Value'] = $scoreVal;
        $data['Evaluation_Details'] = $evalResult['evaluation_details'];
        $data['Grade'] = $gradeResult['grade'];
        $data['Status'] = $gradeResult['pass'] ? 'PASS' : 'FAIL';
        $data['Remarks'] = $data['Notes'] ?? ($data['Remarks'] ?? '');
        $data['Created_At'] = now()->toDateTimeString();

        $res = $this->repository->create($data);
        $this->repository->clearCache();
        
        // Notify Student & Dispatch Event
        $this->enterpriseEvent->dispatch(
            'ACADEMIC',
            'PUBLISH',
            'SCORE',
            $res['Score_ID'] ?? $data['Score_ID'],
            \Illuminate\Support\Facades\Auth::id() ?? 'SYSTEM',
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
        $gradeResult = \App\Helpers\GradeHelper::calculate($scoreVal);

        $data['Assessment_Category'] = $evalResult['category'];
        $data['Score'] = $scoreVal;
        $data['Score_Value'] = $scoreVal;
        $data['Evaluation_Details'] = $evalResult['evaluation_details'];
        $data['Grade'] = $gradeResult['grade'];
        $data['Status'] = $gradeResult['pass'] ? 'PASS' : 'FAIL';
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
            \Illuminate\Support\Facades\Auth::id() ?? 'SYSTEM',
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
            \Illuminate\Support\Facades\Auth::id() ?? 'SYSTEM',
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
            if (!$student || ($student['Is_Active'] ?? 'TRUE') === 'FALSE') {
                throw new Exception("Siswa tidak valid atau sedang tidak aktif.");
            }
        }
    }
}