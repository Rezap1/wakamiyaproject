<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreScoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $category = strtoupper(trim($this->input('Assessment_Category', 'GENERAL')));
        
        $rules = [
            'Student_ID' => 'required|string',
            'Subject_ID' => 'nullable|string',
            'Assessment_ID' => 'nullable|string',
            'Assessment_Category' => 'nullable|string',
            'Assessment_Date' => 'nullable|date',
            'Score' => 'nullable|numeric|min:0|max:100',
            'Notes' => 'nullable|string',
            'Remarks' => 'nullable|string',
        ];

        // Dynamic aspect fields are allow-listed from the SSOT; arbitrary
        // request keys must never become part of the validated payload.
        $configService = app(\App\Services\Academic\AssessmentConfigService::class);
        foreach ($configService->getAspects($category) as $aspect) {
            $id = trim((string) ($aspect['id'] ?? ''));
            if ($id !== '') {
                $rules[$id] = 'required|integer|between:1,5';
            }
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'Score_Value.min' => 'Nilai harus minimal 1.',
            'Score_Value.max' => 'Nilai harus maksimal 100.',
            'Subject_ID.required' => 'Mata Pelajaran wajib dipilih untuk Ujian Bab.',
        ];
    }
}
