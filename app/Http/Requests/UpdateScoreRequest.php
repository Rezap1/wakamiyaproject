<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateScoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'Student_ID' => 'nullable|string',
            'Assessment_ID' => 'nullable|string',
            'Assessment_Category' => 'nullable|string',
            
            // General Score
            'Score_Value' => 'nullable|numeric|min:0|max:100',
            'Score' => 'nullable|numeric|min:0|max:100',
            'Subject_ID' => 'nullable|string',

            'Notes' => 'nullable|string',
            'Remarks' => 'nullable|string',
        ];

        $category = strtoupper(trim((string) $this->input('Assessment_Category', '')));
        foreach (app(\App\Services\Academic\AssessmentConfigService::class)->getAspects($category) as $aspect) {
            $id = trim((string) ($aspect['id'] ?? ''));
            if ($id !== '') $rules[$id] = 'required|integer|between:1,5';
        }

        return $rules;
    }
}
