<?php
namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\PayrollRepositoryInterface;

class PayrollRepository extends BaseSheetRepository implements PayrollRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'MASTER_PAYROLL';
        $this->cacheKey = 'payroll_sheet';
        $this->primaryKey = 'Payroll_ID';
    }

    public function getAll() { return $this->fetchAll(); }
    public function getById($id) { return $this->fetchAll()->firstWhere($this->primaryKey, $id); }
    public function create(array $data) { return $this->append($data); }

    public function delete($id) { return $this->updateRow($id, ['Status' => 'Cancelled']); }
}