<?php
namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\AuditLogRepositoryInterface;

class AuditLogRepository extends BaseSheetRepository implements AuditLogRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'MASTER_AUDIT_LOG';
        $this->cacheKey = 'audit_log_sheet';
        $this->primaryKey = 'Audit_ID';
    }

    public function getAll() { return $this->fetchAll(); }
    public function getById($id) { return $this->fetchAll()->firstWhere($this->primaryKey, $id); }
    public function create(array $data) { return $this->append($data); }
}