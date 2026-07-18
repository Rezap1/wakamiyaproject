<?php

namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\JobOrderRepositoryInterface;

class JobOrderRepository extends BaseSheetRepository implements JobOrderRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'JOB_ORDER';
        $this->cacheKey = 'job_orders_sheet';
        $this->primaryKey = 'Job_Order_ID';
    }

    public function findById(string $id)
    {
        $jobOrders = $this->fetchAll();
        return $jobOrders->firstWhere($this->primaryKey, $id);
    }

    public function findByCompany(string $companyId)
    {
        $jobOrders = $this->fetchAll();
        return $jobOrders->filter(function ($jobOrder) use ($companyId) {
            return ($jobOrder['Company_ID'] ?? '') === $companyId;
        })->values();
    }

    public function create(array $data)
    {
        return $this->append($data);
    }
}
