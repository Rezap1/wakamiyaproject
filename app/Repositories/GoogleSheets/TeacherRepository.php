<?php

namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\TeacherRepositoryInterface;

class TeacherRepository extends BaseSheetRepository implements TeacherRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'MASTER_TEACHER';
        $this->cacheKey = 'teachers_sheet';
        $this->primaryKey = 'Teacher_ID';
    }

    public function findById(string $id)
    {
        return $this->findByIdFresh($id);
    }

    public function findByEmployeeId(string $employeeId)
    {
        return $this->firstWhereColumn('Employee_ID', $employeeId);
    }

    public function generateTeacherCode(string $prefix, string $year, int $padding = 3): string
    {
        $pattern = '/^'.preg_quote($prefix, '/').'-'.preg_quote($year, '/').'-(\d+)$/i';
        $next = $this->allocateNextSequence(
            strtolower($this->sheetName.':teacher_code:'.$prefix.':'.$year),
            'Teacher_Code',
            static fn (string $value): ?int => preg_match($pattern, $value, $matches)
                ? (int) $matches[1]
                : null
        );

        return $prefix.'-'.$year.'-'.str_pad((string) $next, $padding, '0', STR_PAD_LEFT);
    }

    public function create(array $data)
    {
        return $this->append($data);
    }
}
