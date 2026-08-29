<?php

namespace App\Services\Academic;

use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Interfaces\GoogleSheets\BatchRepositoryInterface;
use App\Services\Core\ActivityLogService;
use Exception;

class PlacementService
{
    protected $studentRepo;
    protected $classRepo;
    protected $batchRepo;
    protected $activityLog;

    public function __construct(
        StudentRepositoryInterface $studentRepo,
        ClassRepositoryInterface $classRepo,
        BatchRepositoryInterface $batchRepo,
        ActivityLogService $activityLog
    ) {
        $this->studentRepo = $studentRepo;
        $this->classRepo = $classRepo;
        $this->batchRepo = $batchRepo;
        $this->activityLog = $activityLog;
    }

    public function placeStudent($studentId, $newBatchId, $newClassId, $actorId)
    {
        $student = $this->studentRepo->findById($studentId);
        if (!$student) {
            throw new Exception("Siswa tidak ditemukan.");
        }

        $oldBatchId = $student['Batch_ID'] ?? null;
        $oldClassId = $student['Class_ID'] ?? null;

        // 5. Idempotency Check
        if ($oldBatchId === $newBatchId && $oldClassId === $newClassId) {
            return true; // NO-OP
        }

        // Validate Batch
        if (!empty($newBatchId)) {
            $batch = $this->batchRepo->findById($newBatchId);
            if (!$batch || ($batch['Is_Active'] ?? 'TRUE') === 'FALSE') {
                throw new Exception("Angkatan (Batch) tidak valid atau sedang tidak aktif.");
            }
        }

        // Validate Class
        if (!empty($newClassId)) {
            $newClass = $this->classRepo->findById($newClassId);
            if (!$newClass || ($newClass['Is_Active'] ?? 'TRUE') === 'FALSE') {
                throw new Exception("Kelas tidak valid atau sedang tidak aktif.");
            }

            // 6. Student Batch Consistency
            if (!empty($newBatchId) && $newClass['Batch_ID'] !== $newBatchId) {
                throw new Exception("Kelas yang dipilih tidak berada pada Batch siswa.");
            }

            // 2. Class Capacity Hardening
            $capacity = (int) ($newClass['Capacity'] ?? 0);
            $currentStudentCount = (int) ($newClass['Current_Student'] ?? 0);
            
            // Only validate capacity if we are moving to a new class
            if ($oldClassId !== $newClassId) {
                if ($currentStudentCount >= $capacity) {
                    throw new Exception("Kelas sudah mencapai kapasitas maksimum.");
                }
            }
        }

        // 4. Synchronization Safety (Update Class First to prevent orphan reference if Student update fails)
        
        // Decrement Old Class if exists and changing
        if (!empty($oldClassId) && $oldClassId !== $newClassId) {
            $oldClass = $this->classRepo->findById($oldClassId);
            if ($oldClass) {
                $oldCurrent = max(0, (int) ($oldClass['Current_Student'] ?? 0) - 1);
                $this->classRepo->update($oldClassId, [
                    'Current_Student' => $oldCurrent,
                    'Updated_At' => now()->toDateTimeString()
                ]);
            }
        }

        // Increment New Class if exists and changing
        if (!empty($newClassId) && $oldClassId !== $newClassId) {
            // refresh class object in case
            $newClass = $this->classRepo->findById($newClassId);
            $newCurrent = (int) ($newClass['Current_Student'] ?? 0) + 1;
            $this->classRepo->update($newClassId, [
                'Current_Student' => $newCurrent,
                'Updated_At' => now()->toDateTimeString()
            ]);
        }

        // Update Student
        $this->studentRepo->update($studentId, [
            'Batch_ID' => $newBatchId,
            'Class_ID' => $newClassId,
            'Updated_At' => now()->toDateTimeString(),
            'Updated_By' => $actorId
        ]);

        // Clear Targeted Caches
        $this->studentRepo->clearCache();
        $this->classRepo->clearCache();
        $this->batchRepo->clearCache();

        // 12. Audit Log
        $action = empty($oldClassId) ? 'PLACEMENT_CREATE' : (!empty($newClassId) ? 'PLACEMENT_MOVE' : 'PLACEMENT_REMOVE');
        $this->activityLog->log(
            'ACADEMIC',
            $action,
            "Siswa {$studentId} dipindahkan dari Kelas {$oldClassId} ke {$newClassId}",
            $actorId,
            [
                'Student_ID' => $studentId,
                'Old_Class_ID' => $oldClassId,
                'New_Class_ID' => $newClassId,
                'Old_Batch_ID' => $oldBatchId,
                'New_Batch_ID' => $newBatchId,
                'Actor' => $actorId
            ]
        );

        return true;
    }
}
