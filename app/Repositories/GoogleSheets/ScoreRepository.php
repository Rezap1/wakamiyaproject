<?php

namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\ScoreRepositoryInterface;

class ScoreRepository extends BaseSheetRepository implements ScoreRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'MASTER_SCORE';
        $this->cacheKey = 'scores_sheet';
        $this->primaryKey = 'Score_ID';
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
