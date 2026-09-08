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

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $category = strtoupper(trim((string) $this->input('Assessment_Category', '')));
            if ($category === '') {
                return;
            }

            $configService = app(\App\Services\Academic\AssessmentConfigService::class);
            if (!$configService->isNumericCategory($category)) {
                return;
            }

            $score = $this->input('Score');
            $scoreValue = $this->input('Score_Value');
            if (($score === null || trim((string) $score) === '')
                && ($scoreValue === null || trim((string) $scoreValue) === '')) {
                $message = $category === 'UJIAN_BAB' ? 'Nilai Ujian Bab harus berupa angka.' : 'Nilai harus berupa angka.';
                $validator->errors()->add('Score_Value', $message);
            }
        });
    }

    public function messages(): array
    {
        return [
            'Score_Value.numeric' => 'Nilai harus berupa angka.',
            'Score.numeric' => 'Nilai harus berupa angka.',
            'Score_Value.min' => 'Nilai minimal 0.',
            'Score_Value.max' => 'Nilai maksimal 100.',
            'Score.min' => 'Nilai minimal 0.',
            'Score.max' => 'Nilai maksimal 100.',
        ];
    }
}
