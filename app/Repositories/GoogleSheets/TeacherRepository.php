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
        $teachers = $this->fetchAll();
        return $teachers->firstWhere($this->primaryKey, $id);
    }

    public function findByEmployeeId(string $employeeId)
    {
        $teachers = $this->fetchAll();
        return $teachers->firstWhere('Employee_ID', $employeeId);
    }

    public function generateTeacherCode(string $prefix, string $year, int $padding = 3): string
    {
        $lockKey = $this->sheetName . '_tchcode_lock';
        $counterKey = 'tchcode_counter_' . $this->sheetName . '_' . $prefix . '_' . $year;

        return \Illuminate\Support\Facades\Cache::lock($lockKey, 10)->block(5, function () use ($prefix, $year, $padding, $counterKey) {
            if (!\Illuminate\Support\Facades\Cache::has($counterKey)) {
                $teachers = $this->fetchAll();
                $maxNumber = 0;
                $pattern = "/^{$prefix}-{$year}-(\d{{$padding}})$/i";
                
                foreach ($teachers as $teacher) {
                    $tchCode = $teacher['Teacher_Code'] ?? '';
                    if (preg_match($pattern, $tchCode, $matches)) {
                        $number = (int) $matches[1];
                        if ($number > $maxNumber) {
                            $maxNumber = $number;
                        }
                    }
                }
                \Illuminate\Support\Facades\Cache::forever($counterKey, $maxNumber);
            }
            
            $nextNumber = \Illuminate\Support\Facades\Cache::increment($counterKey);
            return $prefix . '-' . $year . '-' . str_pad((string)$nextNumber, $padding, '0', STR_PAD_LEFT);
        });
    }

    public function create(array $data)
    {
        return $this->append($data);
    }
}
