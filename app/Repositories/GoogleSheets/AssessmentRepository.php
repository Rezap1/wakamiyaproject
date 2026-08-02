<?php
namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\AssessmentRepositoryInterface;

class AssessmentRepository extends BaseSheetRepository implements AssessmentRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'ASSESSMENTS';
        $this->cacheKey = 'assessments_sheet';
        $this->primaryKey = 'Assessment_ID';
    }

    public function getAll()
    {
        return $this->fetchAll();
    }

    public function getById($id)
    {
        $items = $this->fetchAll();
        return $items->firstWhere($this->primaryKey, $id);
    }

    public function create(array $data)
    {
        if (empty($data['Assessment_ID'])) {
            $data['Assessment_ID'] = $this->generateNewId('ASM', 6);
        }
        return $this->append($data);
    }

    public function update($id, array $data)
    {
        return $this->updateRow($id, $data);
    }

    public function delete($id)
    {
        return $this->updateRow($id, ['Status' => 'Archived']);
    }
}