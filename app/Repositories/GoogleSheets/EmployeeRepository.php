<?php

namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\EmployeeRepositoryInterface;

class EmployeeRepository extends BaseSheetRepository implements EmployeeRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'MASTER_EMPLOYEE';
        $this->cacheKey = 'employees_sheet';
        $this->primaryKey = 'Employee_ID';
    }

    public function findById(string $id)
    {
        $employees = $this->fetchAll();
        return $employees->firstWhere($this->primaryKey, $id);
    }

    public function findByEmail(string $email)
    {
        $employees = $this->fetchAll();
        return $employees->first(function ($employee) use ($email) {
            return strtolower($employee['Email'] ?? '') === strtolower($email);
        });
    }

    public function findByNationalId(string $nationalId)
    {
        if (empty($nationalId)) {
            return null;
        }
        $employees = $this->fetchAll();
        return $employees->firstWhere('National_ID', $nationalId);
    }

    public function generateEmployeeNumber(string $prefix, string $year, int $padding = 3): string
    {
        $lockKey = $this->sheetName . '_empno_lock';
        $counterKey = 'empno_counter_' . $this->sheetName . '_' . $prefix . '_' . $year;

        return \Illuminate\Support\Facades\Cache::lock($lockKey, 10)->block(5, function () use ($prefix, $year, $padding, $counterKey) {
            if (!\Illuminate\Support\Facades\Cache::has($counterKey)) {
                $employees = $this->fetchAll();
                $maxNumber = 0;
                $pattern = "/^{$prefix}-{$year}-(\d{{$padding}})$/i";
                
                foreach ($employees as $employee) {
                    $empNo = $employee['Employee_Number'] ?? '';
                    if (preg_match($pattern, $empNo, $matches)) {
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
