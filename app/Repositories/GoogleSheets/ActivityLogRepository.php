<?php

namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\ActivityLogRepositoryInterface;

class ActivityLogRepository extends BaseSheetRepository implements ActivityLogRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'AUDIT_LOG';
        $this->cacheKey = 'audit_log_sheet';
        $this->primaryKey = 'Log_ID';
    }

    public function create(array $data)
    {
        return $this->append($data);
    }
}
