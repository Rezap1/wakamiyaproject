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

            // Language Assessment Rubric (1-5 scale)
            'speaking' => 'nullable|integer|min:1|max:5',
            'writing' => 'nullable|integer|min:1|max:5',
            'listening' => 'nullable|integer|min:1|max:5',
            'reading' => 'nullable|integer|min:1|max:5',
            'ethics' => 'nullable|integer|min:1|max:5',
            'motivation' => 'nullable|integer|min:1|max:5',
            'attendance' => 'nullable|integer|min:1|max:5',

            'Notes' => 'nullable|string',
            'Remarks' => 'nullable|string',
        ];
    }
}
