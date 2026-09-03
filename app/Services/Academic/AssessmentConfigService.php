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
        $needle = strtoupper(trim((string) $categoryId));
        $configs = $this->getActiveCategories();
        foreach ($configs as $config) {
            if (strtoupper(trim((string) ($config['Category_ID'] ?? ''))) === $needle) {
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

        $validKeys = collect($aspects)->pluck('id')->filter()->map(fn ($id) => strtolower(trim((string) $id)))->values()->all();
        $providedKeys = collect($evaluationDetails)
            ->keys()
            ->map(fn ($key) => strtolower(trim((string) $key)))
            ->filter(fn ($key) => $key !== 'notes')
            ->values()
            ->all();

        // Every configured aspect is required. This prevents a forged request
        // from silently persisting an incomplete evaluation.
        if (count($providedKeys) !== count(array_unique($providedKeys))
            || array_diff($validKeys, $providedKeys)
            || array_diff($providedKeys, $validKeys)) {
            return false;
        }

        foreach ($evaluationDetails as $key => $value) {
            if (strtolower(trim((string) $key)) === 'notes') continue;

            if (!in_array(strtolower(trim((string) $key)), $validKeys, true)) {
                return false;
            }
            $normalized = trim((string) $value);
            if ($normalized === '' || !ctype_digit($normalized) || (int) $normalized < 1 || (int) $normalized > 5) {
                return false;
            }
        }
        
        return true;
    }
}
