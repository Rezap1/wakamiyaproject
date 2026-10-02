<?php

namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\StudentRepositoryInterface;

class StudentRepository extends BaseSheetRepository implements StudentRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'MASTER_STUDENT';
        $this->cacheKey = 'students_sheet';
        $this->primaryKey = 'Student_ID';
    }

    public function findById(string $id)
    {
        return $this->findByIdFresh($id);
    }

    public function findByStudentNumber(string $number)
    {
        return $this->firstWhereColumn('Student_Number', $number);
    }

    public function findByNationalId(string $nationalId)
    {
        if (empty($nationalId)) {
            return null;
        }

        return $this->firstWhereColumn('National_ID', $nationalId);
    }

    public function create(array $data)
    {
        return $this->append($data);
    }
}
