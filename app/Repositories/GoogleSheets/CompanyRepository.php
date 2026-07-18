<?php

namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\CompanyRepositoryInterface;

class CompanyRepository extends BaseSheetRepository implements CompanyRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'MASTER_COMPANY';
        $this->cacheKey = 'companies_sheet';
        $this->primaryKey = 'Company_ID';
    }

    public function findById(string $id)
    {
        $companies = $this->fetchAll();
        return $companies->firstWhere($this->primaryKey, $id);
    }

    public function findByCode(string $code)
    {
        $companies = $this->fetchAll();
        return $companies->firstWhere('Company_Code', $code);
    }

    public function create(array $data)
    {
        return $this->append($data);
    }
}
