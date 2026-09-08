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
        $configs = Cache::remember('assessment_configs', 3600, function () {
            return collect($this->repository->getAll())->values()->all();
        });

        return $this->withBuiltInUjianBabConfig($configs);
    }

    private function withBuiltInUjianBabConfig($configs): array
    {
        $configs = collect($configs);
        // Ujian Bab is a code-level category so deployments do not need a
        // production sheet/schema mutation just to expose the workflow.
        // A sheet row, when present, remains authoritative and is not
        // overwritten. Apply this after cache reads so pre-existing cached
        // config payloads are augmented too.
        if (!$configs->contains(fn ($config) => strtoupper(trim((string) ($config['Category_ID'] ?? ''))) === 'UJIAN_BAB')) {
            $configs->push($this->builtInUjianBabConfig());
        }

        return $configs->values()->all();
    }

    public function builtInUjianBabConfig(): array
    {
        return [
            'Category_ID' => 'UJIAN_BAB',
            'Category_Name' => 'Ujian Bab',
            'Is_Active' => 'TRUE',
            'Score_Type' => 'NUMERIC',
            'Min_Score' => 0,
            'Max_Score' => 100,
            'Aspects_JSON' => '',
        ];
    }

    public function isNumericCategory($categoryId): bool
    {
        $config = $this->getCategoryConfig($categoryId);
        if (!$config) {
            return false;
        }

        $type = strtoupper(trim((string) ($config['Score_Type'] ?? $config['ScoreType'] ?? $config['Input_Type'] ?? '')));
        return in_array($type, ['NUMERIC', 'NUMBER', 'SCORE', 'NUMERIC_0_100', '0-100'], true)
            || strtoupper(trim((string) $categoryId)) === 'UJIAN_BAB';
    }

    public function categoryLabel($categoryId): string
    {
        $config = $this->getCategoryConfig($categoryId);
        return trim((string) ($config['Category_Name'] ?? '')) ?: (trim((string) $categoryId) ?: 'Tidak dikategorikan');
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
