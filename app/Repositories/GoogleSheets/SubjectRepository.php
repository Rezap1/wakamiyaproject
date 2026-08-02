<?php

namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\SubjectRepositoryInterface;

class SubjectRepository extends BaseSheetRepository implements SubjectRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'MASTER_SUBJECT';
        $this->cacheKey = 'subjects_sheet';
        $this->primaryKey = 'Subject_ID';
    }

    public function findById(string $id)
    {
        $items = $this->fetchAll();
        return $items->firstWhere($this->primaryKey, $id);
    }

    public function create(array $data)
    {
        return $this->append($data);
    }
    
    public function update($id, array $data)
    {
        return $this->updateRow($id, $data);
    }
    
    public function softDelete($id)
    {
        return $this->updateRow($id, ['Is_Active' => 'FALSE']);
    }
}
