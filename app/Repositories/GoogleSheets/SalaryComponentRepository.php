<?php
namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\SalaryComponentRepositoryInterface;

class SalaryComponentRepository extends BaseSheetRepository implements SalaryComponentRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'MASTER_SALARY_COMPONENT';
        $this->cacheKey = 'salary_component_sheet';
        $this->primaryKey = 'Component_ID';
    }

    public function getAll() { return $this->fetchAll(); }
    public function getById($id) { return $this->fetchAll()->firstWhere($this->primaryKey, $id); }
    public function create(array $data) { return $this->append($data); }
    public function update($id, array $data) { return $this->updateRow($id, $data); }
    public function delete($id) { return $this->updateRow($id, ['Status' => 'Inactive']); }
}