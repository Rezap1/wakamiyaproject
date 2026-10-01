<?php

namespace App\Support\Finance;

use App\Helpers\SheetValue;

final class StudentEducationCollectibility
{
    /**
     * MASTER_STUDENT is the business-lifecycle authority. Login-account state
     * is deliberately absent from this rule.
     */
    public function isCollectible(array $student): bool
    {
        return trim((string) ($student['Student_ID'] ?? '')) !== ''
            && SheetValue::isOperationalStudent($student);
    }

    public function lifecycleLabel(array $student): string
    {
        $enrollment = trim((string) ($student['Enrollment_Status'] ?? ''));
        if ($enrollment !== '') {
            return $enrollment;
        }

        return $this->isCollectible($student) ? 'Aktif' : 'Nonaktif';
    }

    /**
     * Build one request-scoped lookup from a bulk MASTER_STUDENT snapshot.
     *
     * @return array<string, array{student: array, collectible: bool, lifecycle_label: string}>
     */
    public function index(iterable $students): array
    {
        $index = [];
        foreach ($students as $student) {
            $student = (array) $student;
            $studentId = trim((string) ($student['Student_ID'] ?? ''));
            if ($studentId === '') {
                continue;
            }

            $index[$studentId] = [
                'student' => $student,
                'collectible' => $this->isCollectible($student),
                'lifecycle_label' => $this->lifecycleLabel($student),
            ];
        }

        return $index;
    }
}
