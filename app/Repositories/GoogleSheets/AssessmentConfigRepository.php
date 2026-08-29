<?php

namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\AssessmentConfigRepositoryInterface;

class AssessmentConfigRepository extends BaseSheetRepository implements AssessmentConfigRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'MASTER_ASSESSMENT_CONFIG';
        $this->cacheKey = 'assessment_config_sheet';
        $this->primaryKey = 'Category_ID';
    }

    public function getAll()
    {
        return $this->fetchAll()->toArray();
    }

    public function getByCategory($categoryId)
    {
        $configs = $this->getAll();
        foreach ($configs as $config) {
            if (($config['Category_ID'] ?? '') === $categoryId) {
                return $config;
            }
        }
        return null;
    }
}
