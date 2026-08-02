<?php
namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\WorkflowRepositoryInterface;

class WorkflowRepository extends BaseSheetRepository implements WorkflowRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'MASTER_WORKFLOW';
        $this->cacheKey = 'workflow_sheet';
        $this->primaryKey = 'Workflow_ID';
    }

    public function getAll() { return $this->fetchAll(); }
    public function getById($id) { return $this->fetchAll()->firstWhere($this->primaryKey, $id); }
    public function create(array $data) { return $this->append($data); }
    public function update($id, array $data) { return $this->updateRow($id, $data); }
    public function delete($id) { return $this->updateRow($id, ['Status' => 'Inactive']); }
}