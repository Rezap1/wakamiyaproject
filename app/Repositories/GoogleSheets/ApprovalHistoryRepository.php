<?php
namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\ApprovalHistoryRepositoryInterface;

class ApprovalHistoryRepository extends BaseSheetRepository implements ApprovalHistoryRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'MASTER_APPROVAL_HISTORY';
        $this->cacheKey = 'approval_history_sheet';
        $this->primaryKey = 'History_ID';
    }

    public function getAll() { return $this->fetchAll(); }
    public function getById($id) { return $this->fetchAll()->firstWhere($this->primaryKey, $id); }
    public function create(array $data) { return $this->append($data); }
}