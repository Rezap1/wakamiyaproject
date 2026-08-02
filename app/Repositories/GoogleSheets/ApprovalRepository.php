<?php
namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\ApprovalRepositoryInterface;

class ApprovalRepository extends BaseSheetRepository implements ApprovalRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'MASTER_APPROVAL';
        $this->cacheKey = 'approval_sheet';
        $this->primaryKey = 'Approval_ID';
    }

    public function getAll() { return $this->fetchAll(); }
    public function getById($id) { return $this->fetchAll()->firstWhere($this->primaryKey, $id); }
    public function create(array $data) { return $this->append($data); }
    public function update($id, array $data) { return $this->updateRow($id, $data); }
    public function delete($id) { return $this->updateRow($id, ['Status' => 'Cancelled']); }
}