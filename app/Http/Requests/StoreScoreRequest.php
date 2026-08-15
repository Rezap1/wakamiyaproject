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
        return [
            'Student_ID' => 'required|string',
            'Assessment_ID' => 'required|string',
            'Assessment_Category' => 'nullable|in:GENERAL,SPORTS,LANGUAGE',
            
            // General Score
            'Score_Value' => 'nullable|numeric|min:0|max:100',
            'Score' => 'nullable|numeric|min:0|max:100',
            'Subject_ID' => 'nullable|string',

            // Sports Assessment Metrics
            'running_distance' => 'required_if:Assessment_Category,SPORTS|nullable|numeric|min:0',
            'running_time' => 'required_if:Assessment_Category,SPORTS|nullable|numeric|min:0',
            'push_up' => 'required_if:Assessment_Category,SPORTS|nullable|integer|min:0',
            'sit_up' => 'required_if:Assessment_Category,SPORTS|nullable|integer|min:0',

            // Language Assessment Rubric (Scale 0-100 or 1-5)
            'speaking' => 'required_if:Assessment_Category,LANGUAGE|nullable|numeric|min:0|max:100',
            'writing' => 'required_if:Assessment_Category,LANGUAGE|nullable|numeric|min:0|max:100',
            'listening' => 'required_if:Assessment_Category,LANGUAGE|nullable|numeric|min:0|max:100',
            'reading' => 'required_if:Assessment_Category,LANGUAGE|nullable|numeric|min:0|max:100',
            'ethics' => 'required_if:Assessment_Category,LANGUAGE|nullable|numeric|min:0|max:100',
            'motivation' => 'required_if:Assessment_Category,LANGUAGE|nullable|numeric|min:0|max:100',
            'attendance' => 'required_if:Assessment_Category,LANGUAGE|nullable|numeric|min:0|max:100',

            'Notes' => 'nullable|string',
            'Remarks' => 'nullable|string',
        ];
    }
}
