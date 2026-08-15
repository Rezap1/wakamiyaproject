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
        return [
            'Student_ID' => 'nullable|string',
            'Assessment_ID' => 'nullable|string',
            'Assessment_Category' => 'nullable|in:GENERAL,SPORTS,LANGUAGE',
            
            // General Score
            'Score_Value' => 'nullable|numeric|min:0|max:100',
            'Score' => 'nullable|numeric|min:0|max:100',
            'Subject_ID' => 'nullable|string',

            // Sports Assessment Metrics
            'running_distance' => 'nullable|numeric|min:0',
            'running_time' => 'nullable|numeric|min:0',
            'push_up' => 'nullable|integer|min:0',
            'sit_up' => 'nullable|integer|min:0',

            // Language Assessment Rubric
            'speaking' => 'nullable|numeric|min:0|max:100',
            'writing' => 'nullable|numeric|min:0|max:100',
            'listening' => 'nullable|numeric|min:0|max:100',
            'reading' => 'nullable|numeric|min:0|max:100',
            'ethics' => 'nullable|numeric|min:0|max:100',
            'motivation' => 'nullable|numeric|min:0|max:100',
            'attendance' => 'nullable|numeric|min:0|max:100',

            'Notes' => 'nullable|string',
            'Remarks' => 'nullable|string',
        ];
    }
}
