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
        return $this->findByIdFresh($id)
            ?? ($this->firstWhereColumn('Employee_Number', $id)
            ?? $this->firstWhereColumn('User_ID', $id));
    }

    public function findByEmail(string $email)
    {
        return $this->firstWhereColumn('Email', $email, true);
    }

    public function findByNationalId(string $nationalId)
    {
        if (empty($nationalId)) {
            return null;
        }

        return $this->firstWhereColumn('National_ID', $nationalId);
    }

    public function generateEmployeeNumber(string $prefix, string $year, int $padding = 3): string
    {
        $pattern = '/^'.preg_quote($prefix, '/').'-'.preg_quote($year, '/').'-(\d+)$/i';
        $next = $this->allocateNextSequence(
            strtolower($this->sheetName.':employee_number:'.$prefix.':'.$year),
            'Employee_Number',
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
