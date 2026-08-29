<?php

namespace App\Services\Academic;

use App\Interfaces\GoogleSheets\AssessmentConfigRepositoryInterface;
use Illuminate\Support\Facades\Cache;

class AssessmentConfigService
{
    protected $repository;

    public function __construct(AssessmentConfigRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function getAllConfigs()
    {
        return Cache::remember('assessment_configs', 3600, function () {
            return $this->repository->getAll();
        });
    }

    public function getActiveCategories()
    {
        $configs = $this->getAllConfigs();
        return collect($configs)->filter(function ($config) {
            return strtoupper(trim($config['Is_Active'] ?? '')) === 'TRUE';
        })->values()->toArray();
    }

    public function getCategoryConfig($categoryId)
    {
        $configs = $this->getActiveCategories();
        foreach ($configs as $config) {
            if ($config['Category_ID'] === $categoryId) {
                return $config;
            }
        }
        return null;
    }

    public function getAspects($categoryId)
    {
        $config = $this->getCategoryConfig($categoryId);
        if (!$config || empty($config['Aspects_JSON'])) {
            return [];
        }

        $aspects = json_decode($config['Aspects_JSON'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            \Log::error("Invalid JSON in Aspects_JSON for Category_ID: {$categoryId}");
            return [];
        }

        return is_array($aspects) ? $aspects : [];
    }

    public function validateAspectPayload($categoryId, $evaluationDetails)
    {
        $aspects = $this->getAspects($categoryId);
        if (empty($aspects)) {
            return false;
        }

        $validKeys = collect($aspects)->pluck('id')->toArray();
        foreach ($evaluationDetails as $key => $value) {
            // Ignore valid system keys that aren't aspects if they somehow leak in, but we shouldn't have them in evaluationDetails
            if (in_array(strtolower($key), ['notes'])) continue;
            
            if (!in_array($key, $validKeys)) {
                return false;
            }
            if (!is_numeric($value) || $value < 1 || $value > 5) {
                return false;
            }
        }
        
        return true;
    }
}
