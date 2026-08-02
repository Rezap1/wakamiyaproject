<?php
namespace App\Services\Core;

use App\Interfaces\GoogleSheets\WorkflowRepositoryInterface;

class WorkflowService
{
    protected $repo;

    public function __construct(WorkflowRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    public function getAll() { return $this->repo->getAll(); }
    public function getById($id) { return $this->repo->getById($id); }

    public function activeWorkflow($module) {
        return $this->getAll()->where('Module', $module)->where('Status', 'Active')->first();
    }

    public function createWorkflow(array $data) {
        $data['Workflow_ID'] = uniqid('WF_');
        $data['Created_At'] = now()->toDateTimeString();
        $res = $this->repo->create($data);
        $this->repo->clearCache();
        return $res;
    }
}