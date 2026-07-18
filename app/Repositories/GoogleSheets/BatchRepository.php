<?php

namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\BatchRepositoryInterface;

class BatchRepository extends BaseSheetRepository implements BatchRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'MASTER_BATCH';
        $this->cacheKey = 'batches_sheet';
        $this->primaryKey = 'Batch_ID';
    }

    public function findById(string $id)
    {
        $batches = $this->fetchAll();
        return $batches->firstWhere($this->primaryKey, $id);
    }

    public function findByCode(string $code)
    {
        $batches = $this->fetchAll();
        return $batches->firstWhere('Batch_Code', $code);
    }

    public function findByName(string $name)
    {
        $batches = $this->fetchAll();
        return $batches->firstWhere('Batch_Name', $name);
    }

    public function create(array $data)
    {
        return $this->append($data);
    }
}
